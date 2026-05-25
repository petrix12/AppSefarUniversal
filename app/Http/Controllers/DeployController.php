<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class DeployController extends Controller
{
    // Modelos baratos en orden de prioridad
    private const MODELS = [
        'google/gemini-2.0-flash-lite-001',
        'google/gemini-2.5-flash-lite',
    ];

    public function deploy(Request $request)
    {
        $projectPath = base_path();

        $beforeHead = trim(shell_exec(
            "cd " . escapeshellarg($projectPath) . " && git rev-parse HEAD 2>&1"
        ));

        $gitOut = trim(shell_exec(
            "cd " . escapeshellarg($projectPath) . " && git pull 2>&1"
        ));

        $afterHead = trim(shell_exec(
            "cd " . escapeshellarg($projectPath) . " && git rev-parse HEAD 2>&1"
        ));

        $pulledNewChanges = ($beforeHead && $afterHead && $beforeHead !== $afterHead);

        $summary   = null;
        $mailSent  = false;
        $mailError = null;
        $modelUsed = null;
        $releaseVersion = null;
        $migrateOut = null;
        $optimizeClearOut = null;

        if ($pulledNewChanges) {
            $releaseVersion = $this->releaseVersion($afterHead);

            $migrateOut = trim(shell_exec(
                "cd " . escapeshellarg($projectPath) . " && php artisan migrate 2>&1"
            ) ?: '');

            $optimizeClearOut = trim(shell_exec(
                "cd " . escapeshellarg($projectPath) . " && php artisan optimize:clear 2>&1"
            ) ?: '');

            $changes = $this->getCodeChangesSummary($projectPath, $beforeHead, $afterHead);

            try {
                [$summary, $modelUsed] = $this->callOpenRouterSummary($changes, $releaseVersion);
            } catch (\Throwable $e) {
                Log::error('Error IA', ['msg' => $e->getMessage()]);
                $summary = $this->buildFallbackSummary($changes, $releaseVersion);
            }

            try {
                $this->sendSummaryMail($summary, $modelUsed, $releaseVersion);
                $mailSent = true;
            } catch (\Throwable $e) {
                $mailError = $e->getMessage();
                Log::error('Error Mail', ['msg' => $mailError]);
            }
        }

        return response()->json([
            'ok'               => true,
            'changes_detected' => $pulledNewChanges,
            'model_used'       => $modelUsed,
            'version'          => $releaseVersion,
            'summary'          => $summary,
            'mail_sent'        => $mailSent,
            'mail_error'       => $mailError,
            'migrate_output'   => $migrateOut,
            'optimize_output'  => $optimizeClearOut,
        ]);
    }

    // ── Solo commits, sin ruido ───────────────────────────────
    private function getCodeChangesSummary(string $projectPath, string $beforeHead, string $afterHead): string
    {
        $range = escapeshellarg($beforeHead . '..' . $afterHead);

        // Lista de archivos con estado: A, M, D
        $nameStatusCmd = "cd " . escapeshellarg($projectPath)
            . " && git diff --name-status {$range} 2>&1";

        $nameStatusOutput = trim(shell_exec($nameStatusCmd)) ?: '';

        // Diff sin contexto:
        // -U0 => 0 líneas de contexto
        // --no-color => limpio
        // --no-ext-diff => evita herramientas externas
        $diffCmd = "cd " . escapeshellarg($projectPath)
            . " && git diff --unified=0 --no-color --no-ext-diff {$range} 2>&1";

        $diffOutput = trim(shell_exec($diffCmd)) ?: '';

        if ($nameStatusOutput === '' && $diffOutput === '') {
            return 'Sin cambios detectables entre commits.';
        }

        $files = [];
        foreach (preg_split('/\r\n|\r|\n/', $nameStatusOutput) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            [$status, $file] = array_pad(preg_split('/\s+/', $line, 2), 2, null);

            if (!$status || !$file) {
                continue;
            }

            $label = match (true) {
                str_starts_with($status, 'A') => 'CREADO',
                str_starts_with($status, 'M') => 'EDITADO',
                str_starts_with($status, 'D') => 'ELIMINADO',
                str_starts_with($status, 'R') => 'RENOMBRADO',
                default => $status,
            };

            $files[] = "[{$label}] {$file}";
        }

        $filteredDiffLines = [];
        $currentFile = null;

        foreach (preg_split('/\r\n|\r|\n/', $diffOutput) as $line) {
            if (str_starts_with($line, 'diff --git ')) {
                $currentFile = null;
                continue;
            }

            if (str_starts_with($line, 'index ')) {
                continue;
            }

            if (str_starts_with($line, '--- ')) {
                continue;
            }

            if (str_starts_with($line, '+++ ')) {
                $filePath = preg_replace('#^\+\+\+ b/#', '', $line);
                $filePath = preg_replace('#^\+\+\+ /dev/null#', '/dev/null', $filePath);
                $currentFile = $filePath;
                $filteredDiffLines[] = "";
                $filteredDiffLines[] = "Archivo: {$currentFile}";
                continue;
            }

            if (str_starts_with($line, '@@')) {
                continue;
            }

            // Solo líneas reales agregadas/eliminadas
            if (
                str_starts_with($line, '+') &&
                !str_starts_with($line, '+++')
            ) {
                $filteredDiffLines[] = $line;
                continue;
            }

            if (
                str_starts_with($line, '-') &&
                !str_starts_with($line, '---')
            ) {
                $filteredDiffLines[] = $line;
                continue;
            }
        }

        $result = "ARCHIVOS CAMBIADOS:\n"
            . (!empty($files) ? implode("\n", $files) : 'Sin archivos listados.')
            . "\n\nCAMBIOS DE CÓDIGO:\n"
            . (!empty($filteredDiffLines) ? implode("\n", $filteredDiffLines) : 'Sin líneas agregadas o eliminadas.');

        return trim($result);
    }

    // ── Prompt orientado a código ─────────────────────────────
    private function releaseVersion(string $afterHead): string
    {
        $configuredVersion = trim((string) (config('app.version') ?? env('APP_VERSION', '')));

        if ($configuredVersion !== '') {
            return $configuredVersion;
        }

        $shortCommit = preg_match('/^[a-f0-9]{7,40}$/i', $afterHead)
            ? substr($afterHead, 0, 7)
            : 'local';

        return now()->format('Y.m.d.Hi') . '-' . $shortCommit;
    }

    private function buildPrompt(string $changes, string $version): string
    {
        $appName = config('app.name', 'Sefar Universal');

        return <<<PROMPT
    Eres un experto en desarrollo de software. Recibes cambios reales de una aplicacion Laravel.

    Reglas estrictas:
    - Responde solo en texto plano.
    - No uses markdown, tablas, negritas, titulos con #, bloques de codigo ni enlaces.
    - No menciones hashes, commits, diffs ni el modelo.
    - No menciones nombres de archivos, rutas internas ni detalles de implementacion salvo que sean necesarios para entender el cambio.
    - No inventes funcionalidades.
    - No recortes ni reemplaces informacion con puntos suspensivos.
    - Incluye todos los cambios relevantes que puedas identificar.
    - Agrupa los cambios por modulo o area funcional.
    - Cada bullet debe tener un titulo breve y debajo una explicacion clara de una o dos frases.

    Usa exactamente este formato:

    Hola a todos,

    Les compartimos las novedades incluidas en la version {$version} de la aplicacion {$appName}:

    ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    emoji MODULO — Tema del cambio
    ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    • Titulo breve del cambio.
      Explicacion concreta del cambio y de su impacto practico para el usuario o el equipo.

    • Otro titulo breve.
      Explicacion concreta del cambio.

    ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    Un saludo,
    Alejandro — Equipo Tech

    Usa tantas secciones y bullets como sean necesarios. Si no encuentras un emoji adecuado, usa 📌.

    Cambios analizados:

    {$changes}
    PROMPT;
    }

    // ── OpenRouter con fallback entre modelos ─────────────────
    private function callOpenRouterSummary(string $changes, string $version): array
    {
        $apiKey = config('services.openrouter.key') ?? env('OPENROUTER_API_KEY');

        if (! $apiKey) {
            throw new \Exception("Falta OPENROUTER_API_KEY");
        }

        $lastException = null;

        foreach (self::MODELS as $model) {
            try {
                $response = Http::timeout(30)
                    ->withHeaders([
                        'Authorization' => "Bearer {$apiKey}",
                        'Content-Type'  => 'application/json',
                        'HTTP-Referer'  => config('app.url'),
                        'X-Title'       => config('app.name'),
                    ])
                    ->post('https://openrouter.ai/api/v1/chat/completions', [
                        'model'       => $model,
                        'messages'    => [
                            [
                                'role'    => 'system',
                                'content' => 'Redacta notas de version en espanol para un correo de despliegue. Usa solo texto plano y respeta exactamente el formato solicitado.',
                            ],
                            [
                                'role'    => 'user',
                                'content' => $this->buildPrompt($changes, $version),
                            ],
                        ],
                        'temperature' => 0.1,  // Muy determinista para resumenes tecnicos
                    ]);

                if (! $response->successful()) {
                    throw new \Exception("Error con modelo {$model}: " . $response->body());
                }

                $content = $this->normalizeReleaseNotes(
                    trim($response->json()['choices'][0]['message']['content'] ?? ''),
                    $version
                );

                if (empty($content)) {
                    throw new \Exception("Respuesta vacía del modelo {$model}");
                }

                Log::info('Deploy IA OK', ['model' => $model]);

                // Retorna [resumen, modelo_usado]
                return [$content, $model];

            } catch (\Throwable $e) {
                Log::warning("Falló modelo {$model}, intentando siguiente...", [
                    'error' => $e->getMessage(),
                ]);
                $lastException = $e;
            }
        }

        throw new \Exception(
            "Todos los modelos fallaron. Último error: " . $lastException?->getMessage()
        );
    }

    private function normalizeReleaseNotes(string $summary, string $version): string
    {
        $summary = str_replace(["\r\n", "\r"], "\n", trim($summary));
        $summary = preg_replace('/```.*?```/s', '', $summary) ?? $summary;
        $summary = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $summary) ?? $summary;
        $summary = str_replace(['**', '__', '`'], '', $summary);
        $summary = preg_replace('/^\s*#{1,6}\s*/m', '', $summary) ?? $summary;
        $summary = preg_replace("/\n{3,}/", "\n\n", $summary) ?? $summary;
        $summary = trim($summary);

        if ($summary === '') {
            return $this->buildFallbackSummary('', $version);
        }

        if (!str_starts_with($summary, 'Hola')) {
            $summary = $this->releaseIntro($version) . "\n\n" . $summary;
        }

        if (!str_contains($summary, 'Un saludo')) {
            $summary .= "\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\nUn saludo,\nAlejandro — Equipo Tech";
        }

        return $summary;
    }

    private function buildFallbackSummary(string $changes, string $version): string
    {
        $sections = [];

        foreach (preg_split('/\r\n|\r|\n/', $changes) ?: [] as $line) {
            $line = trim($line);

            if (!preg_match('/^\[(CREADO|EDITADO|ELIMINADO|RENOMBRADO)\]\s+(.+)$/', $line, $matches)) {
                continue;
            }

            $status = $matches[1];
            $file = $matches[2];
            $module = $this->moduleLabelForFile($file);

            $sections[$module][] = match ($status) {
                'CREADO' => "• Nuevo ajuste en {$module}.\n  Se incorporaron cambios nuevos en esta area para ampliar o completar el comportamiento disponible.",
                'ELIMINADO' => "• Limpieza en {$module}.\n  Se retiraron piezas que ya no forman parte del flujo actual, dejando el despliegue mas ordenado.",
                default => "• Actualizacion en {$module}.\n  Se aplicaron ajustes recientes en esta area para mantener la aplicacion al dia.",
            };

        }

        if (empty($sections)) {
            $sections['GENERAL — Actualizacion del sistema'][] =
                "• Ajustes generales del despliegue.\n  Se actualizaron cambios internos para dejar la aplicacion al dia.";
        }

        $body = $this->releaseIntro($version);

        foreach ($sections as $module => $items) {
            $body .= "\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $body .= "📌 {$module}\n";
            $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $body .= implode("\n\n", $items);
        }

        return $body . "\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\nUn saludo,\nAlejandro — Equipo Tech";
    }

    private function releaseIntro(string $version): string
    {
        $appName = config('app.name', 'Sefar Universal');

        return "Hola a todos,\n\nLes compartimos las novedades incluidas en la version {$version} de la aplicacion {$appName}:";
    }

    private function moduleLabelForFile(string $file): string
    {
        return match (true) {
            str_contains($file, 'Task') || str_contains($file, 'tasks') => 'TAREAS — Gestion y reasignacion',
            str_contains($file, 'List') || str_contains($file, 'lists') => 'LISTAS — Importacion y contactos',
            str_contains($file, 'Teamleader') || str_contains($file, 'teamleader') => 'TEAMLEADER — Sincronizacion',
            str_contains($file, 'Deploy') || str_contains($file, 'deploy') => 'DEPLOY — Despliegues y notificaciones',
            str_contains($file, 'Hubspot') || str_contains($file, 'hubspot') => 'HUBSPOT — Propietarios y sincronizacion',
            str_contains($file, 'User') || str_contains($file, 'users') => 'USUARIOS — Gestion de clientes',
            str_contains($file, 'migration') || str_contains($file, 'migrations') => 'BASE DE DATOS — Estructura',
            default => 'GENERAL — Actualizacion del sistema',
        };
    }

    // ── Mail con info del modelo usado ────────────────────────
    private function sendSummaryMail(string $summary, ?string $modelUsed, string $version): void
    {
        $recipients = [
            'jladera@sefarvzla.com',
            'crisantoantonio@gmail.com',
            'automatizacion@sefarvzla.com',
            'sistemascol@sefarvzla.com',
            'sistemasccs@sefarvzla.com',
        ];

        $body = $summary;

        Mail::raw($body, function ($message) use ($recipients, $version) {
            $message->to($recipients)
                    ->subject('🚀 Nuevo despliegue - resumen de cambios');
        });
    }
}
