<?php

namespace App\Services;

use App\Models\CustomFieldDefinition;
use App\Models\UnificationAuditLink;
use App\Models\UnificationAuditRelation;
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
    public function inventory(bool $includeAutomaticRelations = true): array
    {
        $legacyLinks = $this->legacyLinks();
        $clientFields = $this->appFields($legacyLinks);
        $businessFields = $this->businessFields();
        $projectFields = $this->projectFields();
        $mondayFields = $this->mondayFields();
        $auditedLinks = $this->auditedLinks();
        $auditedRelations = $this->auditedRelations();
        $platformFields = $this->platformFields(
            $legacyLinks,
            $clientFields,
            $businessFields,
            $projectFields,
            $mondayFields,
        );

        $mapRows = $this->buildMapRows($legacyLinks, $clientFields, $mondayFields, $auditedLinks);
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
        foreach ($clientFields as $appField) {
            if ($legacyAppKeys->has($appField['key'])) {
                continue;
            }

            $mapRows[] = [
                'identity' => 'app:'.$appField['key'],
                'app' => array_merge($appField, ['entity_type' => 'client']),
                'hubspot' => [],
                'teamleader' => [],
                'monday_matches' => [],
                'match_method' => 'app_only',
                'confidence' => null,
                'audit_links' => $this->auditLinksFor($auditedLinks, 'app', $appField['key'], '*'),
            ];
        }

        $mapRows = array_merge(
            $mapRows,
            $this->buildCommercialMapRows($businessFields, $platformFields),
        );

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
                    'entity_type' => 'client',
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

        $derivedRelations = $this->derivedRelations(
            $legacyLinks,
            $auditedLinks,
            $auditedRelations,
        );
        $automaticRelations = $includeAutomaticRelations
            ? $this->automaticRelations($platformFields, $legacyLinks, $auditedRelations)
            : [];

        return [
            'summary' => [
                'legacy_associations' => count($legacyLinks),
                'hubspot_fields' => count($platformFields['hubspot']),
                'teamleader_fields' => count($platformFields['teamleader']),
                'app_fields' => count($platformFields['app']),
                'client_app_fields' => count($clientFields),
                'business_app_fields' => count($businessFields),
                'hubspot_contact_fields' => collect($platformFields['hubspot'])->where('entity_type', 'contact')->count(),
                'hubspot_deal_fields' => collect($platformFields['hubspot'])->where('entity_type', 'deal')->count(),
                'teamleader_contact_fields' => collect($platformFields['teamleader'])->where('entity_type', 'contact')->count(),
                'teamleader_project_fields' => collect($platformFields['teamleader'])->where('entity_type', 'project')->count(),
                'app_legacy_columns' => collect($legacyLinks)->filter(fn (array $link) => $link['app_field']['storage'] === 'users')->pluck('hubspot_key')->unique()->count(),
                'monday_fields' => count($platformFields['monday']),
                'audit_storage_ready' => Schema::hasTable('unification_audit_links'),
                'relation_storage_ready' => Schema::hasTable('unification_audit_relations'),
                'active_mappings' => $this->activeMappingCount(),
                'ai_batch_candidate_limit' => max(40, min(1000, (int) config('services.openrouter.unification_max_batch_candidates', 200))),
                'ai_suggestions_available' => filled(config('services.openrouter.key')),
                'direct_audit_relations' => count($auditedRelations),
                'derived_relations' => count($derivedRelations),
                'automatic_relations' => $includeAutomaticRelations ? count($automaticRelations) : null,
                'automatic_relations_loaded' => $includeAutomaticRelations,
            ],
            'map_rows' => $mapRows,
            'audited_links' => $auditedLinks,
            'audited_relations' => $auditedRelations,
            'derived_relations' => $derivedRelations,
            'automatic_relations' => $automaticRelations,
            'field_options' => $platformFields,
        ];
    }

    /**
     * Computes automatic suggestions only when an administrator requests them.
     * The main map deliberately skips this potentially expensive comparison.
     */
    public function paginatedAutomaticRelations(?string $leftProvider, ?string $rightProvider, int $page, int $perPage): array
    {
        $legacyLinks = $this->legacyLinks();
        $platformFields = $this->fieldOptions();
        $relations = collect($this->automaticRelations(
            $platformFields,
            $legacyLinks,
            $this->auditedRelations(),
        ))->filter(function (array $relation) use ($leftProvider, $rightProvider): bool {
            if (blank($leftProvider) || blank($rightProvider)) {
                return true;
            }

            return ($relation['left']['provider'] === $leftProvider && $relation['right']['provider'] === $rightProvider)
                || ($relation['left']['provider'] === $rightProvider && $relation['right']['provider'] === $leftProvider);
        })->values();

        $perPage = max(10, min(100, $perPage));
        $total = $relations->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));

        return [
            'data' => $relations->forPage($page, $perPage)->values()->all(),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => $page < $lastPage,
            ],
        ];
    }

    /**
     * Returns the selectable local catalog without building map rows or
     * automatic suggestions. Controllers use this for validation and the
     * field picker endpoint so a large catalog is never sent to the browser.
     */
    public function fieldOptions(): array
    {
        $legacyLinks = $this->legacyLinks();
        $clientFields = $this->appFields($legacyLinks);
        $businessFields = $this->businessFields();
        $projectFields = $this->projectFields();
        $mondayFields = $this->mondayFields();

        return $this->platformFields(
            $legacyLinks,
            $clientFields,
            $businessFields,
            $projectFields,
            $mondayFields,
        );
    }

    /**
     * Keeps the relation builder responsive even when a provider has many
     * hundreds of fields or Monday boards.
     */
    public function paginatedFieldOptions(string $provider, ?string $search, int $page, int $perPage, ?string $entityType = null): array
    {
        $options = $this->fieldOptions();
        $term = $this->normalise((string) $search);
        $fields = collect($options[$provider] ?? [])
            ->filter(function (array $field) use ($term, $entityType): bool {
                if (filled($entityType) && ($field['entity_type'] ?? null) !== $entityType) {
                    return false;
                }
                if ($term === '') {
                    return true;
                }

                return str_contains($this->normalise(implode(' ', [
                    $field['label'] ?? '',
                    $field['key'] ?? '',
                    $field['scope_key'] ?? '',
                    $field['scope_label'] ?? '',
                    $field['source'] ?? '',
                ])), $term);
            })
            ->sortBy(fn (array $field) => implode('|', [
                $field['scope_label'] ?? $field['scope_key'] ?? '',
                Str::lower((string) ($field['label'] ?? '')),
                $field['key'] ?? '',
            ]))
            ->values();

        $total = $fields->count();
        $perPage = max(10, min(100, $perPage));
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));

        return [
            'data' => $fields->forPage($page, $perPage)->values()->all(),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => $page < $lastPage,
            ],
        ];
    }

    /**
     * The ER view is deliberately based on the canonical local model and on
     * approved design relations only. Proposed links remain audit work, not
     * structural facts in the diagram.
     */
    public function erDiagram(): array
    {
        $fieldOptions = $this->fieldOptions();
        $approvedRelations = collect($this->auditedRelations())
            ->where('status', 'approved')
            ->values();
        $providerRelationCounts = [];

        foreach (['hubspot', 'teamleader', 'monday'] as $provider) {
            $providerRelationCounts[$provider] = $approvedRelations
                ->filter(fn (array $relation) => $relation['left']['provider'] === $provider
                    || $relation['right']['provider'] === $provider)
                ->count();
        }

        $crossBoardMondayRelations = $approvedRelations
            ->filter(fn (array $relation) => $relation['left']['provider'] === 'monday'
                && $relation['right']['provider'] === 'monday'
                && $relation['left']['scope_key'] !== $relation['right']['scope_key'])
            ->count();

        $nodes = [
            'users' => ['x' => 35, 'y' => 75, 'title' => 'users', 'lines' => ['Cliente canónico', 'id']],
            'negocios' => ['x' => 35, 'y' => 255, 'title' => 'negocios', 'lines' => ['Operación comercial', 'user_id']],
            'custom_definitions' => ['x' => 285, 'y' => 35, 'title' => 'custom_field_definitions', 'lines' => ['Definición de campo', 'entity_type, key']],
            'custom_values' => ['x' => 285, 'y' => 205, 'title' => 'custom_field_values', 'lines' => ['Valor flexible', 'entity_id, definition_id']],
            'external_links' => ['x' => 535, 'y' => 35, 'title' => 'external_entity_links', 'lines' => ['Identidad externa', 'provider, entity_id']],
            'workflow_boards' => ['x' => 535, 'y' => 205, 'title' => 'workflow_boards', 'lines' => ['Tablero local', 'provider, external_board_id']],
            'workflow_memberships' => ['x' => 535, 'y' => 375, 'title' => 'workflow_memberships', 'lines' => ['Cliente en tablero', 'entity_id, workflow_board_id']],
            'audit_relations' => ['x' => 785, 'y' => 145, 'title' => 'unification_audit_relations', 'lines' => ['Diseño aprobado', $approvedRelations->count().' relaciones']],
            'mappings' => ['x' => 785, 'y' => 345, 'title' => 'integration_field_mappings', 'lines' => ['Mapeo operativo', 'No se activa desde auditoría']],
            'hubspot' => ['x' => 1040, 'y' => 35, 'title' => 'HubSpot', 'lines' => [count($fieldOptions['hubspot']).' campos locales', $providerRelationCounts['hubspot'].' asociaciones aprobadas']],
            'teamleader' => ['x' => 1040, 'y' => 215, 'title' => 'Teamleader', 'lines' => [count($fieldOptions['teamleader']).' campos locales', $providerRelationCounts['teamleader'].' asociaciones aprobadas']],
            'monday' => ['x' => 1040, 'y' => 395, 'title' => 'Monday', 'lines' => [count($fieldOptions['monday']).' campos en tableros', $crossBoardMondayRelations.' asociaciones entre tableros']],
        ];

        $edges = [
            ['from' => 'users', 'to' => 'custom_values', 'label' => 'tiene valores'],
            ['from' => 'custom_definitions', 'to' => 'custom_values', 'label' => 'define'],
            ['from' => 'users', 'to' => 'external_links', 'label' => 'identidad'],
            ['from' => 'users', 'to' => 'workflow_memberships', 'label' => 'participa'],
            ['from' => 'workflow_boards', 'to' => 'workflow_memberships', 'label' => 'contiene'],
            ['from' => 'audit_relations', 'to' => 'mappings', 'label' => 'revisión previa'],
            ['from' => 'audit_relations', 'to' => 'hubspot', 'label' => $providerRelationCounts['hubspot'].' aprobadas'],
            ['from' => 'audit_relations', 'to' => 'teamleader', 'label' => $providerRelationCounts['teamleader'].' aprobadas'],
            ['from' => 'audit_relations', 'to' => 'monday', 'label' => $providerRelationCounts['monday'].' aprobadas'],
        ];

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'approved_relations' => $approvedRelations->count(),
            'cross_board_monday_relations' => $crossBoardMondayRelations,
            'generated_at' => now()->format('Y-m-d H:i'),
        ];
    }

    public function renderErDiagramSvg(): string
    {
        $diagram = $this->erDiagram();
        $nodes = $diagram['nodes'];
        $edgeMarkup = '';

        foreach ($diagram['edges'] as $edge) {
            $from = $nodes[$edge['from']];
            $to = $nodes[$edge['to']];
            $fromX = $from['x'] + 205;
            $fromY = $from['y'] + 43;
            $toX = $to['x'];
            $toY = $to['y'] + 43;
            if ($toX < $fromX) {
                $fromX = $from['x'];
                $toX = $to['x'] + 205;
            }
            $labelX = (int) (($fromX + $toX) / 2);
            $labelY = (int) (($fromY + $toY) / 2) - 6;
            $label = $this->escapeXml($edge['label']);

            $edgeMarkup .= '<line x1="'.$fromX.'" y1="'.$fromY.'" x2="'.$toX.'" y2="'.$toY.'" class="edge" marker-end="url(#arrow)" />';
            $edgeMarkup .= '<text x="'.$labelX.'" y="'.$labelY.'" class="edge-label" text-anchor="middle">'.$label.'</text>';
        }

        $nodeMarkup = '';
        foreach ($nodes as $node) {
            $title = $this->escapeXml($node['title']);
            $firstLine = $this->escapeXml($node['lines'][0]);
            $secondLine = $this->escapeXml($node['lines'][1]);
            $nodeMarkup .= '<g class="node"><rect x="'.$node['x'].'" y="'.$node['y'].'" width="205" height="86" rx="5" />';
            $nodeMarkup .= '<text x="'.($node['x'] + 12).'" y="'.($node['y'] + 24).'" class="node-title">'.$title.'</text>';
            $nodeMarkup .= '<text x="'.($node['x'] + 12).'" y="'.($node['y'] + 49).'" class="node-line">'.$firstLine.'</text>';
            $nodeMarkup .= '<text x="'.($node['x'] + 12).'" y="'.($node['y'] + 69).'" class="node-line muted">'.$secondLine.'</text></g>';
        }

        $generatedAt = $this->escapeXml($diagram['generated_at']);

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1285 500" width="1285" height="500" role="img" aria-label="Diagrama ER de unificación">'
            .'<style>.background{fill:#f8fafc}.edge{stroke:#64748b;stroke-width:2;fill:none}.edge-label{font:11px Arial,sans-serif;fill:#475569}.node rect{fill:#fff;stroke:#cbd5e1;stroke-width:1.5}.node-title{font:700 13px Arial,sans-serif;fill:#1e3a5f}.node-line{font:12px Arial,sans-serif;fill:#334155}.muted{fill:#64748b}.footer{font:11px Arial,sans-serif;fill:#64748b}</style>'
            .'<defs><marker id="arrow" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto"><path d="M0,0 L8,4 L0,8 z" fill="#64748b" /></marker></defs>'
            .'<rect class="background" width="1285" height="500" />'
            .'<text x="35" y="25" class="node-title">Modelo de datos unificado</text>'
            .$edgeMarkup.$nodeMarkup
            .'<text x="35" y="485" class="footer">Generado '.$generatedAt.' · Las relaciones de auditoría aprobadas no activan sincronizaciones por sí solas.</text>'
            .'</svg>';
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
                $teamleaderDefinition = $teamleaderDefinitions[$teamleaderKey] ?? [];

                return [
                    'hubspot_key' => $hubspotKey,
                    'hubspot_label' => $this->labelFor($hubspotKey),
                    'teamleader_key' => $teamleaderKey,
                    'teamleader_label' => $teamleaderDefinition['label'] ?? $teamleaderKey,
                    'teamleader_type' => $teamleaderDefinition['type'] ?? null,
                    'teamleader_context' => ($teamleaderDefinition['context'] ?? null) ?: 'contact',
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

        // The selector must expose every currently known App attribute, not
        // only the subset that appears in the historical HubSpot catalogue.
        if (Schema::hasTable('users')) {
            foreach (Schema::getColumnListing('users') as $column) {
                $existing = $fields[$column] ?? [];
                $fields[$column] = array_merge($existing, [
                    'key' => $column,
                    'label' => $existing['label'] ?? $this->labelFor($column),
                    'storage' => 'users',
                    'source' => $existing ? 'Columna histórica + catálogo legado' : 'Columna de users',
                ]);
            }
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

    /**
     * The legacy negocios table is the local representation of a commercial
     * record. It is intentionally catalogued separately from users so a deal
     * is never presented as a client/contact field.
     */
    private function businessFields(): array
    {
        if (! Schema::hasTable('negocios')) {
            return [];
        }

        return collect(Schema::getColumnListing('negocios'))
            ->reject(fn (string $column) => $this->isBusinessIdentityColumn($column))
            ->map(fn (string $column) => [
                'key' => $column,
                'label' => $this->labelFor($column),
                'storage' => 'negocios',
                'source' => 'Columna local de negocios (catálogo de Deals por confirmar)',
                'entity_type' => 'business',
            ])
            ->sortBy('label')
            ->values()
            ->all();
    }

    /**
     * These are the stable, local Project attributes. Project custom fields
     * are added independently from tl_custom_field_definitions when their
     * context is project.
     */
    private function projectFields(): array
    {
        if (! Schema::hasTable('tl_projects')) {
            return [];
        }

        return collect(Schema::getColumnListing('tl_projects'))
            ->reject(fn (string $column) => in_array($column, [
                'id', 'customer_id', 'responsible_user_id', 'participants',
                'milestones', 'custom_fields', 'tags', 'raw_data',
                'tl_created_at', 'tl_updated_at', 'created_at', 'updated_at',
            ], true))
            ->map(fn (string $column) => [
                'key' => $column,
                'label' => $this->labelFor($column),
                'type' => null,
                'entity_type' => 'project',
                'scope_key' => '*',
                'source' => 'Campo estructural de proyecto Teamleader',
            ])
            ->sortBy('label')
            ->values()
            ->all();
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
        $boardLabels = $this->mondayBoardLabels();

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
                        'scope_label' => $boardLabels[$scopeKey] ?? 'Tablero '.$scopeKey,
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
                        'scope_label' => $existing['scope_label'] ?? ($boardLabels[$scopeKey] ?? 'Tablero '.$scopeKey),
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

    private function mondayBoardLabels(): array
    {
        $labels = [];

        if (Schema::hasTable('workflow_boards')) {
            DB::table('workflow_boards')
                ->where('provider', 'monday')
                ->select(['external_board_id', 'name'])
                ->get()
                ->each(function ($board) use (&$labels): void {
                    $labels[(string) $board->external_board_id] = (string) $board->name;
                });
        }

        if (Schema::hasTable('servicios') && Schema::hasColumn('servicios', 'monday_board_id')) {
            DB::table('servicios')
                ->whereNotNull('monday_board_id')
                ->select(['monday_board_id', 'nombre'])
                ->get()
                ->each(function ($service) use (&$labels): void {
                    $boardId = (string) $service->monday_board_id;
                    if ($boardId !== '' && ! isset($labels[$boardId]) && filled($service->nombre)) {
                        $labels[$boardId] = (string) $service->nombre;
                    }
                });
        }

        return $labels;
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

    private function auditedRelations(): array
    {
        if (! Schema::hasTable('unification_audit_relations')) {
            return [];
        }

        return UnificationAuditRelation::query()
            ->latest('updated_at')
            ->get()
            ->map(fn (UnificationAuditRelation $relation) => [
                'id' => $relation->id,
                'left' => $this->endpoint(
                    $relation->left_provider,
                    $relation->left_entity_type,
                    $relation->left_scope_key,
                    $relation->left_field_key,
                    $relation->left_field_label,
                ),
                'right' => $this->endpoint(
                    $relation->right_provider,
                    $relation->right_entity_type,
                    $relation->right_scope_key,
                    $relation->right_field_key,
                    $relation->right_field_label,
                ),
                'match_method' => $relation->match_method,
                'confidence' => $relation->confidence,
                'status' => $relation->status,
                'notes' => $relation->notes,
            ])
            ->all();
    }

    private function platformFields(
        array $legacyLinks,
        array $clientFields,
        array $businessFields,
        array $projectFields,
        array $mondayFields,
    ): array
    {
        $hubspot = collect($legacyLinks)
            ->groupBy('hubspot_key')
            ->map(fn ($links, string $key) => [
                'key' => $key,
                'label' => $links->first()['hubspot_label'],
                'provider' => 'hubspot',
                'entity_type' => 'contact',
                'scope_key' => '*',
                'source' => 'Catálogo legado',
            ])
            ->keyBy(fn (array $field) => 'contact|'.$field['key']);

        // Existing direct users columns are already used by the legacy
        // HubSpot reader as candidate property names. Show them all, but mark
        // the ones outside the historical catalogue as inferred until a live
        // HubSpot catalogue refresh confirms them.
        foreach ($clientFields as $field) {
            $identity = 'contact|'.$field['key'];
            if (($field['storage'] ?? null) !== 'users' || $hubspot->has($identity)) {
                continue;
            }

            $hubspot->put($identity, [
                'key' => $field['key'],
                'label' => $field['label'],
                'provider' => 'hubspot',
                'entity_type' => 'contact',
                'scope_key' => '*',
                'source' => 'Inferido desde columna de users; confirmar en HubSpot',
            ]);
        }

        // negocios is the local deal catalogue. Its fields are deliberately
        // recorded under HubSpot's deal entity, never under contact.
        foreach ($businessFields as $field) {
            $hubspot->put('deal|'.$field['key'], [
                'key' => $field['key'],
                'label' => $field['label'],
                'provider' => 'hubspot',
                'entity_type' => 'deal',
                'scope_key' => '*',
                'source' => 'Inferido desde catálogo local de negocios; confirmar en HubSpot Deals',
            ]);
        }

        $teamleader = collect($legacyLinks)
            ->groupBy('teamleader_key')
            ->map(fn ($links, string $key) => [
                'key' => $key,
                'label' => $links->first()['teamleader_label'],
                'provider' => 'teamleader',
                'entity_type' => $links->first()['teamleader_context'],
                'scope_key' => '*',
                'type' => $links->first()['teamleader_type'],
                'source' => 'Catálogo legado',
            ])
            ->keyBy(fn (array $field) => ($field['entity_type'] ?: 'contact').'|'.$field['key']);

        foreach ($this->teamleaderDefinitions() as $key => $field) {
            $context = $field['context'] ?: 'contact';
            $identity = $context.'|'.$key;
            if ($teamleader->has($identity)) {
                continue;
            }

            $teamleader->put($identity, [
                'key' => $key,
                'label' => $field['label'],
                'provider' => 'teamleader',
                'entity_type' => $context,
                'scope_key' => '*',
                'type' => $field['type'],
                'source' => 'Definición Teamleader local',
            ]);
        }

        foreach ($projectFields as $field) {
            $teamleader->put('project|'.$field['key'], array_merge($field, [
                'provider' => 'teamleader',
                'entity_type' => 'project',
            ]));
        }

        return [
            'app' => collect($clientFields)->map(fn (array $field) => array_merge($field, [
                'provider' => 'app',
                'entity_type' => 'client',
                'scope_key' => '*',
            ]))->merge(collect($businessFields)->map(fn (array $field) => array_merge($field, [
                'provider' => 'app',
                'entity_type' => 'business',
                'scope_key' => '*',
            ])))->sortBy(fn (array $field) => $field['entity_type'].'|'.$field['label'])->values()->all(),
            'hubspot' => $hubspot
                ->sortBy('label')
                ->values()
                ->all(),
            'teamleader' => $teamleader
                ->sortBy('label')
                ->values()
                ->all(),
            'monday' => collect($mondayFields)->map(fn (array $field) => array_merge($field, [
                'provider' => 'monday',
                'entity_type' => 'item',
            ]))->values()->all(),
        ];
    }

    /**
     * Finds A↔C candidates when two existing reference/approved relations form
     * A↔B↔C. The derived relation is a display-only audit suggestion.
     */
    private function derivedRelations(
        array $legacyLinks,
        array $auditedLinks,
        array $auditedRelations,
    ): array {
        $edges = [];
        $nodes = [];

        foreach ($legacyLinks as $link) {
            $app = $this->endpoint('app', 'client', '*', $link['app_field']['key'], $link['app_field']['label']);
            $hubspot = $this->endpoint('hubspot', 'contact', '*', $link['hubspot_key'], $link['hubspot_label']);
            $teamleader = $this->endpoint('teamleader', $link['teamleader_context'], '*', $link['teamleader_key'], $link['teamleader_label']);

            $this->addEdge($edges, $nodes, $app, $hubspot, 'Catálogo histórico App ↔ HubSpot');
            if ($this->areAutomaticEntityTypesCompatible($hubspot['entity_type'], $teamleader['entity_type'])) {
                $this->addEdge($edges, $nodes, $hubspot, $teamleader, 'Catálogo histórico HubSpot ↔ Teamleader');
            }
        }

        foreach ($auditedLinks as $link) {
            if ($link['status'] !== 'approved') {
                continue;
            }

            $this->addEdge(
                $edges,
                $nodes,
                $this->endpoint('app', 'client', '*', $link['app_field_key'], $link['app_field_label']),
                $this->endpoint($link['provider'], $link['external_entity_type'], $link['scope_key'], $link['external_field_key'], $link['external_field_label']),
                'Decisión de auditoría aprobada',
            );
        }

        foreach ($auditedRelations as $relation) {
            if ($relation['status'] === 'approved'
                && $this->areApprovedAuditEntityTypesCompatible($relation['left']['entity_type'], $relation['right']['entity_type'])) {
                $this->addEdge($edges, $nodes, $relation['left'], $relation['right'], 'Decisión de auditoría aprobada');
            }
        }

        $adjacency = [];
        foreach ($edges as $edgeKey => $edge) {
            $adjacency[$edge['left']['identity']][] = ['node' => $edge['right']['identity'], 'edge' => $edgeKey];
            $adjacency[$edge['right']['identity']][] = ['node' => $edge['left']['identity'], 'edge' => $edgeKey];
        }

        $derived = [];
        foreach ($adjacency as $throughId => $neighbours) {
            for ($leftIndex = 0; $leftIndex < count($neighbours); $leftIndex++) {
                for ($rightIndex = $leftIndex + 1; $rightIndex < count($neighbours); $rightIndex++) {
                    $leftId = $neighbours[$leftIndex]['node'];
                    $rightId = $neighbours[$rightIndex]['node'];
                    $directKey = $this->edgeKey($leftId, $rightId);

                    if ($leftId === $rightId || isset($edges[$directKey])) {
                        continue;
                    }

                    $candidateKey = $directKey.'|via|'.$throughId;
                    $derived[$candidateKey] = [
                        'identity' => $candidateKey,
                        'left' => $nodes[$leftId],
                        'through' => $nodes[$throughId],
                        'right' => $nodes[$rightId],
                        'basis' => array_values(array_unique(array_merge(
                            $edges[$neighbours[$leftIndex]['edge']]['basis'],
                            $edges[$neighbours[$rightIndex]['edge']]['basis'],
                        ))),
                    ];
                }
            }
        }

        return collect($derived)
            ->sortBy(fn (array $relation) => $relation['left']['provider'].'|'.$relation['right']['provider'].'|'.$relation['left']['label'])
            ->take(250)
            ->values()
            ->all();
    }

    /**
     * Builds deterministic suggestions across every locally known field. They
     * remain display-only until an administrator turns one into an audit
     * proposal. Exact label/key matches rank first; close semantic names are
     * presented with a lower confidence for human review.
     */
    private function automaticRelations(array $platformFields, array $legacyLinks, array $auditedRelations): array
    {
        $directKeys = [];
        foreach ($legacyLinks as $link) {
            $app = $this->endpoint('app', 'client', '*', $link['app_field']['key'], $link['app_field']['label']);
            $hubspot = $this->endpoint('hubspot', 'contact', '*', $link['hubspot_key'], $link['hubspot_label']);
            $teamleader = $this->endpoint('teamleader', $link['teamleader_context'], '*', $link['teamleader_key'], $link['teamleader_label']);
            $directKeys[$this->edgeKey($app['identity'], $hubspot['identity'])] = true;
            if ($this->areAutomaticEntityTypesCompatible($hubspot['entity_type'], $teamleader['entity_type'])) {
                $directKeys[$this->edgeKey($hubspot['identity'], $teamleader['identity'])] = true;
            }
        }
        foreach ($auditedRelations as $relation) {
            $directKeys[$this->edgeKey($relation['left']['identity'], $relation['right']['identity'])] = true;
        }

        $providers = array_keys($platformFields);
        $candidates = [];
        for ($leftProviderIndex = 0; $leftProviderIndex < count($providers); $leftProviderIndex++) {
            $leftProvider = $providers[$leftProviderIndex];
            $leftFields = $this->preparedAutomaticFields($platformFields[$leftProvider]);

            for ($rightProviderIndex = $leftProviderIndex + 1; $rightProviderIndex < count($providers); $rightProviderIndex++) {
                $rightProvider = $providers[$rightProviderIndex];
                $rightFields = $this->preparedAutomaticFields($platformFields[$rightProvider]);

                foreach ($leftFields as $leftField) {
                    foreach ($rightFields as $rightField) {
                        if (! $this->areAutomaticEntityTypesCompatible($leftField['entity_type'], $rightField['entity_type'])) {
                            continue;
                        }

                        $score = max(
                            $this->normalisedSimilarity($leftField['_normalised_label'], $rightField['_normalised_label']),
                            $this->normalisedSimilarity($leftField['_normalised_key'], $rightField['_normalised_key']),
                            $this->normalisedSimilarity($leftField['_normalised_label'], $rightField['_normalised_key']),
                            $this->normalisedSimilarity($leftField['_normalised_key'], $rightField['_normalised_label']),
                        );
                        if ($score < 86) {
                            continue;
                        }

                        $left = $this->endpoint(
                            $leftProvider,
                            $leftField['entity_type'],
                            $leftField['scope_key'],
                            $leftField['key'],
                            $leftField['label'],
                        );
                        $right = $this->endpoint(
                            $rightProvider,
                            $rightField['entity_type'],
                            $rightField['scope_key'],
                            $rightField['key'],
                            $rightField['label'],
                        );
                        $edgeKey = $this->edgeKey($left['identity'], $right['identity']);
                        if (isset($directKeys[$edgeKey])) {
                            continue;
                        }

                        $candidates[$edgeKey] = [
                            'identity' => $edgeKey,
                            'left' => $left,
                            'right' => $right,
                            'confidence' => $score,
                            'match_method' => $score === 100 ? 'exact_name' : 'similar_name',
                            'reason' => $score === 100
                                ? 'La clave o etiqueta coincide exactamente en ambos catálogos locales.'
                                : 'Las claves o etiquetas son muy similares; confirmar semántica, tipo y alcance.',
                            'left_source' => $leftField['source'] ?? null,
                            'right_source' => $rightField['source'] ?? null,
                        ];
                    }
                }
            }
        }

        return collect($candidates)
            ->sortByDesc('confidence')
            ->take(250)
            ->values()
            ->all();
    }

    private function preparedAutomaticFields(array $fields): mixed
    {
        return collect($fields)
            ->filter(fn (array $field) => $this->isAutomaticCandidate($field)
                && in_array($this->entityFamily((string) ($field['entity_type'] ?? '')), ['contact', 'commercial'], true))
            ->map(fn (array $field) => array_merge($field, [
                '_normalised_label' => $this->normalise((string) $field['label']),
                '_normalised_key' => $this->normalise((string) $field['key']),
            ]));
    }

    private function isAutomaticCandidate(array $field): bool
    {
        $key = Str::lower((string) ($field['key'] ?? ''));
        if ($key === '' || in_array($key, [
            'id', 'created_at', 'updated_at', 'deleted_at', 'remember_token',
            'password', 'password_md5', 'two_factor_secret', 'two_factor_recovery_codes',
        ], true)) {
            return false;
        }

        return ! str_contains($key, 'token')
            && ! str_contains($key, 'secret')
            && ! str_contains($key, 'session');
    }

    private function isBusinessIdentityColumn(string $column): bool
    {
        return in_array($column, [
            'id', 'hubspot_id', 'teamleader_id', 'user_id',
            'created_at', 'updated_at',
        ], true);
    }

    /**
     * A mapping can be suggested only within its semantic entity family. A
     * Monday item is intentionally not inferred: its meaning depends on the
     * board and must first be classified by an administrator.
     */
    private function areAutomaticEntityTypesCompatible(string $leftType, string $rightType): bool
    {
        $leftFamily = $this->entityFamily($leftType);
        $rightFamily = $this->entityFamily($rightType);

        return $leftFamily === $rightFamily
            && in_array($leftFamily, ['contact', 'commercial'], true);
    }

    private function areApprovedAuditEntityTypesCompatible(string $leftType, string $rightType): bool
    {
        if ($this->areAutomaticEntityTypesCompatible($leftType, $rightType)) {
            return true;
        }

        $leftFamily = $this->entityFamily($leftType);
        $rightFamily = $this->entityFamily($rightType);

        return ($leftFamily === 'workflow' && $rightFamily === 'workflow')
            || ($leftFamily === 'workflow' && in_array($rightFamily, ['contact', 'commercial'], true))
            || ($rightFamily === 'workflow' && in_array($leftFamily, ['contact', 'commercial'], true));
    }

    private function entityFamily(string $entityType): string
    {
        return match (Str::lower($entityType)) {
            'client', 'contact' => 'contact',
            'business', 'deal', 'project' => 'commercial',
            'item', 'workflow_item' => 'workflow',
            default => Str::lower($entityType),
        };
    }

    private function addEdge(array &$edges, array &$nodes, array $left, array $right, string $basis): void
    {
        $nodes[$left['identity']] = $left;
        $nodes[$right['identity']] = $right;
        $key = $this->edgeKey($left['identity'], $right['identity']);

        if (! isset($edges[$key])) {
            $edges[$key] = ['left' => $left, 'right' => $right, 'basis' => []];
        }

        $edges[$key]['basis'][] = $basis;
        $edges[$key]['basis'] = array_values(array_unique($edges[$key]['basis']));
    }

    private function endpoint(string $provider, string $entityType, string $scopeKey, string $fieldKey, ?string $label): array
    {
        $scopeKey = $scopeKey ?: '*';
        $fieldKey = (string) $fieldKey;

        return [
            'identity' => implode('|', [$provider, $entityType, $scopeKey, $fieldKey]),
            'provider' => $provider,
            'entity_type' => $entityType,
            'scope_key' => $scopeKey,
            'key' => $fieldKey,
            'label' => $label ?: $fieldKey,
        ];
    }

    private function edgeKey(string $leftIdentity, string $rightIdentity): string
    {
        return collect([$leftIdentity, $rightIdentity])->sort()->implode('↔');
    }

    private function buildMapRows(array $legacyLinks, array $appFields, array $mondayFields, array $auditedLinks): array
    {
        $appFieldsByKey = collect($appFields)->keyBy('key');

        return collect($legacyLinks)
            ->groupBy('hubspot_key')
            ->map(function ($links, string $hubspotKey) use ($appFieldsByKey, $auditedLinks): array {
                $appField = $appFieldsByKey->get($hubspotKey, $links->first()['app_field']);
                // A Monday item can represent a contact, a deal or a
                // workflow task depending on its board. Do not infer that
                // semantic type from a matching label alone.
                $mondayMatches = [];

                return [
                    'identity' => 'legacy:'.$hubspotKey,
                    'app' => array_merge($appField, ['entity_type' => 'client']),
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

    /**
     * Presents the operational/business catalogue separately. It only reads
     * existing local negocio, HubSpot Deal and Teamleader Project metadata;
     * no Deal or Project record is copied or linked by this map.
     */
    private function buildCommercialMapRows(array $businessFields, array $platformFields): array
    {
        $hubspotDeals = collect($platformFields['hubspot'])
            ->where('entity_type', 'deal')
            ->keyBy('key');
        $teamleaderProjects = collect($platformFields['teamleader'])
            ->where('entity_type', 'project');

        return collect($businessFields)
            ->map(function (array $businessField) use ($hubspotDeals, $teamleaderProjects): array {
                $deal = $hubspotDeals->get($businessField['key']);
                $projectMatches = $teamleaderProjects
                    ->map(function (array $projectField) use ($businessField): array {
                        $score = max(
                            $this->similarity($businessField['key'], $projectField['key']),
                            $this->similarity($businessField['label'], $projectField['label']),
                        );

                        return array_merge($projectField, ['confidence' => $score]);
                    })
                    ->filter(fn (array $projectField) => $projectField['confidence'] >= 60)
                    ->sortByDesc('confidence')
                    ->take(3)
                    ->map(fn (array $projectField) => [
                        'key' => $projectField['key'],
                        'label' => $projectField['label'],
                        'type' => $projectField['type'] ?? null,
                        'context' => 'project',
                        'entity_type' => 'project',
                        'confidence' => $projectField['confidence'],
                    ])
                    ->values()
                    ->all();

                return [
                    'identity' => 'business:'.$businessField['key'],
                    'entity_type' => 'business',
                    'app' => $businessField,
                    'hubspot' => $deal ? [[
                        'key' => $deal['key'],
                        'label' => $deal['label'],
                        'scope_key' => '*',
                        'entity_type' => 'deal',
                    ]] : [],
                    'teamleader' => $projectMatches,
                    'monday_matches' => [],
                    'match_method' => 'commercial_catalogue',
                    'confidence' => $deal ? 100 : null,
                    'audit_links' => [],
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
        return $this->normalisedSimilarity($this->normalise($left), $this->normalise($right));
    }

    private function normalisedSimilarity(string $left, string $right): int
    {

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

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
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
