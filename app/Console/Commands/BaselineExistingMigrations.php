<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class BaselineExistingMigrations extends Command
{
    protected $signature = 'migrations:baseline-existing
        {--before= : Marca solo migraciones anteriores a este nombre/timestamp}
        {--up-to= : Marca migraciones hasta este nombre/timestamp, inclusive}
        {--only=* : Marca solo una o varias migraciones concretas}
        {--except=* : Omite una o varias migraciones concretas}
        {--force : Escribe en la tabla migrations. Sin esto solo muestra el plan}';

    protected $description = 'Marca migraciones existentes como ejecutadas sin correrlas, util para bases creadas manualmente.';

    public function handle(): int
    {
        $migrationNames = collect(File::glob(database_path('migrations/*.php')))
            ->map(fn (string $path) => basename($path, '.php'))
            ->sort()
            ->values();

        if ($migrationNames->isEmpty()) {
            $this->warn('No hay archivos de migracion.');
            return self::SUCCESS;
        }

        $only = collect($this->option('only'))->filter()->values();
        $except = collect($this->option('except'))->filter()->values();
        $before = $this->option('before');
        $upTo = $this->option('up-to');
        $force = (bool) $this->option('force');

        if ($only->isEmpty() && blank($before) && blank($upTo)) {
            $this->error('Indica --before, --up-to o --only para evitar marcar migraciones por accidente.');
            $this->line('Ejemplo seguro: php artisan migrations:baseline-existing --before=2026_05_18_120000_add_exclude_from_task_assignment_to_users_table');
            return self::FAILURE;
        }

        $hasMigrationTable = Schema::hasTable('migrations');
        $ran = $hasMigrationTable
            ? DB::table('migrations')->pluck('migration')->all()
            : [];

        $toMark = $migrationNames
            ->reject(fn (string $migration) => in_array($migration, $ran, true))
            ->when($only->isNotEmpty(), fn ($items) => $items->filter(fn (string $migration) => $only->contains($migration)))
            ->when(filled($before), fn ($items) => $items->filter(fn (string $migration) => strcmp($migration, (string) $before) < 0))
            ->when(filled($upTo), fn ($items) => $items->filter(fn (string $migration) => strcmp($migration, (string) $upTo) <= 0))
            ->reject(fn (string $migration) => $except->contains($migration))
            ->values();

        if ($toMark->isEmpty()) {
            $this->info('No hay migraciones pendientes para marcar con esos filtros.');
            return self::SUCCESS;
        }

        $this->info(($force ? 'Se marcaran' : 'Dry-run: se marcarian') . " {$toMark->count()} migracion(es) como ejecutadas:");
        $this->table(['Migracion'], $toMark->map(fn ($migration) => [$migration])->all());

        if (! $force) {
            $this->warn('No se escribio nada. Vuelve a correr con --force para aplicar.');
            return self::SUCCESS;
        }

        if (! $hasMigrationTable) {
            Schema::create('migrations', function ($table) {
                $table->id();
                $table->string('migration');
                $table->integer('batch');
            });
        }

        $batch = ((int) DB::table('migrations')->max('batch')) + 1;
        $nowRows = $toMark
            ->map(fn (string $migration) => [
                'migration' => $migration,
                'batch' => $batch,
            ])
            ->all();

        DB::table('migrations')->insert($nowRows);

        $this->info("Listo. {$toMark->count()} migracion(es) quedaron registradas en batch {$batch}.");
        $this->warn('Esto no modifica tablas de negocio; solo actualiza el historial de Laravel.');

        return self::SUCCESS;
    }
}
