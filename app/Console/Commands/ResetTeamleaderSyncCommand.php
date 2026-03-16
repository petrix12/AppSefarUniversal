<?php
// app/Console/Commands/ResetTeamleaderSyncCommand.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ResetTeamleaderSyncCommand extends Command
{
    protected $signature   = 'teamleader:reset
                                {--force : Sin pedir confirmación}
                                {--only-db : Solo truncar BD, no tocar S3}
                                {--only-s3 : Solo borrar S3, no tocar BD}';

    protected $description = 'Borra todo lo sincronizado de Teamleader (S3 + BD) y limpia la cola';

    // Tablas a truncar en orden (respetar foreign keys)
    private array $tables = [
        'tl_sync_logs',
        'tl_documents',
        'tl_credit_notes',
        'tl_invoices',
        'tl_projects',
        'tl_deals',
        'tl_companies',
        'tl_contacts',
    ];

    public function handle(): int
    {
        $onlyDb = $this->option('only-db');
        $onlyS3 = $this->option('only-s3');

        // ── Confirmación ──────────────────────────────
        if (!$this->option('force')) {
            $this->warn('⚠️  ATENCIÓN: Esta operación es IRREVERSIBLE.');
            $this->newLine();

            if (!$onlyS3) {
                $this->line('  🗄️  Se truncarán las tablas: ' . implode(', ', $this->tables));
            }
            if (!$onlyDb) {
                $this->line('  ☁️  Se borrará la carpeta <comment>teamleader/</comment> en S3');
            }
            $this->line('  📋 Se limpiarán los jobs fallidos de la cola');
            $this->newLine();

            if (!$this->confirm('¿Continuar?', false)) {
                $this->info('Cancelado.');
                return self::SUCCESS;
            }
        }

        // ── Borrar S3 ─────────────────────────────────
        if (!$onlyDb) {
            $this->borrarS3();
        }

        // ── Truncar BD ────────────────────────────────
        if (!$onlyS3) {
            $this->truncarBD();
        }

        // ── Limpiar cola ──────────────────────────────
        $this->limpiarCola();

        $this->newLine();
        $this->info('✅ Reset completado. Puedes volver a correr: php artisan teamleader:sync --entity=all');

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────
    // BORRAR S3
    // ─────────────────────────────────────────────────

    private function borrarS3(): void
    {
        $this->info('☁️  Borrando archivos de S3...');

        try {
            $disk = Storage::disk('s3');

            // Listar todo dentro de teamleader/
            $folders = ['teamleader/'];

            foreach ($folders as $folder) {
                $archivos = $disk->allFiles($folder);

                if (empty($archivos)) {
                    $this->line("  <comment>Sin archivos en {$folder}</comment>");
                    continue;
                }

                $this->line("  Borrando " . count($archivos) . " archivos en {$folder}...");

                // Borrar en lotes de 100 (límite de S3)
                foreach (array_chunk($archivos, 100) as $lote) {
                    $disk->delete($lote);
                }

                $this->line("  ✅ {$folder} limpiada");
            }

        } catch (\Exception $e) {
            $this->error('  ❌ Error al borrar S3: ' . $e->getMessage());
            $this->warn('  Continuando con el resto del reset...');
        }
    }

    // ─────────────────────────────────────────────────
    // TRUNCAR BD
    // ─────────────────────────────────────────────────

    private function truncarBD(): void
    {
        $this->info('🗄️  Truncando tablas de BD...');

        // Desactivar foreign key checks temporalmente
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($this->tables as $table) {
            try {
                // Verificar que la tabla existe antes de truncar
                if (!$this->tablaExiste($table)) {
                    $this->line("  <comment>Tabla {$table} no existe, skip</comment>");
                    continue;
                }

                $count = DB::table($table)->count();
                DB::table($table)->truncate();
                $this->line("  ✅ {$table} — {$count} registros borrados");

            } catch (\Exception $e) {
                $this->error("  ❌ Error truncando {$table}: " . $e->getMessage());
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    // ─────────────────────────────────────────────────
    // LIMPIAR COLA
    // ─────────────────────────────────────────────────

    private function limpiarCola(): void
    {
        $this->info('📋 Limpiando cola de jobs...');

        try {
            // Jobs pendientes en las colas de teamleader
            $pendientes = DB::table('jobs')
                ->where(function ($q) {
                    $q->where('queue', 'teamleader-sync')
                      ->orWhere('queue', 'teamleader-documents');
                })
                ->count();

            DB::table('jobs')
                ->where(function ($q) {
                    $q->where('queue', 'teamleader-sync')
                      ->orWhere('queue', 'teamleader-documents');
                })
                ->delete();

            $this->line("  ✅ {$pendientes} jobs pendientes eliminados");

            // Jobs fallidos
            $fallidos = 0;
            if ($this->tablaExiste('failed_jobs')) {
                $fallidos = DB::table('failed_jobs')->count();
                DB::table('failed_jobs')->truncate();
                $this->line("  ✅ {$fallidos} jobs fallidos eliminados");
            }

        } catch (\Exception $e) {
            $this->error('  ❌ Error limpiando cola: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────
    // HELPER
    // ─────────────────────────────────────────────────

    private function tablaExiste(string $tabla): bool
    {
        return DB::getSchemaBuilder()->hasTable($tabla);
    }
}
