<?php

namespace App\Jobs;

use App\Exceptions\HubspotApiException;
use App\Exceptions\HubspotDailyBudgetExceeded;
use App\Models\CrmObjectAssociationBackfill;
use App\Services\HubspotObjectRecordStore;
use App\Services\HubspotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BackfillHubspotAssociationPage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 50;
    public int $timeout = 300;
    public array $backoff = [60, 180, 600, 1800, 3600];

    public function __construct(public int $backfillId)
    {
        $this->onConnection('crm');
        $this->onQueue('crm-sync');
    }

    public function handle(HubspotService $hubspot, HubspotObjectRecordStore $store): void
    {
        $backfill = CrmObjectAssociationBackfill::query()->find($this->backfillId);
        if (! $backfill || in_array($backfill->status, ['completed', 'unsupported'], true)) return;

        $lock = Cache::lock('crm.hubspot.association-backfill.'.$backfill->id, 330);
        if (! $lock->get()) return;

        try {
            $backfill->update(['status' => 'running', 'last_error_class' => null]);
            $labels = $backfill->association_labels;
            if ($labels === null) {
                try {
                    $labels = $hubspot->webhookAssociationLabels($backfill->from_object_type, $backfill->to_object_type);
                } catch (HubspotApiException $exception) {
                    if (in_array($exception->statusCode, [400, 404], true)) {
                        $backfill->update(['status' => 'unsupported', 'last_error_class' => 'UnsupportedAssociationPair']);
                        return;
                    }
                    throw $exception;
                }
                $backfill->update(['association_labels' => $labels]);
            }

            $sources = $store->query($backfill->from_object_type)->where('provider', 'hubspot')
                ->when($backfill->source_cursor, fn ($query, $cursor) => $query->where('external_id', '>', $cursor))
                ->orderBy('external_id')->limit(25)->get(['external_id']);
            if ($sources->isEmpty()) {
                $backfill->update(['status' => 'completed', 'last_page_at' => now()]);
                return;
            }

            $readAt = now();
            $associationCount = 0;
            foreach ($sources as $source) {
                $sourceId = (string) $source->external_id;
                $records = $hubspot->webhookAssociationRecords(
                    $backfill->from_object_type,
                    $sourceId,
                    $backfill->to_object_type,
                );
                $associationCount += $this->reconcileSource($backfill, $sourceId, $records, $labels, $readAt);
            }

            $lastId = (string) $sources->last()->external_id;
            $hasMore = $store->query($backfill->from_object_type)
                ->where('provider', 'hubspot')->where('external_id', '>', $lastId)->exists();
            DB::transaction(function () use ($backfill, $sources, $associationCount, $lastId, $hasMore): void {
                $locked = CrmObjectAssociationBackfill::query()->lockForUpdate()->findOrFail($backfill->id);
                $locked->records_read += $sources->count();
                $locked->associations_read += $associationCount;
                $locked->source_cursor = $lastId;
                $locked->status = $hasMore ? 'queued' : 'completed';
                $locked->last_page_at = now();
                $locked->last_error_class = null;
                $locked->save();
                if ($hasMore) self::dispatch($locked->id)->delay(now()->addSeconds(2))->afterCommit();
            });
        } catch (HubspotDailyBudgetExceeded $exception) {
            $backfill->update(['status' => 'queued', 'last_error_class' => 'HubSpotDailyBudget']);
            $this->release($exception->retryAfterSeconds);
        } catch (HubspotApiException $exception) {
            if ($exception->statusCode === 429) {
                $backfill->update(['status' => 'queued', 'last_error_class' => 'HubSpotRateLimit']);
                $this->release(max(1, $exception->retryAfterSeconds ?? 60));
                return;
            }
            if ($exception->statusCode >= 400 && $exception->statusCode < 500) {
                $backfill->update([
                    'status' => in_array($exception->statusCode, [400, 404], true) ? 'unsupported' : 'failed',
                    'last_error_class' => $exception::class,
                ]);
                return;
            }
            $backfill->update(['status' => 'retrying', 'last_error_class' => $exception::class]);
            throw $exception;
        } catch (\Throwable $exception) {
            $backfill->update(['status' => 'retrying', 'last_error_class' => $exception::class]);
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    private function reconcileSource(
        CrmObjectAssociationBackfill $backfill,
        string $sourceId,
        array $records,
        array $labels,
        \Illuminate\Support\Carbon $readAt,
    ): int {
        return DB::transaction(function () use ($backfill, $sourceId, $records, $labels, $readAt): int {
            CrmObjectAssociationBackfill::query()->whereKey($backfill->id)->lockForUpdate()->first();
            $sourceTable = HubspotObjectRecordStore::tableFor($backfill->from_object_type);
            if ($sourceTable === null) {
                throw new \InvalidArgumentException('No existe una tabla específica para el objeto de HubSpot.');
            }
            DB::table($sourceTable)->where('provider', 'hubspot')
                ->where('external_id', $sourceId)->lockForUpdate()->first();

            DB::table('crm_object_associations')->where('provider', 'hubspot')
                ->where('from_object_type', $backfill->from_object_type)->where('from_external_id', $sourceId)
                ->where('to_object_type', $backfill->to_object_type)
                ->where(function ($query) use ($readAt): void {
                    $query->whereNull('last_event_at')->orWhere('last_event_at', '<=', $readAt);
                })->update(['active' => false, 'last_event_at' => $readAt]);

            $count = 0;
            foreach ($records as $record) {
                $targetId = (string) ($record['to_object_id'] ?? '');
                if (! ctype_digit($targetId)) throw new \UnexpectedValueException('Invalid HubSpot association target ID.');
                $typeId = (string) ($record['association_type_id'] ?? '');
                $category = (string) ($record['association_category'] ?? '');
                if ($typeId === '' || $category === '') throw new \UnexpectedValueException('Incomplete HubSpot association type.');
                $labelKey = $category.':'.$typeId;
                $key = [
                    'provider' => 'hubspot',
                    'from_object_type' => $backfill->from_object_type,
                    'from_external_id' => $sourceId,
                    'to_object_type' => $backfill->to_object_type,
                    'to_external_id' => $targetId,
                    'association_type_id' => $typeId,
                ];
                $existing = DB::table('crm_object_associations')->where($key)->lockForUpdate()->first();
                if ($existing && $existing->last_event_at !== null && $existing->last_event_at > $readAt) continue;
                DB::table('crm_object_associations')->updateOrInsert($key, [
                    'association_category' => $category,
                    'association_label' => $labels[$labelKey] ?? null,
                    'active' => true,
                    'last_event_at' => $readAt,
                    'created_at' => $existing?->created_at ?? now(),
                    'updated_at' => now(),
                ]);
                $count++;
            }

            return $count;
        }, 3);
    }

    public function failed(?\Throwable $exception): void
    {
        CrmObjectAssociationBackfill::query()->whereKey($this->backfillId)->update([
            'status' => 'failed',
            'last_error_class' => $exception ? $exception::class : null,
        ]);
    }
}
