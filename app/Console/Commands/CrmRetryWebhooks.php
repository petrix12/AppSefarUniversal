<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCrmWebhookEvent;
use App\Models\CrmWebhookEvent;
use Illuminate\Console\Command;

class CrmRetryWebhooks extends Command
{
    protected $signature = 'crm:retry-webhooks {--limit=50}';
    protected $description = 'Recupera eventos CRM pendientes o fallidos, con un máximo de cinco intentos.';

    public function handle(): int
    {
        if (! config('crm_sync.processing_enabled')) return self::SUCCESS;
        $providers = array_values(array_filter(['hubspot', 'teamleader'], fn ($provider) => config("crm_sync.$provider.enabled")));
        CrmWebhookEvent::whereIn('status', ['pending', 'failed'])->where('attempts', '<', 5)
            ->whereIn('provider', $providers)
            ->where('updated_at', '<', now()->subMinutes(5))->orderBy('id')
            ->limit(max(1, min(100, (int) $this->option('limit'))))->get()->each(function ($event) {
                ProcessCrmWebhookEvent::dispatch($event->id);
                $event->touch();
            });
        return self::SUCCESS;
    }
}
