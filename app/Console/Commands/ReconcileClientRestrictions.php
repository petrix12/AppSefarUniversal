<?php

namespace App\Console\Commands;

use App\Models\ClientRestrictionSync;
use App\Models\User;
use App\Services\ClientBlockPropagationService;
use App\Services\ClientRestrictionService;
use App\Services\HubspotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ReconcileClientRestrictions extends Command
{
    protected $signature = 'clients:reconcile-blocks {--limit=30}';
    protected $description = 'Detecta retiros y reintenta propagar marcas de bloqueo sin liberar expedientes automáticamente';

    public function handle(ClientRestrictionService $restrictions, ClientBlockPropagationService $propagation): int
    {
        if (! Schema::hasTable('client_restriction_syncs')) {
            $this->warn('Falta aplicar la migración de restricciones.');
            return self::SUCCESS;
        }
        if (config('client_restrictions.hubspot_poll_enabled')) {
            foreach (['contacts', 'deals'] as $type) {
                try {
                    $key = 'client-block:hubspot-cursor:'.$type;
                    $data = app(HubspotService::class)->retirementPage($type, Cache::get($key));
                    foreach ($data['results'] ?? [] as $record) {
                        foreach (User::role('Cliente')->whereIn('hs_id', $record['contact_ids'] ?? [])->get() as $client) {
                            $restrictions->detectHubspotRetirement($client,
                                $type === 'contacts' ? $record : [], $type === 'deals' ? [$record] : []);
                        }
                    }
                    Cache::forever($key, data_get($data, 'paging.next.after'));
                } catch (\Throwable $exception) {
                    report($exception);
                    $this->error('No se pudo completar la detección en HubSpot: '.$exception->getMessage());
                }
            }
        }
        ClientRestrictionSync::whereNotNull('retry_at')->where('retry_at', '<=', now())
            ->orderBy('id')->limit(max(1, min(100, (int) $this->option('limit'))))->get()
            ->each(fn ($state) => $propagation->sync($state));

        return self::SUCCESS;
    }
}
