<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class DeployController extends Controller
{
    public function deploy(Request $request)
    {
        $projectPath = base_path();

        // HEAD antes
        $beforeHead = trim(shell_exec(
            "cd " . escapeshellarg($projectPath) . " && git rev-parse HEAD 2>&1"
        ));

        // git pull
        $gitOut = trim(shell_exec(
            "cd " . escapeshellarg($projectPath) . " && git pull 2>&1"
        ));

        // HEAD después
        $afterHead = trim(shell_exec(
            "cd " . escapeshellarg($projectPath) . " && git rev-parse HEAD 2>&1"
        ));

        $pulledNewChanges = ($beforeHead && $afterHead && $beforeHead !== $afterHead);

        $summary = null;
        $mailSent = false;
        $mailError = null;

        if ($pulledNewChanges) {

            // limpiar cache
            shell_exec("cd " . escapeshellarg($projectPath) . " && php artisan optimize:clear 2>&1");

            // 👇 obtener commits recientes
            $changes = $this->getSimpleChanges($projectPath, $beforeHead, $afterHead);

            // 👇 generar resumen IA
            try {
                $summary = $this->callOpenRouterSummary($changes);
            } catch (\Throwable $e) {
                Log::error('Error IA', ['msg' => $e->getMessage()]);
                $summary = "Se desplegaron cambios, pero no se pudo generar el resumen automático.\n\n" . $changes;
            }

            // 👇 enviar correo
            try {
                $this->sendSummaryMail($summary);
                $mailSent = true;
            } catch (\Throwable $e) {
                $mailError = $e->getMessage();
                Log::error('Error Mail', ['msg' => $mailError]);
            }
        }

        return response()->json([
            'ok' => true,
            'changes_detected' => $pulledNewChanges,
            'summary' => $summary,
            'mail_sent' => $mailSent,
            'mail_error' => $mailError,
        ]);
    }

    // 🔹 SOLO commits (sin ruido)
    private function getSimpleChanges(string $projectPath, string $beforeHead, string $afterHead): string
    {
        $cmd = "cd " . escapeshellarg($projectPath)
            . " && git log --oneline --no-merges "
            . escapeshellarg($beforeHead . '..' . $afterHead)
            . " 2>&1";

        return trim(shell_exec($cmd)) ?: 'Sin detalles de commits';
    }

    // 🔹 PROMPT IA
    private function buildPrompt(string $changes): string
    {
        return <<<PROMPT
Estos son los últimos commits de una aplicación:

{$changes}

Genera un resumen MUY breve en español.

Formato:
- 1 o 2 frases de resumen general
- 3 a 5 bullets con cambios importantes

Máximo 6 líneas.
No inventes nada.
PROMPT;
    }

    // 🔹 OpenRouter
    private function callOpenRouterSummary(string $changes): string
    {
        $apiKey = env('OPENROUTER_API_KEY');

        if (!$apiKey) {
            throw new \Exception("Falta OPENROUTER_API_KEY");
        }

        $response = Http::timeout(20)
            ->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post("https://openrouter.ai/api/v1/chat/completions", [
                'model' => 'openai/gpt-4o-mini',
                'messages' => [
                    [
                        "role" => "system",
                        "content" => "Eres un asistente que resume cambios de software de forma clara y breve."
                    ],
                    [
                        "role" => "user",
                        "content" => $this->buildPrompt($changes)
                    ]
                ],
                'temperature' => 0.2,
                'max_tokens' => 200,
            ]);

        if (!$response->successful()) {
            throw new \Exception("Error OpenRouter: " . $response->body());
        }

        return trim($response->json()['choices'][0]['message']['content'] ?? 'Sin respuesta IA');
    }

    // 🔹 MAIL SIMPLE
    private function sendSummaryMail(string $summary): void
    {
        $recipients = [
            'jladera@sefarvzla.com',
            'crisantoantonio@gmail.com',
            'automatizacion@sefarvzla.com',
            'sistemascol@sefarvzla.com'
        ];

        Mail::raw($summary, function ($message) use ($recipients) {
            $message->to($recipients)
                ->subject('🚀 Nuevo despliegue - resumen de cambios');
        });
    }
}
