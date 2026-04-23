<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class DeployController extends Controller
{
    // Modelos en orden de prioridad
    private const MODELS = [
        'anthropic/claude-sonnet-4-5',  // Principal — excelente en código
        'google/gemini-2.5-pro',        // Fallback
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

        if ($pulledNewChanges) {

            shell_exec("cd " . escapeshellarg($projectPath) . " && php artisan optimize:clear 2>&1");

            $changes = $this->getCodeChangesSummary($projectPath, $beforeHead, $afterHead);

            try {
                [$summary, $modelUsed] = $this->callOpenRouterSummary($changes);
            } catch (\Throwable $e) {
                Log::error('Error IA', ['msg' => $e->getMessage()]);
                $summary = "Se desplegaron cambios, pero no se pudo generar el resumen automático.\n\n" . $changes;
            }

            try {
                $this->sendSummaryMail($summary, $modelUsed);
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
            'summary'          => $summary,
            'mail_sent'        => $mailSent,
            'mail_error'       => $mailError,
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

        $maxChars = 18000;

        if (mb_strlen($result) > $maxChars) {
            $result = mb_substr($result, 0, $maxChars) . "\n\n...[truncado por longitud]";
        }

        return trim($result);
    }

    // ── Prompt orientado a código ─────────────────────────────
    private function buildPrompt(string $changes): string
    {
        return <<<PROMPT
    Eres un experto en desarrollo de software. A continuación recibes un resumen real de despliegue de una aplicación Laravel.

    El contenido incluye:
    - nombres de archivos creados, editados o eliminados
    - líneas de código agregadas
    - líneas de código eliminadas

    NO estás viendo mensajes de commit.
    NO inventes funcionalidades.
    Describe únicamente lo que se pueda inferir del código.

    Contenido analizado:

    {$changes}

    Genera un resumen claro, breve y técnico en español con este formato:
    - Archivos o módulos afectados
    - Qué cambios funcionales se observan
    - Qué correcciones o ajustes se detectan
    - Impacto general del despliegue

    Sé directo y preciso.
    PROMPT;
    }

    // ── OpenRouter con fallback entre modelos ─────────────────
    private function callOpenRouterSummary(string $changes): array
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
                                'content' => 'Eres un experto en desarrollo de software que resume cambios de código de forma clara, precisa y breve en español.',
                            ],
                            [
                                'role'    => 'user',
                                'content' => $this->buildPrompt($changes),
                            ],
                        ],
                        'temperature' => 0.1,  // Muy determinista para resúmenes técnicos
                        'max_tokens'  => 350,
                    ]);

                if (! $response->successful()) {
                    throw new \Exception("Error con modelo {$model}: " . $response->body());
                }

                $content = trim($response->json()['choices'][0]['message']['content'] ?? '');

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

    // ── Mail con info del modelo usado ────────────────────────
    private function sendSummaryMail(string $summary, ?string $modelUsed): void
    {
        $recipients = [
            'jladera@sefarvzla.com',
            'crisantoantonio@gmail.com',
            'automatizacion@sefarvzla.com',
            'sistemascol@sefarvzla.com',
            'sistemasccs@sefarvzla.com',
        ];

        $modelLabel = $modelUsed ? "\n\n[Resumen generado por: {$modelUsed}]" : '';
        $body       = $summary . $modelLabel;

        Mail::raw($body, function ($message) use ($recipients) {
            $message->to($recipients)
                    ->subject('🚀 Nuevo despliegue - resumen de cambios');
        });
    }
}
