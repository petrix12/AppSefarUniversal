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

            $changes = $this->getSimpleChanges($projectPath, $beforeHead, $afterHead);

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
    private function getSimpleChanges(string $projectPath, string $beforeHead, string $afterHead): string
    {
        $cmd = "cd " . escapeshellarg($projectPath)
            . " && git log --oneline --no-merges "
            . escapeshellarg($beforeHead . '..' . $afterHead)
            . " 2>&1";

        return trim(shell_exec($cmd)) ?: 'Sin detalles de commits';
    }

    // ── Prompt orientado a código ─────────────────────────────
    private function buildPrompt(string $changes): string
    {
        return <<<PROMPT
Eres un experto en desarrollo de software. Estos son los últimos commits de una aplicación Laravel:

{$changes}

Genera un resumen claro y breve en español con este formato:
- Qué funcionalidades se agregaron o modificaron
- Qué bugs o errores se corrigieron (si aplica)
- Impacto general del despliegue

Sé directo. No inventes nada que no esté en los commits.
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
