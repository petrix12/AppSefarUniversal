<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use HubSpot\Factory;
use Illuminate\Support\Facades\Storage;

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

        // 2) Cargar emails existentes en users (normalizados)
        //    Si tu tabla users fuera gigante (millones), te hago versión chunk también,
        //    pero normalmente esto va bien.
        $userEmailsSet = [];
        User::query()
            ->select(['id', 'email'])
            ->whereNotNull('email')
            ->chunkById(2000, function ($rows) use (&$userEmailsSet) {
                foreach ($rows as $r) {
                    $email = strtolower(trim($r->email));
                    if ($email !== '') $userEmailsSet[$email] = true;
                }
            });

        // 3) Detectar unmatched (owners sin par en users)
        $unmatched = [];
        foreach ($owners as $o) {
            if (empty($o['email'])) continue;
            if (!isset($userEmailsSet[$o['email']])) {
                $unmatched[] = $o;
            }
        }

        if ($showUnmatched || $unmatchedOnly || $exportPath) {
            $this->warn('Owners sin par en BD (users.email): ' . count($unmatched));

            if (!empty($unmatched)) {
                $this->table(
                    ['owner_id', 'email', 'name', 'active', 'createdAt', 'updatedAt'],
                    array_slice(array_map(fn($o) => [
                        $o['id'],
                        $o['email'],
                        $o['name'] ?? '',
                        isset($o['active']) ? ($o['active'] ? 'yes' : 'no') : '',
                        $o['createdAt'] ?? '',
                        $o['updatedAt'] ?? '',
                    ], $unmatched), 0, 50)
                );
            }

            if ($exportPath) {
                $csv = $this->toCsv($unmatched);
                Storage::disk('local')->put($exportPath, $csv);
                $this->info("CSV generado en storage/app/{$exportPath}");
            }

            if ($unmatchedOnly) {
                return self::SUCCESS;
            }
        }

        // ============================================================
        // 4) SYNC NORMAL (tu lógica original), pero usando mapa email=>ownerId
        // ============================================================
        $ownersByEmail = [];
        foreach ($owners as $o) {
            if (!empty($o['email']) && !empty($o['id'])) {
                $ownersByEmail[$o['email']] = (string) $o['id'];
            }
        }

        // 5) Buscar users que hagan match por email
        $emails = array_keys($ownersByEmail);

        $totalUsersMatched = 0;
        $toUpdate = [];

        User::query()
            ->select(['id', 'email', 'hubspot_owner_id'])
            ->whereIn('email', $emails)
            ->chunkById($chunkSize, function ($users) use (&$totalUsersMatched, &$toUpdate, $ownersByEmail) {
                foreach ($users as $user) {
                    $totalUsersMatched++;

                    $email = strtolower(trim($user->email));
                    $newOwnerId = $ownersByEmail[$email] ?? null;

                    if (!$newOwnerId) continue;

                    // NO actualizar si no cambia
                    if ((string) $user->hubspot_owner_id === (string) $newOwnerId) {
                        continue;
                    }

                    $toUpdate[] = [
                        'id' => $user->id,
                        'email' => $email,
                        'old' => $user->hubspot_owner_id,
                        'new' => $newOwnerId,
                    ];
                }
            });

        $this->info("Users matcheados por email: {$totalUsersMatched}");
        $this->info("Cambios necesarios: " . count($toUpdate));

        if (empty($toUpdate)) {
            $this->info('Nada que actualizar ✅');
            return self::SUCCESS;
        }

        $this->table(
            ['user_id', 'email', 'hubspot_owner_id_old', 'hubspot_owner_id_new'],
            array_slice(array_map(fn($r) => [$r['id'], $r['email'], $r['old'], $r['new']], $toUpdate), 0, 20)
        );

        if ($dryRun) {
            $this->warn('DRY RUN: no se aplicaron cambios.');
            return self::SUCCESS;
        }

        $updated = 0;
        foreach (array_chunk($toUpdate, $chunkSize) as $chunk) {
            foreach ($chunk as $row) {
                $affected = User::query()
                    ->where('id', $row['id'])
                    ->where(function ($q) use ($row) {
                        $q->whereNull('hubspot_owner_id')
                          ->orWhere('hubspot_owner_id', '!=', $row['new']);
                    })
                    ->update(['hubspot_owner_id' => $row['new']]);

                $updated += $affected;
            }
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
