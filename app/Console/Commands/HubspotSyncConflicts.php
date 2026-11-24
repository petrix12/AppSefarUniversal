<?php

namespace App\Console\Commands;

use App\Models\CrmSyncConflict;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class HubspotSyncConflicts extends Command
{
    protected $signature = 'crm:hubspot-sync-conflicts
        {--limit=100 : Maximum conflicts to display}
        {--show-values : Decrypt and print the local and HubSpot values}';

    protected $description = 'Lists HubSpot-precedence field conflicts recorded for Systems review.';

    public function handle(): int
    {
        if (! Schema::hasTable('crm_sync_conflicts')) {
            $this->error('Aplica la migración crm_sync_conflicts antes de consultar conflictos.');
            return self::FAILURE;
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        $conflicts = CrmSyncConflict::query()->where('status', 'pending')->orderBy('id')->limit($limit)->get();
        $this->info('Conflictos HubSpot pendientes: '.$conflicts->count());
        $headers = ['ID', 'Entidad local', 'ID local', 'Objeto HubSpot', 'ID HubSpot', 'Campo'];
        if ($this->option('show-values')) $headers[] = 'Valores (local / HubSpot)';

        $rows = $conflicts->map(function (CrmSyncConflict $conflict): array {
            $row = [
                $conflict->id,
                $conflict->entity_type,
                $conflict->entity_id,
                $conflict->external_object_type,
                $conflict->external_id,
                $conflict->field_name,
            ];
            if ($this->option('show-values')) {
                $row[] = json_encode($conflict->values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            return $row;
        })->all();

        $this->table($headers, $rows);
        if (! $this->option('show-values')) {
            $this->comment('Los valores están cifrados. Añade --show-values solo desde una sesión de Sistemas autorizada.');
        }

        return self::SUCCESS;
    }
}
