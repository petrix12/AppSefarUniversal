<?php

namespace App\Jobs;

use App\Models\CrmObjectMirrorBackfill;
use App\Exceptions\HubspotApiException;
use App\Exceptions\HubspotDailyBudgetExceeded;
use App\Services\CrmContactMirrorService;
use App\Services\CrmObjectMirrorService;
use App\Services\HubspotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class BackfillHubspotObjectPage implements ShouldQueue
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

    public function handle(
        HubspotService $hubspot,
        CrmObjectMirrorService $mirror,
        CrmContactMirrorService $contacts,
    ): void {
        $backfill = CrmObjectMirrorBackfill::find($this->backfillId);
        if (! $backfill || $backfill->status === 'completed') return;

        $lock = Cache::lock('crm.hubspot.backfill.'.$this->backfillId, 330);
        if (! $lock->get()) return;

        try {
            $backfill->update(['status' => 'running', 'last_error_class' => null]);
            $page = $hubspot->listWebhookObjects(
                $backfill->object_type,
                $backfill->after_cursor,
                25,
                $backfill->archived,
            );

            foreach ($page['results'] as $object) {
                $id = (string) ($object['id'] ?? '');
                if (! ctype_digit($id) || ! is_array($object['properties'] ?? null)) {
                    throw new \UnexpectedValueException('HubSpot devolvió un objeto sin identidad o propiedades válidas.');
                }

                $mirror->storeRemoteObject($backfill->object_type, $id, $object);
                if ($backfill->object_type === 'contacts' && ! $backfill->archived) {
                    $contacts->syncContact('hubspot', $id);
                }
            }

            $next = $page['next'];
            DB::transaction(function () use ($backfill, $page, $next): void {
                $locked = CrmObjectMirrorBackfill::query()->lockForUpdate()->findOrFail($backfill->id);
                $locked->records_read += count($page['results']);
                $locked->after_cursor = $next;
                $locked->status = $next === null ? 'completed' : 'queued';
                $locked->last_page_at = now();
                $locked->last_error_class = null;
                $locked->save();

                if ($next !== null) {
                    self::dispatch($locked->id)->delay(now()->addSeconds(2))->afterCommit();
                }
            });
        } catch (\Throwable $exception) {
            if ($exception instanceof HubspotDailyBudgetExceeded) {
                $backfill->update(['status' => 'queued', 'last_error_class' => 'HubSpotDailyBudget']);
                $this->release($exception->retryAfterSeconds);
                return;
            }
            if ($exception instanceof HubspotApiException && $exception->statusCode === 429) {
                $retryAfter = max(1, $exception->retryAfterSeconds ?? 60);
                $backfill->update(['status' => 'queued', 'last_error_class' => 'HubSpotRateLimit']);
                $this->release($retryAfter);
                return;
            }
            if ($exception instanceof HubspotApiException && $exception->statusCode >= 400 && $exception->statusCode < 500) {
                $backfill->update(['status' => 'failed', 'last_error_class' => $exception::class]);
                return;
            }

            $backfill->update(['status' => 'retrying', 'last_error_class' => $exception::class]);
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    public function failed(?\Throwable $exception): void
    {
        CrmObjectMirrorBackfill::query()->whereKey($this->backfillId)->update([
            'status' => 'failed',
            'last_error_class' => $exception ? $exception::class : null,
        ]);
    }
}
