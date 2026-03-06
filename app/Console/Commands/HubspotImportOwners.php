<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use HubSpot\Factory;
use App\Models\HubspotOwner;

class HubspotImportOwners extends Command
{
    protected $signature = 'hubspot:import-owners
        {--include-inactive : Incluye owners inactivos}
        {--dry-run : Solo muestra resultados sin guardar}';

    protected $description = 'Importa owners activos de HubSpot a la tabla hubspot_owners';

    public function handle(): int
    {
        $hubspot = Factory::createWithAccessToken(env('HUBSPOT_KEY'));

        $includeInactive = $this->option('include-inactive');
        $dryRun = $this->option('dry-run');

        $owners = [];
        $after = null;
        $limit = 100;

        $this->info("Consultando owners de HubSpot...");

        do {

            $query = [
                'limit' => $limit,
                'archived' => 'false'
            ];

            if ($after) {
                $query['after'] = $after;
            }

            $response = $hubspot->apiRequest([
                'method' => 'GET',
                'path'   => '/crm/v3/owners',
                'query'  => $query,
            ]);

            $body = json_decode((string)$response->getBody(), true);

            foreach ($body['results'] ?? [] as $owner) {

                if (!$includeInactive && isset($owner['active']) && !$owner['active']) {
                    continue;
                }

                $owners[] = [
                    'id' => (string) $owner['id'],
                    'name' => trim(($owner['firstName'] ?? '') . ' ' . ($owner['lastName'] ?? '')),
                    'email' => $owner['email'] ?? null,
                    'active' => $owner['active'] ?? true,
                ];
            }

            $after = $body['paging']['next']['after'] ?? null;

        } while ($after);

        $this->info("Owners encontrados: " . count($owners));

        $this->table(
            ['id', 'name', 'email', 'active'],
            array_slice($owners, 0, 20)
        );

        if ($dryRun) {
            $this->warn("Dry run activado. No se guardaron datos.");
            return self::SUCCESS;
        }

        $inserted = 0;

        foreach ($owners as $owner) {

            HubspotOwner::updateOrCreate(
                ['id' => $owner['id']],
                [
                    'name' => $owner['name'],
                    'email' => $owner['email'],
                    'active' => $owner['active'],
                ]
            );

            $inserted++;
        }

        $this->info("Owners importados/actualizados: {$inserted}");

        return self::SUCCESS;
    }
}
