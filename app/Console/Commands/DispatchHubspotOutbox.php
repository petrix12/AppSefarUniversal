<?php

namespace App\Console\Commands;

use App\Jobs\SyncHubspotOutbox;
use App\Models\IntegrationOutbox;
use Illuminate\Console\Command;

class DispatchHubspotOutbox extends Command
{
    protected $signature = 'crm:dispatch-hubspot-outbox {--limit=100} {--retry-failed : Requeue failed HubSpot writes}';
    protected $description = 'Dispatch eligible HubSpot outbox rows to the CRM queue.';

    public function handle(): int
    {
        if (! config('crm_sync.hubspot.outbound_enabled')) {
            $this->comment('Sin cambios: CRM_HUBSPOT_OUTBOUND_ENABLED está desactivado.');
            return self::SUCCESS;
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        if ($this->option('retry-failed')) {
            IntegrationOutbox::query()->where('provider', 'hubspot')->where('status', 'failed')
                ->update(['status' => 'pending', 'available_at' => now(), 'last_error' => null]);
        }

        $ids = IntegrationOutbox::query()->where('provider', 'hubspot')->where('status', 'pending')
            ->where(fn ($query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->orderBy('id')->limit($limit)->pluck('id');
        foreach ($ids as $id) SyncHubspotOutbox::dispatch((int) $id);

        $this->info('Operaciones HubSpot encoladas: '.$ids->count().'.');
        return self::SUCCESS;
    }
}
