<?php

namespace App\Console\Commands;

use App\Models\TlCustomFieldDefinition;
use App\Models\TeamleaderToken;           // ajusta si tu modelo de token se llama diferente
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncTlCustomFieldDefinitions extends Command
{
    protected $signature   = 'tl:sync-custom-field-definitions';
    protected $description  = 'Sincroniza las definiciones de custom fields desde Teamleader';

    // Endpoint de la API de Teamleader
    private const API_URL = 'https://api.focus.teamleader.eu/customFieldDefinitions.list';

    public function handle(): int
    {
        $this->info('Obteniendo token...');

        $token = $this->getAccessToken();

        if (!$token) {
            $this->error('No se encontró token de acceso válido.');
            return self::FAILURE;
        }

        $page   = 1;
        $total  = 0;

        do {
            $this->info("Consultando página {$page}...");

            $response = Http::withToken($token)
                ->post(self::API_URL, [
                    'page' => [
                        'size'   => 100,
                        'number' => $page,
                    ],
                ]);

            if ($response->failed()) {
                $this->error('Error en la API: ' . $response->status() . ' - ' . $response->body());
                Log::channel('teamleader')->error('SyncTlCustomFieldDefinitions error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return self::FAILURE;
            }

            $definitions = $response->json('data') ?? [];

            if (empty($definitions)) {
                break;
            }

            foreach ($definitions as $def) {
                TlCustomFieldDefinition::updateOrCreate(
                    ['id' => $def['id']],
                    [
                        'label'         => $def['label']         ?? 'Sin nombre',
                        'type'          => $def['type']          ?? 'unknown',
                        'context'       => $def['context']       ?? 'unknown',
                        'required'      => $def['required']      ?? false,
                        'configuration' => $def['configuration'] ?? null,
                        'raw_data'      => $def,
                    ]
                );

                $total++;
            }

            $this->line("  → {$total} definiciones procesadas...");
            $page++;

        } while (count($definitions) === 100);

        $this->info("✅ Sincronización completa: {$total} definiciones.");

        return self::SUCCESS;
    }

    private function getAccessToken(): ?string
    {
        // Ajusta según cómo guardas el token en tu app
        $token = DB::table('teamleader_tokens')
            ->orderBy('created_at', 'desc')
            ->first();

        return $token?->access_token ?? null;
    }
}
