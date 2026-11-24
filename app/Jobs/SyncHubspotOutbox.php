<?php

namespace App\Jobs;

use App\Models\IntegrationOutbox;
use App\Services\HubspotOutboundSyncService;
use App\Exceptions\HubspotApiException;
use App\Exceptions\HubspotDailyBudgetExceeded;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncHubspotOutbox implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 50;
    public int $timeout = 120;
    public array $backoff = [60, 180, 600, 1800, 3600];

    public function __construct(public int $outboxId)
    {
        $this->onConnection('crm');
        $this->onQueue('crm-sync');
    }

    public function handle(HubspotOutboundSyncService $sync): void
    {
        try {
            $sync->process($this->outboxId);
        } catch (HubspotApiException $exception) {
            if ($exception->statusCode === 429) {
                $this->release(max(1, $exception->retryAfterSeconds ?? 60));
                return;
            }
            if ($exception->statusCode >= 400 && $exception->statusCode < 500) {
                IntegrationOutbox::query()->whereKey($this->outboxId)->update([
                    'status' => 'failed', 'last_error' => $exception::class, 'updated_at' => now(),
                ]);
                return;
            }
            throw $exception;
        } catch (HubspotDailyBudgetExceeded $exception) {
            $this->release($exception->retryAfterSeconds);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        IntegrationOutbox::query()->whereKey($this->outboxId)->update([
            'status' => 'failed',
            'last_error' => $exception ? $exception::class : null,
            'updated_at' => now(),
        ]);
    }
}
