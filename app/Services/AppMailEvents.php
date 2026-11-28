<?php

namespace App\Services;

use App\Models\AppMailLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AppMailEvents
{
    public function record(array $event, string $eventId): void
    {
        abort_unless(Schema::hasTable('app_mail_events'), 503, 'Historial de correo pendiente de migración.');
        $id = data_get($event, 'mail.tags.app_mail_log_id.0');
        $messageId = data_get($event, 'mail.messageId');
        $type = strtolower((string) ($event['eventType'] ?? $event['notificationType'] ?? ''));
        $field = match ($type) {
            'delivery' => 'delivered_at', 'bounce' => 'bounced_at', 'complaint' => 'complained_at',
            default => null,
        };
        if (! is_scalar($id) || ! ctype_digit((string) $id) || ! is_string($messageId) || strlen($messageId) > 255
            || ! $field || $eventId === '' || strlen($eventId) > 255) return;
        try {
            $at = Carbon::parse(data_get($event, $type.'.timestamp') ?: data_get($event, 'mail.timestamp'));
        } catch (\Throwable) {
            $at = now();
        }
        DB::transaction(function () use ($event, $id, $messageId, $type, $field, $eventId, $at) {
            $log = AppMailLog::whereKey($id)->where('provider', 'ses')->lockForUpdate()->first();
            if (! $log || ($log->provider_message_id && ! hash_equals($log->provider_message_id, $messageId))) return;
            if (DB::table('app_mail_events')->where('provider_event_id', $eventId)->exists()) return;
            DB::table('app_mail_events')->insert([
                'app_mail_log_id' => $log->id, 'provider_event_id' => $eventId,
                'event_type' => $type, 'occurred_at' => $at, 'created_at' => now(), 'updated_at' => now(),
            ]);
            if (! $log->{$field}) $log->{$field} = $at;
            $log->provider_message_id = $messageId;
            $log->status = $log->complained_at ? 'complained' : ($log->bounced_at ? 'bounced' : 'delivered');
            $log->save();
            app(EmailDeliveryPolicy::class)->recordSesEvent($event, $log->recipients);
        });
    }
}
