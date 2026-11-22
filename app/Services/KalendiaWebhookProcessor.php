<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KalendiaWebhookProcessor
{
    public function __construct(private KalendiaApi $api, private KalendiaAppointmentService $appointments) {}

    public function process(int $limit = 4): array
    {
        $counts = ['synced' => 0, 'retry' => 0, 'review' => 0];
        for ($i = 0; $i < $limit; $i++) {
            $row = DB::transaction(function () {
                $row = DB::table('kalendia_webhook_events')->whereIn('status', ['pending','retry','processing'])
                    ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
                    ->where(fn ($q) => $q->whereNull('leased_until')->orWhere('leased_until', '<=', now()))
                    ->orderBy('id')->lockForUpdate()->first();
                if (!$row) return null;
                $row->lease = (string) Str::uuid(); $row->attempts++;
                DB::table('kalendia_webhook_events')->where('id', $row->id)->update([
                    'status'=>'processing', 'attempts'=>$row->attempts, 'lease'=>$row->lease,
                    'leased_until'=>now()->addMinute(), 'updated_at'=>now(),
                ]);
                return $row;
            });
            if (!$row) break;
            try {
                $business = (string) config('kalendia.webhook_business_id');
                if ($business !== '' && $business !== $row->business_id) throw new KalendiaApiException(null, 'business_mismatch');
                // Notifications can be partial, duplicated or out of order. Read current provider state.
                $result = $this->api->request('GET', 'appointments', ['id' => $row->resource_id]);
                $items = $result['items'] ?? [];
                if (!is_array($items) || count($items) !== 1 || !is_array($items[0]) || ($items[0]['id'] ?? null) !== $row->resource_id) {
                    throw new KalendiaApiException(null, 'resource_mismatch');
                }
                $saved = DB::transaction(function () use ($row, $items) {
                    $current = DB::table('kalendia_webhook_events')->where('id', $row->id)->lockForUpdate()->first();
                    if ($current->lease !== $row->lease) return false;
                    if (\Illuminate\Support\Facades\Schema::hasTable('platform_records')) {
                        app(PlatformMirror::class)->save('kalendia','main','appointments',$items[0]);
                    } elseif (!$this->appointments->record($items[0], true)) throw new KalendiaApiException(null, 'record_conflict');
                    DB::table('kalendia_webhook_events')->where('id', $row->id)->update([
                        'status'=>'synced', 'last_error_code'=>null, 'lease'=>null, 'leased_until'=>null, 'next_attempt_at'=>null, 'updated_at'=>now(),
                    ]);
                    return true;
                });
                if ($saved) $counts['synced']++;
            } catch (\Throwable $error) {
                $status = $row->attempts >= 8 ? 'review' : 'retry';
                DB::table('kalendia_webhook_events')->where('id', $row->id)->where('lease', $row->lease)->update([
                    'status'=>$status, 'lease'=>null, 'leased_until'=>null,
                    'last_error_code'=>$error instanceof KalendiaApiException ? ($error->errorCode ?: 'api_'.($error->httpStatus ?? 'unavailable')) : 'processing_error',
                    'next_attempt_at'=>$status === 'retry' ? now()->addSeconds(min(3600, 60 * 2 ** min($row->attempts - 1, 6))) : null,
                    'updated_at'=>now(),
                ]);
                $counts[$status]++;
            }
        }
        return $counts;
    }
}
