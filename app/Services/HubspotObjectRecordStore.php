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
        if ($table === null || ! Schema::hasTable($table)) {
            return null;
        }

        $record = DB::table($table)->where('provider', $provider)->where('external_id', $externalId)->first();
        if ($record && is_string($record->properties ?? null)) {
            $record->properties = json_decode($record->properties, true) ?? [];
        }

        return $record;
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
        if ($table === null || ! Schema::hasTable($table)) {
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
