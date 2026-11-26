<?php

namespace App\Console\Commands;

use App\Exceptions\HubspotApiException;
use App\Exceptions\HubspotDailyBudgetExceeded;
use App\Models\User;
use App\Services\ClientCosSnapshotService;
use App\Services\CosPortfolioBackfill;
use App\Services\HubspotCosFieldSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackfillLocalCosPortfolio extends Command
{
    protected $signature = 'cos:backfill-local
        {--start : Inicia el cálculo de todos los representados}
        {--status : Muestra el progreso sin recalcular}
        {--limit=25 : Máximo de representados por lote}';

    protected $description = 'Completa los datos del COS desde HubSpot y calcula el estatus por lotes reanudables.';

    public function handle(): int
    {
        if ($this->option('status')) {
            $this->line(json_encode(CosPortfolioBackfill::report(), JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $lockPath = CosPortfolioBackfill::progressPath().'.lock';
        File::ensureDirectoryExists(dirname($lockPath));
        $lock = fopen($lockPath, 'c');
        if ($lock === false) {
            $this->error('No se pudo abrir el bloqueo del cálculo del COS.');

            return self::FAILURE;
        }

        try {
            if (! flock($lock, LOCK_EX | LOCK_NB)) {
                $this->info('Ya se está calculando un lote.');

                return self::SUCCESS;
            }
            return $this->processBatch();
        } finally {
            fclose($lock);
        }
    }

    private function processBatch(): int
    {
        $state = CosPortfolioBackfill::state();
        if ($state === [] && ! $this->option('start')) {
            $this->info('Cálculo pendiente de iniciar.');

            return self::SUCCESS;
        }
        if (isset($state['completed_at'])) {
            $this->info('El cálculo inicial ya terminó.');

            return self::SUCCESS;
        }
        if (($state['retry_after'] ?? 0) > now()->timestamp) {
            $this->info('El lote está pausado hasta que pueda reanudar las consultas a HubSpot.');

            return self::SUCCESS;
        }

        $representados = User::query()->clients();
        if ($state === []) {
            $maxId = (int) (clone $representados)->max('users.id');
            $state = [
                'status' => 'running',
                'started_at' => now()->toIso8601String(),
                'last_id' => 0,
                'max_id' => $maxId,
                'total' => (clone $representados)->where('users.id', '<=', $maxId)->count(),
                'calculated' => 0,
                'without_status' => 0,
                'hubspot_synced' => 0,
                'without_hubspot_link' => 0,
                'hubspot_review' => 0,
            ];
            CosPortfolioBackfill::save($state);
        } elseif (! isset($state['total'])) {
            $state['total'] = (clone $representados)->where('users.id', '<=', $state['max_id'])->count();
            CosPortfolioBackfill::save($state);
        }

        $limit = max(1, min(100, (int) $this->option('limit')));
        $users = $representados->where('users.id', '>', $state['last_id'])
            ->where('users.id', '<=', $state['max_id'])->orderBy('users.id')->limit($limit)->get();

        foreach ($users as $user) {
            try {
                $this->calculateUser($user, $state);
            } catch (Throwable $exception) {
                $state['error'] = ['user_id' => $user->id, 'type' => $exception::class];
                $retryDelay = match (true) {
                    $exception instanceof HubspotDailyBudgetExceeded => $exception->retryAfterSeconds,
                    $exception instanceof HubspotApiException && $exception->statusCode === 429 => $exception->retryAfterSeconds ?? 300,
                    default => null,
                };
                if ($retryDelay !== null) {
                    $state['retry_after'] = now()->addSeconds(max(1, $retryDelay))->timestamp;
                    CosPortfolioBackfill::save($state);
                    $this->warn('Se alcanzó el límite de HubSpot. El cálculo se reanudará desde este representado.');

                    return self::SUCCESS;
                }
                if ($exception instanceof HubspotApiException) {
                    $state['error']['http_status'] = $exception->statusCode;
                }
                CosPortfolioBackfill::save($state);
                $this->error("No se pudo calcular el COS del representado {$user->id}. El próximo lote reintentará este ID.");

                return self::FAILURE;
            }
            $state['last_id'] = $user->id;
            unset($state['error'], $state['retry_after']);
            CosPortfolioBackfill::save($state);
        }

        if ($users->count() < $limit || $state['last_id'] >= $state['max_id']) {
            $state['status'] = 'completed';
            $state['completed_at'] = now()->toIso8601String();
            unset($state['error'], $state['retry_after']);
            CosPortfolioBackfill::save($state);
        }
        $this->line(json_encode($state, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    private function calculateUser(User $user, array &$state): void
    {
        if (blank($user->hs_id)) {
            $ready = app(ClientCosSnapshotService::class)->rebuildLocal($user);
            $state['without_hubspot_link']++;
        } else {
            $result = app(HubspotCosFieldSyncService::class)->syncUser($user);
            if ($result['status'] !== 'synced') {
                $state['hubspot_review']++;
                Log::warning('COS: revisión pendiente durante la carga inicial de HubSpot.', [
                    'user_id' => $user->id, 'reason' => $result['status'],
                ]);

                return;
            }
            $ready = $result['cosready'];
            $state['hubspot_synced']++;
        }

        $state['calculated']++;
        if (! $ready) {
            $state['without_status']++;
        }
    }
}
