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
                $summary = $this->buildFallbackSummary($changes);
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
    Eres un experto en desarrollo de software. Recibes cambios reales de una aplicacion Laravel.

    Reglas estrictas:
    - Responde solo en texto plano.
    - No uses markdown, tablas, negritas, titulos con #, bloques de codigo ni enlaces.
    - No menciones hashes, commits, diffs, archivos truncados ni el modelo.
    - No inventes funcionalidades.
    - Maximo 3 lineas de cambios.
    - Cada linea debe ser corta y facil de leer.

    Usa exactamente este formato:

    Cambios implementados
    - Se actualizo ..., que resuelve ...
    - Se actualizo ..., que arregla el problema ...
    - Se agrego ..., que sirve para ...

    Si hay menos cambios, usa solo 1 o 2 lineas.

    Cambios analizados:

    {$changes}
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
                                'content' => 'Resume cambios de codigo en espanol usando solo texto plano y el formato indicado. No uses markdown.',
                            ],
                            [
                                'role'    => 'user',
                                'content' => $this->buildPrompt($changes),
                            ],
                        ],
                        'temperature' => 0.1,  // Muy determinista para resumenes tecnicos
                        'max_tokens'  => 180,
                    ]);

                if (! $response->successful()) {
                    throw new \Exception("Error con modelo {$model}: " . $response->body());
                }

                $content = $this->normalizeSummary(
                    trim($response->json()['choices'][0]['message']['content'] ?? '')
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

    private function normalizeSummary(string $summary): string
    {
        $summary = str_replace(["\r\n", "\r"], "\n", trim($summary));
        $summary = preg_replace('/```.*?```/s', '', $summary) ?? $summary;
        $summary = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $summary) ?? $summary;
        $summary = str_replace(['**', '__', '`'], '', $summary);

        $lines = preg_split('/\n+/', $summary) ?: [];
        $items = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || strcasecmp($line, 'Cambios implementados') === 0) {
                continue;
            }

            $line = preg_replace('/^\s*(#{1,6}|\*|\x{2022}|\d+[.)]|-)\s*/u', '', $line) ?? $line;
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (!preg_match('/^Se\s+/i', $line)) {
                $line = 'Se actualizo ' . lcfirst($line);
            }

            $items[] = $this->limitPlainLine($line);

            if (count($items) >= 3) {
                break;
            }
        }

        if (empty($items)) {
            $items[] = 'Se actualizaron cambios del despliegue, que dejan la aplicacion al dia.';
        }

        return "Cambios implementados\n- " . implode("\n- ", $items);
    }

    private function buildFallbackSummary(string $changes): string
    {
        $items = [];

        foreach (preg_split('/\r\n|\r|\n/', $changes) ?: [] as $line) {
            $line = trim($line);

            if (!preg_match('/^\[(CREADO|EDITADO|ELIMINADO|RENOMBRADO)\]\s+(.+)$/', $line, $matches)) {
                continue;
            }

            $status = $matches[1];
            $file = $matches[2];

            $items[] = match ($status) {
                'CREADO' => "Se agrego {$file}, que sirve para incorporar cambios nuevos al sistema.",
                'ELIMINADO' => "Se elimino {$file}, que retira codigo que ya no se usa.",
                default => "Se actualizo {$file}, que incluye ajustes recientes del despliegue.",
            };

            if (count($items) >= 3) {
                break;
            }
        }

        if (empty($items)) {
            $items[] = 'Se actualizaron cambios del despliegue, que dejan la aplicacion al dia.';
        }

        return "Cambios implementados\n- " . implode("\n- ", array_map([$this, 'limitPlainLine'], $items));
    }

    private function limitPlainLine(string $line): string
    {
        $line = preg_replace('/\s+/', ' ', trim($line)) ?? trim($line);

        return mb_strlen($line) > 160
            ? rtrim(mb_substr($line, 0, 157)) . '...'
            : $line;
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

        $body = $summary;

        Mail::raw($body, function ($message) use ($recipients) {
            $message->to($recipients)
                    ->subject('🚀 Nuevo despliegue - resumen de cambios');
        });
    }
}
