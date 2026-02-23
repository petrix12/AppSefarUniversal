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
        {--dry-run          : Solo muestra cambios, no escribe nada}
        {--skip-contacts    : Salta la fase de corrección de contactos}
        {--skip-deals       : Salta la fase de importación de negocios}
        {--only-user=       : Procesa solo este user_id de la BD}
        {--passport=        : Filtra por número de pasaporte específico}
        {--limit=100        : Page size para búsquedas en HubSpot (max 100)}
        {--sync-deals-now   : Despacha SyncUserDealsJob en modo sync (no queue)}
    ';

    protected $description = '
        FASE 1 — Descarga todos los contactos de HubSpot y corrige la BD:
                 · Rellena users.hs_id
                 · Corrige email si el pasaporte coincide pero el correo difiere
                 · Detecta emails duplicados y pregunta cómo resolver
        FASE 2 — Dispara SyncUserDealsJob para cada usuario con hs_id
                 para migrar sus negocios de HubSpot a la App.
    ';

    // ── Estado interno ─────────────────────────────────────────────────────────

    private $hubspot;
    private bool $dryRun;
    private int $hsLimit;

    /** Reporte acumulado */
    private array $report = [
        'hs_contacts_total'    => 0,
        'hs_id_filled'         => 0,
        'email_corrected'      => 0,
        'duplicates_found'     => 0,
        'duplicates_merged'    => 0,
        'duplicates_skipped'   => 0,
        'deals_jobs_dispatched'=> 0,
        'no_match_in_db'       => [],   // contactos HS sin par en BD
    ];

    // ── Entry point ────────────────────────────────────────────────────────────

    public function handle(): int
    {
        $this->hubspot  = Factory::createWithAccessToken(config('services.hubspot.key', env('HUBSPOT_KEY')));
        $this->dryRun   = (bool) $this->option('dry-run');
        $this->hsLimit  = (int)  $this->option('limit');

        if ($this->dryRun) {
            $this->warn('⚠️  DRY-RUN activo — no se escribirá nada en BD.');
        }

        // ── FASE 1: Corrección de contactos ───────────────────────────────────
        if (!$this->option('skip-contacts')) {
            $abort = $this->fase1FixContacts();
            if ($abort) {
                $this->warn('Ejecución abortada en FASE 1. Corrije los duplicados y vuelve a correr.');
                $this->printReport();
                return self::FAILURE;
            }
        } else {
            $this->warn('[FASE 1 omitida por --skip-contacts]');
        }

        // ── FASE 2: Importar negocios ─────────────────────────────────────────
        if (!$this->option('skip-deals')) {
            $this->fase2SyncDeals();
        } else {
            $this->warn('[FASE 2 omitida por --skip-deals]');
        }

        $this->printReport();
        return self::SUCCESS;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FASE 1 — Corrección de contactos HubSpot → BD
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Retorna true si debe abortar (hubo duplicados no resueltos).
     */
    private function fase1FixContacts(): bool
    {
        $this->info("\n══════════════════════════════════════════");
        $this->info(" FASE 1 — Corrección de contactos");
        $this->info("══════════════════════════════════════════");

        // 1. Descargar TODOS los contactos de HubSpot
        $this->info("Descargando contactos de HubSpot...");
        $hsContacts = $this->fetchAllHsContacts();
        $this->report['hs_contacts_total'] = count($hsContacts);
        $this->info("Total contactos en HubSpot: " . count($hsContacts));

        if (empty($hsContacts)) {
            $this->warn('Sin contactos en HubSpot.');
            return false;
        }

        // Filtro --only-user / --passport
        $onlyUserId = $this->option('only-user');
        $onlyPassport = $this->option('passport');

        // 2. Construir índices para búsqueda rápida
        //    hsContacts indexados por email y por pasaporte
        $hsByEmail    = [];   // email_lower  => contacto HS
        $hsByPassport = [];   // passport_lower => contacto HS

        foreach ($hsContacts as $c) {
            $email    = strtolower(trim($c['email'] ?? ''));
            $passport = strtolower(trim($c['passport'] ?? ''));

            if ($email !== '')    $hsByEmail[$email]       = $c;
            if ($passport !== '') $hsByPassport[$passport] = $c;
        }

        // 3. Cargar usuarios de BD
        $dbQuery = User::query()->select(['id', 'email', 'passport', 'hs_id']);
        if ($onlyUserId)   $dbQuery->where('id', (int) $onlyUserId);
        if ($onlyPassport) $dbQuery->whereRaw('LOWER(passport) = ?', [strtolower($onlyPassport)]);

        $dbUsers = $dbQuery->get();
        $this->info("Usuarios en BD a procesar: " . $dbUsers->count());

        // 4. Detectar duplicados de email en BD antes de procesar
        $emailDuplicatesInDb = $this->detectEmailDuplicatesInDb();
        if (!empty($emailDuplicatesInDb)) {
            $this->report['duplicates_found'] = count($emailDuplicatesInDb);
            $abort = $this->handleDuplicates($emailDuplicatesInDb, $hsContacts);
            if ($abort) return true;
        }

        // 5. Procesar cada usuario
        $emailFixes      = [];   // [user_id => ['old' => ..., 'new' => ...]]
        $hsIdFills       = [];   // [user_id => hs_id]
        $noMatchInDb     = [];   // emails HS sin par

        foreach ($dbUsers as $user) {
            $userEmail    = strtolower(trim($user->email ?? ''));
            $userPassport = strtolower(trim($user->passport ?? ''));

            // ── A. Buscar el contacto HS correspondiente ──────────────────────

            $hsContact = null;

            // Primero intenta por pasaporte (más confiable)
            if ($userPassport && isset($hsByPassport[$userPassport])) {
                $hsContact = $hsByPassport[$userPassport];
            }
            // Si no, por email
            elseif ($userEmail && isset($hsByEmail[$userEmail])) {
                $hsContact = $hsByEmail[$userEmail];
            }

            if (!$hsContact) {
                // No hay match en HS para este usuario de BD → ok, puede ser cliente solo en app
                continue;
            }

            $hsId       = (string) $hsContact['hs_id'];
            $hsEmail    = strtolower(trim($hsContact['email'] ?? ''));
            $hsPassport = strtolower(trim($hsContact['passport'] ?? ''));

            // ── B. Rellenar hs_id si está vacío ──────────────────────────────
            if (empty($user->hs_id) || (string)$user->hs_id !== $hsId) {
                $hsIdFills[$user->id] = $hsId;
            }

            // ── C. Corregir email si el pasaporte coincide pero el email difiere
            if (
                $userPassport
                && $hsPassport
                && $userPassport === $hsPassport
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
        }

        // Contactos HS sin ningún par en BD
        foreach ($hsContacts as $c) {
            $email    = strtolower(trim($c['email'] ?? ''));
            $passport = strtolower(trim($c['passport'] ?? ''));

            $foundByEmail    = $email    && $dbUsers->first(fn($u) => strtolower(trim($u->email ?? '')) === $email);
            $foundByPassport = $passport && $dbUsers->first(fn($u) => strtolower(trim($u->passport ?? '')) === $passport);

            if (!$foundByEmail && !$foundByPassport) {
                $this->report['no_match_in_db'][] = $c;
            }
        }

        // 6. Mostrar y aplicar cambios
        $this->applyHsIdFills($hsIdFills);
        $this->applyEmailFixes($emailFixes);

        return false;  // sin abort
    }

    // ── Helpers FASE 1 ─────────────────────────────────────────────────────────

    /**
     * Detecta emails duplicados en la tabla users.
     * Retorna array de grupos: [email => [user1, user2, ...]]
     */
    private function detectEmailDuplicatesInDb(): array
    {
        $duplicateEmails = DB::table('users')
            ->select(DB::raw('LOWER(email) as email_lower'), DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('email')
            ->groupBy(DB::raw('LOWER(email)'))
            ->having('cnt', '>', 1)
            ->pluck('email_lower')
            ->toArray();

        if (empty($duplicateEmails)) return [];

        $groups = [];
        foreach ($duplicateEmails as $email) {
            $users = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->select(['id', 'email', 'passport', 'hs_id', 'name', 'created_at'])
                ->orderBy('id')
                ->get()
                ->toArray();

            $groups[$email] = $users;
        }

        return $groups;
    }

    /**
     * Maneja los duplicados de forma interactiva.
     * Retorna true si debe abortar.
     */
    private function handleDuplicates(array $groups, array $hsContacts): bool
    {
        $this->error("\n⚠️  Se encontraron " . count($groups) . " email(s) duplicados en BD:");
        $hasUnresolved = false;

        foreach ($groups as $email => $users) {
            $this->error("\n───────────────────────────────────────────");
            $this->warn("Email duplicado: {$email}");
            $this->error("───────────────────────────────────────────");

            // Mostrar info de cada usuario duplicado
            $rows = array_map(fn($u) => [
                $u['id'],
                $u['name'] ?? '',
                $u['email'],
                $u['passport'] ?? 'N/A',
                $u['hs_id'] ?? 'N/A',
                $u['created_at'],
            ], $users);

            $this->table(
                ['user_id', 'nombre', 'email', 'pasaporte', 'hs_id', 'creado'],
                $rows
            );

            // Buscar info adicional en HS para este email
            $hsInfo = collect($hsContacts)->first(
                fn($c) => strtolower(trim($c['email'] ?? '')) === $email
            );

            if ($hsInfo) {
                $this->info("ℹ️  En HubSpot este email corresponde a:");
                $this->line("   HS ID:      " . $hsInfo['hs_id']);
                $this->line("   Nombre:     " . ($hsInfo['firstname'] ?? '') . ' ' . ($hsInfo['lastname'] ?? ''));
                $this->line("   Pasaporte:  " . ($hsInfo['passport'] ?? 'N/A'));
                $this->line("   Teléfono:   " . ($hsInfo['phone'] ?? 'N/A'));
            }

            if ($this->dryRun) {
                $this->warn('   DRY-RUN: resolución omitida.');
                $hasUnresolved = true;
                continue;
            }

            // ── Preguntar qué hacer ───────────────────────────────────────────
            $choice = $this->choice(
                "\n¿Qué deseas hacer con este duplicado?",
                [
                    'unify'  => '🔀 Unificar (mantener un registro, reasignar negocios)',
                    'skip'   => '⏭  Saltar este duplicado (lo dejo para después)',
                    'abort'  => '🛑 Abortar todo el proceso',
                ],
                'skip'
            );

            if ($choice === 'abort') {
                $this->error('Proceso abortado por el usuario.');
                return true;
            }

            if ($choice === 'skip') {
                $this->report['duplicates_skipped']++;
                $hasUnresolved = true;
                continue;
            }

            if ($choice === 'unify') {
                $resolved = $this->unifyDuplicates($users, $hsInfo);
                if ($resolved) {
                    $this->report['duplicates_merged']++;
                } else {
                    $hasUnresolved = true;
                }
            }
        }

        // Si quedaron sin resolver, preguntar si continuar de todas formas
        if ($hasUnresolved) {
            $continue = $this->confirm(
                "\nQuedan duplicados sin resolver. ¿Deseas continuar de todas formas con los demás usuarios?",
                false
            );
            return !$continue;
        }

        return false;
    }

    /**
     * Unifica dos o más usuarios duplicados.
     * Retorna true si se realizó la unificación.
     */
    private function unifyDuplicates(array $users, ?array $hsInfo): bool
    {
        $userIds   = array_column($users, 'id');
        $passports = array_filter(array_column($users, 'passport'));

        // ── 1. Elegir pasaporte correcto ──────────────────────────────────────
        $passportChoices = array_values(array_unique($passports));

        if (empty($passportChoices)) {
            $correctPassport = $this->ask('Ningún registro tiene pasaporte. Ingresa el número de pasaporte correcto (o deja vacío para omitir)');
        } elseif (count($passportChoices) === 1) {
            $correctPassport = $passportChoices[0];
            $this->info("Pasaporte único encontrado: {$correctPassport}");
        } else {
            $this->warn("Múltiples pasaportes encontrados:");
            foreach ($passportChoices as $i => $p) {
                $this->line("  [{$i}] {$p}");
            }

            if ($hsInfo && !empty($hsInfo['passport'])) {
                $this->info("  HubSpot tiene registrado: " . $hsInfo['passport']);
            }

            $idx = $this->ask(
                'Ingresa el número de índice del pasaporte CORRECTO (o escribe el pasaporte manualmente)'
            );

            $correctPassport = isset($passportChoices[(int)$idx])
                ? $passportChoices[(int)$idx]
                : $idx;
        }

        // ── 2. Elegir qué user_id conservar (master) ─────────────────────────
        $this->info("\nElige el registro MAESTRO (el que se conservará):");
        $choices = [];
        foreach ($users as $u) {
            $choices[$u['id']] = "ID {$u['id']} | {$u['name']} | passport: " . ($u['passport'] ?? 'N/A') . " | creado: {$u['created_at']}";
        }

        $masterIdStr = $this->choice('¿Cuál es el registro maestro?', $choices);
        // Extraer el ID del string "ID X | ..."
        preg_match('/^ID (\d+)/', $masterIdStr, $m);
        $masterId = (int) ($m[1] ?? $users[0]['id']);

        $slaveIds = array_filter($userIds, fn($id) => $id !== $masterId);

        $this->warn("Maestro: user_id={$masterId}");
        $this->warn("Se eliminarán: user_ids=[" . implode(', ', $slaveIds) . "]");
        $this->warn("Todos los negocios y relaciones serán reasignados al maestro.");

        if (!$this->confirm('¿Confirmas la unificación?', false)) {
            $this->report['duplicates_skipped']++;
            return false;
        }

        // ── 3. Reasignar registros relacionados ───────────────────────────────
        DB::transaction(function () use ($masterId, $slaveIds, $correctPassport, $hsInfo) {
            foreach ($slaveIds as $slaveId) {
                // Negocios
                Negocio::where('user_id', $slaveId)->update(['user_id' => $masterId]);

                // Otras tablas que referencian user_id — añade las tuyas aquí
                // Compras, Facturas, etc.
                DB::table('compras')->where('id_user', $slaveId)->update(['id_user' => $masterId]);
                // DB::table('facturas')->where('user_id', $slaveId)->update(['user_id' => $masterId]);

                // Eliminar el duplicado
                User::where('id', $slaveId)->delete();
                $this->line("   ✅ user_id={$slaveId} eliminado y reasignado → {$masterId}");
            }

            // Actualizar pasaporte y hs_id en el maestro
            $updateData = [];
            if ($correctPassport) $updateData['passport'] = $correctPassport;
            if ($hsInfo)          $updateData['hs_id']    = (string) $hsInfo['hs_id'];

            if (!empty($updateData)) {
                User::where('id', $masterId)->update($updateData);
                $this->line("   ✅ Maestro actualizado: " . json_encode($updateData));
            }
        });

        return true;
    }

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
            array_map(fn($userId, $hsId) => [$userId, $hsId], array_keys($preview), $preview)
        );
        if (count($fills) > 20) {
            $this->line('... (mostrando primeros 20)');
        }

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

        if (!$this->confirm("\n¿Aplicar estas " . count($fixes) . " correcciones de email?", true)) {
            $this->warn('Correcciones omitidas por el usuario.');
            return;
        }

        foreach ($fixes as $fix) {
            User::where('id', $fix['user_id'])->update(['email' => $fix['new_email']]);
            $this->report['email_corrected']++;
        }

        $this->info("✅ Emails corregidos: {$this->report['email_corrected']}");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FASE 2 — Importar negocios via SyncUserDealsJob
    // ══════════════════════════════════════════════════════════════════════════

    private function fase2SyncDeals(): void
    {
        $this->info("\n══════════════════════════════════════════");
        $this->info(" FASE 2 — Importar negocios desde HubSpot");
        $this->info("══════════════════════════════════════════");

        $syncNow   = (bool) $this->option('sync-deals-now');
        $onlyUser  = $this->option('only-user');

        // Solo usuarios que tienen hs_id (ya sincronizado en FASE 1)
        $query = User::query()
            ->select(['id', 'email', 'hs_id', 'tl_id'])
            ->whereNotNull('hs_id')
            ->where('hs_id', '!=', '');

        if ($onlyUser) {
            $query->where('id', (int) $onlyUser);
        }

        $users = $query->get();

        $this->info("Usuarios con hs_id a procesar: " . $users->count());

        if ($users->isEmpty()) {
            $this->warn('Sin usuarios con hs_id. ¿Corriste la FASE 1?');
            return;
        }

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            try {
                if ($syncNow) {
                    // Ejecutar sincronamente (útil para debug o pocos usuarios)
                    app(\App\Jobs\SyncUserDealsJob::class, ['user' => $user])
                        ->handle(
                            app(\App\Services\HubspotService::class),
                            app(\App\Services\TeamleaderService::class)
                        );
                } else {
                    // Encolar (recomendado para producción)
                    SyncUserDealsJob::dispatch($user);
                }

                $this->report['deals_jobs_dispatched']++;
            } catch (\Throwable $e) {
                $this->error("\nError despachando job para user_id={$user->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $mode = $syncNow ? 'ejecutados síncronamente' : 'encolados';
        $this->info("✅ Jobs {$mode}: {$this->report['deals_jobs_dispatched']}");

        if (!$syncNow) {
            $this->info("   Corre: php artisan queue:work para procesar la cola.");
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FETCH — Todos los contactos de HubSpot
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Descarga TODOS los contactos de HubSpot con las propiedades necesarias.
     * Usa paginación via Search API para obtener email, passport y hs_id.
     *
     * Retorna array de:
     * [
     *   'hs_id'     => '123456',
     *   'email'     => 'foo@bar.com',
     *   'passport'  => 'AB123456',
     *   'firstname' => 'Juan',
     *   'lastname'  => 'Pérez',
     *   'phone'     => '+1234...',
     * ]
     */
    private function fetchAllHsContacts(): array
    {
        $all   = [];
        $after = null;

        // Propiedades a solicitar
        $properties = ['email', 'passport', 'firstname', 'lastname', 'phone'];

        do {
            // Usamos Search API con filtro vacío (trae todos)
            // pero con un filter que siempre es true: email EXISTS
            // Nota: para traer absolutamente todos usamos el endpoint /crm/v3/objects/contacts
            $query = [
                'limit' => $this->hsLimit,
                'properties' => implode(',', $properties),
                'archived' => 'false',
            ];

            if ($after) $query['after'] = $after;

            $resp = $this->hubspot->apiRequest([
                'method' => 'GET',
                'path'   => '/crm/v3/objects/contacts',
                'query'  => $query,
            ]);

            $body = json_decode((string) $resp->getBody(), true);

            foreach ($body['results'] ?? [] as $contact) {
                $props = $contact['properties'] ?? [];
                $all[] = [
                    'hs_id'     => (string) $contact['id'],
                    'email'     => strtolower(trim($props['email'] ?? '')),
                    'passport'  => strtolower(trim($props['passport'] ?? '')),
                    'firstname' => $props['firstname'] ?? '',
                    'lastname'  => $props['lastname'] ?? '',
                    'phone'     => $props['phone'] ?? '',
                ];
            }

            $after = $body['paging']['next']['after'] ?? null;

            // Feedback de progreso en consola
            $this->output->write("\r   Descargados: " . count($all) . " contactos...");

        } while (!is_null($after));

        $this->newLine();
        return $all;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // REPORTE FINAL
    // ══════════════════════════════════════════════════════════════════════════

    private function printReport(): void
    {
        $r = $this->report;

        $this->info("\n══════════════════════════════════════════");
        $this->info(" REPORTE FINAL" . ($this->dryRun ? ' (DRY-RUN)' : ''));
        $this->info("══════════════════════════════════════════");

        $this->table(['Métrica', 'Valor'], [
            ['Contactos descargados de HubSpot',  $r['hs_contacts_total']],
            ['hs_id rellenados en BD',             $r['hs_id_filled']],
            ['Emails corregidos por pasaporte',    $r['email_corrected']],
            ['Duplicados detectados',              $r['duplicates_found']],
            ['Duplicados unificados',              $r['duplicates_merged']],
            ['Duplicados saltados',                $r['duplicates_skipped']],
            ['Contactos HS sin par en BD',         count($r['no_match_in_db'])],
            ['Jobs de negocios despachados',       $r['deals_jobs_dispatched']],
        ]);

        if (!empty($r['no_match_in_db'])) {
            $this->warn("\nContactos en HubSpot SIN par en BD (primeros 30):");
            $this->table(
                ['hs_id', 'email', 'pasaporte', 'nombre'],
                array_slice(array_map(fn($c) => [
                    $c['hs_id'],
                    $c['email'],
                    $c['passport'] ?: 'N/A',
                    trim(($c['firstname'] ?? '') . ' ' . ($c['lastname'] ?? '')),
                ], $r['no_match_in_db']), 0, 30)
            );
        }
    }
}
