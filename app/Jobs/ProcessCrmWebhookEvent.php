<?php

namespace App\Jobs;

use App\Models\CrmWebhookEvent;
use App\Exceptions\HubspotDailyBudgetExceeded;
use App\Services\CosPortfolioBackfill;
use App\Services\CrmContactMirrorService;
use App\Services\HubspotCosFieldSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ProcessCrmWebhookEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 300;
    public int $backoff = 60;

    public function __construct(public int $eventId)
    {
        $this->onConnection('crm');
        $this->onQueue('crm-sync');
    }

    public function handle(CrmContactMirrorService $mirror): void
    {
        $event = CrmWebhookEvent::find($this->eventId);
        if (! $event || in_array($event->status, ['completed', 'review'], true)) return;
        if (! config('crm_sync.'.$event->provider.'.enabled')) return;
        if (! CosPortfolioBackfill::allowsCrmProcessing($event->provider)) return;
        // Serialize provider events; reread current remote state instead of applying old deltas.
        $lock = Cache::lock('crm.webhooks.'.$event->provider, 330);
        if (! $lock->get()) { $this->release(10); return; }
        try {
            $event->refresh();
            if (in_array($event->status, ['completed', 'review'], true)) return;
            $event->increment('attempts');
            $status = 'completed';
            if ($event->provider === 'hubspot' && CosPortfolioBackfill::hubspotFieldsOnly()) {
                $result = app(HubspotCosFieldSyncService::class)->ingestEvent($event->payload);
                $status = $result['status'] === 'review' ? 'review' : 'completed';
            } else {
                $mirror->process($event->provider, $event->payload);
            }
            $event->update(['status' => $status, 'error_class' => null, 'processed_at' => now()]);
        } catch (HubspotDailyBudgetExceeded $exception) {
            $event->update(['status' => 'pending', 'error_class' => 'HubSpotDailyBudget']);
            $this->release($exception->retryAfterSeconds);
        } catch (\Throwable $exception) {
            $event->update(['status' => 'failed', 'error_class' => $exception::class]);
            throw $exception;
        } finally {
            $lock->release();
        }
    }
}
