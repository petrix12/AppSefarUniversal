<?php

namespace App\Services;

use App\Models\ExternalEntityLink;
use App\Models\IntegrationOutbox;
use App\Models\CrmObjectMirror;
use App\Models\Lead;
use App\Models\Negocio;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/** Pushes allowlisted contact/lead/deal fields from the App through the CRM outbox. */
class HubspotOutboundSyncService
{
    public function __construct(private HubspotService $hubspot) {}

    public function process(int $outboxId): void
    {
        $lock = Cache::lock('crm.hubspot.outbox.'.$outboxId, 150);
        if (! $lock->get()) return;

        try {
            $entry = IntegrationOutbox::query()->find($outboxId);
            if (! $entry || $entry->status === 'sent' || $entry->status === 'cancelled') return;
            if (! config('crm_sync.hubspot.outbound_enabled')) {
                return;
            }

            $entry->update(['status' => 'processing', 'attempts' => $entry->attempts + 1]);
            $processingUpdatedAt = $entry->fresh()->updated_at;
            $processingGeneration = data_get($entry->payload, 'generation');
            try {
                [$model, $objectType, $idColumn, $properties, $search] = $this->prepare($entry);
                if ($properties !== []) {
                    $externalId = $this->externalId($entry, $model, $objectType, $idColumn);
                    if ($externalId === null) {
                        if (! $search['created_in_app']) {
                            throw new \RuntimeException('Falta el ID permanente de HubSpot; el registro requiere revisión antes de enlazarlo.');
                        }
                        if (! $search['app_id_property']) {
                            throw new \RuntimeException("Falta la propiedad única {$search['property']} en HubSpot; el registro nuevo no se puede crear con reintentos seguros.");
                        }
                        $found = $this->hubspot->searchWebhookObjects(
                            $objectType,
                            $search['property'],
                            $search['value'],
                            2,
                        );
                        if (count($found) > 1) {
                            throw new \RuntimeException('Identidad HubSpot duplicada; requiere revisión manual.');
                        }
                        $externalId = isset($found[0]['id']) ? (string) $found[0]['id'] : null;
                    }

                    if ($externalId !== null && $properties !== []) {
                        $properties = $this->applyHubspotConflictPolicy(
                            $entry,
                            $objectType,
                            $externalId,
                            $model,
                            $properties,
                        );
                    }

                    $creating = $externalId === null;
                    if ($creating) {
                        $properties[$search['property']] = $search['value'];
                    }

                    if ($properties !== []) {
                        $remote = $this->hubspot->writeWebhookObject($objectType, $externalId, $properties);
                        $externalId = (string) $remote['id'];
                        $this->storeLink($entry, $model, $objectType, $idColumn, $externalId, $remote['updatedAt'] ?? null);
                        $currentRemote = $this->hubspot->webhookObject($objectType, $externalId);
                        if ($currentRemote !== null) {
                            app(CrmObjectMirrorService::class)->storeRemoteObject($objectType, $externalId, $currentRemote);
                        }
                    }
                }

                DB::transaction(function () use ($entry, $processingUpdatedAt, $processingGeneration): void {
                    $current = IntegrationOutbox::query()->lockForUpdate()->findOrFail($entry->id);
                    if ($current->updated_at->gt($processingUpdatedAt)
                        || data_get($current->payload, 'generation') !== $processingGeneration) {
                        $current->update(['status' => 'pending', 'available_at' => now(), 'last_error' => null]);
                        \App\Jobs\SyncHubspotOutbox::dispatch($current->id)->afterCommit();
                        return;
                    }
                    $current->update(['status' => 'sent', 'processed_at' => now(), 'last_error' => null]);
                });
            } catch (\Throwable $exception) {
                $retryDelay = $exception instanceof \App\Exceptions\HubspotDailyBudgetExceeded
                    ? $exception->retryAfterSeconds
                    : 60;
                $entry->fresh()?->update([
                    'status' => 'pending',
                    'available_at' => now()->addSeconds($retryDelay),
                    'last_error' => $exception::class,
                ]);
                Log::warning('HubSpot outbound CRM sync pending.', [
                    'outbox_id' => $outboxId,
                    'entity_type' => $entry->entity_type,
                    'exception_type' => $exception::class,
                ]);
                throw $exception;
            }
        } finally {
            $lock->release();
        }
    }

    /** @return array{Model,string,string,array<string,mixed>,array{property:string,value:string,app_id_property:bool,created_in_app:bool}} */
    private function prepare(IntegrationOutbox $entry): array
    {
        $fields = (array) data_get($entry->payload, 'fields', []);
        $model = match ($entry->entity_type) {
            'client' => User::query()->findOrFail($entry->entity_id),
            'lead' => Lead::query()->findOrFail($entry->entity_id),
            'deal' => Negocio::query()->findOrFail($entry->entity_id),
            default => throw new \InvalidArgumentException('Unsupported HubSpot outbox entity.'),
        };

        if ($model instanceof User && ! $model->hasRole('Cliente')) {
            throw new \RuntimeException('Solo se sincronizan contactos con rol Cliente.');
        }
        if ($model instanceof Lead && $model->converted_at) {
            return [$model, 'contacts', 'hubspot_id', [], [
                'property' => 'sefar_app_contact_id', 'value' => (string) $model->getKey(),
                'app_id_property' => false, 'created_in_app' => false,
            ]];
        }

        $createdInApp = (bool) data_get($entry->payload, 'created_in_app', false);

        $objectType = $entry->entity_type === 'deal' ? 'deals' : 'contacts';
        $idColumn = $entry->entity_type === 'client' ? 'hs_id' : 'hubspot_id';
        $available = array_fill_keys($this->hubspot->webhookPropertyNames($objectType), true);

        if ($entry->entity_type === 'deal') {
            $excluded = ['id', 'user_id', 'created_at', 'updated_at', 'hs_id', 'hubspot_id', 'teamleader_id',
                'teamleader_comparisons', 'teamleader_match_confidence', 'teamleader_match_reason', 'teamleader_matched_at'];
            $properties = [];
            foreach ($model->getAttributes() as $key => $value) {
                if (in_array($key, $excluded, true) || ! isset($available[$key])) continue;
                if ($fields !== [] && ! in_array($key, $fields, true) && ! in_array($key, ['dealname'], true)) continue;
                $properties[$key] = $this->normalizeValue($value);
            }
            $appIdAvailable = isset($available['sefar_app_negocio_id']);
            $search = $appIdAvailable
                ? ['property' => 'sefar_app_negocio_id', 'value' => (string) $model->getKey(), 'app_id_property' => true]
                : ['property' => 'sefar_app_negocio_id', 'value' => (string) $model->getKey(), 'app_id_property' => false];
            if (isset($available['dealname']) && ($fields === [] || in_array('proyecto', $fields, true))) {
                $properties['dealname'] = $model->proyecto;
            }
            if (! filled($model->proyecto)) {
                throw new \RuntimeException('El negocio no tiene dealname; se dejó sin sincronizar.');
            }
            return [$model, $objectType, $idColumn, $properties, $search + ['created_in_app' => $createdInApp]];
        }

        $mapping = $entry->entity_type === 'client'
            ? ['email' => 'email', 'firstname' => 'nombres', 'lastname' => 'apellidos', 'phone' => 'phone',
                'numero_de_pasaporte' => 'passport', 'city' => 'city', 'address' => 'address']
            : ['email' => 'email', 'firstname' => 'nombre', 'lastname' => 'nombre', 'phone' => 'telefono',
                'numero_de_pasaporte' => 'passport'];
        $properties = [];
        foreach ($mapping as $property => $attribute) {
            if (! isset($available[$property]) || ! $attribute || ($fields !== [] && ! in_array($attribute, $fields, true))) continue;
            $value = $model->getAttribute($attribute);
            if ($property === 'firstname' && $model instanceof Lead) {
                [$value] = array_pad(preg_split('/\s+/', trim((string) $value), 2) ?: [], 2, '');
            }
            if ($property === 'lastname' && $model instanceof Lead) {
                $parts = preg_split('/\s+/', trim((string) $value), 2) ?: [];
                $value = $parts[1] ?? '';
            }
            if ($property === 'lastname' && $model instanceof User) {
                $value = $model->getAttribute('apellidos');
            }
            if ($value !== null || $model->exists) $properties[$property] = $this->normalizeValue($value);
        }

        $contactIdProperty = 'sefar_app_contact_id';
        return [$model, $objectType, $idColumn, $properties, [
            'property' => $contactIdProperty,
            'value' => (string) $model->getKey(),
            'app_id_property' => isset($available[$contactIdProperty]),
            'created_in_app' => $createdInApp,
        ]];
    }

    private function externalId(IntegrationOutbox $entry, Model $model, string $objectType, string $idColumn): ?string
    {
        $direct = $model->getAttribute($idColumn);
        if (is_scalar($direct) && trim((string) $direct) !== '') return trim((string) $direct);
        return ExternalEntityLink::query()->forEntity($entry->entity_type, (int) $model->getKey())
            ->where('provider', 'hubspot')->where('external_entity_type', $objectType)->value('external_id');
    }

    private function applyHubspotConflictPolicy(
        IntegrationOutbox $entry,
        string $objectType,
        string $externalId,
        Model $model,
        array $localProperties,
    ): array
    {
        $remote = $this->hubspot->webhookObject($objectType, $externalId);
        if (! $remote) return $localProperties;
        if (($remote['archived'] ?? false) === true) {
            throw new \RuntimeException('El objeto remoto está archivado y requiere revisión antes de reactivarlo.');
        }
        $baseline = CrmObjectMirror::query()->where('provider', 'hubspot')
            ->where('object_type', $objectType)->where('external_id', $externalId)->first();
        $hubspotWinsFields = app(CrmSyncConflictRecorder::class)->recordHubspotPrecedence(
            $entry->entity_type,
            $model,
            $objectType,
            $externalId,
            $localProperties,
            $baseline?->properties,
            (array) ($remote['properties'] ?? []),
            $remote['updatedAt'] ?? null,
        );
        if ($hubspotWinsFields !== []) {
            $this->applyRemoteConflictValues($model, $objectType, $hubspotWinsFields, (array) ($remote['properties'] ?? []));
        }

        app(CrmObjectMirrorService::class)->storeRemoteObject($objectType, $externalId, $remote);

        return array_diff_key($localProperties, array_fill_keys($hubspotWinsFields, true));
    }

    private function applyRemoteConflictValues(Model $model, string $objectType, array $fields, array $remoteProperties): void
    {
        $values = [];
        if ($model instanceof User) {
            $map = ['email' => 'email', 'firstname' => 'nombres', 'lastname' => 'apellidos', 'phone' => 'phone',
                'numero_de_pasaporte' => 'passport', 'city' => 'city', 'address' => 'address'];
            foreach ($fields as $field) {
                if ($field === 'email' && app(HubspotContactLocalSyncService::class)->parkContactEmailConflict(
                    (string) $model->hs_id,
                    ['id' => (string) $model->hs_id, 'properties' => $remoteProperties],
                    (int) $model->getKey(),
                )) {
                    continue;
                }
                if (isset($map[$field])) $values[$map[$field]] = $remoteProperties[$field] ?? null;
            }
        } elseif ($model instanceof Lead) {
            foreach ($fields as $field) {
                if ($field === 'email' && ! app(HubspotContactLocalSyncService::class)->parkContactEmailConflict(
                    (string) $model->hubspot_id,
                    ['id' => (string) $model->hubspot_id, 'properties' => $remoteProperties],
                    null,
                    (int) $model->getKey(),
                )) $values['email'] = $remoteProperties[$field] ?? null;
                if ($field === 'phone') $values['telefono'] = $remoteProperties[$field] ?? null;
                if ($field === 'numero_de_pasaporte') $values['passport'] = $remoteProperties[$field] ?? null;
            }
            if (in_array('firstname', $fields, true) || in_array('lastname', $fields, true)) {
                $nameParts = preg_split('/\s+/', trim((string) $model->nombre), 2) ?: [];
                $first = in_array('firstname', $fields, true)
                    ? trim((string) ($remoteProperties['firstname'] ?? ''))
                    : (string) ($nameParts[0] ?? '');
                $last = in_array('lastname', $fields, true)
                    ? trim((string) ($remoteProperties['lastname'] ?? ''))
                    : (string) ($nameParts[1] ?? '');
                $values['nombre'] = trim($first.' '.$last);
            }
        } elseif ($model instanceof Negocio) {
            foreach ($fields as $field) {
                $attribute = $field === 'dealname' ? 'proyecto' : $field;
                if (Schema::hasColumn($model->getTable(), $attribute)) {
                    $values[$attribute] = $remoteProperties[$field] ?? null;
                }
            }
        }

        if ($values === []) return;
        HubspotSyncOrigin::fromHubspot(function () use ($model, $values): void {
            $model->forceFill($values)->save();
        });
    }

    private function storeLink(IntegrationOutbox $entry, Model $model, string $objectType, string $idColumn, string $externalId, mixed $remoteUpdatedAt): void
    {
        $existing = ExternalEntityLink::query()->where('provider', 'hubspot')
            ->where('external_entity_type', $objectType)->where('external_id', $externalId)->first();
        if ($existing && ($existing->entity_type !== $entry->entity_type || (int) $existing->entity_id !== (int) $model->getKey())) {
            if ($entry->entity_type === 'client' && $existing->entity_type === 'lead') {
                $existing->update(['entity_type' => 'client', 'entity_id' => $model->getKey()]);
            } else {
                throw new \RuntimeException('El registro HubSpot ya está enlazado a otro registro local.');
            }
        } else {
            $existing = ExternalEntityLink::query()->updateOrCreate([
                'provider' => 'hubspot', 'external_entity_type' => $objectType, 'external_id' => $externalId,
            ], [
                'entity_type' => $entry->entity_type,
                'entity_id' => $model->getKey(),
                'last_pushed_at' => now(),
                'external_updated_at' => is_string($remoteUpdatedAt) ? $remoteUpdatedAt : null,
            ]);
        }

        $existing->forceFill(['last_pushed_at' => now()])->save();
        if ((string) $model->getAttribute($idColumn) !== $externalId) {
            HubspotSyncOrigin::fromHubspot(function () use ($model, $idColumn, $externalId): void {
                $model->forceFill([$idColumn => $externalId])->save();
            });
        }
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) return $value->format('Y-m-d');
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_array($value)) return implode(';', array_map('strval', $value));
        if (is_object($value)) return null;
        return $value;
    }
}
