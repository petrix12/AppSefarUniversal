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

    public function handle(): int
    {
        if (! Schema::hasTable('crm_object_mirrors')) {
            $this->error('Falta crm_object_mirrors; aplica las migraciones de CRM primero.');
            return self::FAILURE;
        }

        if (! $this->option('write')) {
            $this->comment('Solo lectura: agrega --write para completar la copia local. No se llama a HubSpot.');
        }

        foreach (HubspotMigrationScope::OBJECT_TYPES as $type) {
            $table = HubspotObjectRecordStore::tableFor($type);
            if ($table === null || ! Schema::hasTable($table)) {
                $this->error("Falta la tabla específica para {$type}; aplica las migraciones de CRM primero.");
                return self::FAILURE;
            }

            $query = DB::table('crm_object_mirrors')->where('provider', 'hubspot')->where('object_type', $type);
            $sourceCount = (clone $query)->count();
            if ($this->option('write') && $sourceCount > 0) {
                $query->orderBy('id')->chunkById(500, function ($rows) use ($table): void {
                    $records = $rows->map(fn ($row) => [
                        'provider' => $row->provider,
                        'external_id' => $row->external_id,
                        'properties' => $row->properties,
                        'archived' => (bool) $row->archived,
                        'remote_created_at' => $row->remote_created_at,
                        'remote_updated_at' => $row->remote_updated_at,
                        'remote_archived_at' => $row->remote_archived_at,
                        'content_hash' => $row->content_hash,
                        'synced_at' => $row->synced_at,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ])->all();

                    if ($records !== []) {
                        DB::table($table)->upsert($records, ['provider', 'external_id'], [
                            'properties', 'archived', 'remote_created_at', 'remote_updated_at',
                            'remote_archived_at', 'content_hash', 'synced_at', 'updated_at',
                        ]);
                    }
                });
            }

            $tableCount = DB::table($table)->where('provider', 'hubspot')->count();
            $this->line("{$type}: origen {$sourceCount} · tabla propia {$tableCount}".($this->option('write') ? ' · sincronizada' : ''));
        }

        return self::SUCCESS;
    }
}
