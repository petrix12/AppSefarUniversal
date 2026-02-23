<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use HubSpot\Factory;
use HubSpot\Client\Crm\Contacts\Model\Filter;
use HubSpot\Client\Crm\Contacts\Model\FilterGroup;
use HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest;

class HubspotSyncClientOwners extends Command
{
    protected $signature = 'hubspot:sync-client-owners
        {--dry-run : Solo muestra cambios, no escribe en BD}
        {--chunk=500 : Chunk para updates locales}
        {--limit=100 : Page size HubSpot (max 100 recomendado)}
        {--only-owner= : Solo procesa un hubspot_owner_id específico}
        {--only-user= : Solo procesa un user (asesor) por id}
        {--match=email : match mode: email|hs_id}
    ';

    protected $description = 'Asigna users.owner_id a cada cliente según el owner (hubspot_owner_id) del contacto en HubSpot';

    public function handle(): int
    {
        $hubspot = Factory::createWithAccessToken(env('HUBSPOT_KEY'));

        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');
        $limit = (int) $this->option('limit');
        $onlyOwner = $this->option('only-owner');
        $onlyUserId = $this->option('only-user');
        $matchMode = $this->option('match'); // email | hs_id

        if (!in_array($matchMode, ['email', 'hs_id'], true)) {
            $this->error("Opción --match inválida. Usa: email|hs_id");
            return self::FAILURE;
        }

        // 1) Traer asesores (users internos) con hubspot_owner_id
        $advisorsQ = User::query()
            ->select(['id', 'email', 'hubspot_owner_id'])
            ->whereNotNull('hubspot_owner_id');

        if ($onlyOwner) {
            $advisorsQ->where('hubspot_owner_id', (string) $onlyOwner);
        }
        if ($onlyUserId) {
            $advisorsQ->where('id', (int) $onlyUserId);
        }

        $advisors = $advisorsQ->get();

        if ($advisors->isEmpty()) {
            $this->warn('No hay users con hubspot_owner_id para procesar.');
            return self::SUCCESS;
        }

        $this->info("Advisors a procesar: " . $advisors->count());

        $totalContacts = 0;
        $totalMatchedClients = 0;
        $totalUpdates = 0;

        foreach ($advisors as $advisor) {
            $advisorUserId = (int) $advisor->id;
            $hsOwnerId = (string) $advisor->hubspot_owner_id;

            $this->line("-> Owner HS {$hsOwnerId} => advisor user_id {$advisorUserId}");

            // 2) Buscar TODOS los contactos de ese owner
            $contacts = $this->fetchAllContactsByOwner($hubspot, $hsOwnerId, $limit, $matchMode);
            $totalContacts += count($contacts);

            if (empty($contacts)) {
                continue;
            }

            // 3) Mapear contactos -> users (clientes)
            //    match por email o por hs_id (según tu BD)
            if ($matchMode === 'email') {
                $keys = array_values(array_filter(array_map(fn($c) => $c['email'] ?? null, $contacts)));
                $keys = array_values(array_unique(array_map(fn($e) => strtolower(trim($e)), $keys)));

                if (empty($keys)) {
                    continue;
                }

                $clients = User::query()
                    ->select(['id', 'email', 'owner_id'])
                    ->whereIn(DB::raw('LOWER(email)'), $keys)
                    ->get()
                    ->keyBy(fn($u) => strtolower(trim($u->email)));

                $updates = [];
                foreach ($keys as $email) {
                    if (!isset($clients[$email])) continue;
                    $client = $clients[$email];
                    $totalMatchedClients++;

                    // no tocar si ya está igual
                    if ((int) ($client->owner_id ?? 0) === $advisorUserId) {
                        continue;
                    }

                    $updates[] = [
                        'client_id' => (int) $client->id,
                        'email' => $email,
                        'old_owner_id' => $client->owner_id,
                        'new_owner_id' => $advisorUserId,
                    ];
                }

            } else { // hs_id
                // IMPORTANTE: cambia 'hs_id' por tu columna real en users
                $keys = array_values(array_filter(array_map(fn($c) => $c['hs_id'] ?? null, $contacts)));
                $keys = array_values(array_unique($keys));

                if (empty($keys)) {
                    continue;
                }

                $clients = User::query()
                    ->select(['id', 'hs_id', 'owner_id'])
                    ->whereIn('hs_id', $keys) // <-- TU COLUMNA real
                    ->get()
                    ->keyBy('hs_id');

                $updates = [];
                foreach ($keys as $hsId) {
                    if (!isset($clients[$hsId])) continue;
                    $client = $clients[$hsId];
                    $totalMatchedClients++;

                    if ((int) ($client->owner_id ?? 0) === $advisorUserId) {
                        continue;
                    }

                    $updates[] = [
                        'client_id' => (int) $client->id,
                        'hs_id' => $hsId,
                        'old_owner_id' => $client->owner_id,
                        'new_owner_id' => $advisorUserId,
                    ];
                }
            }

            if (empty($updates)) {
                continue;
            }

            $this->info("   Cambios para este advisor: " . count($updates));
            $this->table(
                ['client_id', ($matchMode === 'email' ? 'email' : 'hs_id'), 'old_owner_id', 'new_owner_id'],
                array_slice(array_map(function ($r) use ($matchMode) {
                    return [
                        $r['client_id'],
                        $matchMode === 'email' ? $r['email'] : $r['hs_id'],
                        $r['old_owner_id'],
                        $r['new_owner_id'],
                    ];
                }, $updates), 0, 15)
            );

            if ($dryRun) {
                continue;
            }

            // 4) Aplicar updates en BD en chunks (solo si cambió)
            foreach (array_chunk($updates, $chunkSize) as $chunk) {
                foreach ($chunk as $row) {
                    $affected = User::query()
                        ->where('id', $row['client_id'])
                        ->where(function ($q) use ($row) {
                            $q->whereNull('owner_id')
                              ->orWhere('owner_id', '!=', $row['new_owner_id']);
                        })
                        ->update(['owner_id' => $row['new_owner_id']]);

                    $totalUpdates += $affected;
                }
            }
        }

        $this->info("Contactos HubSpot leídos: {$totalContacts}");
        $this->info("Clientes matcheados en users: {$totalMatchedClients}");
        $this->info("Updates aplicados: {$totalUpdates}" . ($dryRun ? " (dry-run)" : ""));

        return self::SUCCESS;
    }

    /**
     * Retorna lista de contactos del owner con keys:
     *  - email (si match=email)
     *  - hs_id (si match=hs_id)
     */
    private function fetchAllContactsByOwner($hubspot, string $ownerId, int $limit, string $matchMode): array
    {
        $all = [];
        $after = null;

        do {
            $filter = new Filter();
            $filter->setOperator('EQ')
                ->setPropertyName('hubspot_owner_id')
                ->setValue($ownerId);

            $filterGroup = new FilterGroup();
            $filterGroup->setFilters([$filter]);

            $req = new PublicObjectSearchRequest();
            $req->setFilterGroups([$filterGroup]);
            $req->setLimit($limit);

            // Pedimos email si lo vamos a usar
            $props = $matchMode === 'email' ? ['email'] : [];
            if (!empty($props)) $req->setProperties($props);

            if (!is_null($after)) $req->setAfter($after);

            $page = $hubspot->crm()->contacts()->searchApi()->doSearch($req);

            foreach ($page->getResults() as $contact) {
                if ($matchMode === 'email') {
                    $email = $contact->getProperties()['email'] ?? null;
                    if ($email) {
                        $all[] = ['email' => $email];
                    }
                } else {
                    // hs_id = HubSpot contact id
                    $all[] = ['hs_id' => $contact->getId()];
                }
            }

            $paging = $page->getPaging();
            $after = ($paging && $paging->getNext()) ? $paging->getNext()->getAfter() : null;

        } while (!is_null($after));

        return $all;
    }
}
