<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

class SyncHubspotEmails extends Command
{
    protected $signature = 'hubspot:sync-emails
                            {--dry-run : Simula los cambios sin escribir en la BD}
                            {--from=1  : Número de fila CSV desde donde arrancar (inclusive)}';

    protected $description = 'Sincroniza correos de HubSpot hacia la BD local comparando por pasaporte';

    // ─── Nombres de columnas en el CSV de HubSpot ───────────────────────────
    private const HS_COL_PASSPORT = 'Pasaporte';
    private const HS_COL_EMAIL    = 'Correo';

    // ─── Rutas en Storage ────────────────────────────────────────────────────
    private const CSV_HUBSPOT = 'sync/hubspot.csv';
    private const CSV_SKIPPED = 'sync/skipped_conflicts.csv';

    // ─── Pasaportes inválidos / placeholder ──────────────────────────────────
    private const INVALID_PASSPORT_PATTERNS = [
        'no tengo',
        'sin pasaporte',
        'sin documento',
        'no tiene',
        'no poseo',
        'vencido',
        'ninguno',
        'n/a',
        'no aplica',
        'no',
        'no disponible',
        's/n',
        'sin numero',
        'sin número',
        's/d',
    ];

    private const INVALID_PASSPORT_EXACT = [
        'na',
        '0',
        '00',
        '000',
        'xxx',
        'xxxx',
        'xxxxx',
        'xxxxxx',
        'xxxxxxx',
        'xxxxxxxx',
        'xxxxxxxxx',
    ];

    // ─── Contadores ──────────────────────────────────────────────────────────
    private int $updated = 0;
    private int $skipped = 0;
    private int $merged  = 0;
    private int $errors  = 0;

    // ─── Registros omitidos para exportar ────────────────────────────────────
    private array $skippedRecords = [];

    // ─── Índices de usuarios ─────────────────────────────────────────────────
    private array $usersByPassport = [];
    private array $usersByEmail    = [];

    // ════════════════════════════════════════════════════════════════════════
    // ENTRY POINT
    // ════════════════════════════════════════════════════════════════════════

    private function normalizePassport(string $passport): string
    {
        if (preg_match('/^\d+$/', $passport)) {
            return ltrim($passport, '0') ?: '0';
        }
        return $passport;
    }

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $fromRow  = max(1, (int) $this->option('from'));

        $this->newLine();
        $this->line('<fg=cyan;options=bold>╔══════════════════════════════════════════╗</>');
        $this->line('<fg=cyan;options=bold>║      🔄  HubSpot Email Sync Tool        ║</>');
        $this->line('<fg=cyan;options=bold>╚══════════════════════════════════════════╝</>');
        $this->newLine();

        if ($isDryRun) {
            $this->warn('  ⚠️  Modo DRY-RUN activo — no se escribirá nada en la BD.');
            $this->newLine();
        }

        if ($fromRow > 1) {
            $this->warn("  ⏩ Arrancando desde la fila #$fromRow — se omitirán las anteriores.");
            $this->newLine();
        }

        // ── 1. Leer CSV de HubSpot ───────────────────────────────────────────
        $this->info('📂 Leyendo archivo HubSpot desde Storage...');
        $hubspotRecords = $this->readCsvFromStorage(self::CSV_HUBSPOT);
        $this->line('   → HubSpot : <fg=cyan>' . count($hubspotRecords) . '</> registros');

        // ── 2. Obtener usuarios desde el modelo User ─────────────────────────
        $this->info('🗄️  Consultando usuarios desde la base de datos...');
        $users = User::select('id', 'email', 'passport')->where('email', 'LIKE', "%correo.auxiliar%")->get();
        $this->line('   → App DB  : <fg=cyan>' . $users->count() . '</> usuarios');
        $this->newLine();

        // ── 3. Construir índices ─────────────────────────────────────────────
        $this->usersByPassport = $this->indexUsersBy($users, 'passport');
        $this->usersByEmail    = $this->indexUsersBy($users, 'email');

        // ── 4. Procesar cada fila de HubSpot ─────────────────────────────────
        $this->info('🔍 Procesando registros...');
        $this->newLine();

        foreach ($hubspotRecords as $index => $hsRow) {
            $rowNumber = $index + 1;

            if ($rowNumber < $fromRow) {
                continue;
            }

            $this->processRow(
                rowNumber: $rowNumber,
                hsRow:     $hsRow,
                isDryRun:  $isDryRun,
            );
        }

        // ── 5. Resumen final ─────────────────────────────────────────────────
        $this->printSummary($isDryRun);

        return self::SUCCESS;
    }

    // ════════════════════════════════════════════════════════════════════════
    // PROCESAMIENTO DE CADA FILA
    // ════════════════════════════════════════════════════════════════════════

    private function processRow(
        int   $rowNumber,
        array $hsRow,
        bool  $isDryRun,
    ): void {
        $hsPassport = $this->normalize($hsRow[self::HS_COL_PASSPORT] ?? '');
        $hsEmail    = $this->normalize($hsRow[self::HS_COL_EMAIL]    ?? '');

        // ── Validaciones básicas ─────────────────────────────────────────────
        if ($hsPassport === '' || $hsEmail === '') {
            //$this->warn("  [Fila $rowNumber] ⚠️  Pasaporte o correo vacío en HubSpot — omitido.");
            $this->errors++;
            return;
        }

        // ── Pasaporte inválido o placeholder ─────────────────────────────────
        if ($this->isInvalidPassport($hsPassport)) {
            //$this->line("  [Fila $rowNumber] <fg=yellow>🚫 Pasaporte inválido <options=bold>\"$hsPassport\"</> — omitido y guardado en CSV.</>");

            $this->skippedRecords[] = [
                'hs_pasaporte'            => $hsPassport,
                'hs_correo'               => $hsEmail,
                'app_id_match'            => '',
                'app_pasaporte_match'     => '',
                'app_correo_match'        => '',
                'tipo_match'              => 'pasaporte_invalido',
                'app_id_conflicto'        => '',
                'app_pasaporte_conflicto' => '',
                'app_correo_conflicto'    => '',
                'omitido_en'              => now()->toDateTimeString(),
            ];

            $this->skipped++;
            return;
        }

        // ── Buscar usuarios por pasaporte (exacto + parcial) ─────────────────
        ['exact' => $exactMatches, 'like' => $likeMatches] = $this->findUsersByPassport($hsPassport);

        if (empty($exactMatches) && empty($likeMatches)) {
            //$this->line("  [Fila $rowNumber] <fg=yellow>⚠️  Pasaporte <options=bold>$hsPassport</> no encontrado — omitido.</>");
            $this->skipped++;
            return;
        }

        // ── Filtrar exactMatches que ya tienen el correo correcto ─────────────
        $exactMatches = array_values(array_filter(
            $exactMatches,
            function ($user) use ($hsEmail) {
                if ($this->normalize($user->email) === $hsEmail) {
                    $this->skipped++;
                    return false;
                }
                return true;
            }
        ));

        // ── Si después del filtro no queda nada que hacer, salir ─────────────
        if (empty($exactMatches) && empty($likeMatches)) {
            return;
        }

        // ── Detectar conflictos de correo ─────────────────────────────────────
        $emailConflicts = array_values(array_filter(
            $this->usersByEmail[$hsEmail] ?? [],
            fn($u) => $this->normalize($u->passport ?? '') !== $hsPassport
                && !$this->isLikeMatch($hsPassport, $this->normalize($u->passport ?? ''))
        ));

        // ── Si hay matches LIKE o conflictos de correo → prompt interactivo ───
        if (!empty($likeMatches) || !empty($emailConflicts)) {
            $resolved = $this->handleConflict(
                rowNumber:      $rowNumber,
                hsPassport:     $hsPassport,
                hsEmail:        $hsEmail,
                exactMatches:   $exactMatches,
                likeMatches:    $likeMatches,
                emailConflicts: $emailConflicts,
                isDryRun:       $isDryRun,
            );

            $resolved ? $this->merged++ : $this->skipped++;
            return;
        }

        // ── CASO NORMAL: solo exactMatches sin conflictos ─────────────────────
        foreach ($exactMatches as $user) {
            $this->line(
                "  [Fila $rowNumber] <fg=green>✅ ID {$user->id} — Pasaporte: $hsPassport</>" .
                " | <fg=red>{$user->email}</> → <fg=green>$hsEmail</>"
            );

            if (!$isDryRun) {
                $success = $this->updateEmail($user->id, $hsEmail);

                if (!$success) {
                    $this->skippedRecords[] = [
                        'hs_pasaporte'            => $hsPassport,
                        'hs_correo'               => $hsEmail,
                        'app_id_match'            => $user->id,
                        'app_pasaporte_match'     => $user->passport,
                        'app_correo_match'        => $user->email,
                        'tipo_match'              => 'update_fallido',
                        'app_id_conflicto'        => '',
                        'app_pasaporte_conflicto' => '',
                        'app_correo_conflicto'    => $hsEmail,
                        'omitido_en'              => now()->toDateTimeString(),
                    ];
                    $this->skipped++;
                    continue;
                }
            }

            $this->updated++;
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // BÚSQUEDA DE USUARIOS POR PASAPORTE (EXACTO + LIKE)
    // ════════════════════════════════════════════════════════════════════════

    /**
     * @return array{ exact: object[], like: object[] }
     */
    private function findUsersByPassport(string $hsPassport): array
    {
        $exact = [];
        $like  = [];

        foreach ($this->usersByPassport as $dbPassport => $users) {
            if ($this->isInvalidPassport($dbPassport)) {
                continue;
            }

            if ($dbPassport === $hsPassport) {
                foreach ($users as $user) {
                    $exact[] = $user;
                }
            } elseif ($this->isLikeMatch($hsPassport, $dbPassport)) {
                foreach ($users as $user) {
                    if ($this->normalize($user->passport) === $hsPassport) {
                        $exact[] = $user;
                    } else {
                        $like[] = $user;
                    }
                }
            }
        }

        return ['exact' => $exact, 'like' => $like];
    }

    private function isLikeMatch(string $hsPassport, string $dbPassport): bool
    {
        if ($dbPassport === '' || $hsPassport === '') {
            return false;
        }

        $shorter = min(strlen($hsPassport), strlen($dbPassport));
        $longer  = max(strlen($hsPassport), strlen($dbPassport));

        if ($shorter < 6) {
            return false;
        }

        if (($shorter / $longer) < 0.7) {
            return false;
        }

        return str_contains($hsPassport, $dbPassport)
            || str_contains($dbPassport, $hsPassport);
    }

    private function isInvalidPassport(string $passport): bool
    {
        if ($passport === '') {
            return true;
        }

        foreach (self::INVALID_PASSPORT_PATTERNS as $pattern) {
            if ($passport === $pattern || str_contains($passport, $pattern)) {
                return true;
            }
        }

        foreach (self::INVALID_PASSPORT_EXACT as $exact) {
            if ($passport === $exact) {
                return true;
            }
        }

        if (preg_match('/^x+$/i', $passport)) {
            return true;
        }

        if (preg_match('/^0+$/', $passport)) {
            return true;
        }

        if (preg_match('/^(\d)\1{3,}$/', $passport)) {
            return true;
        }

        if ($this->isAscendingSequence($passport)) {
            return true;
        }

        if (preg_match('/^0+\d{1,2}$/', $passport)) {
            return true;
        }

        if ($this->isDescendingSequence($passport)) {
            return true;
        }

        return false;
    }

    private function isAscendingSequence(string $passport): bool
    {
        if (!preg_match('/^\d+$/', $passport)) {
            return false;
        }

        $sequence = '';
        $digit    = 1;

        while (strlen($sequence) < strlen($passport)) {
            $sequence .= (string) $digit;
            $digit++;
        }

        return $passport === substr($sequence, 0, strlen($passport));
    }

    private function isDescendingSequence(string $passport): bool
    {
        if (!preg_match('/^\d+$/', $passport)) {
            return false;
        }

        $sequence = '';
        $digit    = 9;

        while (strlen($sequence) < strlen($passport)) {
            $sequence .= (string) $digit;
            $digit--;

            if ($digit < 0) {
                break;
            }
        }

        return $passport === substr($sequence, 0, strlen($passport));
    }

    // ════════════════════════════════════════════════════════════════════════
    // MANEJO DE CONFLICTOS
    // ════════════════════════════════════════════════════════════════════════

    private function handlePassportAmbiguity(
        int    $rowNumber,
        string $hsPassport,
        string $hsEmail,
        array  $likeMatches,
        bool   $isDryRun,
    ): bool {
        // ── Si el correo HS ya existe en otro usuario → conflicto normal ──────
        $emailOwner = User::whereRaw('LOWER(TRIM(email)) = ?', [$hsEmail])->first();

        if ($emailOwner !== null) {
            return $this->handleConflict(
                rowNumber:      $rowNumber,
                hsPassport:     $hsPassport,
                hsEmail:        $hsEmail,
                exactMatches:   [],
                likeMatches:    $likeMatches,
                emailConflicts: [$emailOwner],
                isDryRun:       $isDryRun,
            );
        }

        // ── Contar registros en agclientes para cada pasaporte ────────────────
        // Esto determina cuál pasaporte es el "real" en el árbol genealógico
        $countHs = \App\Models\Agcliente::where('IDCliente', $hsPassport)->count();

        $dbPassportCounts = [];
        foreach ($likeMatches as $user) {
            $dbPassport = $user->passport;
            $dbPassportCounts[$dbPassport] = \App\Models\Agcliente::where('IDCliente', $dbPassport)->count();
        }

        // ── CASO: Solo HS tiene registros → HS es el correcto ─────────────────
        $algúnDbTieneRegistros = array_sum($dbPassportCounts) > 0;

        if ($countHs > 0 && !$algúnDbTieneRegistros) {
            $this->line(sprintf(
                '  [Fila %s] <fg=green>✅ Auto-resuelto</> — Pasaporte HS <fg=cyan>%s</> tiene <fg=green>%s</> registros en árbol, DB no tiene ninguno.',
                $rowNumber, $hsPassport, $countHs,
            ));

            foreach ($likeMatches as $user) {
                if ($isDryRun) {
                    $this->line(sprintf(
                        '  <fg=cyan>[DRY-RUN]</> ID %s — pasaporte: <fg=red>%s</> → <fg=green>%s</> | correo: <fg=red>%s</> → <fg=green>%s</>',
                        $user->id, $user->passport, $hsPassport, $user->email, $hsEmail,
                    ));
                } else {
                    $oldPassport = $user->passport;
                    $oldEmail    = $user->email;

                    try {
                        $user->passport = $hsPassport;
                        $user->email    = $hsEmail;
                        $user->save();

                        $this->line(sprintf(
                            '  <fg=green>✅ ID %s</> — pasaporte: <fg=gray>%s</> → <fg=green>%s</> | correo: <fg=gray>%s</> → <fg=green>%s</>',
                            $user->id, $oldPassport, $hsPassport, $oldEmail, $hsEmail,
                        ));

                        $this->updated++;

                    } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                        $user->passport = $oldPassport;
                        $user->email    = $oldEmail;

                        $this->line('  <fg=red>❌ No se pudo actualizar — el correo ya está en uso.</>');

                        $this->skippedRecords[] = [
                            'hs_pasaporte'            => $hsPassport,
                            'hs_correo'               => $hsEmail,
                            'app_id_match'            => $user->id,
                            'app_pasaporte_match'     => $oldPassport,
                            'app_correo_match'        => $oldEmail,
                            'tipo_match'              => 'update_fallido',
                            'app_id_conflicto'        => '',
                            'app_pasaporte_conflicto' => '',
                            'app_correo_conflicto'    => $hsEmail,
                            'omitido_en'              => now()->toDateTimeString(),
                        ];

                        $this->skipped++;
                        return false;
                    }
                }
            }

            return true;
        }

        // ── CASO: Solo DB tiene registros → DB es el correcto ─────────────────
        if ($countHs === 0 && $algúnDbTieneRegistros) {
            foreach ($likeMatches as $user) {
                $dbCount = $dbPassportCounts[$user->passport] ?? 0;

                if ($dbCount === 0) {
                    continue;
                }

                $this->line(sprintf(
                    '  [Fila %s] <fg=green>✅ Auto-resuelto</> — Pasaporte DB <fg=cyan>%s</> tiene <fg=green>%s</> registros en árbol, HS no tiene ninguno.',
                    $rowNumber, $user->passport, $dbCount,
                ));

                if ($this->normalize($user->email) === $hsEmail) {
                    $this->line(sprintf(
                        '  <fg=gray>➖ ID %s — correo ya es igual (%s) — sin cambios.</>',
                        $user->id, $hsEmail,
                    ));
                    $this->skipped++;
                    return true;
                }

                if ($isDryRun) {
                    $this->line(sprintf(
                        '  <fg=cyan>[DRY-RUN]</> ID %s — correo: <fg=red>%s</> → <fg=green>%s</> (pasaporte DB <fg=cyan>%s</> conservado)',
                        $user->id, $user->email, $hsEmail, $user->passport,
                    ));
                } else {
                    $oldEmail = $user->email;

                    try {
                        $user->email = $hsEmail;
                        $user->save();

                        $this->line(sprintf(
                            '  <fg=green>✅ ID %s</> — correo: <fg=gray>%s</> → <fg=green>%s</> (pasaporte DB <fg=cyan>%s</> conservado)',
                            $user->id, $oldEmail, $hsEmail, $user->passport,
                        ));

                        $this->updated++;

                    } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                        $user->email = $oldEmail;

                        $this->line('  <fg=red>❌ No se pudo actualizar — el correo ya está en uso.</>');

                        $this->skippedRecords[] = [
                            'hs_pasaporte'            => $hsPassport,
                            'hs_correo'               => $hsEmail,
                            'app_id_match'            => $user->id,
                            'app_pasaporte_match'     => $user->passport,
                            'app_correo_match'        => $oldEmail,
                            'tipo_match'              => 'update_fallido',
                            'app_id_conflicto'        => '',
                            'app_pasaporte_conflicto' => '',
                            'app_correo_conflicto'    => $hsEmail,
                            'omitido_en'              => now()->toDateTimeString(),
                        ];

                        $this->skipped++;
                        return false;
                    }
                }
            }

            return true;
        }

        // ── CASO: Ninguno tiene registros → DB es el correcto (cero extra) ────
        if ($countHs === 0 && !$algúnDbTieneRegistros) {
            $this->line(sprintf(
                '  [Fila %s] <fg=green>✅ Auto-resuelto</> — Ningún pasaporte tiene árbol. Se asume DB <fg=cyan>%s</> como correcto (posible cero extra en HS).',
                $rowNumber,
                implode(', ', array_map(fn($u) => $u->passport, $likeMatches)),
            ));

            foreach ($likeMatches as $user) {
                if ($this->normalize($user->email) === $hsEmail) {
                    $this->line(sprintf(
                        '  <fg=gray>➖ ID %s — correo ya es igual (%s) — sin cambios.</>',
                        $user->id, $hsEmail,
                    ));
                    $this->skipped++;
                    return true;
                }

                if ($isDryRun) {
                    $this->line(sprintf(
                        '  <fg=cyan>[DRY-RUN]</> ID %s — correo: <fg=red>%s</> → <fg=green>%s</> (pasaporte conservado)',
                        $user->id, $user->email, $hsEmail,
                    ));
                } else {
                    $oldEmail = $user->email;

                    try {
                        $user->email = $hsEmail;
                        $user->save();

                        $this->line(sprintf(
                            '  <fg=green>✅ ID %s</> — correo: <fg=gray>%s</> → <fg=green>%s</> (pasaporte conservado)',
                            $user->id, $oldEmail, $hsEmail,
                        ));

                        $this->updated++;

                    } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                        $user->email = $oldEmail;

                        $this->line('  <fg=red>❌ No se pudo actualizar — el correo ya está en uso.</>');

                        $this->skippedRecords[] = [
                            'hs_pasaporte'            => $hsPassport,
                            'hs_correo'               => $hsEmail,
                            'app_id_match'            => $user->id,
                            'app_pasaporte_match'     => $user->passport,
                            'app_correo_match'        => $oldEmail,
                            'tipo_match'              => 'update_fallido',
                            'app_id_conflicto'        => '',
                            'app_pasaporte_conflicto' => '',
                            'app_correo_conflicto'    => $hsEmail,
                            'omitido_en'              => now()->toDateTimeString(),
                        ];

                        $this->skipped++;
                        return false;
                    }
                }
            }

            return true;
        }

        // ── CASO: Ambos tienen registros → mostrar ambigüedad con conteos ─────
        $this->newLine();
        $this->line('<fg=yellow;options=bold>┌─────────────────────────────────────────────────────┐</>');
        $this->line('<fg=yellow;options=bold>│          🔍  AMBIGÜEDAD DE PASAPORTE                 │</>');
        $this->line('<fg=yellow;options=bold>└─────────────────────────────────────────────────────┘</>');
        $this->newLine();

        $this->line("  <options=bold>Fila CSV      :</> <fg=white>#$rowNumber</>");
        $this->line("  <options=bold>Pasaporte HS  :</> <fg=cyan>$hsPassport</>");
        $this->line("  <options=bold>Correo HS     :</> <fg=cyan>$hsEmail</>");
        $this->newLine();

        $this->line('  <fg=white;options=bold>Ambos pasaportes tienen registros en el árbol genealógico:</>');
        $this->newLine();

        $this->line(sprintf(
            '  <fg=cyan>[HS]</> Pasaporte: <fg=white;options=bold>%s</> — <fg=green>%s registros</> en árbol',
            $hsPassport, $countHs,
        ));
        $this->newLine();

        foreach ($likeMatches as $user) {
            $dbCount = $dbPassportCounts[$user->passport] ?? 0;
            $this->line(sprintf(
                '  <fg=green>[DB]</> ID: <fg=white>%s</> | Pasaporte: <fg=white;options=bold>%s</> | Correo: <fg=gray>%s</> — <fg=green>%s registros</> en árbol',
                $user->id, $user->passport, $user->email, $dbCount,
            ));
            $this->newLine();
        }

        // ── Construir opciones ────────────────────────────────────────────────
        $choices   = [];
        $choiceMap = [];

        foreach ($likeMatches as $user) {
            $labelHs = sprintf(
                '✏️  [HS] Pasaporte "%s" es correcto → actualizar pasaporte+correo del ID %s (DB: %s)',
                $hsPassport, $user->id, $user->passport,
            );
            $choices[]           = $labelHs;
            $choiceMap[$labelHs] = ['action' => 'use_hs_passport', 'user' => $user];

            $labelDb = sprintf(
                '✏️  [DB] Pasaporte "%s" es correcto → solo actualizar correo del ID %s',
                $user->passport, $user->id,
            );
            $choices[]           = $labelDb;
            $choiceMap[$labelDb] = ['action' => 'use_db_passport', 'user' => $user];
        }

        $skipLabel             = '⏭️  Omitir este registro (guardar en CSV para revisión manual)';
        $choices[]             = $skipLabel;
        $choiceMap[$skipLabel] = ['action' => 'skip'];

        $selected = $this->choice(
            question: '  ¿Cuál pasaporte es el correcto?',
            choices:  $choices,
            default:  $skipLabel,
        );

        $chosen = $choiceMap[$selected];

        switch ($chosen['action']) {

            case 'use_hs_passport':
                /** @var User $user */
                $user        = $chosen['user'];
                $oldPassport = $user->passport;
                $oldEmail    = $user->email;

                if ($isDryRun) {
                    $this->line(sprintf(
                        '  <fg=cyan>[DRY-RUN]</> ID %s — pasaporte: <fg=red>%s</> → <fg=green>%s</> | correo: <fg=red>%s</> → <fg=green>%s</>',
                        $user->id, $oldPassport, $hsPassport, $oldEmail, $hsEmail,
                    ));
                } else {
                    try {
                        $user->passport = $hsPassport;
                        $user->email    = $hsEmail;
                        $user->save();

                        $this->line(sprintf(
                            '  <fg=green>✅ ID %s</> — pasaporte: <fg=gray>%s</> → <fg=green>%s</> | correo: <fg=gray>%s</> → <fg=green>%s</>',
                            $user->id, $oldPassport, $hsPassport, $oldEmail, $hsEmail,
                        ));

                        $this->updated++;

                    } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                        $user->passport = $oldPassport;
                        $user->email    = $oldEmail;

                        $this->line('  <fg=red>❌ No se pudo actualizar — el correo ya está en uso.</>');

                        $this->skippedRecords[] = [
                            'hs_pasaporte'            => $hsPassport,
                            'hs_correo'               => $hsEmail,
                            'app_id_match'            => $user->id,
                            'app_pasaporte_match'     => $oldPassport,
                            'app_correo_match'        => $oldEmail,
                            'tipo_match'              => 'update_fallido',
                            'app_id_conflicto'        => '',
                            'app_pasaporte_conflicto' => '',
                            'app_correo_conflicto'    => $hsEmail,
                            'omitido_en'              => now()->toDateTimeString(),
                        ];

                        $this->skipped++;
                        return false;
                    }
                }
                return true;

            case 'use_db_passport':
                /** @var User $user */
                $user = $chosen['user'];

                if ($this->normalize($user->email) === $hsEmail) {
                    $this->line(sprintf(
                        '  <fg=gray>➖ ID %s — correo ya es igual (%s) — sin cambios.</>',
                        $user->id, $hsEmail,
                    ));
                    $this->skipped++;
                    return true;
                }

                if ($isDryRun) {
                    $this->line(sprintf(
                        '  <fg=cyan>[DRY-RUN]</> ID %s — correo: <fg=red>%s</> → <fg=green>%s</> (pasaporte "%s" conservado)',
                        $user->id, $user->email, $hsEmail, $user->passport,
                    ));
                } else {
                    $oldEmail = $user->email;

                    try {
                        $user->email = $hsEmail;
                        $user->save();

                        $this->line(sprintf(
                            '  <fg=green>✅ ID %s</> — correo: <fg=gray>%s</> → <fg=green>%s</> (pasaporte "%s" conservado)',
                            $user->id, $oldEmail, $hsEmail, $user->passport,
                        ));

                        $this->updated++;

                    } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                        $user->email = $oldEmail;

                        $this->line('  <fg=red>❌ No se pudo actualizar — el correo ya está en uso.</>');

                        $this->skippedRecords[] = [
                            'hs_pasaporte'            => $hsPassport,
                            'hs_correo'               => $hsEmail,
                            'app_id_match'            => $user->id,
                            'app_pasaporte_match'     => $user->passport,
                            'app_correo_match'        => $oldEmail,
                            'tipo_match'              => 'update_fallido',
                            'app_id_conflicto'        => '',
                            'app_pasaporte_conflicto' => '',
                            'app_correo_conflicto'    => $hsEmail,
                            'omitido_en'              => now()->toDateTimeString(),
                        ];

                        $this->skipped++;
                        return false;
                    }
                }
                return true;

            case 'skip':
            default:
                $this->skippedRecords[] = [
                    'hs_pasaporte'            => $hsPassport,
                    'hs_correo'               => $hsEmail,
                    'app_id_match'            => implode('|', array_map(fn($u) => $u->id,       $likeMatches)),
                    'app_pasaporte_match'     => implode('|', array_map(fn($u) => $u->passport, $likeMatches)),
                    'app_correo_match'        => implode('|', array_map(fn($u) => $u->email,    $likeMatches)),
                    'tipo_match'              => 'like_ambiguo',
                    'app_id_conflicto'        => '',
                    'app_pasaporte_conflicto' => '',
                    'app_correo_conflicto'    => '',
                    'omitido_en'              => now()->toDateTimeString(),
                ];

                $this->line('  <fg=yellow>⏭️  Omitido — guardado en CSV de revisión manual.</>');
                $this->skipped++;
                return false;
        }
    }

    private function handleConflict(
        int    $rowNumber,
        string $hsPassport,
        string $hsEmail,
        array  $exactMatches,
        array  $likeMatches,
        array  $emailConflicts,
        bool   $isDryRun,
    ): bool {
        // ── CASO ESPECIAL: solo likeMatches → ambigüedad de pasaporte ─────────
        if (empty($exactMatches) && empty($emailConflicts) && !empty($likeMatches)) {
            return $this->handlePassportAmbiguity(
                rowNumber:   $rowNumber,
                hsPassport:  $hsPassport,
                hsEmail:     $hsEmail,
                likeMatches: $likeMatches,
                isDryRun:    $isDryRun,
            );
        }

        // ── CASO ESPECIAL: likeMatch y emailConflict son el mismo pasaporte ───
        // Ej: likeMatch=98871647 y emailConflict=098871647 (cero extra)
        // En este caso el emailConflict ya tiene el correo correcto,
        // pero el likeMatch puede tener el árbol genealógico.
        // Solución: mover el correo al likeMatch y borrar el emailConflict.
        if (!empty($likeMatches) && !empty($emailConflicts)) {
            $likeMatchesResueltos  = [];
            $conflictosResueltos   = [];

            foreach ($likeMatches as $likeUser) {
                foreach ($emailConflicts as $conflictUser) {
                    $likeNorm     = $this->normalizePassport($this->normalize($likeUser->passport));
                    $conflictNorm = $this->normalizePassport($this->normalize($conflictUser->passport));
                    $hsNorm       = $this->normalizePassport($hsPassport);

                    // Son el mismo pasaporte normalizado
                    if ($likeNorm === $conflictNorm || $likeNorm === $hsNorm || $conflictNorm === $hsNorm) {

                        $countLike     = \App\Models\Agcliente::where('IDCliente', $likeUser->passport)->count();
                        $countConflict = \App\Models\Agcliente::where('IDCliente', $conflictUser->passport)->count();

                        $this->newLine();
                        $this->line(sprintf(
                            '  [Fila %s] <fg=yellow>🔍 Mismo pasaporte con variante de ceros:</> <fg=cyan>%s</> ↔ <fg=cyan>%s</>',
                            $rowNumber, $likeUser->passport, $conflictUser->passport,
                        ));
                        $this->line(sprintf(
                            '     ID <fg=white>%s</> (pasaporte <fg=cyan>%s</>): <fg=green>%s</> registros en árbol | correo: <fg=gray>%s</>',
                            $likeUser->id, $likeUser->passport, $countLike, $likeUser->email,
                        ));
                        $this->line(sprintf(
                            '     ID <fg=white>%s</> (pasaporte <fg=cyan>%s</>): <fg=green>%s</> registros en árbol | correo: <fg=gray>%s</>',
                            $conflictUser->id, $conflictUser->passport, $countConflict, $conflictUser->email,
                        ));
                        $this->newLine();

                        // ── El likeMatch tiene árbol, el conflict no (o tiene menos) ──
                        if ($countLike >= $countConflict) {
                            $this->line(sprintf(
                                '  <fg=green>✅ Auto-resuelto</> — ID <fg=white>%s</> tiene el árbol. Se actualiza su correo y se elimina ID <fg=white>%s</>.',
                                $likeUser->id, $conflictUser->id,
                            ));

                            if (!$isDryRun) {
                                try {
                                    // Primero borrar el conflict para liberar el correo
                                    $conflictUser->delete();

                                    // Ahora actualizar correo (y pasaporte si HS es el normalizado)
                                    $likeUser->email = $hsEmail;

                                    // Si el pasaporte de HS tiene más info (ej: con cero), actualizarlo
                                    if ($this->normalizePassport($hsPassport) === $this->normalizePassport($this->normalize($likeUser->passport))) {
                                        $likeUser->passport = $hsPassport;
                                    }

                                    $likeUser->save();

                                    $this->line(sprintf(
                                        '  <fg=green>✅ ID %s</> — correo: <fg=gray>%s</> → <fg=green>%s</> | ID %s eliminado.',
                                        $likeUser->id, $likeUser->getOriginal('email'), $hsEmail, $conflictUser->id,
                                    ));

                                    $this->updated++;

                                } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                                    $this->line('  <fg=red>❌ No se pudo actualizar — error inesperado de unicidad.</>');

                                    $this->skippedRecords[] = [
                                        'hs_pasaporte'            => $hsPassport,
                                        'hs_correo'               => $hsEmail,
                                        'app_id_match'            => $likeUser->id,
                                        'app_pasaporte_match'     => $likeUser->passport,
                                        'app_correo_match'        => $likeUser->email,
                                        'tipo_match'              => 'update_fallido',
                                        'app_id_conflicto'        => $conflictUser->id,
                                        'app_pasaporte_conflicto' => $conflictUser->passport,
                                        'app_correo_conflicto'    => $conflictUser->email,
                                        'omitido_en'              => now()->toDateTimeString(),
                                    ];

                                    $this->skipped++;
                                    return false;
                                }
                            } else {
                                $this->line(sprintf(
                                    '  <fg=cyan>[DRY-RUN]</> ID %s — correo: <fg=red>%s</> → <fg=green>%s</> | ID %s sería eliminado.',
                                    $likeUser->id, $likeUser->email, $hsEmail, $conflictUser->id,
                                ));
                            }

                            $likeMatchesResueltos[] = $likeUser->id;
                            $conflictosResueltos[]  = $conflictUser->id;

                        // ── El conflict tiene más árbol que el likeMatch ───────────
                        } else {
                            $this->line(sprintf(
                                '  <fg=green>✅ Auto-resuelto</> — ID <fg=white>%s</> tiene más árbol (%s reg). El ID <fg=white>%s</> ya tiene el correo correcto.',
                                $conflictUser->id, $countConflict, $conflictUser->id,
                            ));
                            $this->line(sprintf(
                                '  <fg=yellow>ℹ️  ID %s (pasaporte %s) queda sin actualizar — revisar manualmente si corresponde eliminarlo.</>',
                                $likeUser->id, $likeUser->passport,
                            ));

                            $this->skippedRecords[] = [
                                'hs_pasaporte'            => $hsPassport,
                                'hs_correo'               => $hsEmail,
                                'app_id_match'            => $likeUser->id,
                                'app_pasaporte_match'     => $likeUser->passport,
                                'app_correo_match'        => $likeUser->email,
                                'tipo_match'              => 'like_con_conflicto_mayor',
                                'app_id_conflicto'        => $conflictUser->id,
                                'app_pasaporte_conflicto' => $conflictUser->passport,
                                'app_correo_conflicto'    => $conflictUser->email,
                                'omitido_en'              => now()->toDateTimeString(),
                            ];

                            $likeMatchesResueltos[] = $likeUser->id;
                            $conflictosResueltos[]  = $conflictUser->id;
                            $this->skipped++;
                        }
                    }
                }
            }

            // ── Filtrar los ya resueltos del flujo normal ─────────────────────
            $likeMatches   = array_values(array_filter($likeMatches,   fn($u) => !in_array($u->id, $likeMatchesResueltos)));
            $emailConflicts = array_values(array_filter($emailConflicts, fn($u) => !in_array($u->id, $conflictosResueltos)));

            // Si todo quedó resuelto, salir
            if (empty($likeMatches) && empty($emailConflicts) && empty($exactMatches)) {
                return true;
            }
        }

        // ── Resto del flujo normal (prompt) ───────────────────────────────────
        $this->newLine();
        $this->line('<fg=red;options=bold>┌─────────────────────────────────────────────────────┐</>');
        $this->line('<fg=red;options=bold>│              ⚠️   CONFLICTO DETECTADO                │</>');
        $this->line('<fg=red;options=bold>└─────────────────────────────────────────────────────┘</>');
        $this->newLine();

        $this->line("  <options=bold>Fila CSV      :</> <fg=white>#$rowNumber</>");
        $this->line("  <options=bold>Pasaporte HS  :</> <fg=cyan>$hsPassport</>");
        $this->line("  <options=bold>Correo HS     :</> <fg=cyan>$hsEmail</>");
        $this->newLine();

        if (!empty($exactMatches)) {
            $this->line('  <fg=green;options=bold>✅ Match EXACTO de pasaporte:</>');
            foreach ($exactMatches as $user) {
                $this->line(sprintf(
                    '     • ID: <fg=green>%s</> | Pasaporte: <fg=green>%s</> | Correo actual: <fg=yellow>%s</>',
                    $user->id, $user->passport, $user->email,
                ));
            }
            $this->newLine();
        }

        if (!empty($likeMatches)) {
            $this->line('  <fg=yellow;options=bold>🔍 Match PARCIAL de pasaporte (LIKE):</>');
            foreach ($likeMatches as $user) {
                $this->line(sprintf(
                    '     • ID: <fg=yellow>%s</> | Pasaporte DB: <fg=yellow>%s</> | Correo actual: <fg=yellow>%s</>',
                    $user->id, $user->passport, $user->email,
                ));
            }
            $this->newLine();
        }

        if (!empty($emailConflicts)) {
            $this->line('  <fg=red;options=bold>⚠️  Correo ya existe en otro usuario (diferente pasaporte):</>');
            foreach ($emailConflicts as $user) {
                $this->line(sprintf(
                    '     • ID: <fg=red>%s</> | Pasaporte: <fg=red>%s</> | Correo actual: <fg=yellow>%s</>',
                    $user->id, $user->passport, $user->email,
                ));
            }
            $this->newLine();
        }

        $choices   = [];
        $choiceMap = [];

        foreach ($exactMatches as $user) {
            $label             = sprintf('✅ [EXACTO] Actualizar ID %s (pasaporte=%s) → %s', $user->id, $user->passport, $hsEmail);
            $choices[]         = $label;
            $choiceMap[$label] = ['action' => 'update', 'user' => $user];
        }

        foreach ($likeMatches as $user) {
            $label             = sprintf('🔍 [LIKE]   Actualizar ID %s (pasaporte=%s) → %s', $user->id, $user->passport, $hsEmail);
            $choices[]         = $label;
            $choiceMap[$label] = ['action' => 'update', 'user' => $user];
        }

        foreach ($emailConflicts as $user) {
            $label             = sprintf('🔀 [CORREO] Sobrescribir ID %s (pasaporte=%s) con → %s', $user->id, $user->passport, $hsEmail);
            $choices[]         = $label;
            $choiceMap[$label] = ['action' => 'update', 'user' => $user];
        }

        $skipLabel             = '⏭️  Omitir este registro (guardar en CSV para revisión manual)';
        $choices[]             = $skipLabel;
        $choiceMap[$skipLabel] = ['action' => 'skip'];

        $selected = $this->choice(
            question: '  ¿Qué deseas hacer?',
            choices:  $choices,
            default:  0,
        );

        $decision = $choiceMap[$selected];

        if ($decision['action'] === 'skip') {
            $this->line('  <fg=yellow>⏭️  Omitido — guardado en CSV de revisión manual.</>');
            $this->newLine();

            $allMatched = array_merge($exactMatches, $likeMatches);
            $rowsToSave = !empty($allMatched) ? $allMatched : [null];

            foreach ($rowsToSave as $user) {
                $this->skippedRecords[] = [
                    'hs_pasaporte'            => $hsPassport,
                    'hs_correo'               => $hsEmail,
                    'app_id_match'            => $user?->id ?? '',
                    'app_pasaporte_match'     => $user?->passport ?? '',
                    'app_correo_match'        => $user?->email ?? '',
                    'tipo_match'              => $user === null
                                                    ? 'ninguno'
                                                    : (in_array($user, $exactMatches) ? 'exacto' : 'like'),
                    'app_id_conflicto'        => implode('|', array_map(fn($u) => $u->id,       $emailConflicts)),
                    'app_pasaporte_conflicto' => implode('|', array_map(fn($u) => $u->passport, $emailConflicts)),
                    'app_correo_conflicto'    => implode('|', array_map(fn($u) => $u->email,    $emailConflicts)),
                    'omitido_en'              => now()->toDateTimeString(),
                ];
            }

            return false;
        }

        $targetUser = $decision['user'];

        $this->line("  <fg=green>✅ Actualizando ID {$targetUser->id} con correo: $hsEmail</>");

        if (!$isDryRun) {
            $success = $this->updateEmail($targetUser->id, $hsEmail);

            if (!$success) {
                $this->skippedRecords[] = [
                    'hs_pasaporte'            => $hsPassport,
                    'hs_correo'               => $hsEmail,
                    'app_id_match'            => $targetUser->id,
                    'app_pasaporte_match'     => $targetUser->passport,
                    'app_correo_match'        => $targetUser->email,
                    'tipo_match'              => 'update_fallido',
                    'app_id_conflicto'        => '',
                    'app_pasaporte_conflicto' => '',
                    'app_correo_conflicto'    => $hsEmail,
                    'omitido_en'              => now()->toDateTimeString(),
                ];

                $this->line('  <fg=yellow>⏭️  Guardado en CSV de revisión manual.</>');
                $this->skipped++;
                return false;
            }
        }

        $this->newLine();
        return true;
    }

    // ════════════════════════════════════════════════════════════════════════
    // BASE DE DATOS
    // ════════════════════════════════════════════════════════════════════════

    private function updateEmail(int $id, string $email): bool
    {
        try {
            User::where('id', $id)->update(['email' => $email]);
            return true;

        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // ── Buscar quién ya tiene ese correo ──────────────────────────────
            $owner   = User::whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();
            $target  = User::find($id);

            if (!$owner || !$target) {
                $this->line('  <fg=red>❌ No se pudo actualizar — error inesperado.</>');
                return false;
            }

            $countTarget = \App\Models\Agcliente::where('IDCliente', $target->passport)->count();
            $countOwner  = \App\Models\Agcliente::where('IDCliente', $owner->passport)->count();

            $this->newLine();
            $this->line('  <fg=yellow>⚠️  Correo ya en uso — revisando árboles:</>');
            $this->line(sprintf(
                '     ID <fg=white>%s</> (pasaporte <fg=cyan>%s</>): <fg=green>%s</> registros en árbol | correo: <fg=gray>%s</>',
                $target->id, $target->passport, $countTarget, $target->email,
            ));
            $this->line(sprintf(
                '     ID <fg=white>%s</> (pasaporte <fg=cyan>%s</>): <fg=green>%s</> registros en árbol | correo: <fg=gray>%s</>',
                $owner->id, $owner->passport, $countOwner, $owner->email,
            ));
            $this->newLine();

            // ── El owner no tiene árbol → borrarlo y reasignar correo ─────────
            if ($countOwner === 0) {
                $this->line(sprintf(
                    '  <fg=green>✅ Auto-resuelto</> — ID <fg=white>%s</> no tiene árbol → eliminado. Asignando correo a ID <fg=white>%s</>.',
                    $owner->id, $target->id,
                ));

                $owner->delete();
                User::where('id', $target->id)->update(['email' => $email]);

                $this->line(sprintf(
                    '  <fg=green>✅ ID %s</> — correo actualizado a <fg=green>%s</>.',
                    $target->id, $email,
                ));

                return true;
            }

            // ── El target no tiene árbol → no tiene sentido actualizar ────────
            if ($countTarget === 0) {
                $this->line(sprintf(
                    '  <fg=yellow>ℹ️  ID <fg=white>%s</> no tiene árbol y el correo ya está en ID <fg=white>%s</> (que sí tiene árbol) — sin cambios.</>',
                    $target->id, $owner->id,
                ));

                return false;
            }

            // ── Ambos tienen árbol → intentar auto-resolver por cantidad ──────
            if ($countTarget > 0 && $countOwner > 0) {

                // Si uno tiene solo 1 registro, o el otro tiene el doble o más → auto-resolver
                $puedeAutoResolver = ($countOwner === 1)
                    || ($countTarget === 1)
                    || ($countTarget >= $countOwner * 2)
                    || ($countOwner >= $countTarget * 2);

                if ($puedeAutoResolver) {
                    // El que tiene más registros es el correcto
                    if ($countTarget >= $countOwner) {
                        $this->line(sprintf(
                            '  <fg=green>✅ Auto-resuelto</> — ID <fg=white>%s</> tiene más árbol (%s reg) vs ID <fg=white>%s</> (%s reg) → eliminando el menor.',
                            $target->id, $countTarget, $owner->id, $countOwner,
                        ));

                        $owner->delete();
                        User::where('id', $target->id)->update(['email' => $email]);

                        $this->line(sprintf(
                            '  <fg=green>✅ ID %s</> — correo actualizado a <fg=green>%s</> | ID %s eliminado.',
                            $target->id, $email, $owner->id,
                        ));

                        return true;

                    } else {
                        $this->line(sprintf(
                            '  <fg=green>✅ Auto-resuelto</> — ID <fg=white>%s</> tiene más árbol (%s reg) vs ID <fg=white>%s</> (%s reg) → el correo ya está donde corresponde.',
                            $owner->id, $countOwner, $target->id, $countTarget,
                        ));

                        $this->line(sprintf(
                            '  <fg=yellow>ℹ️  ID %s (pasaporte %s, %s reg) podría ser un duplicado — revisar manualmente.</>',
                            $target->id, $target->passport, $countTarget,
                        ));

                        $this->skippedRecords[] = [
                            'hs_pasaporte'            => $target->passport,
                            'hs_correo'               => $email,
                            'app_id_match'            => $target->id,
                            'app_pasaporte_match'     => $target->passport,
                            'app_correo_match'        => $target->email,
                            'tipo_match'              => 'correo_ya_en_usuario_con_mas_arbol',
                            'app_id_conflicto'        => $owner->id,
                            'app_pasaporte_conflicto' => $owner->passport,
                            'app_correo_conflicto'    => $owner->email,
                            'omitido_en'              => now()->toDateTimeString(),
                        ];

                        return false;
                    }
                }

                // ── Ambos tienen árbol similar → revisión manual ───────────────
                $this->line('  <fg=red>❌ Ambos IDs tienen árbol genealógico similar — requiere revisión manual.</>');
                $this->line(sprintf(
                    '     ID <fg=white>%s</> (pasaporte <fg=cyan>%s</>, <fg=green>%s</> reg) vs ID <fg=white>%s</> (pasaporte <fg=cyan>%s</>, <fg=green>%s</> reg)',
                    $target->id, $target->passport, $countTarget,
                    $owner->id,  $owner->passport,  $countOwner,
                ));

                $this->skippedRecords[] = [
                    'hs_pasaporte'            => $target->passport,
                    'hs_correo'               => $email,
                    'app_id_match'            => $target->id,
                    'app_pasaporte_match'     => $target->passport,
                    'app_correo_match'        => $target->email,
                    'tipo_match'              => 'ambos_con_arbol',
                    'app_id_conflicto'        => $owner->id,
                    'app_pasaporte_conflicto' => $owner->passport,
                    'app_correo_conflicto'    => $owner->email,
                    'omitido_en'              => now()->toDateTimeString(),
                ];

                return false;
            }
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // CSV
    // ════════════════════════════════════════════════════════════════════════

    private function readCsvFromStorage(string $storagePath): array
    {
        if (!Storage::exists($storagePath)) {
            $this->error("❌ Archivo no encontrado en Storage: $storagePath");
            exit(1);
        }

        $content = Storage::get($storagePath);

        $content = str_replace("\xEF\xBB\xBF", '', $content);

        $lines = array_values(array_filter(
            explode("\n", str_replace("\r\n", "\n", $content)),
            fn($l) => trim($l) !== ''
        ));

        if (count($lines) < 2) {
            $this->error("❌ El CSV está vacío o no tiene datos: $storagePath");
            exit(1);
        }

        $firstLine = $lines[0];
        $separator = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        $this->line("   → Separador detectado: <fg=cyan>'$separator'</>");

        $headers = array_map(fn($h) => trim($h), str_getcsv(array_shift($lines), $separator));

        $rows = [];

        foreach ($lines as $line) {
            $values = str_getcsv($line, $separator);

            if (count($values) !== count($headers)) {
                continue;
            }

            $rows[] = array_combine($headers, $values);
        }

        return $rows;
    }

    private function exportSkippedRecords(): void
    {
        if (empty($this->skippedRecords)) {
            return;
        }

        $headers = array_keys($this->skippedRecords[0]);
        $lines   = [];

        $lines[] = implode(',', array_map(fn($h) => '"' . $h . '"', $headers));

        foreach ($this->skippedRecords as $record) {
            $lines[] = implode(',', array_map(
                fn($v) => '"' . str_replace('"', '""', (string) $v) . '"',
                $record
            ));
        }

        Storage::put(self::CSV_SKIPPED, implode("\n", $lines));

        $this->newLine();
        $this->info('📄 CSV de registros omitidos generado:');
        $this->line('   → <fg=cyan>storage/app/' . self::CSV_SKIPPED . '</>');
        $this->line('   → <fg=cyan>' . count($this->skippedRecords) . ' registros guardados</>');
    }

    // ════════════════════════════════════════════════════════════════════════
    // HELPERS
    // ════════════════════════════════════════════════════════════════════════

    /**
     * @return array<string, object[]>
     */
    private function indexUsersBy($users, string $field): array
    {
        $index = [];

        foreach ($users as $user) {
            $key = $this->normalize($user->$field ?? '');

            if ($key === '') {
                continue;
            }

            $index[$key][] = $user;
        }

        return $index;
    }

    private function normalize(?string $value): string
    {
        return strtolower(trim($value ?? ''));
    }

    // ════════════════════════════════════════════════════════════════════════
    // RESUMEN FINAL
    // ════════════════════════════════════════════════════════════════════════

    private function printSummary(bool $isDryRun): void
    {
        $this->exportSkippedRecords();

        $this->newLine();
        $this->line('<fg=cyan;options=bold>╔══════════════════════════════════════════╗</>');
        $this->line('<fg=cyan;options=bold>║              📊  RESUMEN FINAL           ║</>');
        $this->line('<fg=cyan;options=bold>╚══════════════════════════════════════════╝</>');
        $this->newLine();

        $this->line("  ✅  Actualizados  : <fg=green>{$this->updated}</>");
        $this->line("  🔀  Mergeados     : <fg=blue>{$this->merged}</>");
        $this->line("  ⏭️   Omitidos      : <fg=yellow>{$this->skipped}</>");
        $this->line("  ❌  Errores       : <fg=red>{$this->errors}</>");
        $this->newLine();

        if ($isDryRun) {
            $this->warn('  ⚠️  Modo DRY-RUN — ningún cambio fue aplicado en la BD.');
        } else {
            $this->info('  💾 Cambios aplicados correctamente en la BD.');
        }

        $this->newLine();
    }
}
