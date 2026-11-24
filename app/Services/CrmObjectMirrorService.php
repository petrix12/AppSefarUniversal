<?php

namespace App\Services;

use App\Models\CrmObjectAssociation;
use App\Models\CrmObjectMirror;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Stores full HubSpot CRM object snapshots and association events without exposing them in user-facing records. */
class CrmObjectMirrorService
{
    private const EVENT_SUFFIXES = ['creation', 'propertyChange', 'deletion', 'archived', 'restore', 'associationChange', 'merge'];

    public function __construct(private HubspotService $hubspot) {}

    public function processEvent(array $event): bool
    {
        $subscriptionType = (string) ($event['subscriptionType'] ?? '');
        [$objectName, $eventName] = array_pad(explode('.', $subscriptionType, 2), 2, '');
        if (! in_array($eventName, self::EVENT_SUFFIXES, true) || ! $this->validObjectType($objectName)) {
            return false;
        }

        if ($eventName === 'associationChange') {
            if (($event['genericWebhook'] ?? false) === true) {
                $this->reconcileGenericAssociation($event);
                return true;
            }
            $this->recordAssociationEvent($event, $objectName);
            foreach ([[$this->pluralObjectType($objectName), $event['fromObjectId'] ?? $event['objectId'] ?? null],
                [$this->associatedObjectType($event['associationType'] ?? null), $event['toObjectId'] ?? null]] as [$type, $id]) {
                if ($type && $this->validId($id)) $this->syncObject($type, (string) $id);
            }
            return true;
        }

        $ids = $eventName === 'merge'
            ? array_merge((array) ($event['mergedObjectIds'] ?? []), [(string) ($event['primaryObjectId'] ?? ''), (string) ($event['newObjectId'] ?? '')])
            : [(string) ($event['objectId'] ?? '')];

        foreach (array_unique(array_filter($ids, fn ($id) => $this->validId($id))) as $id) {
            $this->syncObject($this->pluralObjectType($objectName), (string) $id);
        }

        return true;
    }

    public function syncObject(string $objectType, string $externalId): CrmObjectMirror
    {
        if (! $this->validObjectType($objectType) || ! $this->validId($externalId)) {
            throw new \InvalidArgumentException('Invalid HubSpot object identity.');
        }

        $remote = $this->hubspot->webhookObject($objectType, $externalId);
        return $this->storeRemoteObject($objectType, $externalId, $remote);
    }

    public function storeRemoteObject(string $objectType, string $externalId, ?array $remote): CrmObjectMirror
    {
        if (! $this->validObjectType($objectType) || ! $this->validId($externalId)) {
            throw new \InvalidArgumentException('Invalid HubSpot object identity.');
        }
        $previous = CrmObjectMirror::query()
            ->where('provider', 'hubspot')->where('object_type', $objectType)->where('external_id', $externalId)->first();
        $archived = $remote === null || (bool) ($remote['archived'] ?? false);
        $properties = $remote['properties'] ?? $previous?->properties ?? [];
        $archivedAt = $remote['archivedAt'] ?? $previous?->remote_archived_at?->toIso8601String() ?? ($archived ? now()->toIso8601String() : null);
        $remoteState = [
            'properties' => $properties,
            'archived' => $archived,
            'remote_created_at' => $remote['createdAt'] ?? $previous?->remote_created_at?->toIso8601String(),
            'remote_updated_at' => $remote['updatedAt'] ?? $previous?->remote_updated_at?->toIso8601String(),
            'remote_archived_at' => $archivedAt,
        ];
        $hash = hash('sha256', json_encode($this->canonical($remoteState), JSON_THROW_ON_ERROR));

        if ($previous && $remote !== null && ! $archived && $previous->content_hash !== $hash) {
            $this->recordInboundFieldConflicts($objectType, $externalId, $previous, $properties, $remote['updatedAt'] ?? null);
        }

        $mirror = DB::transaction(function () use ($objectType, $externalId, $properties, $remote, $archived, $archivedAt, $hash): CrmObjectMirror {
            $mirror = CrmObjectMirror::query()->firstOrNew([
                'provider' => 'hubspot', 'object_type' => $objectType, 'external_id' => $externalId,
            ]);
            if ($mirror->content_hash === $hash) {
                $mirror->forceFill(['synced_at' => now()])->save();
                return $mirror;
            }

            $mirror->forceFill([
                'properties' => $properties,
                'archived' => $archived,
                'remote_created_at' => $this->remoteDate($remote['createdAt'] ?? null),
                'remote_updated_at' => $this->remoteDate($remote['updatedAt'] ?? null),
                'remote_archived_at' => $this->remoteDate($archivedAt),
                'content_hash' => $hash,
                'synced_at' => now(),
            ])->save();

            return $mirror;
        });

        if ($objectType === 'deals' && $remote !== null && ! $archived) {
            app(HubspotDealLocalSyncService::class)->syncUnassigned([[
                'id' => $externalId,
                'properties' => $properties,
            ]]);
        }

        return $mirror;
    }

    private function recordInboundFieldConflicts(
        string $objectType,
        string $externalId,
        CrmObjectMirror $previous,
        array $remoteProperties,
        mixed $remoteUpdatedAt,
    ): void {
        if (! Schema::hasTable('crm_sync_conflicts')) return;

        $entityType = null;
        $model = null;
        $localProperties = [];

        if ($objectType === 'contacts') {
            $users = User::query()->where('hs_id', $externalId)->limit(2)->get();
            if ($users->count() === 1 && $users->first()->hasRole('Cliente')) {
                $entityType = 'client';
                $model = $users->first();
                foreach (['email' => 'email', 'firstname' => 'nombres', 'lastname' => 'apellidos', 'phone' => 'phone', 'numero_de_pasaporte' => 'passport'] as $property => $column) {
                    $localProperties[$property] = $model->getAttribute($column);
                }
            } elseif ($users->isEmpty()) {
                $leads = \App\Models\Lead::query()->where('hubspot_id', $externalId)->limit(2)->get();
                if ($leads->count() === 1) {
                    $entityType = 'lead';
                    $model = $leads->first();
                    $name = trim((string) $model->nombre);
                    $parts = preg_split('/\s+/', $name, 2) ?: [];
                    $localProperties = [
                        'firstname' => $parts[0] ?? '',
                        'lastname' => $parts[1] ?? '',
                        'email' => $model->email,
                        'phone' => $model->telefono,
                        'numero_de_pasaporte' => $model->passport,
                    ];
                }
            }
        } elseif ($objectType === 'deals') {
            $deals = Negocio::query()->where('hubspot_id', $externalId)->limit(2)->get();
            if ($deals->count() === 1) {
                $entityType = 'deal';
                $model = $deals->first();
                foreach (array_keys($remoteProperties) as $property) {
                    $column = $property === 'dealname' ? 'proyecto' : $property;
                    if (Schema::hasColumn($model->getTable(), $column)) {
                        $localProperties[$property] = $model->getAttribute($column);
                    }
                }
            }
        }

        if ($model && $localProperties !== []) {
            app(CrmSyncConflictRecorder::class)->recordHubspotPrecedence(
                $entityType,
                $model,
                $objectType,
                $externalId,
                $localProperties,
                (array) ($previous->properties ?? []),
                $remoteProperties,
                $remoteUpdatedAt,
            );
        }
    }

    /** Replace the currently known contact/deal relation set after a full read. */
    public function storeAssociationSnapshot(string $fromType, string $fromId, string $toType, array $toIds): void
    {
        if (! $this->validObjectType($fromType) || ! $this->validObjectType($toType)
            || ! preg_match('/^[0-9]{1,64}$/', $fromId)) {
            throw new \InvalidArgumentException('Invalid HubSpot association snapshot.');
        }
        $toIds = array_values(array_unique(array_filter(array_map('strval', $toIds), fn ($id) => preg_match('/^[0-9]{1,64}$/', $id))));

        DB::transaction(function () use ($fromType, $fromId, $toType, $toIds): void {
            $pair = CrmObjectAssociation::query()->where('provider', 'hubspot')
                ->where('from_object_type', $fromType)->where('from_external_id', $fromId)
                ->where('to_object_type', $toType);
            $detailedTargetIds = (clone $pair)->where('association_type_id', '!=', 'snapshot')
                ->pluck('to_external_id')->map(fn ($id) => (string) $id)->unique()->all();

            CrmObjectAssociation::query()->where('provider', 'hubspot')
                ->where('from_object_type', $fromType)->where('from_external_id', $fromId)
                ->where('to_object_type', $toType)->where('association_type_id', 'snapshot')
                ->update(['active' => false, 'last_event_at' => now()]);

            if ($detailedTargetIds !== []) {
                $detailed = (clone $pair)->where('association_type_id', '!=', 'snapshot');
                if ($toIds === []) {
                    $detailed->update(['active' => false, 'last_event_at' => now()]);
                    return;
                }
                (clone $detailed)->whereNotIn('to_external_id', $toIds)
                    ->update(['active' => false, 'last_event_at' => now()]);
                (clone $detailed)->whereIn('to_external_id', $toIds)
                    ->update(['active' => true, 'last_event_at' => now()]);
                $toIds = array_values(array_diff($toIds, $detailedTargetIds));
            }

            foreach ($toIds as $toId) {
                CrmObjectAssociation::query()->updateOrCreate([
                    'provider' => 'hubspot',
                    'from_object_type' => $fromType,
                    'from_external_id' => $fromId,
                    'to_object_type' => $toType,
                    'to_external_id' => $toId,
                    'association_type_id' => 'snapshot',
                ], [
                    'association_category' => 'snapshot',
                    'active' => true,
                    'last_event_at' => now(),
                ]);
            }
        });
    }

    private function recordAssociationEvent(array $event, string $subscriptionObject): void
    {
        $fromType = $this->pluralObjectType((string) ($event['fromObjectType'] ?? $subscriptionObject));
        $toType = $this->pluralObjectType((string) ($event['toObjectType'] ?? $this->associatedObjectType($event['associationType'] ?? null)));
        $fromId = (string) ($event['fromObjectId'] ?? $event['objectId'] ?? '');
        $toId = (string) ($event['toObjectId'] ?? '');
        if (! $toType || ! $this->validId($fromId) || ! $this->validId($toId)) {
            throw new \UnexpectedValueException('HubSpot association event is missing a valid object identity.');
        }

        CrmObjectAssociation::query()->updateOrCreate([
            'provider' => 'hubspot',
            'from_object_type' => $fromType,
            'from_external_id' => $fromId,
            'to_object_type' => $toType,
            'to_external_id' => $toId,
            'association_type_id' => isset($event['associationTypeId']) ? (string) $event['associationTypeId'] : '',
        ], [
            'association_category' => isset($event['associationCategory']) ? (string) $event['associationCategory'] : null,
            'active' => ! (bool) ($event['associationRemoved'] ?? false),
            'last_event_at' => $this->remoteDate($event['occurredAt'] ?? null) ?? now(),
        ]);
    }

    /** Generic association events identify the changed object and object-type pair, not both record IDs. */
    private function reconcileGenericAssociation(array $event): void
    {
        $subjectType = HubspotWebhookEventNormalizer::objectType($event['objectTypeId'] ?? null);
        $fromType = HubspotWebhookEventNormalizer::objectType($event['fromObjectTypeId'] ?? null);
        $toType = HubspotWebhookEventNormalizer::objectType($event['toObjectTypeId'] ?? null);
        $subjectId = (string) ($event['objectId'] ?? '');
        if ($subjectType === null || $fromType === null || $toType === null || ! $this->validId($subjectId)) {
            throw new \UnexpectedValueException('HubSpot generic association event is missing a valid object identity.');
        }

        $apiSubjectType = $this->pluralObjectType($subjectType);
        $apiFromType = $this->pluralObjectType($fromType);
        $apiToType = $this->pluralObjectType($toType);
        if ($subjectType === $fromType) {
            $associatedIds = $this->hubspot->webhookAssociations($apiFromType, $subjectId, $apiToType);
            $this->storeAssociationSnapshot($apiFromType, $subjectId, $apiToType, $associatedIds);
        } elseif ($subjectType === $toType) {
            $associatedIds = $this->hubspot->webhookAssociations($apiToType, $subjectId, $apiFromType);
            $this->storeAssociationSnapshot($apiToType, $subjectId, $apiFromType, $associatedIds);
        } else {
            throw new \UnexpectedValueException('HubSpot generic association object type does not match its association pair.');
        }

        $this->syncObject($apiSubjectType, $subjectId);
    }

    private function associatedObjectType(mixed $association): ?string
    {
        $parts = preg_split('/_TO_/i', (string) $association);
        if (count($parts) !== 2) return null;
        return $this->pluralObjectType((string) $parts[1]);
    }

    private function pluralObjectType(string $type): string
    {
        $type = strtolower(trim($type));
        return match ($type) {
            'contact' => 'contacts', 'deal' => 'deals', 'company' => 'companies',
            'ticket' => 'tickets', 'lead' => 'leads', 'line_item', 'line item' => 'line_items',
            'invoice' => 'invoices', 'quote' => 'quotes', 'product' => 'products',
            'commerce_payment', 'commerce payment' => 'commerce_payments',
            'order' => 'orders', 'call' => 'calls', 'email' => 'emails',
            'meeting' => 'meetings', 'note' => 'notes', 'task' => 'tasks',
            'appointment' => 'appointments', 'cart' => 'carts', 'communication' => 'communications',
            'course' => 'courses', 'discount' => 'discounts', 'feedback_submission' => 'feedback_submissions',
            'fee' => 'fees', 'goal' => 'goals', 'listing' => 'listings',
            'marketing_event' => 'marketing_events', 'postal_mail' => 'postal_mail', 'project' => 'projects',
            'service' => 'services', 'subscription' => 'subscriptions', 'tax' => 'taxes', 'user' => 'users',
            default => $type,
        };
    }

    private function validObjectType(string $type): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9_-]{2,80}$/', $type);
    }

    private function validId(mixed $id): bool
    {
        return is_scalar($id) && preg_match('/^[0-9]{1,64}$/', (string) $id) === 1;
    }

    private function remoteDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') return null;
        try { return Carbon::parse($value); } catch (\Throwable) { return null; }
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) return $value;
        if (! array_is_list($value)) ksort($value);
        return array_map(fn ($item) => $this->canonical($item), $value);
    }
}
