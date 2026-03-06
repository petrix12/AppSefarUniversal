<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use HubSpot\Factory;
use Illuminate\Support\Facades\Storage;
use App\Models\HubspotOwner;


class HubspotSyncOwners extends Command
{
    protected $signature = 'hubspot:sync-owners
        {--dry-run : Solo muestra cambios, no escribe en BD}
        {--include-archived : Incluye owners archivados}
        {--chunk=500 : Tamaño de chunk para updates en BD}
        {--show-unmatched : Muestra owners de HubSpot sin par en users}
        {--unmatched-only : Solo lista/exporta los unmatched y termina}
        {--export-unmatched= : Exporta unmatched a CSV (ruta relativa a storage/app)}';

    protected $description = 'Sincroniza HubSpot Owners (por email) hacia users.hubspot_owner_id y lista owners sin match en BD';

    public function handle(): int
    {
        $hubspot = Factory::createWithAccessToken(env('HUBSPOT_KEY'));

        $dryRun = (bool) $this->option('dry-run');
        $includeArchived = (bool) $this->option('include-archived');
        $chunkSize = (int) $this->option('chunk');
        $showUnmatched = (bool) $this->option('show-unmatched');
        $unmatchedOnly = (bool) $this->option('unmatched-only');
        $exportPath = $this->option('export-unmatched'); // e.g. "hubspot/unmatched_owners.csv"

        // 1) Traer TODOS los owners (paginado)
        $owners = $this->fetchOwners($hubspot, $includeArchived); // lista con id+email+name
        if (empty($owners)) {
            $this->warn('No se encontraron owners en HubSpot.');
            return self::SUCCESS;
        }
        $this->info('Owners encontrados: ' . count($owners));

        foreach ($owners as $o) {
            HubspotOwner::updateOrCreate(
                ['id' => (string) $o['id']],
                [
                    'email' => $o['email'] ?: null,
                    'name' => $o['name'] ?: null,
                    'active' => $o['active'],
                    'hubspot_created_at' => $o['createdAt'] ? \Carbon\Carbon::parse($o['createdAt']) : null,
                    'hubspot_updated_at' => $o['updatedAt'] ? \Carbon\Carbon::parse($o['updatedAt']) : null,
                ]
            );
        }

        $this->info("Actualizados: {$updated} ✅");
        return self::SUCCESS;
    }

    /**
     * Devuelve owners completos (id, email, name, active, createdAt, updatedAt) normalizados.
     */
    private function fetchOwners($hubspot, bool $includeArchived): array
    {
        $owners = [];
        $after = null;
        $limit = 500;

        do {
            $query = [
                'limit' => $limit,
                'archived' => $includeArchived ? 'true' : 'false',
            ];
            if (!is_null($after)) {
                $query['after'] = $after;
            }

            $resp = $hubspot->apiRequest([
                'method' => 'GET',
                'path'   => '/crm/v3/owners',
                'query'  => $query,
            ]);

            $body = json_decode((string) $resp->getBody(), true);

            foreach (($body['results'] ?? []) as $owner) {
                $email = strtolower(trim($owner['email'] ?? ''));
                $id = (string) ($owner['id'] ?? '');

                if ($id === '') continue;

                $owners[] = [
                    'id' => $id,
                    'email' => $email,
                    'name' => $owner['firstName'] ?? ($owner['name'] ?? ''),
                    'active' => $owner['active'] ?? null,
                    'createdAt' => $owner['createdAt'] ?? null,
                    'updatedAt' => $owner['updatedAt'] ?? null,
                ];
            }

            $after = $body['paging']['next']['after'] ?? null;

        } while (!is_null($after));

        return $owners;
    }

    private function toCsv(array $rows): string
    {
        $headers = ['owner_id', 'email', 'name', 'active', 'createdAt', 'updatedAt'];
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $headers);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'] ?? '',
                $r['email'] ?? '',
                $r['name'] ?? '',
                isset($r['active']) ? ($r['active'] ? '1' : '0') : '',
                $r['createdAt'] ?? '',
                $r['updatedAt'] ?? '',
            ]);
        }

        rewind($out);
        return stream_get_contents($out);
    }
}
