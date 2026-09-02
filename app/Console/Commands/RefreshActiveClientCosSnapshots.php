<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\ClientAppNotification;
use App\Services\ClientCosSnapshotService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefreshActiveClientCosSnapshots extends Command
{
    protected $signature = 'cos:refresh-active-clients
        {--limit= : Máximo de clientes a procesar en esta corrida}
        {--force : Incluye clientes cuyo caché COS todavía no ha vencido}
        {--no-notify : Actualiza COS, pero no envía correos ni notificaciones}';

    protected $description = 'Actualiza gradualmente el COS vencido de clientes con pay > 1 y contrato = 1.';

    public function handle(ClientCosSnapshotService $snapshots): int
    {
        $limit = $this->limit();
        $force = (bool) $this->option('force');
        $notify = ! (bool) $this->option('no-notify');
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
        $notified = 0;
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

                if ($notify && $this->shouldNotify($previousCos, $hasChanged)) {
                    $freshClient = $snapshot['client']->fresh() ?? $client->fresh() ?? $client;
                    $freshClient->notify(new ClientAppNotification(
                        title: 'Actualización de estatus de tu proceso',
                        body: $this->notificationBody($currentCos),
                        actionUrl: route('clientes.status'),
                        actionText: 'Ver mi estatus',
                        category: 'cos_status',
                        sendEmail: true,
                        storeInApp: true,
                    ));
                    $notified++;
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

        $this->info("Procesados: {$updated}; cambios: {$changed}; notificados: {$notified}; errores: {$failed}.");

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

    private function shouldNotify(?array $previousCos, bool $hasChanged): bool
    {
        if (! $hasChanged) {
            return false;
        }

        return $previousCos !== null || (bool) config('cos_snapshot.notify_on_initial_snapshot', false);
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

    private function notificationBody(array $cos): string
    {
        $statuses = collect($cos)
            ->map(function ($item) {
                $service = trim((string) ($item['servicio'] ?? 'Tu proceso'));
                $step = trim((string) ($item['currentStepName'] ?? 'Estado actualizado'));

                return "{$service}: {$step}";
            })
            ->filter()
            ->take(3)
            ->implode(' | ');

        $detail = $statuses !== '' ? " Estado actual: {$statuses}." : '';

        return 'El estatus de tu proceso ha sido actualizado.' . $detail . ' Ingresa a la app para ver el detalle.';
    }
}
