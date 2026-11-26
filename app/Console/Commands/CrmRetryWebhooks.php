<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCrmWebhookEvent;
use App\Models\CrmWebhookEvent;
use App\Services\CosPortfolioBackfill;
use Illuminate\Console\Command;

class CrmRetryWebhooks extends Command
{
    protected $signature = 'crm:retry-webhooks {--limit=50}';
    protected $description = 'Recupera eventos CRM pendientes o fallidos, con un máximo de cinco intentos.';

    public function handle(): int
    {
        $providers = array_values(array_filter(['hubspot', 'teamleader'], fn ($provider) =>
            config("crm_sync.$provider.enabled") && CosPortfolioBackfill::allowsCrmProcessing($provider)
        ));
        if ($providers === []) return self::SUCCESS;
        CrmWebhookEvent::whereIn('status', ['pending', 'failed'])->where('attempts', '<', 5)
            ->whereIn('provider', $providers)
            ->when(CosPortfolioBackfill::hubspotFieldsOnly(), fn ($query) => $query->where(fn ($scope) => $scope
                ->where('provider', '!=', 'hubspot')
                ->orWhere('payload->subscriptionType', 'like', 'contact.%')
                ->orWhere('payload->subscriptionType', 'like', 'deal.%')))
            ->where('updated_at', '<', now()->subMinutes(5))->orderBy('id')
            ->limit(max(1, min(100, (int) $this->option('limit'))))->get()->each(function ($event) {
                ProcessCrmWebhookEvent::dispatch($event->id);
                $event->touch();
            });
        return self::SUCCESS;
    }
}
