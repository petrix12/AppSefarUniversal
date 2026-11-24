<?php

namespace App\Console\Commands;

use App\Services\HubspotMigrationScope;
use App\Services\HubspotObjectRecordStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HubspotSyncObjectTables extends Command
{
    protected $signature = 'crm:hubspot-sync-object-tables {--write : Copy existing local snapshots into their type-specific tables}';

    protected $description = 'Copies existing HubSpot mirror rows into the dedicated table for each object type.';

    public function handle(HubspotObjectRecordStore $store): int
    {
        if (! Schema::hasTable('crm_object_mirrors')) {
            $this->error('Falta crm_object_mirrors; aplica las migraciones de CRM primero.');
            return self::FAILURE;
        }

        if (! $this->option('write')) {
            $this->comment('Solo lectura: agrega --write para completar la copia local. No se llama a HubSpot.');
        }

        $tables = [];
        foreach (HubspotMigrationScope::OBJECT_TYPES as $type) {
            $table = HubspotObjectRecordStore::tableFor($type);
            if ($table === null || ! Schema::hasIndex($table, ['provider', 'external_id'], 'unique')) {
                $this->error("Falta la tabla o el índice único para {$type}. Aplica primero la migración de tablas de HubSpot.");
                return self::FAILURE;
            }
            $tables[$type] = $table;
        }

        foreach ($tables as $type => $table) {
            if ($this->option('write')) {
                $this->copySnapshots($type, $table);
            }

            $sourceCount = DB::table('crm_object_mirrors')->where('provider', 'hubspot')->where('object_type', $type)->count();
            $tableCount = DB::table($table)->where('provider', 'hubspot')->count();
            $missing = $store->missingFromDedicatedTable($type)->count();
            $this->line("{$type}: origen {$sourceCount} · tabla propia {$tableCount} · IDs faltantes {$missing}");
        }

        return self::SUCCESS;
    }

    private function copySnapshots(string $type, string $table): void
    {
        $columns = [
            'provider', 'external_id', 'properties', 'archived', 'remote_created_at', 'remote_updated_at',
            'remote_archived_at', 'content_hash', 'synced_at', 'created_at', 'updated_at',
        ];
        $updateColumns = array_values(array_diff($columns, ['provider', 'external_id', 'created_at']));
        $source = DB::table('crm_object_mirrors')->where('provider', 'hubspot')->where('object_type', $type);

        (clone $source)->select('id')->chunkById(100, function ($page) use ($source, $table, $columns, $updateColumns): void {
            DB::transaction(function () use ($page, $source, $table, $columns, $updateColumns): void {
                // Lock the source before copying so a parallel import cannot be overwritten by an older snapshot.
                $records = (clone $source)->whereIn('id', $page->pluck('id'))->orderBy('id')->lockForUpdate()
                    ->get($columns)->map(fn (object $row): array => (array) $row)->all();

                DB::table($table)->upsert($records, ['provider', 'external_id'], $updateColumns);
            }, 3);
        });
    }
}
