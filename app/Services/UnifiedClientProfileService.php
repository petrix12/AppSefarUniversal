<?php

namespace App\Services;

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\ExternalEntityLink;
use App\Models\IntegrationFieldMapping;
use App\Models\IntegrationOutbox;
use App\Models\User;
use App\Models\WorkflowBoard;
use App\Models\WorkflowMembership;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/**
 * Canonical write path for information that does not belong in users anymore.
 *
 * users remains the client identity used by the application today. This service
 * stores extensible information separately and only queues external work; it
 * never performs a network request as part of a user-facing write.
 */
class UnifiedClientProfileService
{
    public const ENTITY_CLIENT = CustomFieldDefinition::ENTITY_CLIENT;

    public function defineField(array $attributes): CustomFieldDefinition
    {
        $entityType = $attributes['entity_type'] ?? self::ENTITY_CLIENT;
        $key = Str::of((string) ($attributes['key'] ?? ''))
            ->lower()
            ->replaceMatches('/[^a-z0-9_]+/', '_')
            ->trim('_')
            ->toString();
        $dataType = (string) ($attributes['data_type'] ?? 'text');

        if ($key === '') {
            throw new InvalidArgumentException('El identificador del campo es obligatorio.');
        }

        if (! in_array($dataType, CustomFieldDefinition::DATA_TYPES, true)) {
            throw new InvalidArgumentException("Tipo de campo no soportado: {$dataType}");
        }

        return CustomFieldDefinition::updateOrCreate(
            ['entity_type' => $entityType, 'key' => $key],
            array_merge($attributes, [
                'entity_type' => $entityType,
                'key' => $key,
                'data_type' => $dataType,
            ])
        );
    }

    public function setValue(
        User $client,
        CustomFieldDefinition|string $definition,
        mixed $value,
        string $source = 'app',
        ?CarbonInterface $sourceUpdatedAt = null,
        bool $queueOutboundSync = true,
    ): CustomFieldValue {
        $definition = $this->resolveDefinition($definition);
        $normalisedValue = $this->normaliseValue($definition, $value);

        [$fieldValue, $previousValue, $changed] = DB::transaction(function () use (
            $client,
            $definition,
            $normalisedValue,
            $source,
            $sourceUpdatedAt,
            $queueOutboundSync
        ): array {
            $existing = CustomFieldValue::query()
                ->where('custom_field_definition_id', $definition->id)
                ->forEntity(self::ENTITY_CLIENT, $client->id)
                ->first();
            $encodedValue = $normalisedValue === null
                ? null
                : json_encode($normalisedValue, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $previousValue = $existing?->decoded_value;
            $changed = ! $existing || $existing->value !== $encodedValue;

            $fieldValue = CustomFieldValue::updateOrCreate(
                [
                    'custom_field_definition_id' => $definition->id,
                    'entity_type' => self::ENTITY_CLIENT,
                    'entity_id' => $client->id,
                ],
                [
                    'value' => $encodedValue,
                    'value_text' => $this->valueText($normalisedValue),
                    'source' => $source,
                    'source_updated_at' => $sourceUpdatedAt,
                ]
            );

            if ($queueOutboundSync && in_array($source, ['app', 'automation'], true)) {
                $this->queueMappedFieldUpdates($client, $definition, $normalisedValue, $fieldValue);
            }

            return [$fieldValue, $previousValue, $changed];
        });

        // Automation-originated writes deliberately do not re-enter the event
        // engine. This prevents a rule that writes a field from looping on its
        // own field-changed trigger.
        if ($changed && ! in_array($source, ['automation', 'legacy_app'], true) && Schema::hasTable('automation_rules')) {
            app(AutomationEngine::class)->trigger(
                AutomationEngine::EVENT_FIELD_CHANGED,
                $client,
                [
                    'field' => [
                        'key' => $definition->key,
                        'old_value' => $previousValue,
                        'new_value' => $normalisedValue,
                        'source' => $source,
                    ],
                ],
                'field:'.sha1(implode('|', [
                    $fieldValue->id,
                    $fieldValue->updated_at?->format('Y-m-d H:i:s.u'),
                ])),
                $fieldValue->updated_at,
            );
        }

        return $fieldValue;
    }

    /**
     * Applies one configured external field. Unmapped fields are intentionally
     * ignored: raw provider payloads stay in the existing source tables until
     * an administrator makes the mapping explicit.
     */
    public function applyIncomingValue(
        User $client,
        string $provider,
        string $externalEntityType,
        string $externalFieldKey,
        mixed $value,
        string $scopeKey = '*',
        ?CarbonInterface $sourceUpdatedAt = null,
    ): ?CustomFieldValue {
        $mapping = IntegrationFieldMapping::query()
            ->inbound()
            ->where('is_active', true)
            ->where('provider', $provider)
            ->where('external_entity_type', $externalEntityType)
            ->where('entity_type', self::ENTITY_CLIENT)
            ->where('external_field_key', $externalFieldKey)
            ->whereIn('scope_key', array_unique([$scopeKey, '*']))
            ->orderByRaw('scope_key = ? desc', [$scopeKey])
            ->first();

        if (! $mapping) {
            return null;
        }

        if ($mapping->custom_field_definition_id) {
            $existing = CustomFieldValue::query()
                ->where('custom_field_definition_id', $mapping->custom_field_definition_id)
                ->forEntity(self::ENTITY_CLIENT, $client->id)
                ->first();

            if ($this->shouldKeepLocalValue($mapping, $existing, $sourceUpdatedAt)) {
                return $existing;
            }

            return $this->setValue(
                $client,
                $mapping->customFieldDefinition,
                $this->transformIncomingValue($value, $mapping->transform),
                $provider,
                $sourceUpdatedAt,
                false,
            );
        }

        if ($mapping->local_attribute && Schema::hasColumn('users', $mapping->local_attribute)) {
            $client->forceFill([
                $mapping->local_attribute => $this->transformIncomingValue($value, $mapping->transform),
            ])->save();
        }

        return null;
    }

    public function linkExternalEntity(
        User $client,
        string $provider,
        string $externalEntityType,
        string|int $externalId,
        array $metadata = [],
        ?CarbonInterface $externalUpdatedAt = null,
    ): ExternalEntityLink {
        $externalId = trim((string) $externalId);

        if ($externalId === '') {
            throw new InvalidArgumentException('No se puede enlazar una entidad externa sin identificador.');
        }

        $link = ExternalEntityLink::firstOrNew([
            'provider' => $provider,
            'external_entity_type' => $externalEntityType,
            'external_id' => $externalId,
        ]);

        if ($link->exists && (
            $link->entity_type !== self::ENTITY_CLIENT || (int) $link->entity_id !== (int) $client->id
        )) {
            throw new LogicException(
                "El {$externalEntityType} {$externalId} de {$provider} ya está enlazado a otro cliente."
            );
        }

        $link->fill([
            'entity_type' => self::ENTITY_CLIENT,
            'entity_id' => $client->id,
            'metadata' => array_replace((array) $link->metadata, $metadata),
            'external_updated_at' => $externalUpdatedAt ?? $link->external_updated_at,
            'last_seen_at' => now(),
        ]);
        $link->save();

        return $link;
    }

    /**
     * Mirrors a successful Monday creation in the canonical workflow model.
     * The actual Monday API call still belongs to MondayRegistrationService.
     */
    public function recordMondayItem(
        User $client,
        string|int $boardId,
        string $boardName,
        string $groupId,
        string $groupName,
        string|int $itemId,
        array $metadata = [],
        string $source = 'monday',
    ): WorkflowMembership {
        return DB::transaction(function () use (
            $client,
            $boardId,
            $boardName,
            $groupId,
            $groupName,
            $itemId,
            $metadata,
            $source
        ): WorkflowMembership {
            $board = WorkflowBoard::firstOrCreate(
                ['provider' => 'monday', 'external_board_id' => (string) $boardId],
                ['name' => $boardName]
            );
            $board->fill(['name' => $boardName ?: $board->name])->save();

            $stage = WorkflowStage::firstOrCreate(
                ['workflow_board_id' => $board->id, 'external_stage_id' => $groupId],
                ['name' => $groupName ?: $groupId]
            );
            $stage->fill(['name' => $groupName ?: $stage->name])->save();

            $membership = WorkflowMembership::firstOrNew([
                'entity_type' => self::ENTITY_CLIENT,
                'entity_id' => $client->id,
                'workflow_board_id' => $board->id,
            ]);
            $previousStageId = $membership->exists ? $membership->workflow_stage_id : null;

            $membership->fill([
                'workflow_stage_id' => $stage->id,
                'external_item_id' => (string) $itemId,
                'status' => 'active',
                'source' => $source,
                'entered_at' => $membership->entered_at ?? now(),
                'left_at' => null,
                'metadata' => array_replace((array) $membership->metadata, $metadata),
            ]);
            $membership->save();

            if (! $membership->wasRecentlyCreated && (int) $previousStageId !== (int) $stage->id) {
                WorkflowTransition::create([
                    'workflow_membership_id' => $membership->id,
                    'entity_type' => self::ENTITY_CLIENT,
                    'entity_id' => $client->id,
                    'from_workflow_board_id' => $board->id,
                    'from_workflow_stage_id' => $previousStageId,
                    'to_workflow_board_id' => $board->id,
                    'to_workflow_stage_id' => $stage->id,
                    'source' => $source,
                ]);
            }

            $this->linkExternalEntity($client, 'monday', 'item', $itemId, array_merge($metadata, [
                'board_id' => (string) $boardId,
                'group_id' => $groupId,
                'workflow_membership_id' => $membership->id,
            ]));

            return $membership;
        });
    }

    private function resolveDefinition(CustomFieldDefinition|string $definition): CustomFieldDefinition
    {
        if ($definition instanceof CustomFieldDefinition) {
            return $definition;
        }

        return CustomFieldDefinition::query()
            ->forEntity(self::ENTITY_CLIENT)
            ->where('key', $definition)
            ->firstOrFail();
    }

    private function normaliseValue(CustomFieldDefinition $definition, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($definition->data_type) {
            'text', 'long_text', 'select' => trim((string) $value),
            'email' => $this->normaliseEmail($value),
            'url' => $this->normaliseUrl($value),
            'number' => $this->normaliseNumber($value, false),
            'decimal' => $this->normaliseNumber($value, true),
            'boolean' => $this->normaliseBoolean($value),
            'date' => Carbon::parse($value)->toDateString(),
            'datetime' => Carbon::parse($value)->toIso8601String(),
            'multiselect' => $this->normaliseMultiSelect($value),
            'json' => $this->normaliseJson($value),
            default => throw new InvalidArgumentException("Tipo de campo no soportado: {$definition->data_type}"),
        };
    }

    private function normaliseEmail(mixed $value): string
    {
        $value = mb_strtolower(trim((string) $value));

        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El valor no es un correo electrónico válido.');
        }

        return $value;
    }

    private function normaliseUrl(mixed $value): string
    {
        $value = trim((string) $value);

        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('El valor no es una URL válida.');
        }

        return $value;
    }

    private function normaliseNumber(mixed $value, bool $decimal): int|float
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException('El valor debe ser numérico.');
        }

        return $decimal ? (float) $value : (int) $value;
    }

    private function normaliseBoolean(mixed $value): bool
    {
        $normalised = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($normalised === null) {
            throw new InvalidArgumentException('El valor debe ser verdadero o falso.');
        }

        return $normalised;
    }

    private function normaliseMultiSelect(mixed $value): array
    {
        $values = is_array($value) ? $value : explode(',', (string) $value);

        return collect($values)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normaliseJson(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    private function valueText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function shouldKeepLocalValue(
        IntegrationFieldMapping $mapping,
        ?CustomFieldValue $existing,
        ?CarbonInterface $sourceUpdatedAt,
    ): bool {
        if (! $existing || $mapping->conflict_policy !== 'local_wins' || $existing->source !== 'app') {
            return false;
        }

        return ! $sourceUpdatedAt
            || ! $existing->updated_at
            || $existing->updated_at->greaterThanOrEqualTo($sourceUpdatedAt);
    }

    private function transformIncomingValue(mixed $value, ?array $transform): mixed
    {
        if (! $transform) {
            return $value;
        }

        if (($transform['type'] ?? null) === 'semicolon_list' && is_string($value)) {
            return explode(';', $value);
        }

        if (($transform['type'] ?? null) === 'boolean_string') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return $value;
    }

    private function queueMappedFieldUpdates(
        User $client,
        CustomFieldDefinition $definition,
        mixed $value,
        CustomFieldValue $fieldValue,
    ): void {
        IntegrationFieldMapping::query()
            ->outbound()
            ->where('is_active', true)
            ->where('entity_type', self::ENTITY_CLIENT)
            ->where('custom_field_definition_id', $definition->id)
            ->each(function (IntegrationFieldMapping $mapping) use ($client, $value, $fieldValue): void {
                $link = ExternalEntityLink::query()
                    ->forEntity(self::ENTITY_CLIENT, $client->id)
                    ->where('provider', $mapping->provider)
                    ->where('external_entity_type', $mapping->external_entity_type)
                    ->first();

                $dedupeKey = 'field:'.sha1(implode('|', [
                    $mapping->id,
                    $client->id,
                    $fieldValue->id,
                ]));

                IntegrationOutbox::updateOrCreate(
                    ['dedupe_key' => $dedupeKey],
                    [
                        'external_entity_link_id' => $link?->id,
                        'provider' => $mapping->provider,
                        'entity_type' => self::ENTITY_CLIENT,
                        'entity_id' => $client->id,
                        'operation' => 'update_fields',
                        'payload' => [
                            'external_entity_type' => $mapping->external_entity_type,
                            'external_id' => $link?->external_id,
                            'scope_key' => $mapping->scope_key,
                            'fields' => [$mapping->external_field_key => $value],
                        ],
                        'status' => 'pending',
                        'attempts' => 0,
                        'available_at' => now(),
                        'processed_at' => null,
                        'last_error' => null,
                    ]
                );
            });
    }
}
