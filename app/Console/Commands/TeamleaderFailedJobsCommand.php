<?php
// app/Console/Commands/TeamleaderFailedJobsCommand.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TeamleaderFailedJobsCommand extends Command
{
    protected $signature   = 'teamleader:failed
                                {--id= : Ver detalle de un job específico}
                                {--retry : Reintentar todos los fallidos de teamleader}
                                {--clear : Borrar todos los fallidos de teamleader}';

    protected $description = 'Ver y gestionar jobs fallidos de Teamleader';

    public function handle(): int
    {
        // ── Ver detalle de uno ────────────────────────
        if ($id = $this->option('id')) {
            return $this->verDetalle($id);
        }

        // ── Reintentar todos ──────────────────────────
        if ($this->option('retry')) {
            return $this->reintentarTodos();
        }

        // ── Borrar todos ──────────────────────────────
        if ($this->option('clear')) {
            return $this->borrarTodos();
        }

        // ── Listar todos (default) ────────────────────
        return $this->listarTodos();
    }

    // ─────────────────────────────────────────────────
    // LISTAR
    // ─────────────────────────────────────────────────

    private function listarTodos(): int
    {
        $failed = DB::table('failed_jobs')
            ->where(function ($q) {
                $q->where('queue', 'teamleader-sync')
                  ->orWhere('queue', 'teamleader-documents');
            })
            ->orderByDesc('failed_at')
            ->get();

        if ($failed->isEmpty()) {
            $this->info('✅ No hay jobs fallidos de Teamleader.');
            return self::SUCCESS;
        }

        $this->error("❌ {$failed->count()} jobs fallidos encontrados:");
        $this->newLine();

        $rows = $failed->map(function ($job) {
            $payload    = json_decode($job->payload, true);
            $exception  = $this->extractException($job->exception);
            $jobClass   = class_basename($payload['displayName'] ?? 'Desconocido');

            return [
                $job->id,
                $job->queue,
                $jobClass,
                $this->extractJobArgs($payload),
                $exception['message'],
                $job->failed_at,
            ];
        });

        $this->table(
            ['ID', 'Cola', 'Job', 'Args', 'Error', 'Fallido en'],
            $rows
        );

        $this->newLine();
        $this->line('Ver detalle:  <comment>php artisan teamleader:failed --id=X</comment>');
        $this->line('Reintentar:   <comment>php artisan teamleader:failed --retry</comment>');
        $this->line('Borrar todos: <comment>php artisan teamleader:failed --clear</comment>');

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────
    // DETALLE DE UNO
    // ─────────────────────────────────────────────────

    private function verDetalle(string $id): int
    {
        $job = DB::table('failed_jobs')->find($id);

        if (!$job) {
            $this->error("No se encontró el job con ID: {$id}");
            return self::FAILURE;
        }

        $payload   = json_decode($job->payload, true);
        $exception = $this->extractException($job->exception);

        $this->info("═══════════════════════════════════════");
        $this->info("  Job #{$job->id}");
        $this->info("═══════════════════════════════════════");
        $this->newLine();

        // Info básica
        $this->table([], [
            ['Cola',      $job->queue],
            ['Job',       $payload['displayName'] ?? 'Desconocido'],
            ['Fallido en', $job->failed_at],
            ['Intentos',  $payload['attempts'] ?? 'N/A'],
        ]);

        // Argumentos del Job
        $this->newLine();
        $this->comment('── Argumentos del Job ──────────────────');
        $command = unserialize($payload['data']['command'] ?? '');
        if ($command) {
            foreach ((array) $command as $key => $value) {
                if (str_starts_with(ltrim($key), '*')) {
                    $cleanKey = trim(ltrim($key), "\x00*\x00");
                    if (!in_array($cleanKey, ['job', 'queue', 'chainQueue', 'chainConnection', 'delay', 'middleware', 'chained', 'failOnTimeout', 'backoff', 'retryUntil'])) {
                        $this->line("  <info>{$cleanKey}</info>: " . (is_array($value) ? json_encode($value) : $value));
                    }
                }
            }
        }

        // Error completo
        $this->newLine();
        $this->comment('── Error ────────────────────────────────');
        $this->error($exception['message']);

        // Stack trace
        $this->newLine();
        $this->comment('── Stack Trace (primeras 10 líneas) ────');
        $lines = explode("\n", $exception['trace']);
        foreach (array_slice($lines, 0, 10) as $line) {
            $this->line("  <comment>{$line}</comment>");
        }

        $this->newLine();
        $this->line("Reintentar este job: <comment>php artisan queue:retry {$id}</comment>");
        $this->line("Borrar este job:     <comment>php artisan queue:forget {$id}</comment>");

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────
    // REINTENTAR TODOS
    // ─────────────────────────────────────────────────

    private function reintentarTodos(): int
    {
        $ids = DB::table('failed_jobs')
            ->where(function ($q) {
                $q->where('queue', 'teamleader-sync')
                  ->orWhere('queue', 'teamleader-documents');
            })
            ->pluck('uuid');

        if ($ids->isEmpty()) {
            $this->info('No hay jobs fallidos para reintentar.');
            return self::SUCCESS;
        }

        $this->info("🔄 Reintentando {$ids->count()} jobs...");

        foreach ($ids as $uuid) {
            $this->call('queue:retry', ['id' => [$uuid]]);
        }

        $this->info('✅ Jobs puestos de nuevo en cola.');
        $this->line('Recuerda correr el worker: <comment>php artisan queue:work --queue=teamleader-sync --timeout=600</comment>');

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────
    // BORRAR TODOS
    // ─────────────────────────────────────────────────

    private function borrarTodos(): int
    {
        $count = DB::table('failed_jobs')
            ->where(function ($q) {
                $q->where('queue', 'teamleader-sync')
                  ->orWhere('queue', 'teamleader-documents');
            })
            ->count();

        if (!$this->confirm("¿Borrar {$count} jobs fallidos?", false)) {
            $this->info('Cancelado.');
            return self::SUCCESS;
        }

        DB::table('failed_jobs')
            ->where(function ($q) {
                $q->where('queue', 'teamleader-sync')
                  ->orWhere('queue', 'teamleader-documents');
            })
            ->delete();

        $this->info("✅ {$count} jobs fallidos borrados.");

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────

    private function extractException(string $exceptionText): array
    {
        $lines   = explode("\n", $exceptionText);
        $message = $lines[0] ?? 'Sin mensaje';
        $trace   = implode("\n", array_slice($lines, 1));

        return [
            'message' => $message,
            'trace'   => $trace,
        ];
    }

    private function extractJobArgs(array $payload): string
    {
        try {
            $command = unserialize($payload['data']['command'] ?? '');
            if (!$command) return 'N/A';

            $args = [];
            foreach ((array) $command as $key => $value) {
                $cleanKey = trim(ltrim($key), "\x00*\x00");
                if (in_array($cleanKey, ['page', 'pageSize', 'entityType', 'entityId'])) {
                    $args[] = "{$cleanKey}={$value}";
                }
            }

            return implode(', ', $args) ?: 'N/A';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }
}
