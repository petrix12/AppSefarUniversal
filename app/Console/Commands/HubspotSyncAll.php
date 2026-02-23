<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use HubSpot\Factory;
use HubSpot\Client\Crm\Contacts\Model\Filter;
use HubSpot\Client\Crm\Contacts\Model\FilterGroup;
use HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest;

class HubspotSyncAll extends Command
{
    protected $signature = 'hubspot:sync-all
        {--dry-run            : Solo muestra cambios, no escribe en BD}
        {--match=email        : Modo match para contactos: email|hs_id}
        {--chunk=500          : Chunk para updates locales}
        {--limit=100          : Page size HubSpot contacts (max 100)}
        {--only-owner=        : Solo procesa un hubspot_owner_id específico}
        {--only-user=         : Solo procesa un asesor por user.id}
        {--include-archived   : Incluye owners archivados de HubSpot}
        {--show-unmatched     : Muestra owners y contactos sin par en BD}
        {--export-unmatched=  : Exporta unmatched a CSV (ruta relativa a storage/app)}
        {--skip-owner-sync    : Salta el paso 1 (sync de hubspot_owner_id en asesores)}
        {--skip-client-sync   : Salta el paso 2 (sync de owner_id en clientes)}
    ';

    protected $description = '
        [Paso 1] Sincroniza HubSpot Owners → users.hubspot_owner_id (por email de asesor).
        [Paso 2] Por cada asesor, trae sus contactos de HubSpot y asigna users.owner_id a los clientes matcheados.
        Detecta conflictos (mismo owner_id en múltiples asesores) y owners/contactos sin par en BD.
    ';

    private $hubspot;
    private bool $dryRun;
    private string $matchMode;
    private int $chunkSize;
    private int $hsLimit;

    // Reportes acumulados
    private array $report = [
        'owners_in_hs'           => 0,
        'owners_matched'         => 0,
        'owners_updated'         => 0,
        'owners_unmatched'       => [],   // owners HS sin par en users
        'owner_id_conflicts'     => [],   // hubspot_owner_id duplicado en múltiples users
        'contacts_fetched'       => 0,
        'clients_matched'        => 0,
        'clients_updated'        => 0,
        'contacts_unmatched'     => [],   // emails/hs_ids de HS sin par en users
    ];

    public function handle(): int
    {
        $this->hubspot   = Factory::createWithAccessToken(config('services.hubspot.key', env('HUBSPOT_KEY')));
        $this->dryRun    = (bool)  $this->option('dry-run');
        $this->matchMode = (string) $this->option('match');
        $this->chunkSize = (int)   $this->option('chunk');
        $this->hsLimit   = (int)   $this->option('limit');

        if (!in_array($this->matchMode, ['email', 'hs_id'], true)) {
            $this->error("--match debe ser: email|hs_id");
            return self::FAILURE;
        }

        if ($this->dryRun) {
            $this->warn('⚠️  DRY-RUN activo — no se escribirá nada en BD.');
        }

        // ── PASO 1: Owners HS → users.hubspot_owner_id ──────────────────────
        if (!$this->option('skip-owner-sync')) {
            $this->step1SyncOwners();
        } else {
            $this->warn('[Paso 1 omitido por --skip-owner-sync]');
        }

        // ── PASO 2: Contactos HS → users.owner_id ───────────────────────────
        if (!$this->option('skip-client-sync')) {
            $this->step2SyncClientOwners();
        } else {
            $this->warn('[Paso 2 omitido por --skip-client-sync]');
        }

        // ── REPORTE FINAL ────────────────────────────────────────────────────
        $this->printFinalReport();

        return self::SUCCESS;
    }

    // =========================================================================
    // PASO 1 — Owners de HubSpot → users.hubspot_owner_id
    // =========================================================================

    private function step1SyncOwners(): void
    {
        $this->info("\n══════════════════════════════════════════");
        $this->info(" PASO 1 — Sync owners HubSpot → BD");
        $this->info("══════════════════════════════════════════");

        $includeArchived = (bool) $this->option('include-archived');
        $onlyOwner       = $this->option('only-owner');

        // 1a. Fetch owners desde HubSpot
        $hsOwners = $this->fetchHsOwners($includeArchived);
        $this->report['owners_in_hs'] = count($hsOwners);
        $this->info("Owners en HubSpot: " . count($hsOwners));

        if (empty($hsOwners)) {
            $this->warn('  Sin owners en HubSpot.');
            return;
        }

        // Filtro opcional
        if ($onlyOwner) {
            $hsOwners = array_filter($hsOwners, fn($o) => (string)$o['id'] === (string)$onlyOwner);
            $hsOwners = array_values($hsOwners);
            $this->info("  (Filtrado a owner_id={$onlyOwner}: " . count($hsOwners) . " owners)");
        }

        // 1b. Construir mapa email (asesor) → hs_owner_id
        $ownerByEmail = []; // email_normalizado => hs_owner_id
        foreach ($hsOwners as $o) {
            if (!empty($o['email']) && !empty($o['id'])) {
                $ownerByEmail[$o['email']] = (string) $o['id'];
            }
        }

        // 1c. Verificar conflictos: ¿hay users en BD con el mismo hubspot_owner_id asignado a varios?
        $this->detectOwnerIdConflictsInDb();

        // 1d. Buscar users que matcheen por email con algún owner de HS
        $emails        = array_keys($ownerByEmail);
        $hsEmailsSet   = array_flip($emails); // para detectar unmatched
        $dbEmailsFound = [];

        $toUpdate = [];

        User::query()
            ->select(['id', 'email', 'hubspot_owner_id'])
            ->whereIn(DB::raw('LOWER(email)'), $emails)
            ->chunkById($this->chunkSize, function ($users) use ($ownerByEmail, &$toUpdate, &$dbEmailsFound) {
                foreach ($users as $user) {
                    $email    = strtolower(trim($user->email));
                    $newOwnId = $ownerByEmail[$email] ?? null;

                    if (!$newOwnId) continue;

                    $dbEmailsFound[$email] = true;
                    $this->report['owners_matched']++;

                    // Sin cambio → skip
                    if ((string)($user->hubspot_owner_id ?? '') === $newOwnId) continue;

                    $toUpdate[] = [
                        'id'    => $user->id,
                        'email' => $email,
                        'old'   => $user->hubspot_owner_id,
                        'new'   => $newOwnId,
                    ];
                }
            });

        // 1e. Owners de HS sin par en BD
        foreach ($hsEmailsSet as $email => $_) {
            if (!isset($dbEmailsFound[$email])) {
                // Buscar el owner original para el reporte
                foreach ($hsOwners as $o) {
                    if ($o['email'] === $email) {
                        $this->report['owners_unmatched'][] = $o;
                        break;
                    }
                }
            }
        }

        $this->info("  Matcheados: {$this->report['owners_matched']}");
        $this->info("  Sin par en BD: " . count($this->report['owners_unmatched']));
        $this->info("  Cambios pendientes: " . count($toUpdate));

        if (!empty($toUpdate)) {
            $this->table(
                ['user_id', 'email', 'hs_owner_id_old', 'hs_owner_id_new'],
                array_slice(
                    array_map(fn($r) => [$r['id'], $r['email'], $r['old'] ?? 'NULL', $r['new']], $toUpdate),
                    0, 20
                )
            );
            if (count($toUpdate) > 20) {
                $this->line('  ... (mostrando primeros 20)');
            }
        }

        if ($this->dryRun || empty($toUpdate)) {
            if ($this->dryRun && !empty($toUpdate)) {
                $this->warn('  DRY-RUN: no se aplicaron cambios en hubspot_owner_id.');
            }
            return;
        }

        // 1f. Aplicar updates
        foreach (array_chunk($toUpdate, $this->chunkSize) as $chunk) {
            foreach ($chunk as $row) {
                $affected = User::query()
                    ->where('id', $row['id'])
                    ->where(function ($q) use ($row) {
                        $q->whereNull('hubspot_owner_id')
                          ->orWhere('hubspot_owner_id', '!=', $row['new']);
                    })
                    ->update(['hubspot_owner_id' => $row['new']]);

                $this->report['owners_updated'] += $affected;
            }
        }

        $this->info("  ✅ hubspot_owner_id actualizados: {$this->report['owners_updated']}");
    }

    /**
     * Detecta hubspot_owner_id duplicado asignado a más de un user (asesor) en BD.
     * Esto causa el bug que viste en la salida.
     */
    private function detectOwnerIdConflictsInDb(): void
    {
        $conflicts = DB::table('users')
            ->select('hubspot_owner_id', DB::raw('COUNT(*) as cnt'), DB::raw('GROUP_CONCAT(id ORDER BY id) as user_ids'))
            ->whereNotNull('hubspot_owner_id')
            ->groupBy('hubspot_owner_id')
            ->having('cnt', '>', 1)
            ->get();

        if ($conflicts->isEmpty()) return;

        $this->warn("  ⚠️  CONFLICTOS: hubspot_owner_id asignado a múltiples users en BD:");
        $rows = [];
        foreach ($conflicts as $c) {
            $rows[] = [$c->hubspot_owner_id, $c->cnt, $c->user_ids];
            $this->report['owner_id_conflicts'][] = (array) $c;
        }
        $this->table(['hubspot_owner_id', 'num_users', 'user_ids'], $rows);
        $this->warn("  Estos conflicts DEBEN resolverse manualmente antes de continuar.");
        $this->warn("  El Paso 2 los saltará para evitar asignaciones incorrectas.");
    }

    // =========================================================================
    // PASO 2 — Contactos HubSpot por owner → users.owner_id (clientes)
    // =========================================================================

    private function step2SyncClientOwners(): void
    {
        $this->info("\n══════════════════════════════════════════");
        $this->info(" PASO 2 — Sync clientes (owner_id) desde contactos HubSpot");
        $this->info("══════════════════════════════════════════");

        $onlyOwner  = $this->option('only-owner');
        $onlyUserId = $this->option('only-user');

        // IDs de owners conflictivos → los saltamos
        $conflictOwnerIds = array_map(
            fn($c) => (string)$c['hubspot_owner_id'],
            $this->report['owner_id_conflicts']
        );

        // Traer asesores con hubspot_owner_id
        $advisorsQ = User::query()
            ->select(['id', 'email', 'hubspot_owner_id'])
            ->whereNotNull('hubspot_owner_id');

        if ($onlyOwner)  $advisorsQ->where('hubspot_owner_id', (string)$onlyOwner);
        if ($onlyUserId) $advisorsQ->where('id', (int)$onlyUserId);

        // IMPORTANTE: excluir conflictos (a menos que --only-owner los forzó explícitamente)
        if (!empty($conflictOwnerIds) && !$onlyOwner) {
            $advisorsQ->whereNotIn('hubspot_owner_id', $conflictOwnerIds);
        }

        // IMPORTANTE: garantizar unicidad de hubspot_owner_id para no procesar el mismo owner
        // dos veces si hubiera duplicados residuales
        $advisors = $advisorsQ->get()->unique('hubspot_owner_id')->values();

        if ($advisors->isEmpty()) {
            $this->warn('  Sin asesores con hubspot_owner_id para procesar.');
            return;
        }

        $this->info("Asesores a procesar: " . $advisors->count());
        if (!empty($conflictOwnerIds)) {
            $this->warn("  (Saltando owners con conflicto: " . implode(', ', $conflictOwnerIds) . ")");
        }

        foreach ($advisors as $advisor) {
            $this->processAdvisor($advisor);
        }
    }

    private function processAdvisor(User $advisor): void
    {
        $advisorUserId = (int)    $advisor->id;
        $hsOwnerId     = (string) $advisor->hubspot_owner_id;

        $this->line("\n-> Asesor user_id={$advisorUserId} | HS owner_id={$hsOwnerId} | {$advisor->email}");

        // Fetch contactos de este owner en HubSpot
        [$contacts, $fetchedCount] = $this->fetchContactsByOwner($hsOwnerId);
        $this->report['contacts_fetched'] += $fetchedCount;

        if (empty($contacts)) {
            $this->line("   Sin contactos en HubSpot para este owner.");
            return;
        }

        $this->line("   Contactos en HubSpot: {$fetchedCount}");

        // Match contra BD
        [$updates, $unmatchedKeys] = $this->matchContactsToClients($contacts, $advisorUserId);

        // Acumular unmatched
        foreach ($unmatchedKeys as $key) {
            $this->report['contacts_unmatched'][] = [
                'advisor_user_id' => $advisorUserId,
                'hs_owner_id'     => $hsOwnerId,
                'key'             => $key,
            ];
        }

        $this->report['clients_matched'] += count($updates) + 0; // matched = los que encontramos (con y sin cambio)

        if (empty($updates)) {
            $this->line("   Sin cambios necesarios para este asesor ✅");
            return;
        }

        $this->info("   Cambios: " . count($updates));
        $col = $this->matchMode === 'email' ? 'email' : 'hs_id';
        $this->table(
            ['client_id', $col, 'old_owner_id', 'new_owner_id'],
            array_slice(array_map(fn($r) => [
                $r['client_id'],
                $r[$col],
                $r['old_owner_id'] ?? 'NULL',
                $r['new_owner_id'],
            ], $updates), 0, 15)
        );
        if (count($updates) > 15) {
            $this->line('   ... (mostrando primeros 15)');
        }

        if ($this->dryRun) {
            $this->warn('   DRY-RUN: no se aplicaron cambios.');
            return;
        }

        // Aplicar updates
        foreach (array_chunk($updates, $this->chunkSize) as $chunk) {
            foreach ($chunk as $row) {
                $affected = User::query()
                    ->where('id', $row['client_id'])
                    ->where(function ($q) use ($row) {
                        $q->whereNull('owner_id')
                          ->orWhere('owner_id', '!=', $row['new_owner_id']);
                    })
                    ->update(['owner_id' => $row['new_owner_id']]);

                $this->report['clients_updated'] += $affected;
            }
        }

        $this->info("   ✅ Actualizados: " . count($updates));
    }

    /**
     * Fetch de contactos de HubSpot para un owner.
     * Retorna [$contacts, $totalFetched]
     * $contacts = array de ['email'=>..., 'hs_id'=>...]
     */
    private function fetchContactsByOwner(string $ownerId): array
    {
        $all   = [];
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
            $req->setLimit($this->hsLimit);
            $req->setProperties(['email']); // siempre pedimos email

            if (!is_null($after)) $req->setAfter($after);

            $page = $this->hubspot->crm()->contacts()->searchApi()->doSearch($req);

            foreach ($page->getResults() as $contact) {
                $email = strtolower(trim($contact->getProperties()['email'] ?? ''));
                $hsId  = (string) $contact->getId();

                $entry = ['hs_id' => $hsId];
                if ($email !== '') $entry['email'] = $email;

                $all[] = $entry;
            }

            $paging = $page->getPaging();
            $after  = ($paging && $paging->getNext()) ? $paging->getNext()->getAfter() : null;

        } while (!is_null($after));

        return [$all, count($all)];
    }

    /**
     * Cruza $contacts (array desde HS) con users de BD.
     * Retorna [$updates, $unmatchedKeys]
     */
    private function matchContactsToClients(array $contacts, int $advisorUserId): array
    {
        $updates       = [];
        $unmatchedKeys = [];

        if ($this->matchMode === 'email') {
            // ── Match por email ─────────────────────────────────────────────
            $keys = array_values(array_unique(array_filter(
                array_map(fn($c) => $c['email'] ?? null, $contacts)
            )));

            if (empty($keys)) return [[], []];

            $clients = User::query()
                ->select(['id', 'email', 'owner_id'])
                ->whereIn(DB::raw('LOWER(email)'), $keys)
                ->get()
                ->keyBy(fn($u) => strtolower(trim($u->email)));

            foreach ($keys as $email) {
                if (!isset($clients[$email])) {
                    $unmatchedKeys[] = $email;
                    continue;
                }

                $client = $clients[$email];

                // Ya tiene el owner correcto → skip
                if ((int)($client->owner_id ?? 0) === $advisorUserId) continue;

                $updates[] = [
                    'client_id'    => (int) $client->id,
                    'email'        => $email,
                    'old_owner_id' => $client->owner_id,
                    'new_owner_id' => $advisorUserId,
                ];
            }
        } else {
            // ── Match por hs_id ──────────────────────────────────────────────
            $keys = array_values(array_unique(array_filter(
                array_map(fn($c) => $c['hs_id'] ?? null, $contacts)
            )));

            if (empty($keys)) return [[], []];

            $clients = User::query()
                ->select(['id', 'hs_id', 'owner_id'])
                ->whereIn('hs_id', $keys)
                ->get()
                ->keyBy('hs_id');

            foreach ($keys as $hsId) {
                if (!isset($clients[$hsId])) {
                    $unmatchedKeys[] = $hsId;
                    continue;
                }

                $client = $clients[$hsId];

                if ((int)($client->owner_id ?? 0) === $advisorUserId) continue;

                $updates[] = [
                    'client_id'    => (int) $client->id,
                    'hs_id'        => $hsId,
                    'old_owner_id' => $client->owner_id,
                    'new_owner_id' => $advisorUserId,
                ];
            }
        }

        return [$updates, $unmatchedKeys];
    }

    // =========================================================================
    // PASO 3 — Fetch owners desde HubSpot API
    // =========================================================================

    private function fetchHsOwners(bool $includeArchived): array
    {
        $owners = [];
        $after  = null;

        do {
            $query = [
                'limit'    => 500,
                'archived' => $includeArchived ? 'true' : 'false',
            ];
            if (!is_null($after)) $query['after'] = $after;

            $resp = $this->hubspot->apiRequest([
                'method' => 'GET',
                'path'   => '/crm/v3/owners',
                'query'  => $query,
            ]);

            $body = json_decode((string) $resp->getBody(), true);

            foreach ($body['results'] ?? [] as $o) {
                $id    = (string) ($o['id'] ?? '');
                $email = strtolower(trim($o['email'] ?? ''));

                if ($id === '') continue;

                $owners[] = [
                    'id'        => $id,
                    'email'     => $email,
                    'name'      => trim(($o['firstName'] ?? '') . ' ' . ($o['lastName'] ?? '')),
                    'active'    => $o['active'] ?? null,
                    'createdAt' => $o['createdAt'] ?? null,
                    'updatedAt' => $o['updatedAt'] ?? null,
                ];
            }

            $after = $body['paging']['next']['after'] ?? null;

        } while (!is_null($after));

        return $owners;
    }

    // =========================================================================
    // REPORTE FINAL
    // =========================================================================

    private function printFinalReport(): void
    {
        $r = $this->report;

        $this->info("\n══════════════════════════════════════════");
        $this->info(" REPORTE FINAL" . ($this->dryRun ? ' (DRY-RUN)' : ''));
        $this->info("══════════════════════════════════════════");

        $this->table(['Métrica', 'Valor'], [
            ['Owners en HubSpot',             $r['owners_in_hs']],
            ['Owners matcheados en BD',        $r['owners_matched']],
            ['hubspot_owner_id actualizados',  $r['owners_updated']],
            ['Owners HS sin par en BD',        count($r['owners_unmatched'])],
            ['Conflictos owner_id duplicado',  count($r['owner_id_conflicts'])],
            ['Contactos HS leídos',            $r['contacts_fetched']],
            ['Clientes matcheados',            $r['clients_matched']],
            ['users.owner_id actualizados',    $r['clients_updated']],
            ['Contactos HS sin par en BD',     count($r['contacts_unmatched'])],
        ]);

        // Mostrar / exportar unmatched
        $showUnmatched = (bool) $this->option('show-unmatched');
        $exportPath    = $this->option('export-unmatched');

        if ($showUnmatched && !empty($r['owners_unmatched'])) {
            $this->warn("\nOwners HubSpot sin par en BD:");
            $this->table(
                ['hs_owner_id', 'email', 'name'],
                array_slice(array_map(
                    fn($o) => [$o['id'], $o['email'], $o['name']],
                    $r['owners_unmatched']
                ), 0, 30)
            );
        }

        if ($showUnmatched && !empty($r['contacts_unmatched'])) {
            $this->warn("\nContactos HubSpot sin par en BD (primeros 30):");
            $this->table(
                ['advisor_user_id', 'hs_owner_id', 'email/hs_id'],
                array_slice(array_map(
                    fn($c) => [$c['advisor_user_id'], $c['hs_owner_id'], $c['key']],
                    $r['contacts_unmatched']
                ), 0, 30)
            );
        }

        if ($exportPath) {
            $this->exportUnmatchedCsv($exportPath);
        }

        if (!empty($r['owner_id_conflicts'])) {
            $this->warn("\n⚠️  Hay conflictos de hubspot_owner_id duplicado en BD.");
            $this->warn("   Revisa la tabla de conflictos del Paso 1 y resuélvelos manualmente.");
        }
    }

    private function exportUnmatchedCsv(string $path): void
    {
        $out = fopen('php://temp', 'r+');

        fputcsv($out, ['tipo', 'hs_owner_id', 'advisor_user_id', 'email_o_hs_id', 'name']);

        foreach ($this->report['owners_unmatched'] as $o) {
            fputcsv($out, ['owner_unmatched', $o['id'], '', $o['email'], $o['name'] ?? '']);
        }
        foreach ($this->report['contacts_unmatched'] as $c) {
            fputcsv($out, ['contact_unmatched', $c['hs_owner_id'], $c['advisor_user_id'], $c['key'], '']);
        }

        rewind($out);
        Storage::disk('local')->put($path, stream_get_contents($out));
        fclose($out);

        $this->info("CSV exportado → storage/app/{$path}");
    }
}
