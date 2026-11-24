<?php

namespace App\Console\Commands;

use App\Jobs\BackfillHubspotAssociationPage;
use App\Models\CrmObjectAssociationBackfill;
use App\Services\HubspotMigrationScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class HubspotAssociationBackfill extends Command
{
    protected $signature = 'crm:hubspot-backfill-associations
        {--queue : Enqueue the supported directed object pairs}
        {--status : Show association backfill progress}
        {--write : Create local queue entries and dispatch jobs}';

    protected $description = 'Backfills HubSpot association IDs, labels, and type metadata into the local CRM mirror.';

    public function handle(): int
    {
        if ($this->option('status')) {
            return $this->showStatus();
        }

        if (! $this->option('queue') || ! $this->option('write')) {
            $this->error('Usa --queue --write para iniciar la copia local de asociaciones.');
            return self::INVALID;
        }

        foreach (['crm_object_mirrors', 'crm_object_mirror_backfills', 'crm_object_association_backfills'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Falta la tabla {$table}; aplica las migraciones del espejo CRM primero.");
                return self::FAILURE;
            }
        }

        $incomplete = collect(HubspotMigrationScope::OBJECT_TYPES)->first(function (string $type): bool {
            foreach ([false, true] as $archived) {
                $complete = \App\Models\CrmObjectMirrorBackfill::query()
                    ->where('provider', 'hubspot')->where('object_type', $type)
                    ->where('archived', $archived)->where('status', 'completed')->exists();
                if (! $complete) return true;
            }
            return false;
        });
        if ($incomplete !== null) {
            $this->error("Primero completa la carga de objetos activos y archivados; aún falta {$incomplete}.");
            return self::FAILURE;
        }

        $queued = 0;
        foreach (HubspotMigrationScope::ASSOCIATION_PAIRS as [$from, $to]) {
            $backfill = CrmObjectAssociationBackfill::query()->firstOrCreate([
                'provider' => 'hubspot', 'from_object_type' => $from, 'to_object_type' => $to,
            ]);
            if (in_array($backfill->status, ['completed', 'unsupported', 'running'], true)) continue;
            $backfill->update(['status' => 'queued', 'last_error_class' => null]);
            BackfillHubspotAssociationPage::dispatch($backfill->id);
            $queued++;
        }

        $this->info("Pares de asociaciones puestos en crm-sync: {$queued}.");
        $this->comment('La cola solo lee HubSpot y escribe el espejo local; usa el limitador global conservador.');
        $this->comment('La lectura usa Associations API 2026-09 con el limitador global.');

        return self::SUCCESS;
    }

    private function showStatus(): int
    {
        if (! Schema::hasTable('crm_object_association_backfills')) {
            $this->error('Falta crm_object_association_backfills; aplica las migraciones de CRM primero.');
            return self::FAILURE;
        }
        $rows = CrmObjectAssociationBackfill::query()->orderBy('from_object_type')->orderBy('to_object_type')->get();
        $this->info('Backfill de asociaciones HubSpot: '.$rows->count().' pares.');
        $this->table(['Desde', 'Hacia', 'Estado', 'Registros', 'Relaciones', 'Fallo'], $rows->map(fn ($row) => [
            $row->from_object_type,
            $row->to_object_type,
            $row->status,
            $row->records_read,
            $row->associations_read,
            $row->last_error_class ?? '—',
        ])->all());
        return self::SUCCESS;
    }
}
