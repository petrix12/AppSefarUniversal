<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Services\HubspotService;

class HubspotSyncAll extends Command
{
    protected $signature = 'hubspot:sync-all
        {--dry-run            : Solo muestra cambios, no escribe en BD}
        {--match=hs_id        : Modo de coincidencia para clientes: hs_id|email (email solo diagnóstico)}
        {--chunk=500          : Chunk para updates locales}
        {--limit=100          : Page size HubSpot contacts (max 100)}
        {--only-owner=        : Solo procesa un hubspot_owner_id específico}
        {--only-user=         : Solo procesa un asesor por user.id}
        {--include-archived   : Incluye owners archivados de HubSpot}
        {--show-unmatched     : Muestra owners y contactos sin par en BD}
        {--export-unmatched=  : Exporta unmatched a CSV (ruta relativa a storage/app)}
        {--skip-owner-sync    : Salta el paso 1 (sync de hubspot_owner_id en asesores)}
        {--skip-client-sync   : Salta el paso 2 (sync de owner_id en clientes)}
        {--owners-only        : Solo lista owners de HubSpot y termina}
        {--interactive-owners : Modo interactivo para mapear HS owners -> user_id y resolver duplicados}
        {--owners-map=exports/hubspot/owners_map.json : Ruta del mapa JSON en storage/app}
        {--use-owners-map     : En Paso 1 usa el mapa (hs_owner_id->user_id) en vez de matchear por email}
    ';

    protected $description = '
        [Paso 0] (Opcional) Listar owners / modo interactivo para mapear owners y resolver duplicados.
        [Paso 1] Sincroniza HubSpot Owners → users.hubspot_owner_id (por cruce explícito de IDs).
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
        $this->hubspot   = app(HubspotService::class);
        $this->dryRun    = (bool)  $this->option('dry-run');
        $this->matchMode = (string) $this->option('match');
        $this->chunkSize = (int)   $this->option('chunk');
        $this->hsLimit   = (int)   $this->option('limit');

        if (!in_array($this->matchMode, ['email', 'hs_id'], true)) {
            $this->error("--match debe ser: email|hs_id");
            return self::FAILURE;
        }
        if ($this->matchMode === 'email' && ! $this->option('dry-run')) {
            $this->error('La actualización por correo está bloqueada. Usa --match=hs_id; el correo solo se admite en --dry-run para diagnóstico.');
            return self::FAILURE;
        }

        if ($this->dryRun) {
            $this->warn('⚠️  DRY-RUN activo — no se escribirá nada en BD.');
        }

        // ── MODO: SOLO LISTAR OWNERS ─────────────────────────────────────────
        if ($this->option('owners-only')) {
            $this->listHsOwners((bool)$this->option('include-archived'));
            return self::SUCCESS;
        }

        // ── MODO: INTERACTIVO (resolver duplicados + mapear owners) ─────────
        if ($this->option('interactive-owners')) {
            // 0a) Resolver duplicados ya existentes en BD
            $this->resolveHubspotOwnerIdDuplicatesInteractive();

            // 0b) Construir/actualizar mapa hs_owner_id -> user_id
            $this->interactiveOwnersMap((bool)$this->option('include-archived'));

            return self::SUCCESS;
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

        // ── REPORTE FINAL ───────────────────────────────────────────────────
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

        // Siempre detecta duplicados actuales (y reporta)
        $this->detectOwnerIdConflictsInDb();

        // El cruce de responsables se basa en un mapa explícito por IDs.
        if ($this->option('use-owners-map')) {
            $this->step1SyncOwnersUsingMap();
        } else {
            $this->step1SyncOwnersFromBridge();
        }
    }

    /** Apply the explicit HubSpot owner ID ↔ internal account crosswalk. */
    private function step1SyncOwnersFromBridge(): void
    {
        $this->info('  (Mapa explícito hubspot_owner_user por ID)');
        if (! \Illuminate\Support\Facades\Schema::hasTable('hubspot_owner_user')) {
            $this->warn('  Falta hubspot_owner_user; no se infieren responsables por correo.');
            return;
        }

        $internalUserIds = User::internalUsers()->select('users.id');
        $query = DB::table('hubspot_owner_user')
            ->whereIn('user_id', $internalUserIds)
            ->whereNotNull('hubspot_owner_id')
            ->whereRaw("TRIM(hubspot_owner_id) <> ''");
        if ($this->option('only-owner')) $query->where('hubspot_owner_id', (string) $this->option('only-owner'));
        if ($this->option('only-user')) $query->where('user_id', (int) $this->option('only-user'));
        $mappings = $query->get(['user_id', 'hubspot_owner_id', 'hubspot_owner_name']);

        $byOwner = $mappings->groupBy('hubspot_owner_id');
        $rows = [];
        foreach ($byOwner as $ownerId => $ownerMappings) {
            if ($ownerMappings->count() !== 1) {
                $this->warn("  ID de responsable ambigu en hubspot_owner_user; se omite {$ownerId}.");
                continue;
            }
            $mapping = $ownerMappings->first();
            $user = User::internalUsers()->whereKey($mapping->user_id)->first(['id', 'email', 'hubspot_owner_id']);
            if (! $user) continue;

            $alreadyAssigned = User::internalUsers()
                ->where('hubspot_owner_id', (string) $ownerId)
                ->where('id', '!=', $user->id)
                ->exists();
            if ($alreadyAssigned) {
                $this->warn("  {$ownerId} ya está asignado a otra cuenta interna; se omite.");
                continue;
            }

            if ((string) $user->hubspot_owner_id === (string) $ownerId) continue;
            $rows[] = [
                'user_id' => (int) $user->id,
                'email' => (string) $user->email,
                'old' => $user->hubspot_owner_id,
                'new' => (string) $ownerId,
            ];
        }

        $this->report['owners_matched'] = $mappings->count();
        $this->info('  Mapeos únicos aplicables: '.count($rows));
        if ($rows !== []) {
            $this->table(['user_id', 'correo interno', 'ID anterior', 'ID HubSpot'], array_map(
                fn (array $row): array => [$row['user_id'], $row['email'], $row['old'] ?? '—', $row['new']],
                array_slice($rows, 0, 30),
            ));
        }

        if ($this->dryRun) {
            $this->warn('  DRY-RUN: no se modificaron los responsables.');
            return;
        }

        foreach ($rows as $row) {
            $this->report['owners_updated'] += User::internalUsers()
                ->whereKey($row['user_id'])
                ->where('hubspot_owner_id', $row['old'])
                ->update(['hubspot_owner_id' => $row['new']]);
        }
        $this->info('  Responsables internos actualizados: '.$this->report['owners_updated']);
    }

    /** Optional explicit JSON crosswalk for installations not using the bridge table. */
    private function step1SyncOwnersUsingMap(): void
    {
        $this->info("  (Modo mapa activo: --use-owners-map)");

        $mapPath = (string)$this->option('owners-map');
        if (!Storage::disk('local')->exists($mapPath)) {
            $this->error("No existe el mapa: storage/app/{$mapPath}. Ejecuta primero --interactive-owners.");
            return;
        }

        $map = json_decode(Storage::disk('local')->get($mapPath), true) ?: [];
        if (empty($map)) {
            $this->warn("Mapa vacío. Nada que hacer.");
            return;
        }

        $onlyOwner = (string)($this->option('only-owner') ?? '');
        $onlyUser  = $this->option('only-user');

        $rows = [];
        foreach ($map as $hsOwnerId => $data) {
            $userId = (int)($data['user_id'] ?? 0);
            if ($userId <= 0) continue;
            if (! User::internalUsers()->whereKey($userId)->exists()) {
                $this->warn("Mapa omitido: user_id={$userId} no es una cuenta interna.");
                continue;
            }

            if ($onlyOwner !== '' && (string)$hsOwnerId !== (string)$onlyOwner) continue;
            if ($onlyUser && (int)$onlyUser !== $userId) continue;

            $rows[] = ['hs_owner_id' => (string)$hsOwnerId, 'user_id' => $userId];
        }

        if (empty($rows)) {
            $this->warn("No hay filas para aplicar (por filtros o mapa).");
            return;
        }

        $this->info("Entradas a aplicar: " . count($rows));

        // Validación previa: duplicados dentro del MAPA (mismo hsOwnerId es key, ok),
        // pero un mismo user_id podría repetirse: eso NO es problema para unicidad del hs_owner_id,
        // aunque conceptualmente quizás no quieras 2 owners -> 1 user. Te lo avisamos:
        $userIdCounts = [];
        foreach ($rows as $r) {
            $userIdCounts[$r['user_id']] = ($userIdCounts[$r['user_id']] ?? 0) + 1;
        }
        $multi = array_filter($userIdCounts, fn($n) => $n > 1);
        if (!empty($multi)) {
            $this->warn("⚠️  El mapa tiene el mismo user_id repetido para varios hs_owner_id. Revisa si es intencional.");
        }

        if ($this->dryRun) {
            $this->warn("DRY-RUN: no se aplicarán cambios.");
            $this->table(['hs_owner_id', 'user_id'], array_slice(array_map(fn($r) => [$r['hs_owner_id'], $r['user_id']], $rows), 0, 50));
            return;
        }

        $updated = 0;
        foreach ($rows as $r) {
            $hsOwnerId = (string)$r['hs_owner_id'];
            $userId    = (int)$r['user_id'];

            // Regla dura: si hs_owner_id ya está en OTRO user, no lo asignamos
            $existsElsewhere = User::internalUsers()
                ->where('hubspot_owner_id', $hsOwnerId)
                ->where('id', '!=', $userId)
                ->exists();

            if ($existsElsewhere) {
                $this->warn("Saltado: hs_owner_id={$hsOwnerId} ya está asignado a otro user (no a {$userId}). Usa --interactive-owners para resolver.");
                continue;
            }

            $affected = User::internalUsers()
                ->whereKey($userId)
                ->where(function ($q) use ($hsOwnerId) {
                    $q->whereNull('hubspot_owner_id')
                        ->orWhere('hubspot_owner_id', '!=', $hsOwnerId);
                })
                ->update([
                    'hubspot_owner_id' => $hsOwnerId,
                    'updated_at' => now(),
                ]);

            $updated += $affected;
        }

        $this->report['owners_updated'] += $updated;
        $this->info("✅ Aplicado por mapa. Actualizados: {$updated}");
    }

    /**
     * Detecta hubspot_owner_id duplicado asignado a más de un user en BD.
     */
    private function detectOwnerIdConflictsInDb(): void
    {
        $internalUserIds = User::internalUsers()->select('users.id');
        $conflicts = DB::table('users')
            ->select(
                'hubspot_owner_id',
                DB::raw('COUNT(*) as num_users'),
                DB::raw('GROUP_CONCAT(id ORDER BY id SEPARATOR ",") as user_ids')
            )
            ->whereIn('id', $internalUserIds)
            ->whereNotNull('hubspot_owner_id')
            ->whereRaw("TRIM(hubspot_owner_id) <> ''")
            ->groupBy('hubspot_owner_id')
            ->having('num_users', '>', 1)
            ->get();

        if ($conflicts->isEmpty()) {
            return;
        }

        $this->warn("  ⚠️  CONFLICTOS: hubspot_owner_id asignado a múltiples users en BD:");

        $rows = [];
        foreach ($conflicts as $c) {
            $rows[] = [
                $c->hubspot_owner_id,
                $c->num_users,
                strlen($c->user_ids) > 80 ? substr($c->user_ids, 0, 80) . '…' : $c->user_ids,
            ];
            $this->report['owner_id_conflicts'][] = (array) $c;
        }

        $this->table(['hubspot_owner_id', 'num_users', 'user_ids (primeros)'], $rows);
        $this->warn("  Estos conflictos deben resolverse. Puedes usar: php artisan hubspot:sync-all --interactive-owners");
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
            ->internalUsers()
            ->select(['id', 'email', 'hubspot_owner_id'])
            ->whereNotNull('hubspot_owner_id')
            ->whereRaw("TRIM(hubspot_owner_id) <> ''");

        if ($onlyOwner)  $advisorsQ->where('hubspot_owner_id', (string)$onlyOwner);
        if ($onlyUserId) $advisorsQ->where('id', (int)$onlyUserId);

        // Excluir conflictos si no forzó un owner
        if (!empty($conflictOwnerIds) && !$onlyOwner) {
            $advisorsQ->whereNotIn('hubspot_owner_id', $conflictOwnerIds);
        }

        // Unicidad de hubspot_owner_id
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

        foreach ($unmatchedKeys as $key) {
            $this->report['contacts_unmatched'][] = [
                'advisor_user_id' => $advisorUserId,
                'hs_owner_id'     => $hsOwnerId,
                'key'             => $key,
            ];
        }

        $this->report['clients_matched'] += count($updates) + 0;

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

        foreach (array_chunk($updates, $this->chunkSize) as $chunk) {
            foreach ($chunk as $row) {
                $affected = User::clients()
                    ->where('id', $row['client_id'])
                    ->where('hs_id', $row['hs_id'] ?? '')
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
     */
    private function fetchContactsByOwner(string $ownerId): array
    {
        $all = [];
        $contacts = $this->hubspot->getAllContactsByOwnerId($ownerId, ['email'], $this->hsLimit);
        foreach ($contacts as $contact) {
            $email = strtolower(trim((string) ($contact['properties']['email'] ?? '')));
            $entry = ['hs_id' => (string) $contact['id']];
            if ($email !== '') $entry['email'] = $email;
            $all[] = $entry;
        }

        return [$all, count($all)];
    }

    /**
     * Cruza $contacts con users de BD.
     * Retorna [$updates, $unmatchedKeys]
     */
    private function matchContactsToClients(array $contacts, int $advisorUserId): array
    {
        $updates       = [];
        $unmatchedKeys = [];

        if ($this->matchMode === 'email') {
            $keys = array_values(array_unique(array_filter(
                array_map(fn($c) => $c['email'] ?? null, $contacts)
            )));

            if (empty($keys)) return [[], []];

            $clients = User::clients()
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

                if ((int)($client->owner_id ?? 0) === $advisorUserId) continue;

                $updates[] = [
                    'client_id'    => (int) $client->id,
                    'email'        => $email,
                    'old_owner_id' => $client->owner_id,
                    'new_owner_id' => $advisorUserId,
                ];
            }
        } else {
            $keys = array_values(array_unique(array_filter(
                array_map(fn($c) => $c['hs_id'] ?? null, $contacts)
            )));

            if (empty($keys)) return [[], []];

            $clients = User::clients()
                ->select(['id', 'hs_id', 'owner_id'])
                ->whereIn('hs_id', $keys)
                ->get()
                ->groupBy('hs_id');

            foreach ($keys as $hsId) {
                if (!isset($clients[$hsId]) || $clients[$hsId]->count() !== 1) {
                    $unmatchedKeys[] = $hsId;
                    continue;
                }

                $client = $clients[$hsId]->first();

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
        return $this->hubspot->getAllOwners($includeArchived);
    }

    // =========================================================================
    // INTERACTIVO — LISTA OWNERS
    // =========================================================================

    private function listHsOwners(bool $includeArchived): void
    {
        $hsOwners = $this->fetchHsOwners($includeArchived);

        if (empty($hsOwners)) {
            $this->warn('Sin owners en HubSpot.');
            return;
        }

        $rows = array_map(fn($o) => [
            $o['id'],
            $o['email'] ?? '',
            $o['name'] ?? '',
            ($o['active'] ?? null) === true ? 'yes' : (($o['active'] ?? null) === false ? 'no' : ''),
        ], $hsOwners);

        $this->table(['hs_owner_id', 'email', 'name', 'active'], array_slice($rows, 0, 200));
        if (count($rows) > 200) $this->line('... (mostrando primeros 200)');
    }

    // =========================================================================
    // INTERACTIVO — RESOLVER DUPLICADOS EN BD
    // =========================================================================

    private function resolveHubspotOwnerIdDuplicatesInteractive(): void
    {
        $this->info("\n══════════════════════════════════════════");
        $this->info(" RESOLVER DUPLICADOS: users.hubspot_owner_id");
        $this->info("══════════════════════════════════════════");

        $internalUserIds = User::internalUsers()->select('users.id');
        $dups = DB::table('users')
            ->select('hubspot_owner_id', DB::raw('COUNT(*) as n'))
            ->whereIn('id', $internalUserIds)
            ->whereNotNull('hubspot_owner_id')
            ->whereRaw("TRIM(hubspot_owner_id) <> ''")
            ->groupBy('hubspot_owner_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('n')
            ->get();

        if ($dups->isEmpty()) {
            $this->info("✅ No hay duplicados de hubspot_owner_id en BD.");
            return;
        }

        $this->warn("⚠️  Duplicados encontrados: " . $dups->count());
        $this->warn("Regla: cada hubspot_owner_id debe quedar en UN SOLO user.");

        foreach ($dups as $d) {
            $hsOwnerId = (string)$d->hubspot_owner_id;

            $users = DB::table('users')
                ->select(['id', 'email', 'name', 'hubspot_owner_id'])
                ->whereIn('id', $internalUserIds)
                ->where('hubspot_owner_id', $hsOwnerId)
                ->orderBy('id')
                ->get();

            $this->line("\nHubSpot Owner ID duplicado: {$hsOwnerId} (n={$d->n})");
            $this->table(
                ['user_id', 'email', 'name', 'hubspot_owner_id'],
                $users->map(fn($u) => [$u->id, $u->email, $u->name, $u->hubspot_owner_id])->toArray()
            );

            $keep = $this->ask("¿Qué user_id debe CONSERVAR hubspot_owner_id={$hsOwnerId}? (enter=skip)");

            $keep = trim((string)$keep);
            if ($keep === '') {
                $this->warn("Skip: no se resolvió este duplicado.");
                continue;
            }

            $keepId = (int)$keep;
            if ($keepId <= 0 || !$users->firstWhere('id', $keepId)) {
                $this->error("user_id inválido: {$keepId}. Skip.");
                continue;
            }

            if ($this->dryRun) {
                $this->warn("[DRY-RUN] Se mantendría en user_id={$keepId} y se nullearía en los demás.");
                continue;
            }

            // Nullear en los demás (regla dura)
            $nulled = DB::table('users')
                ->where('hubspot_owner_id', $hsOwnerId)
                ->whereIn('id', $internalUserIds)
                ->where('id', '!=', $keepId)
                ->update([
                    'hubspot_owner_id' => null,
                    'updated_at' => now(),
                ]);

            $this->info("✅ Resuelto: conservado en user_id={$keepId}. Nulleados: {$nulled}");
        }

        // Re-check
        $still = DB::table('users')
            ->select('hubspot_owner_id', DB::raw('COUNT(*) as n'))
            ->whereIn('id', $internalUserIds)
            ->whereNotNull('hubspot_owner_id')
            ->whereRaw("TRIM(hubspot_owner_id) <> ''")
            ->groupBy('hubspot_owner_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($still > 0) {
            $this->warn("⚠️  Aún quedan duplicados sin resolver: {$still} (quizá los saltaste).");
        } else {
            $this->info("✅ Todos los duplicados quedaron resueltos.");
        }
    }

    // =========================================================================
    // INTERACTIVO — MAPEAR OWNERS HS -> user_id CON GUARDA DE UNICIDAD
    // =========================================================================

    private function interactiveOwnersMap(bool $includeArchived): void
    {
        $mapPath = (string) $this->option('owners-map');

        $hsOwners = $this->fetchHsOwners($includeArchived);
        if (empty($hsOwners)) {
            $this->warn('Sin owners en HubSpot.');
            return;
        }

        // Cargar mapa existente si existe
        $ownerMap = [];
        if (Storage::disk('local')->exists($mapPath)) {
            $ownerMap = json_decode(Storage::disk('local')->get($mapPath), true) ?: [];
        }

        $this->info("\n══════════════════════════════════════════");
        $this->info(" MAPEAR OWNERS: hs_owner_id -> user_id");
        $this->info("══════════════════════════════════════════");
        $this->info("Mapa: storage/app/{$mapPath}");
        $this->info("Owners en HS: " . count($hsOwners));
        $this->line("Enter vacío = saltar, '0' = borrar mapeo para ese hs_owner_id.");

        $updated = 0;

        foreach ($hsOwners as $o) {
            $hsOwnerId = (string)($o['id'] ?? '');
            $email     = strtolower(trim((string)($o['email'] ?? '')));
            $name      = (string)($o['name'] ?? '');

            if ($hsOwnerId === '') continue;

            // si filtran solo un owner
            $onlyOwner = (string)($this->option('only-owner') ?? '');
            if ($onlyOwner !== '' && $onlyOwner !== $hsOwnerId) continue;

            $currentMappedUserId = (int)($ownerMap[$hsOwnerId]['user_id'] ?? 0);

            $this->line("\nHS OWNER: {$hsOwnerId} | {$name} | {$email}");
            if ($currentMappedUserId > 0) {
                $this->warn("Map actual: user_id={$currentMappedUserId}");
            }

            // Mostrar si ya existe este hs_owner_id en BD asignado (debería ser 1:1)
            $assignedInDb = DB::table('users')
                ->select(['id', 'email', 'name', 'hubspot_owner_id'])
                ->whereIn('id', User::internalUsers()->select('users.id'))
                ->where('hubspot_owner_id', $hsOwnerId)
                ->orderBy('id')
                ->get();

            if ($assignedInDb->count() > 0) {
                $this->warn("Actualmente en BD hubspot_owner_id={$hsOwnerId} está asignado a:");
                $this->table(
                    ['user_id', 'email', 'name', 'hubspot_owner_id'],
                    $assignedInDb->map(fn($u) => [$u->id, $u->email, $u->name, $u->hubspot_owner_id])->toArray()
                );
            }

            // Candidatos por email
            $candidates = [];
            if ($email !== '') {
                $candidates = User::internalUsers()
                    ->select(['id', 'email', 'name', 'hubspot_owner_id'])
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->limit(10)
                    ->get()
                    ->map(fn($u) => [
                        'id' => (int)$u->id,
                        'email' => $u->email,
                        'name' => $u->name,
                        'hubspot_owner_id' => $u->hubspot_owner_id,
                    ])
                    ->toArray();

                if (!empty($candidates)) {
                    $this->table(
                        ['user_id', 'email', 'name', 'hubspot_owner_id'],
                        array_map(fn($c) => [$c['id'], $c['email'], $c['name'], $c['hubspot_owner_id'] ?? ''], $candidates)
                    );
                }
            } else {
                $this->line("Sin email en HS para sugerir candidatos.");
            }

            $answer = $this->ask("Asigna user_id para hs_owner_id={$hsOwnerId} (enter=skip, 0=borrar)");

            $answer = trim((string)$answer);
            if ($answer === '') continue;

            // borrar mapeo
            if ($answer === '0') {
                unset($ownerMap[$hsOwnerId]);
                $updated++;
                $this->warn("Borrado mapeo para hs_owner_id={$hsOwnerId}");
                continue;
            }

            $userId = (int)$answer;
            if ($userId <= 0) {
                $this->error("user_id inválido: {$answer}");
                continue;
            }

            /** GUARDRAILS (lo que me pediste):
             * 1) Si este hs_owner_id ya está en otro user, NO se permite duplicar:
             *    - te doy opción a moverlo (nullear en otro y asignar al nuevo) o cancelar.
             * 2) Si el user seleccionado ya tiene OTRO hubspot_owner_id, te pregunto si reemplazar.
             */

            $targetUser = DB::table('users')
                ->select(['id', 'email', 'name', 'hubspot_owner_id'])
                ->whereIn('id', User::internalUsers()->select('users.id'))
                ->where('id', $userId)
                ->first();

            if (!$targetUser) {
                $this->error("No existe user_id={$userId}");
                continue;
            }

            $targetCurrentHs = (string)($targetUser->hubspot_owner_id ?? '');

            // 1) hs_owner_id ya asignado a otro user?
            $assignedOther = DB::table('users')
                ->select(['id', 'email', 'name'])
                ->whereIn('id', User::internalUsers()->select('users.id'))
                ->where('hubspot_owner_id', $hsOwnerId)
                ->where('id', '!=', $userId)
                ->first();

            if ($assignedOther) {
                $this->warn("⚠️  hs_owner_id={$hsOwnerId} YA está asignado a OTRO user:");
                $this->line("   other_user_id={$assignedOther->id} | {$assignedOther->email} | {$assignedOther->name}");
                $choice = $this->choice(
                    "¿Qué hago?",
                    [
                        'move' => 'Mover hs_owner_id al user seleccionado (nullear en el otro)',
                        'skip' => 'No hacer nada / saltar este owner',
                    ],
                    'skip'
                );

                if ($choice === 'skip') {
                    $this->warn("Saltado hs_owner_id={$hsOwnerId}");
                    continue;
                }

                if ($choice === 'move' && !$this->dryRun) {
                    DB::table('users')
                        ->where('id', (int)$assignedOther->id)
                        ->whereIn('id', User::internalUsers()->select('users.id'))
                        ->update(['hubspot_owner_id' => null, 'updated_at' => now()]);
                    $this->info("Nulleado en other_user_id={$assignedOther->id}");
                } elseif ($choice === 'move' && $this->dryRun) {
                    $this->warn("[DRY-RUN] Se nullearía en other_user_id={$assignedOther->id}");
                }
            }

            // 2) user seleccionado ya tiene otro hubspot_owner_id?
            if ($targetCurrentHs !== '' && $targetCurrentHs !== $hsOwnerId) {
                $this->warn("⚠️  user_id={$userId} ya tiene hubspot_owner_id={$targetCurrentHs}");
                $choice2 = $this->choice(
                    "¿Reemplazarlo por {$hsOwnerId}?",
                    [
                        'replace' => 'Sí, reemplazar',
                        'skip'    => 'No, saltar',
                    ],
                    'skip'
                );

                if ($choice2 === 'skip') {
                    $this->warn("Saltado user_id={$userId}");
                    continue;
                }

                // si reemplaza, mantener unicidad del viejo (opcional) — aquí solo reemplazamos en ese user
            }

            // Guardar mapa
            $ownerMap[$hsOwnerId] = [
                'user_id' => $userId,
                'email' => $email,
                'name' => $name,
                'updated_at' => now()->toIso8601String(),
            ];
            $updated++;
            $this->info("✅ Mapeado hs_owner_id={$hsOwnerId} -> user_id={$userId}");

            // Opcional: aplicar YA en BD (para que quede consistente inmediatamente)
            if (!$this->dryRun) {
                // Regla dura: no debe existir en otro user (arriba ya lo movimos o saltamos)
                DB::table('users')
                    ->where('id', $userId)
                    ->whereIn('id', User::internalUsers()->select('users.id'))
                    ->update(['hubspot_owner_id' => $hsOwnerId, 'updated_at' => now()]);
            } else {
                $this->warn("[DRY-RUN] Se asignaría hubspot_owner_id={$hsOwnerId} a user_id={$userId}");
            }
        }

        if (! $this->dryRun) {
            Storage::disk('local')->put($mapPath, json_encode($ownerMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("\nGuardado mapa: storage/app/{$mapPath}");
        } else {
            $this->warn("\nDRY-RUN: no se guardó el mapa en storage.");
        }
        $this->info("Cambios realizados: {$updated}");

        // Validación final: no duplicados en BD
        $still = DB::table('users')
            ->select('hubspot_owner_id', DB::raw('COUNT(*) as n'))
            ->whereIn('id', User::internalUsers()->select('users.id'))
            ->whereNotNull('hubspot_owner_id')
            ->whereRaw("TRIM(hubspot_owner_id) <> ''")
            ->groupBy('hubspot_owner_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(5)
            ->get();

        if ($still->count() > 0) {
            $this->warn("⚠️  Aún existen duplicados en BD (muestra 5):");
            $this->table(['hubspot_owner_id', 'n'], $still->map(fn($x) => [$x->hubspot_owner_id, $x->n])->toArray());
            $this->warn("Repite --interactive-owners para resolverlos.");
        } else {
            $this->info("✅ Unicidad OK: no hay hubspot_owner_id duplicados en BD.");
        }
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
            $this->warn("   Ejecuta: php artisan hubspot:sync-all --interactive-owners");
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
