<?php

namespace App\Console\Commands;

use App\Jobs\Monday\EnsureMondayBoardWebhooksJob;
use App\Jobs\Monday\SyncMondayAccountJob;
use App\Jobs\Monday\SyncMondayBoardJob;
use Illuminate\Bus\UniqueLock;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ResetMondayMirrorCommand extends Command
{
    protected $signature = 'monday:reset
                            {--force : Confirmar el borrado sin interacción}
                            {--release-reserved : Liberar trabajos reservados después de confirmar que no queda ningún worker activo}';

    protected $description = 'Vacía únicamente la réplica local de Monday y su cola para comenzar una importación desde cero.';

    private const MIRROR_TABLES = [
        'monday_item_movements',
        'monday_item_values',
        'monday_items',
        'monday_columns',
        'monday_groups',
        'monday_webhook_events',
        'monday_sync_runs',
        'monday_boards',
    ];

    private const UNIQUE_JOB_CLASSES = [
        SyncMondayAccountJob::class,
        SyncMondayBoardJob::class,
        EnsureMondayBoardWebhooksJob::class,
    ];

    public function handle(CacheRepository $cache): int
    {
        if ((bool) config('services.monday.mirror_enabled')) {
            $this->error('Primero establece MONDAY_MIRROR_ENABLED=false y ejecuta php artisan optimize:clear.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(
            'Esto borrará definitivamente la réplica local, el historial de sincronización y los eventos recibidos de Monday. ¿Continuar?'
        )) {
            $this->warn('Reinicio cancelado.');

            return self::SUCCESS;
        }

        try {
            [$queuedJobs, $failedJobs] = $this->clearMondayQueue($cache);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $deleted = [];
        DB::transaction(function () use (&$deleted): void {
            foreach (self::MIRROR_TABLES as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $deleted[$table] = DB::table($table)->delete();
            }
        }, 5);

        $this->table(
            ['Tabla', 'Registros eliminados'],
            collect($deleted)->map(fn (int $count, string $table): array => [$table, $count])->values()->all()
        );
        $this->info("Cola monday-sync eliminada: {$queuedJobs} trabajos. Fallos de Monday eliminados: {$failedJobs}.");
        $this->info('La réplica local quedó vacía. No se modificaron los webhooks remotos ni las tablas de configuración de Monday.');

        return self::SUCCESS;
    }

    private function clearMondayQueue(CacheRepository $cache): array
    {
        $queueConfig = (array) config('queue.connections.monday', []);
        $queueName = (string) ($queueConfig['queue'] ?? 'monday-sync');
        $jobs = collect();

        if (($queueConfig['driver'] ?? null) === 'database') {
            $connectionName = (string) ($queueConfig['connection'] ?: config('database.default'));
            $table = (string) ($queueConfig['table'] ?? 'jobs');
            if (Schema::connection($connectionName)->hasTable($table)) {
                $query = DB::connection($connectionName)->table($table)->where('queue', $queueName);
                $retryAfter = max(1, (int) ($queueConfig['retry_after'] ?? 90));
                $activeReservation = (clone $query)
                    ->whereNotNull('reserved_at')
                    ->where('reserved_at', '>', now()->timestamp - $retryAfter)
                    ->orderByDesc('reserved_at')
                    ->first(['id', 'reserved_at']);
                if ($activeReservation !== null && ! $this->option('release-reserved')) {
                    $remaining = max(1, $retryAfter - (now()->timestamp - (int) $activeReservation->reserved_at));
                    throw new RuntimeException(
                        "Hay un trabajo monday-sync reservado recientemente. Si el worker ya está detenido, espera {$remaining} segundos o repite con --release-reserved."
                    );
                }
                $jobs = (clone $query)->get(['payload']);
            }
        }

        $uniqueLock = new UniqueLock($cache);
        foreach ($jobs as $queuedJob) {
            $this->releaseUniqueLock($uniqueLock, (string) $queuedJob->payload);
        }

        $queuedJobs = (int) Queue::connection('monday')->clear($queueName);
        $failedJobs = $this->deleteFailedMondayJobs();

        return [$queuedJobs, $failedJobs];
    }

    private function releaseUniqueLock(UniqueLock $uniqueLock, string $payload): void
    {
        $decoded = json_decode($payload, true);
        $class = (string) data_get($decoded, 'data.commandName', data_get($decoded, 'displayName', ''));
        $serialized = data_get($decoded, 'data.command');
        if (! in_array($class, self::UNIQUE_JOB_CLASSES, true) || ! is_string($serialized)) {
            return;
        }

        $job = @unserialize($serialized, [
            'allowed_classes' => self::UNIQUE_JOB_CLASSES,
        ]);
        if (! is_object($job)) {
            $job = @unserialize(base64_decode($serialized, true) ?: '', [
                'allowed_classes' => self::UNIQUE_JOB_CLASSES,
            ]);
        }
        if (is_object($job) && in_array($job::class, self::UNIQUE_JOB_CLASSES, true)) {
            $uniqueLock->release($job);
        }
    }

    private function deleteFailedMondayJobs(): int
    {
        $failedConfig = (array) config('queue.failed', []);
        if (! str_starts_with((string) ($failedConfig['driver'] ?? ''), 'database')) {
            return 0;
        }

        $connectionName = (string) ($failedConfig['database'] ?: config('database.default'));
        $table = (string) ($failedConfig['table'] ?? 'failed_jobs');
        if (! Schema::connection($connectionName)->hasTable($table)) {
            return 0;
        }

        $connection = DB::connection($connectionName);
        $ids = $connection->table($table)
            ->get(['id', 'payload'])
            ->filter(fn ($row): bool => str_contains((string) $row->payload, 'App\\\\Jobs\\\\Monday\\\\'))
            ->pluck('id');

        return $ids->isEmpty() ? 0 : $connection->table($table)->whereIn('id', $ids)->delete();
    }
}
