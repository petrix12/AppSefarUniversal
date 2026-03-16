<?php
// app/Console/Commands/SyncTeamleaderCommand.php

namespace App\Console\Commands;

use App\Jobs\Teamleader\SyncContactsJob;
use App\Jobs\Teamleader\SyncCompaniesJob;
use App\Jobs\Teamleader\SyncDealsJob;
use App\Jobs\Teamleader\SyncProjectsJob;
use App\Jobs\Teamleader\SyncInvoicesJob;
use App\Models\TlSyncLog;
use Illuminate\Console\Command;

class SyncTeamleaderCommand extends Command
{
    protected $signature = 'teamleader:sync
                            {--entity=all : contacts|companies|deals|projects|invoices|all}
                            {--no-pdfs    : No descargar PDFs de facturas}
                            {--no-docs    : No descargar documentos}';

    protected $description = 'Migración inicial de Teamleader → BD local + S3';

    private array $entityMap = [
        'contacts'  => SyncContactsJob::class,
        'companies' => SyncCompaniesJob::class,
        'deals'     => SyncDealsJob::class,
        'projects'  => SyncProjectsJob::class,
        'invoices'  => SyncInvoicesJob::class,
    ];

    public function handle(): void
    {
        $entity   = $this->option('entity');
        $noPdfs   = $this->option('no-pdfs');
        $noDocs   = $this->option('no-docs');

        $entities = $entity === 'all'
            ? array_keys($this->entityMap)
            : [$entity];

        // Validar entidad
        foreach ($entities as $e) {
            if (!isset($this->entityMap[$e])) {
                $this->error("❌ Entidad desconocida: {$e}");
                $this->line("Opciones: " . implode(', ', array_keys($this->entityMap)) . ", all");
                return;
            }
        }

        $this->info("🚀 Iniciando sync de Teamleader...");
        $this->newLine();

        foreach ($entities as $e) {
            // Crear log de sync
            $log = TlSyncLog::start($e);

            // Dispatch primer Job (encadena el resto automáticamente)
            $jobClass = $this->entityMap[$e];

            $job = match($e) {
                'invoices' => new $jobClass(1, 100, $log->id, !$noPdfs),
                default    => new $jobClass(1, 100, $log->id),
            };

            dispatch($job)->onQueue('teamleader-sync');

            $this->line("  ✅ <info>{$e}</info> — Job despachado (Log ID: {$log->id})");
        }

        $this->newLine();
        $this->info("📋 Jobs en cola. Ejecuta el worker:");
        $this->newLine();

        // Dos workers: uno para entidades, otro para documentos
        $this->line("  <comment># Worker principal (entidades)</comment>");
        $this->line("  php artisan queue:work --queue=teamleader-sync --timeout=600");
        $this->newLine();
        $this->line("  <comment># Worker de documentos (en otra terminal)</comment>");
        $this->line("  php artisan queue:work --queue=teamleader-documents --timeout=600");
        $this->newLine();

        $this->info("📊 Ver progreso:");
        $this->line("  php artisan teamleader:status");
    }
}
