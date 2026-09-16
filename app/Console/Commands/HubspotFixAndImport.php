<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Negocio;
use App\Jobs\SyncUserDealsJob;
use HubSpot\Factory;
use HubSpot\Client\Crm\Contacts\Model\Filter;
use HubSpot\Client\Crm\Contacts\Model\FilterGroup;
use HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest;

class HubspotFixAndImport extends Command
{
    protected $signature = 'hubspot:fix-and-import
        {--dry-run            : Solo muestra cambios, no escribe nada}
        {--skip-contacts      : Salta la fase de corrección de contactos}
        {--skip-deals         : Salta la fase de importación de negocios}
        {--only-user=         : Procesa solo este user_id de la BD}
        {--passport=          : Filtra por número de pasaporte específico}
        {--limit=100          : Page size para búsquedas en HubSpot (max 100)}
        {--sync-deals-now     : Despacha SyncUserDealsJob en modo sync (no queue)}
        {--duplicates-csv=    : Ruta del CSV para duplicados sin resolver}
    ';

    protected $description = '
        FASE 1 — Busca cada usuario de la BD en HubSpot (por email y pasaporte):
                 · Rellena users.hs_id
                 · Corrige email si el pasaporte coincide pero el correo difiere
                 · Detecta duplicados de email/pasaporte y ofrece:
                   1) Combinar  2) Sincronizar con X  3) Guardar en CSV
        FASE 2 — Dispara SyncUserDealsJob para cada usuario con hs_id.
    ';

    // ── Estado interno ─────────────────────────────────────────────────────────

    private $hubspot;
    private bool   $dryRun;
    private int    $hsLimit;
    private string $duplicatesCsvPath;

    // ── Rate limiting ──────────────────────────────────────────────────────────
    // HubSpot Search API: ~10 req/s en planes Professional/Enterprise.
    // Hacemos una pausa de 1.1 s cada 9 requests para mantenernos holgados.
    private int $hsRequestCount     = 0;
    private int $hsRequestsPerBurst = 9;      // requests antes de pausar
    private int $hsSleepMs          = 3100;   // milisegundos de pausa

    /** Reporte acumulado */
    private array $report = [
        'db_users_total'        => 0,
        'hs_found_by_email'     => 0,
        'hs_found_by_passport'  => 0,
        'hs_not_found'          => 0,
        'hs_id_filled'          => 0,
        'email_corrected'       => 0,
        'duplicates_found'      => 0,
        'duplicates_merged'     => 0,
        'duplicates_synced_x'   => 0,
        'duplicates_csv'        => 0,
        'deals_jobs_dispatched' => 0,
    ];

    // ── Entry point ────────────────────────────────────────────────────────────

    public function handle(): int
    {
        $this->hubspot           = Factory::createWithAccessToken(
            config('services.hubspot.key', env('HUBSPOT_KEY'))
        );
        $this->dryRun            = (bool) $this->option('dry-run');
        $this->hsLimit           = (int)  $this->option('limit');
        $this->duplicatesCsvPath = $this->option('duplicates-csv')
            ?? storage_path('hubspot_duplicates.csv');

        if ($this->dryRun) {
            $this->warn('⚠️  DRY-RUN activo — no se escribirá nada en BD.');
        }

        // ── FASE 1 ────────────────────────────────────────────────────────────
        if (!$this->option('skip-contacts')) {
            $abort = $this->fase1FixContacts();
            if ($abort) {
                $this->warn('Ejecución abortada en FASE 1.');
                $this->printReport();
                return self::FAILURE;
            }
        } else {
            $this->warn('[FASE 1 omitida por --skip-contacts]');
        }

        // ── FASE 2 ────────────────────────────────────────────────────────────
        if (!$this->option('skip-deals')) {
            $this->fase2SyncDeals();
        } else {
            $this->warn('[FASE 2 omitida por --skip-deals]');
        }

        $this->printReport();
        return self::SUCCESS;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FASE 1 — BD → HubSpot
    // ══════════════════════════════════════════════════════════════════════════

    private function fase1FixContacts(): bool
    {
        $this->info("\n══════════════════════════════════════════");
        $this->info(" FASE 1 — BD → HubSpot");
        $this->info("══════════════════════════════════════════");

        // 1. Detectar duplicados internos en BD
        $this->info("Detectando duplicados internos en BD...");
        $duplicates = $this->detectDuplicatesInDb();

        $idsToSkip = [];

        if (!empty($duplicates)) {
            $this->report['duplicates_found'] = count($duplicates);
            [$idsToSkip, $abort] = $this->handleDuplicates($duplicates);
            if ($abort) return true;
        }

        // 2. Cargar usuarios de BD
        $onlyUserId   = $this->option('only-user');
        $onlyPassport = $this->option('passport');

        $dbQuery = User::query()->select(['id', 'email', 'passport', 'hs_id', 'name']);
        if ($onlyUserId)        $dbQuery->where('id', (int) $onlyUserId);
        if ($onlyPassport)      $dbQuery->whereRaw('LOWER(passport) = ?', [strtolower($onlyPassport)]);
        if (!empty($idsToSkip)) $dbQuery->whereNotIn('id', $idsToSkip);

        $dbUsers = $dbQuery->get();
        $this->report['db_users_total'] = $dbUsers->count();
        $this->info("Usuarios a procesar: {$this->report['db_users_total']}");

        // 3. Buscar cada usuario en HubSpot
        $hsIdFills  = [];
        $emailFixes = [];

        $bar = $this->output->createProgressBar($dbUsers->count());
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('iniciando...');
        $bar->start();

        foreach ($dbUsers as $user) {
            $bar->setMessage("user_id={$user->id}");

            $hsContact = $this->findHsContact($user);

            if (!$hsContact) {
                $this->report['hs_not_found']++;
                $bar->advance();
                continue;
            }

            $hsId       = (string) $hsContact['hs_id'];
            $hsEmail    = strtolower(trim($hsContact['email']    ?? ''));
            $hsPassport = strtolower(trim($hsContact['passport'] ?? ''));
            $userEmail  = strtolower(trim($user->email           ?? ''));
            $userPass   = strtolower(trim($user->passport        ?? ''));

            // Rellenar hs_id
            if (empty($user->hs_id) || (string) $user->hs_id !== $hsId) {
                $hsIdFills[$user->id] = $hsId;
            }

            // Corregir email si el pasaporte coincide pero el email difiere
            if (
                $userPass && $hsPassport
                && $userPass === $hsPassport
                && $userEmail !== $hsEmail
                && $hsEmail !== ''
            ) {
                $emailFixes[$user->id] = [
                    'user_id'   => $user->id,
                    'passport'  => $user->passport,
                    'old_email' => $user->email,
                    'new_email' => $hsEmail,
                ];
            }

            $bar->advance();
        }

        $bar->setMessage('✔ completado');
        $bar->finish();
        $this->newLine(2);

        $this->info("Requests a HubSpot realizados: {$this->hsRequestCount}");

        // 4. Aplicar cambios
        $this->applyHsIdFills($hsIdFills);
        $this->applyEmailFixes($emailFixes);

        return false;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Búsqueda individual en HubSpot
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Busca el contacto en HubSpot para un User de BD.
     * 1º por email, 2º por pasaporte.
     */
    private function findHsContact(User $user): ?array
    {
        $properties = ['email', 'passport', 'firstname', 'lastname', 'phone'];

        // Intento 1: por email
        if (!empty($user->email)) {
            $result = $this->searchHsContactByProperty('email', $user->email, $properties);
            if ($result) {
                $this->report['hs_found_by_email']++;
                return $result;
            }
        }

        // Intento 2: por pasaporte
        if (!empty($user->passport)) {
            $result = $this->searchHsContactByProperty('passport', $user->passport, $properties);
            if ($result) {
                $this->report['hs_found_by_passport']++;
                return $result;
            }
        }

        return null;
    }

    /**
     * Llama a HubSpot Search API con un filtro exacto.
     * Incluye rate limiting y reintentos ante 429.
     */
    private function searchHsContactByProperty(
        string $property,
        string $value,
        array  $properties
    ): ?array {
        // ── Rate limiting preventivo ──────────────────────────────────────────
        // Cada $hsRequestsPerBurst requests hacemos una pausa corta
        // para no superar el límite de HubSpot (~10 req/s).
        $this->hsRequestCount++;

        if ($this->hsRequestCount % $this->hsRequestsPerBurst === 0) {
            usleep($this->hsSleepMs * 1000);
        }

        // ── Llamada con reintento ante 429 ────────────────────────────────────
        $maxRetries = 3;
        $attempt    = 0;

        while ($attempt < $maxRetries) {
            try {
                $filter = new Filter([
                    'property_name' => $property,
                    'operator'      => 'EQ',
                    'value'         => $value,
                ]);

                $filterGroup = new FilterGroup(['filters' => [$filter]]);

                $request = new PublicObjectSearchRequest([
                    'filter_groups' => [$filterGroup],
                    'properties'    => $properties,
                    'limit'         => 1,
                ]);

                $response = $this->hubspot
                    ->crm()
                    ->contacts()
                    ->searchApi()
                    ->doSearch($request);

                $results = $response->getResults();

                if (empty($results)) return null;

                $contact = $results[0];
                $props   = $contact->getProperties();

                return [
                    'hs_id'     => (string) $contact->getId(),
                    'email'     => strtolower(trim($props['email']     ?? '')),
                    'passport'  => strtolower(trim($props['passport']  ?? '')),
                    'firstname' => $props['firstname'] ?? '',
                    'lastname'  => $props['lastname']  ?? '',
                    'phone'     => $props['phone']     ?? '',
                ];

            } catch (\GuzzleHttp\Exception\ClientException $e) {
                // 429 Too Many Requests → esperar y reintentar
                if ($e->getResponse() && $e->getResponse()->getStatusCode() === 429) {
                    $attempt++;
                    $waitMs = 5000 * $attempt; // 5s, 10s, 15s
                    $this->warn(
                        "\n   ⏳ 429 recibido en {$property}={$value} "
                        . "(intento {$attempt}/{$maxRetries}). "
                        . "Esperando {$waitMs}ms..."
                    );
                    usleep($waitMs * 1000);
                    continue;
                }
                // Otro error de cliente → no reintentar
                $this->warn("\n   ⚠️  Error HTTP buscando {$property}={$value}: " . $e->getMessage());
                return null;

            } catch (\Throwable $e) {
                $this->warn("\n   ⚠️  Error buscando {$property}={$value}: " . $e->getMessage());
                return null;
            }
        }

        $this->warn("\n   ❌ Se agotaron los reintentos para {$property}={$value}.");
        return null;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Detección y manejo de duplicados en BD
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Detecta grupos de usuarios con el mismo email O el mismo pasaporte.
     */
    private function detectDuplicatesInDb(): array
    {
        $groups = [];

        // Duplicados por email
        $dupEmails = DB::table('users')
            ->select(DB::raw('LOWER(email) as val'), DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->groupBy(DB::raw('LOWER(email)'))
            ->having('cnt', '>', 1)
            ->pluck('val')
            ->toArray();

        foreach ($dupEmails as $email) {
            $users = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->select(['id', 'email', 'passport', 'hs_id', 'name', 'created_at'])
                ->orderBy('id')
                ->get()
                ->toArray();

            $groups[] = ['type' => 'email', 'value' => $email, 'users' => $users];
        }

        // Duplicados por pasaporte
        $dupPassports = DB::table('users')
            ->select(DB::raw('LOWER(passport) as val'), DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('passport')
            ->where('passport', '!=', '')
            ->groupBy(DB::raw('LOWER(passport)'))
            ->having('cnt', '>', 1)
            ->pluck('val')
            ->toArray();

        foreach ($dupPassports as $passport) {
            $users = User::query()
                ->whereRaw('LOWER(passport) = ?', [$passport])
                ->select(['id', 'email', 'passport', 'hs_id', 'name', 'created_at'])
                ->orderBy('id')
                ->get()
                ->toArray();

            $groups[] = ['type' => 'passport', 'value' => $passport, 'users' => $users];
        }

        return $groups;
    }

    /**
     * Maneja los grupos de duplicados de forma interactiva.
     * Retorna [idsToSkip[], abort_bool]
     */
    private function handleDuplicates(array $groups): array
    {
        $this->error("\n⚠️  Se encontraron " . count($groups) . " grupo(s) de duplicados en BD:");

        $idsToSkip = [];
        $csvRows   = [];

        foreach ($groups as $group) {
            $type  = $group['type'];
            $value = $group['value'];
            $users = $group['users'];

            $this->error("\n───────────────────────────────────────────────────────");
            $this->warn("Duplicado por {$type}: {$value}");
            $this->error("───────────────────────────────────────────────────────");

            $this->table(
                ['user_id', 'nombre', 'email', 'pasaporte', 'hs_id', 'creado'],
                array_map(fn($u) => [
                    $u['id'],
                    $u['name']     ?? '',
                    $u['email']    ?? 'N/A',
                    $u['passport'] ?? 'N/A',
                    $u['hs_id']    ?? 'N/A',
                    $u['created_at'],
                ], $users)
            );

            if ($this->dryRun) {
                $this->warn('   DRY-RUN: resolución omitida — se guardará en CSV.');
                $csvRows = array_merge($csvRows, $this->buildCsvRows($group, 'dry-run'));
                foreach ($users as $u) $idsToSkip[] = $u['id'];
                $this->report['duplicates_csv']++;
                continue;
            }

            // ── Las 3 opciones ───────────────────────────────────────────────
            $choice = $this->choice(
                "\n¿Qué deseas hacer con este grupo?",
                [
                    '1' => '🔀  1) Combinar (mantener un registro, reasignar negocios)',
                    '2' => '🔗  2) Solo sincronizar con X (elegir cuál lleva el hs_id)',
                    '3' => '📄  3) No hacer nada — guardar en CSV para revisión manual',
                    '0' => '🛑  0) Abortar todo el proceso',
                ],
                '3'
            );

            if ($choice === '0') {
                $this->error('Proceso abortado por el usuario.');
                return [$idsToSkip, true];
            }

            if ($choice === '3') {
                $csvRows = array_merge($csvRows, $this->buildCsvRows($group, 'pendiente'));
                foreach ($users as $u) $idsToSkip[] = $u['id'];
                $this->report['duplicates_csv']++;
                $this->info("   📄 Se guardará en CSV: {$this->duplicatesCsvPath}");
                continue;
            }

            if ($choice === '2') {
                $masterId = $this->syncWithOneUser($users, $type, $value);
                if ($masterId) {
                    $this->report['duplicates_synced_x']++;
                    foreach ($users as $u) {
                        if ($u['id'] !== $masterId) $idsToSkip[] = $u['id'];
                    }
                } else {
                    $csvRows = array_merge($csvRows, $this->buildCsvRows($group, 'sin-resolver'));
                    foreach ($users as $u) $idsToSkip[] = $u['id'];
                }
                continue;
            }

            if ($choice === '1') {
                $resolved = $this->mergeDuplicates($users);
                if ($resolved) {
                    $this->report['duplicates_merged']++;
                } else {
                    $csvRows = array_merge($csvRows, $this->buildCsvRows($group, 'sin-resolver'));
                    foreach ($users as $u) $idsToSkip[] = $u['id'];
                }
            }
        }

        // Escribir CSV si hay filas pendientes
        if (!empty($csvRows)) {
            $this->writeCsv($csvRows);
        }

        if (!empty($idsToSkip)) {
            $continue = $this->confirm(
                "\nHay " . count(array_unique($idsToSkip)) . " usuario(s) marcados para omitir. ¿Continuar con el resto?",
                true
            );
            if (!$continue) return [$idsToSkip, true];
        }

        return [array_unique($idsToSkip), false];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Opción 1: Combinar duplicados
    // ══════════════════════════════════════════════════════════════════════════

    private function mergeDuplicates(array $users): bool
    {
        $masterId = $this->chooseMasterUser($users, 'combinar');
        if (!$masterId) return false;

        $slaveIds = array_values(array_filter(
            array_column($users, 'id'),
            fn($id) => $id !== $masterId
        ));

        $correctPassport = $this->resolvePassport($users);

        $this->warn("\nMaestro:   user_id={$masterId}");
        $this->warn("Esclavos:  [" . implode(', ', $slaveIds) . "] → serán eliminados");
        $this->warn("Pasaporte: " . ($correctPassport ?: 'sin cambio'));

        if (!$this->confirm('¿Confirmar la fusión?', false)) {
            return false;
        }

        DB::transaction(function () use ($masterId, $slaveIds, $correctPassport) {
            foreach ($slaveIds as $slaveId) {
                Negocio::where('user_id', $slaveId)->update(['user_id' => $masterId]);
                DB::table('compras')->where('id_user', $slaveId)->update(['id_user' => $masterId]);
                User::where('id', $slaveId)->delete();
                $this->line("   ✅ user_id={$slaveId} eliminado y negocios → {$masterId}");
            }

            if ($correctPassport) {
                User::where('id', $masterId)->update(['passport' => $correctPassport]);
                $this->line("   ✅ Pasaporte actualizado en maestro: {$correctPassport}");
            }
        });

        return true;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Opción 2: Solo sincronizar con X
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * El operador elige qué user_id recibirá el hs_id.
     * Los demás se excluyen de la búsqueda HS en esta corrida.
     * Retorna el masterId elegido, o null si se canceló.
     */
    private function syncWithOneUser(array $users, string $type, string $value): ?int
    {
        $this->info("\nElige el user_id que recibirá el hs_id de HubSpot para {$type}={$value}:");

        $choices = [];
        foreach ($users as $u) {
            $choices[(string) $u['id']] =
                "ID {$u['id']} | {$u['name']} | email: {$u['email']} | passport: " . ($u['passport'] ?? 'N/A');
        }
        $choices['cancel'] = '❌ Cancelar (guardar en CSV)';

        $selected = $this->choice('¿Con cuál usuario sincronizas?', $choices, 'cancel');

        if ($selected === 'cancel' || !isset($choices[$selected])) {
            return null;
        }

        preg_match('/^ID (\d+)/', $selected, $m);
        $masterId = (int) ($m[1] ?? 0);

        if (!$masterId) return null;

        $this->info("   ✅ Solo se sincronizará HubSpot con user_id={$masterId}.");
        $this->warn("   Los demás IDs del grupo serán omitidos en esta corrida.");

        return $masterId;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Helpers de duplicados
    // ══════════════════════════════════════════════════════════════════════════

    private function chooseMasterUser(array $users, string $context): ?int
    {
        $choices = [];
        foreach ($users as $u) {
            $choices[(string) $u['id']] =
                "ID {$u['id']} | {$u['name']} | email: {$u['email']} | passport: "
                . ($u['passport'] ?? 'N/A') . " | creado: {$u['created_at']}";
        }
        $choices['cancel'] = '❌ Cancelar';

        $selected = $this->choice("Elige el registro MAESTRO para [{$context}]:", $choices, 'cancel');

        if ($selected === 'cancel') return null;

        preg_match('/^ID (\d+)/', $selected, $m);
        return (int) ($m[1] ?? 0) ?: null;
    }

    private function resolvePassport(array $users): ?string
    {
        $passports = array_values(array_unique(array_filter(array_column($users, 'passport'))));

        if (empty($passports))       return null;
        if (count($passports) === 1) return $passports[0];

        $choices = array_merge(
            $passports,
            ['manual' => 'Ingresar manualmente', 'skip' => 'No cambiar']
        );

        $choice = $this->choice('Múltiples pasaportes — ¿cuál es el correcto?', $choices, 'skip');

        if ($choice === 'skip')   return null;
        if ($choice === 'manual') return $this->ask('Ingresa el pasaporte correcto') ?: null;

        return $choice;
    }

    private function buildCsvRows(array $group, string $status): array
    {
        $rows = [];
        foreach ($group['users'] as $u) {
            $rows[] = [
                'status'     => $status,
                'type'       => $group['type'],
                'value'      => $group['value'],
                'user_id'    => $u['id'],
                'name'       => $u['name']      ?? '',
                'email'      => $u['email']     ?? '',
                'passport'   => $u['passport']  ?? '',
                'hs_id'      => $u['hs_id']     ?? '',
                'created_at' => $u['created_at'],
            ];
        }
        return $rows;
    }

    private function writeCsv(array $rows): void
    {
        if (empty($rows)) return;

        $fileExists = file_exists($this->duplicatesCsvPath);
        $handle     = fopen($this->duplicatesCsvPath, 'a');

        if (!$handle) {
            $this->error("No se pudo abrir el CSV: {$this->duplicatesCsvPath}");
            return;
        }

        if (!$fileExists) {
            fputcsv($handle, array_keys($rows[0]));
        }

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
        $this->info("   📄 CSV actualizado: {$this->duplicatesCsvPath} (" . count($rows) . " filas)");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Aplicar cambios en BD
    // ══════════════════════════════════════════════════════════════════════════

    private function applyHsIdFills(array $fills): void
    {
        if (empty($fills)) {
            $this->info('hs_id: sin cambios necesarios.');
            return;
        }

        $this->info("\nRelleno de hs_id: " . count($fills) . " registros");

        $preview = array_slice($fills, 0, 20, true);
        $this->table(
            ['user_id', 'hs_id_nuevo'],
            array_map(fn($uid, $hsId) => [$uid, $hsId], array_keys($preview), $preview)
        );
        if (count($fills) > 20) $this->line('... (mostrando primeros 20)');

        if ($this->dryRun) {
            $this->warn('DRY-RUN: hs_id no actualizados.');
            return;
        }

        foreach ($fills as $userId => $hsId) {
            User::where('id', $userId)->update(['hs_id' => $hsId]);
            $this->report['hs_id_filled']++;
        }

        $this->info("✅ hs_id rellenados: {$this->report['hs_id_filled']}");
    }

    private function applyEmailFixes(array $fixes): void
    {
        if (empty($fixes)) {
            $this->info('Emails: sin correcciones necesarias.');
            return;
        }

        $this->warn("\nCorrecciones de email por pasaporte: " . count($fixes));
        $this->table(
            ['user_id', 'pasaporte', 'email_actual', 'email_correcto_HS'],
            array_map(fn($f) => [
                $f['user_id'],
                $f['passport'],
                $f['old_email'],
                $f['new_email'],
            ], $fixes)
        );

        if ($this->dryRun) {
            $this->warn('DRY-RUN: emails no corregidos.');
            return;
        }

        if (!$this->confirm("\n¿Aplicar " . count($fixes) . " correcciones de email?", true)) {
            $this->warn('Omitidas por el usuario.');
            return;
        }

        foreach ($fixes as $fix) {
            User::where('id', $fix['user_id'])->update(['email' => $fix['new_email']]);
            $this->report['email_corrected']++;
        }

        $this->info("✅ Emails corregidos: {$this->report['email_corrected']}");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FASE 2 — Sincronizar negocios
    // ══════════════════════════════════════════════════════════════════════════

    private function fase2SyncDeals(): void
    {
        $this->info("\n══════════════════════════════════════════");
        $this->info(" FASE 2 — Sincronizar negocios desde HubSpot");
        $this->info("══════════════════════════════════════════");

        $syncNow  = (bool) $this->option('sync-deals-now');
        $onlyUser = $this->option('only-user');

        $query = User::query()
            ->select(['id', 'email', 'hs_id', 'tl_id'])
            ->whereNotNull('hs_id')
            ->where('hs_id', '!=', '');

        if ($onlyUser) $query->where('id', (int) $onlyUser);

        $users = $query->get();
        $this->info("Usuarios con hs_id a procesar: " . $users->count());

        if ($users->isEmpty()) {
            $this->warn('Sin usuarios con hs_id. ¿Corriste la FASE 1?');
            return;
        }

        $bar = $this->output->createProgressBar($users->count());
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('iniciando...');
        $bar->start();

        foreach ($users as $user) {
            $bar->setMessage("user_id={$user->id}");

            try {
                if ($syncNow) {
                    app(\App\Jobs\SyncUserDealsJob::class, ['user' => $user])
                        ->handle(
                            app(\App\Services\HubspotService::class),
                            app(\App\Services\TeamleaderService::class)
                        );
                } else {
                    SyncUserDealsJob::dispatch($user);
                }
                $this->report['deals_jobs_dispatched']++;
            } catch (\Throwable $e) {
                $this->error("\nError despachando job para user_id={$user->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->setMessage('✔ completado');
        $bar->finish();
        $this->newLine(2);

        $mode = $syncNow ? 'ejecutados síncronamente' : 'encolados';
        $this->info("✅ Jobs {$mode}: {$this->report['deals_jobs_dispatched']}");
        if (!$syncNow) $this->info("   Corre: php artisan queue:work");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Reporte final
    // ══════════════════════════════════════════════════════════════════════════

    private function printReport(): void
    {
        $r = $this->report;

        $this->info("\n══════════════════════════════════════════");
        $this->info(" REPORTE FINAL" . ($this->dryRun ? ' (DRY-RUN)' : ''));
        $this->info("══════════════════════════════════════════");

        $this->table(['Métrica', 'Valor'], [
            ['Usuarios BD procesados',             $r['db_users_total']],
            ['Encontrados en HS por email',        $r['hs_found_by_email']],
            ['Encontrados en HS por pasaporte',    $r['hs_found_by_passport']],
            ['No encontrados en HS',               $r['hs_not_found']],
            ['hs_id rellenados en BD',             $r['hs_id_filled']],
            ['Emails corregidos por pasaporte',    $r['email_corrected']],
            ['Grupos de duplicados detectados',    $r['duplicates_found']],
            ['Duplicados fusionados',              $r['duplicates_merged']],
            ['Duplicados sincronizados con X',     $r['duplicates_synced_x']],
            ['Duplicados enviados a CSV',          $r['duplicates_csv']],
            ['Jobs de negocios despachados',       $r['deals_jobs_dispatched']],
            ['Total requests a HubSpot API',       $this->hsRequestCount],
        ]);

        $this->info("\n📄 CSV de duplicados: {$this->duplicatesCsvPath}");
    }
}
