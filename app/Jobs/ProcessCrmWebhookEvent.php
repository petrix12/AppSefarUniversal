<?php

namespace App\Jobs;

use App\Models\CrmWebhookEvent;
use App\Services\CrmContactMirrorService;
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
        if (! $event || $event->status === 'completed') return;
        if (! config('crm_sync.'.$event->provider.'.enabled')) return;
        if (! config('crm_sync.processing_enabled')) return;
        // Serialize provider events; reread current remote state instead of applying old deltas.
        $lock = Cache::lock('crm.webhooks.'.$event->provider, 330);
        if (! $lock->get()) { $this->release(10); return; }
        try {
            $event->refresh();
            if ($event->status === 'completed') return;
            $event->increment('attempts');
            $mirror->process($event->provider, $event->payload);
            $event->update(['status' => 'completed', 'error_class' => null, 'processed_at' => now()]);
        } catch (\Throwable $exception) {
            $event->update(['status' => 'failed', 'error_class' => $exception::class]);
            throw $exception;
        } finally {
            $lock->release();
        }
    }
}
