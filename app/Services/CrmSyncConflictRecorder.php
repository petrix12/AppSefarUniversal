<?php

namespace App\Services;

use App\Models\CrmSyncConflict;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class CrmSyncConflictRecorder
{
    public function recordHubspotPrecedence(
        string $entityType,
        Model $model,
        string $externalObjectType,
        string $externalId,
        array $localProperties,
        ?array $baselineProperties,
        array $remoteProperties,
        mixed $remoteUpdatedAt,
    ): array {
        $conflicts = [];
        $hubspotWins = [];
        foreach ($localProperties as $field => $localValue) {
            if (! array_key_exists($field, $remoteProperties)) continue;
            $remoteValue = $remoteProperties[$field];
            $hasBaseline = $baselineProperties !== null && array_key_exists($field, $baselineProperties);
            $baselineValue = $hasBaseline ? $baselineProperties[$field] : null;
            $remoteChanged = ! $hasBaseline || $this->canonical($baselineValue) !== $this->canonical($remoteValue);
            if (! $remoteChanged) continue;

            // HubSpot wins every remotely changed field. Only record a true
            // conflict when both sides changed it since the saved baseline.
            $hubspotWins[] = (string) $field;
            if (! $hasBaseline
                || $this->canonical($localValue) === $this->canonical($baselineValue)
                || $this->canonical($localValue) === $this->canonical($remoteValue)) {
                continue;
            }

            $key = implode('|', [
                'hubspot', $entityType, (string) $model->getKey(), $externalObjectType,
                $externalId, (string) $field, (string) $model->updated_at, (string) $remoteUpdatedAt,
            ]);
            $conflicts[] = [
                'provider' => 'hubspot',
                'entity_type' => $entityType,
                'entity_id' => $model->getKey(),
                'external_object_type' => $externalObjectType,
                'external_id' => $externalId,
                'field_name' => (string) $field,
                'values' => ['local' => $localValue, 'hubspot' => $remoteValue],
                'local_updated_at' => $model->updated_at,
                'remote_updated_at' => $remoteUpdatedAt,
                'conflict_key' => hash('sha256', $key),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($conflicts !== [] && ! Schema::hasTable('crm_sync_conflicts')) {
            throw new \RuntimeException('Aplica la migración crm_sync_conflicts antes de arbitrar cambios HubSpot.');
        }

        foreach ($conflicts as $conflict) {
            CrmSyncConflict::query()->firstOrCreate(
                ['conflict_key' => $conflict['conflict_key']],
                $conflict,
            );
        }

        return array_values(array_unique($hubspotWins));
    }

    private function canonical(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }
        return (string) $value;
    }
}
