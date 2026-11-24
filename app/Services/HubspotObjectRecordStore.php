<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Provides a type-specific storage table for each HubSpot CRM object. */
class HubspotObjectRecordStore
{
    public static function tableFor(string $objectType): ?string
    {
        if (! in_array($objectType, HubspotMigrationScope::OBJECT_TYPES, true)) {
            return null;
        }

        return 'crm_hubspot_'.$objectType;
    }

    public function query(string $objectType): Builder
    {
        $table = self::tableFor($objectType);
        if ($table === null || ! Schema::hasTable($table)) {
            throw new \InvalidArgumentException('No existe una tabla de almacenamiento para ese tipo HubSpot.');
        }

        return DB::table($table);
    }

    public function find(string $provider, string $objectType, string $externalId): ?object
    {
        $table = self::tableFor($objectType);
        if ($table === null) {
            return null;
        }

        $identity = ['provider' => $provider, 'external_id' => $externalId];
        $record = Schema::hasTable($table) ? DB::table($table)->where($identity)->first() : null;
        if ($record === null && Schema::hasTable('crm_object_mirrors')) {
            $record = DB::table('crm_object_mirrors')->where($identity)->where('object_type', $objectType)->first();
        }
        if ($record && is_string($record->properties ?? null)) {
            $record->properties = json_decode($record->properties, true) ?? [];
        }

        return $record;
    }

    public function missingFromDedicatedTable(string $objectType): Builder
    {
        $destination = $this->query($objectType);
        $table = $destination->from;
        $destination->selectRaw('1')->whereColumn([
            ["{$table}.provider", 'mirror.provider'],
            ["{$table}.external_id", 'mirror.external_id'],
        ]);

        return DB::table('crm_object_mirrors as mirror')
            ->where('mirror.provider', 'hubspot')->where('mirror.object_type', $objectType)
            ->whereNotExists($destination);
    }

    public function store(
        string $provider,
        string $objectType,
        string $externalId,
        array $properties,
        bool $archived,
        mixed $remoteCreatedAt,
        mixed $remoteUpdatedAt,
        mixed $remoteArchivedAt,
        string $contentHash,
        mixed $syncedAt = null,
    ): bool {
        $table = self::tableFor($objectType);
        // For MySQL, the CREATE and unique index are separate DDL statements.
        if ($table === null || ! Schema::hasIndex($table, ['provider', 'external_id'], 'unique')) {
            return false;
        }

        $now = now();
        DB::table($table)->upsert([[
            'provider' => $provider,
            'external_id' => $externalId,
            'properties' => json_encode($properties, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'archived' => $archived,
            'remote_created_at' => $remoteCreatedAt,
            'remote_updated_at' => $remoteUpdatedAt,
            'remote_archived_at' => $remoteArchivedAt,
            'content_hash' => $contentHash,
            'synced_at' => $syncedAt ?? $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['provider', 'external_id'], [
            'properties', 'archived', 'remote_created_at', 'remote_updated_at',
            'remote_archived_at', 'content_hash', 'synced_at', 'updated_at',
        ]);

        return true;
    }
}
