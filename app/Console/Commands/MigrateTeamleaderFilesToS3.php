<?php

namespace App\Console\Commands;

use App\Jobs\Teamleader\SyncDocumentsJob;
use App\Models\TlCompany;
use App\Models\TlContact;
use App\Models\TlDeal;
use App\Models\TlProject;
use App\Models\TlSyncLog;
use App\Services\TeamleaderService;
use Illuminate\Console\Command;

class MigrateTeamleaderFilesToS3 extends Command
{
    protected $signature = 'teamleader:migrate-files-to-s3
                            {--entity=all : contacts|companies|deals|projects|all}
                            {--limit=0 : Limite de entidades a procesar, 0 para todas}
                            {--sync : Ejecutar ahora en este proceso, sin cola}
                            {--queue=teamleader-documents : Cola donde se despachan los jobs}';

    protected $description = 'Migra a S3 todos los archivos de Teamleader asociados a entidades locales.';

    public function handle(TeamleaderService $teamleader): int
    {
        $entity = (string) $this->option('entity');
        $limit = max(0, (int) $this->option('limit'));
        $sync = (bool) $this->option('sync');
        $queue = (string) $this->option('queue');

        if (!in_array($entity, ['contacts', 'companies', 'deals', 'projects', 'all'], true)) {
            $this->error('Entidad invalida. Usa contacts, companies, deals, projects o all.');
            return self::FAILURE;
        }

        $targets = collect();

        if (in_array($entity, ['contacts', 'all'], true)) {
            $targets = $targets->merge(
                TlContact::query()
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn (string $id) => ['type' => 'contact', 'id' => $id])
            );
        }

        if (in_array($entity, ['companies', 'all'], true)) {
            $targets = $targets->merge(
                TlCompany::query()
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn (string $id) => ['type' => 'company', 'id' => $id])
            );
        }

        if (in_array($entity, ['deals', 'all'], true)) {
            $targets = $targets->merge(
                TlDeal::query()
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn (string $id) => ['type' => 'deal', 'id' => $id])
            );
        }

        if (in_array($entity, ['projects', 'all'], true)) {
            $targets = $targets->merge(
                TlProject::query()
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn (string $id) => ['type' => 'project', 'id' => $id])
            );
        }

        if ($limit > 0) {
            $targets = $targets->take($limit);
        }

        if ($targets->isEmpty()) {
            $this->warn('No hay entidades locales de Teamleader para procesar.');
            return self::SUCCESS;
        }

        $log = TlSyncLog::start('documents');
        $log->update(['total' => $targets->count()]);

        $this->info("Entidades a procesar: {$targets->count()}");

        foreach ($targets as $index => $target) {
            $job = new SyncDocumentsJob(
                $target['type'],
                $target['id'],
                1,
                $log->id,
                100,
                true,
                $sync
            );

            if ($sync) {
                $this->line(($index + 1) . "/{$targets->count()} {$target['type']}:{$target['id']}");
                $job->handle($teamleader);
                continue;
            }

            dispatch($job)->onQueue($queue);
        }

        if ($sync) {
            $log->refresh();
            $this->info("Migracion terminada. Procesadas: {$log->processed}. Fallidas: {$log->failed}.");
            return $log->failed > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info("Jobs despachados en la cola {$queue}.");
        $this->line("Ejecuta: php artisan queue:work --queue={$queue} --timeout=600");

        return self::SUCCESS;
    }
}
