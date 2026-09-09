<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ClientCosSnapshotService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefreshActiveClientCosSnapshots extends Command
{
    protected $signature = 'cos:refresh-active-clients
        {--limit= : Máximo de clientes a procesar en esta corrida}
        {--force : Incluye clientes cuyo caché COS todavía no ha vencido}';

    protected $description = 'Actualiza gradualmente el COS vencido de clientes con pay > 1 y contrato = 1.';

    public function handle(ClientCosSnapshotService $snapshots): int
    {
        $limit = $this->limit();
        $force = (bool) $this->option('force');
        $delaySeconds = max(0, (int) config('cos_snapshot.inter_client_delay_seconds', 2));

        $clients = $this->eligibleClients($force)
            ->limit($limit)
            ->get();

        if ($clients->isEmpty()) {
            $this->info('No hay clientes elegibles con COS vencido.');

            return self::SUCCESS;
        }

        $updated = 0;
        $changed = 0;
        $failed = 0;

        foreach ($clients as $index => $client) {
            $previousCos = $client->arraycos;
            $previousSignature = $this->statusSignature($previousCos);

            try {
                $snapshot = $snapshots->refresh($client, true);
                $currentCos = $snapshot['cos'] ?? [];
                $currentSignature = $this->statusSignature($currentCos);
                $hasChanged = $previousSignature !== $currentSignature;
                $updated++;

                if ($hasChanged) {
                    $changed++;
                }

                $this->line("Cliente {$client->id}: COS actualizado" . ($hasChanged ? ' (con cambio de estatus).' : '.'));
            } catch (Throwable $exception) {
                $failed++;

                Log::warning('COS automático: no se pudo actualizar cliente.', [
                    'user_id' => $client->id,
                    'error' => $exception->getMessage(),
                ]);

                $this->warn("Cliente {$client->id}: no se pudo actualizar.");
            }

            if ($delaySeconds > 0 && $index < $clients->count() - 1) {
                usleep($delaySeconds * 1_000_000);
            }
        }

        $this->info("Procesados: {$updated}; cambios: {$changed}; errores: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function eligibleClients(bool $force): Builder
    {
        return User::query()
            ->where('pay', '>', 1)
            ->where('contrato', 1)
            ->when(! $force, function (Builder $query) {
                $query->where(function (Builder $query) {
                    $query->whereNull('arraycos_expire')
                        ->orWhere('arraycos_expire', '<=', now());
                });
            })
            ->orderByRaw('CASE WHEN arraycos_expire IS NULL THEN 0 ELSE 1 END')
            ->orderBy('arraycos_expire')
            ->orderBy('id');
    }

    private function limit(): int
    {
        $option = $this->option('limit');
        $limit = $option !== null && $option !== ''
            ? (int) $option
            : (int) config('cos_snapshot.daily_limit', 50);

        return max(1, $limit);
    }

    /**
     * Compara solo datos que representan el estatus para que cambios internos
     * de orden o de metadatos no generen correos innecesarios.
     */
    private function statusSignature(?array $cos): string
    {
        $statuses = collect($cos ?? [])
            ->map(function ($item) {
                return [
                    'servicio' => $item['servicio'] ?? null,
                    'paso' => $item['currentStepName'] ?? null,
                    'paso_genealogico' => $item['currentStepGen'] ?? null,
                    'paso_juridico' => $item['currentStepJur'] ?? null,
                    'progreso_genealogico' => $item['progressPercentageGen'] ?? null,
                    'progreso_juridico' => $item['progressPercentageJur'] ?? null,
                    'certificado_descargado' => $item['certificadoDescargado'] ?? null,
                ];
            })
            ->sortBy(fn (array $item) => strtolower((string) $item['servicio']))
            ->values()
            ->all();

        return json_encode($statuses, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

}
