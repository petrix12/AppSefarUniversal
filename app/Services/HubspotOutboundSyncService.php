<?php

namespace App\Services;

use App\Models\ExternalEntityLink;
use App\Models\IntegrationOutbox;
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
                        if ($entry->entity_type === 'deal' && ! $search['app_id_property']) {
                            throw new \RuntimeException('Para crear negocios desde la App, crea en HubSpot la propiedad sefar_app_negocio_id.');
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

                    if ($externalId !== null && $this->remoteWins($objectType, $externalId, $model)) {
                        $properties = [];
                    }

                    $creating = $externalId === null;
                    if ($creating && $entry->entity_type === 'deal') {
                        if (! $search['app_id_property']) {
                            throw new \RuntimeException('Para crear negocios desde la App, crea en HubSpot la propiedad sefar_app_negocio_id.');
                        }
                        $properties['sefar_app_negocio_id'] = (string) $model->getKey();
                    }

                    if ($properties !== []) {
                        $remote = $this->hubspot->writeWebhookObject($objectType, $externalId, $properties);
                        $externalId = (string) $remote['id'];
                        $this->storeLink($entry, $model, $objectType, $idColumn, $externalId, $remote['updatedAt'] ?? null);
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

    /** @return array{Model,string,string,array<string,mixed>,array{property:string,value:string,app_id_property:bool}} */
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
            return [$model, 'contacts', 'hubspot_id', [], ['property' => 'email', 'value' => '', 'app_id_property' => false]];
        }

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
            return [$model, $objectType, $idColumn, $properties, $search];
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

        $email = trim((string) ($model instanceof User ? $model->email : $model->email));
        if ($email === '') throw new \RuntimeException('Falta correo: no se puede identificar con seguridad el contacto en HubSpot.');
        return [$model, $objectType, $idColumn, $properties, ['property' => 'email', 'value' => $email, 'app_id_property' => false]];
    }

    private function externalId(IntegrationOutbox $entry, Model $model, string $objectType, string $idColumn): ?string
    {
        $direct = $model->getAttribute($idColumn);
        if (is_scalar($direct) && trim((string) $direct) !== '') return trim((string) $direct);
        return ExternalEntityLink::query()->forEntity($entry->entity_type, (int) $model->getKey())
            ->where('provider', 'hubspot')->where('external_entity_type', $objectType)->value('external_id');
    }

    private function remoteWins(string $objectType, string $externalId, Model $model): bool
    {
        $remote = $this->hubspot->webhookObject($objectType, $externalId);
        if (! $remote) return false;
        if (($remote['archived'] ?? false) === true) {
            throw new \RuntimeException('El objeto remoto está archivado y requiere revisión antes de reactivarlo.');
        }
        $remoteUpdatedAt = $remote['updatedAt'] ?? null;
        if (! is_string($remoteUpdatedAt) || ! $model->updated_at) return false;
        try {
            $remoteDate = \Illuminate\Support\Carbon::parse($remoteUpdatedAt);
            $localDate = \Illuminate\Support\Carbon::parse($model->updated_at);
        } catch (\Throwable) {
            return false;
        }
        if (! $remoteDate->gt($localDate)) return false;

        app(CrmObjectMirrorService::class)->storeRemoteObject($objectType, $externalId, $remote);
        if ($objectType === 'contacts') {
            app(CrmContactMirrorService::class)->syncContact('hubspot', $externalId);
        } elseif ($model instanceof Negocio && $model->user_id) {
            $user = User::query()->find($model->user_id);
            if ($user) app(HubspotDealLocalSyncService::class)->sync($user, [$remote]);
        }

        return true;
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
