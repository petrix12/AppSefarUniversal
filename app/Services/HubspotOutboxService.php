<?php

namespace App\Services;

use App\Jobs\SyncHubspotOutbox;
use App\Models\IntegrationOutbox;
use App\Models\Lead;
use App\Models\Negocio;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class HubspotOutboxService
{
    public function queue(Model $model, array $fields): void
    {
        if (! config('crm_sync.hubspot.outbound_enabled')
            || ! Schema::hasTable('integration_outbox')
            || HubspotSyncOrigin::isRemoteWrite()) return;

        [$entityType, $externalType] = match (true) {
            $model instanceof User && $model->hasRole('Cliente') => ['client', 'contacts'],
            $model instanceof Lead && ! $model->converted_at => ['lead', 'contacts'],
            $model instanceof Negocio => ['deal', 'deals'],
            default => [null, null],
        };
        if (! $entityType) return;

        $fields = array_values(array_unique(array_filter($fields, 'is_string')));
        $dedupeKey = 'hubspot:'.sha1($entityType.':'.$model->getKey());

        DB::transaction(function () use ($model, $entityType, $externalType, $fields, $dedupeKey): void {
            $entry = IntegrationOutbox::query()->where('dedupe_key', $dedupeKey)->lockForUpdate()->first();
            $fields = array_values(array_unique(array_merge((array) data_get($entry?->payload, 'fields', []), $fields)));
            $idColumn = match ($entityType) {
                'client' => 'hs_id',
                'lead' => 'hubspot_id',
                'deal' => 'hubspot_id',
            };
            $createdInApp = (bool) data_get($entry?->payload, 'created_in_app')
                || ($model->wasRecentlyCreated && ! filled($model->getAttribute($idColumn)));
            $entry = IntegrationOutbox::query()->updateOrCreate(['dedupe_key' => $dedupeKey], [
                'provider' => 'hubspot',
                'entity_type' => $entityType,
                'entity_id' => (int) $model->getKey(),
                'operation' => 'upsert_crm_object',
                'payload' => [
                    'external_entity_type' => $externalType,
                    'fields' => $fields,
                    'generation' => (string) Str::uuid(),
                    'created_in_app' => $createdInApp,
                ],
                'status' => 'pending',
                'attempts' => 0,
                'available_at' => now(),
                'processed_at' => null,
                'last_error' => null,
            ]);

            SyncHubspotOutbox::dispatch($entry->id)->afterCommit();
        });
    }
}
