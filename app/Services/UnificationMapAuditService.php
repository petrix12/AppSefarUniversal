<?php

namespace App\Services;

use App\Models\CustomFieldDefinition;
use App\Models\UnificationAuditLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Builds a read-only inventory from the local copies of each platform.
 *
 * It never calls HubSpot, Monday or Teamleader, and never writes integration
 * mappings. The only optional persistence belongs to the separate audit log.
 */
class UnificationMapAuditService
{
    public function inventory(): array
    {
        $legacyLinks = $this->legacyLinks();
        $appFields = $this->appFields($legacyLinks);
        $mondayFields = $this->mondayFields();
        $auditedLinks = $this->auditedLinks();

        $mapRows = $this->buildMapRows($legacyLinks, $appFields, $mondayFields, $auditedLinks);
        $coveredMondayFields = collect($mapRows)
            ->pluck('monday_matches')
            ->flatten(1)
            ->pluck('identity')
            ->filter()
            ->flip();

        foreach ($mondayFields as $mondayField) {
            if ($coveredMondayFields->has($mondayField['identity'])) {
                continue;
            }

            $mapRows[] = [
                'identity' => 'monday:'.$mondayField['identity'],
                'app' => null,
                'hubspot' => [],
                'teamleader' => [],
                'monday_matches' => [$mondayField],
                'match_method' => 'unmatched',
                'confidence' => null,
                'audit_links' => $this->auditLinksFor($auditedLinks, 'monday', $mondayField['key'], $mondayField['scope_key']),
            ];
        }

        $legacyAppKeys = collect($legacyLinks)->pluck('hubspot_key')->unique()->flip();
        foreach ($appFields as $appField) {
            if ($legacyAppKeys->has($appField['key'])) {
                continue;
            }

            $mapRows[] = [
                'identity' => 'app:'.$appField['key'],
                'app' => $appField,
                'hubspot' => [],
                'teamleader' => [],
                'monday_matches' => [],
                'match_method' => 'app_only',
                'confidence' => null,
                'audit_links' => $this->auditLinksFor($auditedLinks, 'app', $appField['key'], '*'),
            ];
        }

        // A proposal may introduce a future App field that does not exist in
        // users or custom_field_definitions yet. It must still be visible in
        // the diagram and the review queue.
        $auditByAppField = collect($auditedLinks)->groupBy('app_field_key');
        $knownAppKeys = collect($mapRows)
            ->map(fn (array $row) => $row['app']['key'] ?? null)
            ->filter()
            ->flip();

        foreach ($mapRows as &$row) {
            if ($row['app']['key'] ?? null) {
                $row['audit_links'] = $auditByAppField->get($row['app']['key'], collect())->values()->all();
            }
        }
        unset($row);

        foreach ($auditByAppField as $appFieldKey => $links) {
            if ($knownAppKeys->has($appFieldKey)) {
                continue;
            }

            $first = $links->first();
            $mapRows[] = [
                'identity' => 'audit:'.$appFieldKey,
                'app' => [
                    'key' => $appFieldKey,
                    'label' => $first['app_field_label'],
                    'storage' => 'proposed',
                    'source' => 'Propuesta de auditoría',
                ],
                'hubspot' => $links->where('provider', 'hubspot')->map(fn (array $link) => [
                    'key' => $link['external_field_key'],
                    'label' => $link['external_field_label'] ?: $link['external_field_key'],
                    'scope_key' => $link['scope_key'],
                ])->values()->all(),
                'teamleader' => $links->where('provider', 'teamleader')->map(fn (array $link) => [
                    'key' => $link['external_field_key'],
                    'label' => $link['external_field_label'] ?: $link['external_field_key'],
                    'type' => null,
                    'context' => $link['external_entity_type'],
                ])->values()->all(),
                'monday_matches' => $links->where('provider', 'monday')->map(fn (array $link) => [
                    'identity' => $link['scope_key'].':'.$link['external_field_key'],
                    'key' => $link['external_field_key'],
                    'label' => $link['external_field_label'] ?: $link['external_field_key'],
                    'scope_key' => $link['scope_key'],
                    'type' => null,
                    'source' => 'Propuesta manual',
                    'confidence' => $link['confidence'],
                ])->values()->all(),
                'match_method' => 'manual',
                'confidence' => null,
                'audit_links' => $links->values()->all(),
            ];
        }

        $mapRows = collect($mapRows)
            ->sortBy(fn (array $row) => Str::lower($row['app']['label'] ?? $row['monday_matches'][0]['label'] ?? ''))
            ->values()
            ->all();

        return [
            'summary' => [
                'legacy_associations' => count($legacyLinks),
                'hubspot_fields' => collect($legacyLinks)->pluck('hubspot_key')->unique()->count(),
                'teamleader_fields' => collect($legacyLinks)->pluck('teamleader_key')->filter()->unique()->count(),
                'app_fields' => count($appFields),
                'app_legacy_columns' => collect($legacyLinks)->filter(fn (array $link) => $link['app_field']['storage'] === 'users')->pluck('hubspot_key')->unique()->count(),
                'monday_fields' => count($mondayFields),
                'audit_storage_ready' => Schema::hasTable('unification_audit_links'),
                'active_mappings' => $this->activeMappingCount(),
            ],
            'map_rows' => $mapRows,
            'audited_links' => $auditedLinks,
            'field_options' => [
                'app' => $appFields,
                'monday' => $mondayFields,
            ],
        ];
    }

    private function legacyLinks(): array
    {
        if (! Schema::hasTable('assoc_tl_hs')) {
            return [];
        }

        $teamleaderDefinitions = $this->teamleaderDefinitions();
        $userColumns = Schema::hasTable('users') ? Schema::getColumnListing('users') : [];

        return DB::table('assoc_tl_hs')
            ->select(['tl_id', 'hs_id', 'modulo'])
            ->whereNotNull('tl_id')
            ->whereNotNull('hs_id')
            ->get()
            ->filter(fn ($row) => filled($row->tl_id) && filled($row->hs_id))
            ->map(function ($row) use ($teamleaderDefinitions, $userColumns): array {
                $hubspotKey = (string) $row->hs_id;
                $teamleaderKey = (string) $row->tl_id;

                return [
                    'hubspot_key' => $hubspotKey,
                    'hubspot_label' => $this->labelFor($hubspotKey),
                    'teamleader_key' => $teamleaderKey,
                    'teamleader_label' => $teamleaderDefinitions[$teamleaderKey]['label'] ?? $teamleaderKey,
                    'teamleader_type' => $teamleaderDefinitions[$teamleaderKey]['type'] ?? null,
                    'teamleader_context' => $teamleaderDefinitions[$teamleaderKey]['context'] ?? 'contact',
                    'module' => $row->modulo,
                    'app_field' => [
                        'key' => $hubspotKey,
                        'label' => $this->labelFor($hubspotKey),
                        'storage' => in_array($hubspotKey, $userColumns, true) ? 'users' : 'proposed',
                        'source' => in_array($hubspotKey, $userColumns, true) ? 'Columna histórica' : 'Catálogo legado',
                    ],
                ];
            })
            ->all();
    }

    private function appFields(array $legacyLinks): array
    {
        $fields = [];
        foreach ($legacyLinks as $link) {
            $fields[$link['app_field']['key']] = $link['app_field'];
        }

        if (Schema::hasTable('custom_field_definitions')) {
            CustomFieldDefinition::query()
                ->where('entity_type', CustomFieldDefinition::ENTITY_CLIENT)
                ->orderBy('label')
                ->get(['key', 'label', 'data_type', 'is_active'])
                ->each(function (CustomFieldDefinition $field) use (&$fields): void {
                    $fields[$field->key] = [
                        'key' => $field->key,
                        'label' => $field->label,
                        'storage' => 'custom_field_definitions',
                        'source' => $field->is_active ? 'Campo flexible' : 'Campo flexible (inactivo)',
                        'data_type' => $field->data_type,
                    ];
                });
        }

        return collect($fields)->sortBy('label')->values()->all();
    }

    private function teamleaderDefinitions(): array
    {
        if (! Schema::hasTable('tl_custom_field_definitions')) {
            return [];
        }

        return DB::table('tl_custom_field_definitions')
            ->select(['id', 'label', 'type', 'context'])
            ->get()
            ->mapWithKeys(fn ($field) => [(string) $field->id => [
                'label' => (string) $field->label,
                'type' => (string) $field->type,
                'context' => (string) $field->context,
            ]])
            ->all();
    }

    private function mondayFields(): array
    {
        $fields = [];

        if (Schema::hasTable('monday_form_builder')) {
            DB::table('monday_form_builder')
                ->select(['board_id', 'column_id', 'title', 'type'])
                ->orderBy('board_id')
                ->orderBy('title')
                ->get()
                ->each(function ($field) use (&$fields): void {
                    $scopeKey = (string) $field->board_id;
                    $key = (string) $field->column_id;
                    $identity = $scopeKey.':'.$key;
                    $fields[$identity] = [
                        'identity' => $identity,
                        'key' => $key,
                        'label' => (string) $field->title,
                        'scope_key' => $scopeKey,
                        'type' => (string) $field->type,
                        'source' => 'Constructor de formularios',
                    ];
                });
        }

        if (Schema::hasTable('monday_field_mappings')) {
            DB::table('monday_field_mappings')
                ->select(['board_id', 'monday_column_id', 'local_field_key'])
                ->orderBy('board_id')
                ->get()
                ->each(function ($field) use (&$fields): void {
                    $scopeKey = (string) $field->board_id;
                    $key = (string) $field->monday_column_id;
                    $identity = $scopeKey.':'.$key;
                    $label = filled($field->local_field_key)
                        ? $this->labelFor((string) $field->local_field_key)
                        : $key;

                    $existing = $fields[$identity] ?? null;
                    $fields[$identity] = array_merge($existing ?? [], [
                        'identity' => $identity,
                        'key' => $key,
                        'label' => $existing['label'] ?? $label,
                        'scope_key' => $scopeKey,
                        'type' => $existing['type'] ?? null,
                        'source' => $existing ? 'Formulario + mapeo histórico' : 'Mapeo histórico',
                        'local_field_key' => (string) $field->local_field_key,
                    ]);
                });
        }

        return collect($fields)
            ->sortBy(fn (array $field) => $field['scope_key'].'|'.Str::lower($field['label']))
            ->values()
            ->all();
    }

    private function auditedLinks(): array
    {
        if (! Schema::hasTable('unification_audit_links')) {
            return [];
        }

        return UnificationAuditLink::query()
            ->latest('updated_at')
            ->get()
            ->map(fn (UnificationAuditLink $link) => [
                'id' => $link->id,
                'app_field_key' => $link->app_field_key,
                'app_field_label' => $link->app_field_label,
                'provider' => $link->provider,
                'external_entity_type' => $link->external_entity_type,
                'scope_key' => $link->scope_key,
                'external_field_key' => $link->external_field_key,
                'external_field_label' => $link->external_field_label,
                'match_method' => $link->match_method,
                'confidence' => $link->confidence,
                'status' => $link->status,
                'notes' => $link->notes,
            ])
            ->all();
    }

    private function buildMapRows(array $legacyLinks, array $appFields, array $mondayFields, array $auditedLinks): array
    {
        $appFieldsByKey = collect($appFields)->keyBy('key');

        return collect($legacyLinks)
            ->groupBy('hubspot_key')
            ->map(function ($links, string $hubspotKey) use ($appFieldsByKey, $mondayFields, $auditedLinks): array {
                $appField = $appFieldsByKey->get($hubspotKey, $links->first()['app_field']);
                $mondayMatches = collect($mondayFields)
                    ->map(function (array $mondayField) use ($hubspotKey, $appField): array {
                        $score = max(
                            $this->similarity($hubspotKey, $mondayField['key']),
                            $this->similarity($hubspotKey, $mondayField['label']),
                            $this->similarity($appField['label'], $mondayField['label']),
                            $this->similarity($appField['key'], $mondayField['local_field_key'] ?? '')
                        );

                        return array_merge($mondayField, ['confidence' => $score]);
                    })
                    ->filter(fn (array $field) => $field['confidence'] >= 60)
                    ->sortByDesc('confidence')
                    ->take(3)
                    ->values()
                    ->all();

                return [
                    'identity' => 'legacy:'.$hubspotKey,
                    'app' => $appField,
                    'hubspot' => [[
                        'key' => $hubspotKey,
                        'label' => $links->first()['hubspot_label'],
                        'scope_key' => '*',
                    ]],
                    'teamleader' => $links->map(fn (array $link) => [
                        'key' => $link['teamleader_key'],
                        'label' => $link['teamleader_label'],
                        'type' => $link['teamleader_type'],
                        'context' => $link['teamleader_context'],
                    ])->values()->all(),
                    'monday_matches' => $mondayMatches,
                    'match_method' => 'legacy_catalog',
                    'confidence' => 100,
                    'audit_links' => array_values(array_filter($auditedLinks, fn (array $audit) => $audit['app_field_key'] === $hubspotKey)),
                ];
            })
            ->values()
            ->all();
    }

    private function auditLinksFor(array $auditLinks, string $provider, string $fieldKey, string $scopeKey): array
    {
        return array_values(array_filter($auditLinks, fn (array $link) => $link['provider'] === $provider
            && $link['external_field_key'] === $fieldKey
            && $link['scope_key'] === $scopeKey));
    }

    private function activeMappingCount(): int
    {
        if (! Schema::hasTable('integration_field_mappings')) {
            return 0;
        }

        return DB::table('integration_field_mappings')->where('is_active', true)->count();
    }

    private function similarity(string $left, string $right): int
    {
        $left = $this->normalise($left);
        $right = $this->normalise($right);

        if ($left === '' || $right === '') {
            return 0;
        }

        if ($left === $right) {
            return 100;
        }

        $leftTokens = array_values(array_filter(explode(' ', $left)));
        $rightTokens = array_values(array_filter(explode(' ', $right)));
        $intersection = count(array_intersect($leftTokens, $rightTokens));
        $union = count(array_unique(array_merge($leftTokens, $rightTokens)));
        $tokenScore = $union > 0 ? (int) round(($intersection / $union) * 100) : 0;

        $maxLength = max(strlen($left), strlen($right));
        $distanceScore = $maxLength > 0
            ? (int) round((1 - (levenshtein($left, $right) / $maxLength)) * 100)
            : 0;

        return max($tokenScore, $distanceScore);
    }

    private function normalise(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    private function labelFor(string $key): string
    {
        return Str::of($key)
            ->replace('__', ' ')
            ->replace('_', ' ')
            ->squish()
            ->ucfirst()
            ->toString();
    }
}
