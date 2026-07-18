<?php

namespace App\Console\Commands;

use App\Jobs\Teamleader\SyncCompaniesJob;
use App\Jobs\Teamleader\SyncContactsJob;
use App\Jobs\Teamleader\SyncCreditNotesJob;
use App\Jobs\Teamleader\SyncDealsJob;
use App\Jobs\Teamleader\SyncInvoicesJob;
use App\Jobs\Teamleader\SyncProjectsJob;
use App\Models\TlSyncLog;
use Illuminate\Console\Command;

class SyncTeamleaderCommand extends Command
{
    protected $signature = 'teamleader:sync
                            {--entity=all : contacts|companies|deals|projects|invoices|credit_notes|all}
                            {--no-pdfs : No descargar PDFs de facturas}
                            {--with-pdfs : Descargar PDFs de facturas}
                            {--no-docs : No revisar/descargar documentos vinculados a entidades}
                            {--with-docs : Revisar/descargar documentos vinculados a entidades}
                            {--force : Despachar aunque exista un sync activo reciente}';

    protected $description = 'Sincroniza Teamleader con la base local y S3, creando nuevos registros y actualizando existentes.';

    private array $entityMap = [
        'contacts' => SyncContactsJob::class,
        'companies' => SyncCompaniesJob::class,
        'deals' => SyncDealsJob::class,
        'projects' => SyncProjectsJob::class,
        'invoices' => SyncInvoicesJob::class,
        'credit_notes' => SyncCreditNotesJob::class,
    ];

    public function handle(): int
    {
        $entity = (string) $this->option('entity');
        $downloadPdfs = (bool) $this->option('with-pdfs') && ! (bool) $this->option('no-pdfs');
        $syncDocuments = ((bool) config('services.teamleader.sync_documents', false) || (bool) $this->option('with-docs'))
            && ! (bool) $this->option('no-docs');
        $force = (bool) $this->option('force');

        $entities = $entity === 'all'
            ? array_keys($this->entityMap)
            : [$entity];

        foreach ($entities as $e) {
            if (!isset($this->entityMap[$e])) {
                $this->error("Entidad desconocida: {$e}");
                $this->line('Opciones: ' . implode(', ', array_keys($this->entityMap)) . ', all');
                return self::FAILURE;
            }
        }

        $this->info('Iniciando sync de Teamleader...');
        $this->newLine();

        $dispatched = 0;

        foreach ($entities as $e) {
            if (!$force && $this->hasRecentRunningSync($e)) {
                $this->warn("  {$e} omitido: ya hay un sync activo reciente. Usa --force para forzarlo.");
                continue;
            }

            $log = TlSyncLog::start($e);
            $job = $this->makeJob($e, $log->id, $downloadPdfs, $syncDocuments);

            dispatch($job)->onQueue('teamleader-sync');
            $dispatched++;

            $this->line("  {$e}: job despachado (Log ID: {$log->id})");
        }

        if ($dispatched === 0) {
            $this->warn('No se despacho ningun job nuevo.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Workers necesarios:');
        $this->line('  php artisan queue:work --queue=teamleader-sync --timeout=600');
        $this->line('  php artisan queue:work --queue=teamleader-documents --timeout=600');
        $this->newLine();
        $this->line('Ver progreso: php artisan teamleader:status');

        return self::SUCCESS;
    }

    private function makeJob(string $entity, int $syncLogId, bool $downloadPdfs, bool $syncDocuments): object
    {
        return match ($entity) {
            'contacts' => new SyncContactsJob($syncLogId, $syncDocuments),
            'companies' => new SyncCompaniesJob($syncLogId, $syncDocuments),
            'deals' => new SyncDealsJob($syncLogId, $syncDocuments),
            'projects' => new SyncProjectsJob($syncLogId, $syncDocuments),
            'invoices' => new SyncInvoicesJob($syncLogId, $downloadPdfs),
            'credit_notes' => new SyncCreditNotesJob($syncLogId),
        };
    }

    private function hasRecentRunningSync(string $entity): bool
    {
        return TlSyncLog::query()
            ->where('entity', $entity)
            ->where('status', 'running')
            ->where('started_at', '>=', now()->subHours(12))
            ->exists();
    }
}
