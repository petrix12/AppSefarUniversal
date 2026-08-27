<?php

namespace App\Http\Controllers;

use App\Models\UnificationAuditLink;
use App\Models\UnificationAuditRelation;
use App\Services\MondayCatalogService;
use App\Services\UnificationAiSuggestionService;
use App\Services\UnificationMapAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class UnificationMapController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'can:administrador']);
    }

    public function map(Request $request, UnificationMapAuditService $map): mixed
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
        ]);
        // Automatic comparisons are O(n^2) across the local catalog. They
        // are requested separately so searching or paging this screen stays responsive.
        $inventory = $map->inventory(false);
        $mapRows = $this->filterMapRows($inventory['map_rows'], $data['q'] ?? null);
        $perPage = (int) ($data['per_page'] ?? 25);

        $inventory['map_rows_pagination'] = $this->paginateItems($mapRows, $request, 'map_page', $perPage);
        $inventory['map_rows'] = $inventory['map_rows_pagination']->items();
        $inventory['derived_relations_pagination'] = $this->paginateItems($inventory['derived_relations'], $request, 'derived_page', 20);
        $inventory['derived_relations'] = $inventory['derived_relations_pagination']->items();
        $inventory['audited_relations_pagination'] = $this->paginateItems($inventory['audited_relations'], $request, 'relation_page', 20);
        $inventory['audited_relations'] = $inventory['audited_relations_pagination']->items();
        $inventory['audited_links_pagination'] = $this->paginateItems($inventory['audited_links'], $request, 'audit_page', 20);
        $inventory['audited_links'] = $inventory['audited_links_pagination']->items();

        // Field pickers query their own compact endpoint on demand.
        unset($inventory['field_options']);

        return view('unification-map.index', $inventory);
    }

    public function fields(Request $request, UnificationMapAuditService $map): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(UnificationAuditRelation::PROVIDERS)],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        return response()->json($map->paginatedFieldOptions(
            $data['provider'],
            $data['search'] ?? null,
            (int) ($data['page'] ?? 1),
            (int) ($data['per_page'] ?? 50),
        ));
    }

    public function mondayBoards(MondayCatalogService $catalog): JsonResponse
    {
        try {
            return response()->json(['data' => $catalog->boards()]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }
    }

    public function mondayFields(Request $request, MondayCatalogService $catalog): JsonResponse
    {
        $data = $request->validate([
            'board_id' => ['required', 'regex:/^\d+$/'],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        try {
            $term = Str::lower(trim((string) ($data['search'] ?? '')));
            $fields = collect($catalog->columns($data['board_id']))
                ->map(fn (array $field): array => [
                    'identity' => $data['board_id'].':'.$field['id'],
                    'provider' => 'monday',
                    'entity_type' => 'item',
                    'scope_key' => $data['board_id'],
                    'key' => $field['id'],
                    'label' => $field['name'],
                    'type' => $field['type'],
                    'source' => 'Catálogo de Monday',
                ])
                ->filter(fn (array $field): bool => $term === '' || str_contains(
                    Str::lower($field['label'].' '.$field['key'].' '.$field['type']),
                    $term,
                ))
                ->values();
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        $perPage = (int) ($data['per_page'] ?? 50);
        $page = max(1, (int) ($data['page'] ?? 1));
        $lastPage = max(1, (int) ceil($fields->count() / $perPage));
        $page = min($page, $lastPage);

        return response()->json([
            'data' => $fields->forPage($page, $perPage)->values()->all(),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $fields->count(),
                'has_more' => $page < $lastPage,
            ],
        ]);
    }

    public function automaticRelations(Request $request, UnificationMapAuditService $map): JsonResponse
    {
        $data = $request->validate([
            'left_provider' => ['nullable', Rule::in(UnificationAuditRelation::PROVIDERS)],
            'right_provider' => ['nullable', Rule::in(UnificationAuditRelation::PROVIDERS)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        return response()->json($map->paginatedAutomaticRelations(
            $data['left_provider'] ?? null,
            $data['right_provider'] ?? null,
            (int) ($data['page'] ?? 1),
            (int) ($data['per_page'] ?? 25),
        ));
    }

    public function diagram(Request $request, UnificationMapAuditService $map): mixed
    {
        if ($request->query('format') === 'svg') {
            $response = response($map->renderErDiagramSvg(), 200, [
                'Content-Type' => 'image/svg+xml; charset=UTF-8',
            ]);

            if ($request->boolean('download')) {
                $response->header('Content-Disposition', 'attachment; filename="diagrama-er-unificacion.svg"');
            }

            return $response;
        }

        return view('unification-map.diagram', [
            'diagram' => $map->erDiagram(),
        ]);
    }

    /**
     * Stores a proposal in the audit ledger only. It intentionally does not
     * create CustomFieldDefinitions or IntegrationFieldMappings.
     */
    public function store(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('unification_audit_links')) {
            return back()->with('error', 'El registro de auditoría aún no está disponible. Esta pantalla sigue en modo lectura y no se ha cambiado ningún proceso.');
        }

        $data = $request->validate([
            'app_field_key' => ['required', 'string', 'max:191'],
            'app_field_label' => ['required', 'string', 'max:255'],
            'provider' => ['required', Rule::in(['hubspot', 'teamleader', 'monday'])],
            'external_entity_type' => ['required', 'string', 'max:64'],
            'scope_key' => ['nullable', 'string', 'max:191'],
            'external_field_key' => ['required', 'string', 'max:191'],
            'external_field_label' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $key = Str::of($data['app_field_key'])
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9_]+/', '_')
            ->trim('_')
            ->toString();

        if ($key === '') {
            return back()->withErrors(['app_field_key' => 'Indica un identificador válido para el campo de la App.'])->withInput();
        }

        UnificationAuditLink::create([
            'entity_type' => 'client',
            'app_field_key' => $key,
            'app_field_label' => $data['app_field_label'],
            'provider' => $data['provider'],
            'external_entity_type' => $data['external_entity_type'],
            'scope_key' => $data['scope_key'] ?: '*',
            'external_field_key' => $data['external_field_key'],
            'external_field_label' => $data['external_field_label'] ?: null,
            'match_method' => 'manual',
            'status' => 'proposed',
            'notes' => $data['notes'] ?? null,
            'created_by_user_id' => $request->user()->id,
            'metadata' => ['created_from' => 'unification_map'],
        ]);

        return redirect()
            ->route('unification-map.index')
            ->with('success', 'Propuesta guardada en el registro de auditoría. No se creó ningún campo operativo ni se activó ninguna sincronización.');
    }

    /**
     * A review changes only the audit decision. Promotion to an active mapping
     * is deliberately outside this controller and requires a later workflow.
     */
    public function review(Request $request, UnificationAuditLink $auditLink): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(UnificationAuditLink::STATUSES)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $auditLink->update([
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Decisión de auditoría actualizada. Sigue sin existir ningún mapeo activo a partir de esta decisión.');
    }

    /**
     * An administrator explicitly invokes this endpoint for a selected pair
     * of platforms. The suggestion is returned to the browser and is never
     * persisted.
     */
    public function suggest(
        Request $request,
        UnificationMapAuditService $map,
        UnificationAiSuggestionService $suggestions,
    ): JsonResponse {
        $data = $request->validate([
            'left_provider' => ['required', Rule::in(UnificationAuditRelation::PROVIDERS)],
            'right_provider' => ['required', Rule::in(UnificationAuditRelation::PROVIDERS)],
            'left_entity_type' => ['nullable', 'string', 'max:64'],
            'left_scope_key' => ['nullable', 'string', 'max:191'],
            'left_field_key' => ['nullable', 'string', 'max:191'],
            'left_field_label' => ['nullable', 'string', 'max:255'],
            'right_entity_type' => ['nullable', 'string', 'max:64'],
            'right_scope_key' => ['nullable', 'string', 'max:191'],
            'right_field_key' => ['nullable', 'string', 'max:191'],
            'right_field_label' => ['nullable', 'string', 'max:255'],
            'batch_pairs' => ['nullable', 'array', 'max:1000'],
            'batch_pairs.*.left.entity_type' => ['required_with:batch_pairs', 'string', 'max:64'],
            'batch_pairs.*.left.scope_key' => ['nullable', 'string', 'max:191'],
            'batch_pairs.*.left.field_key' => ['required_with:batch_pairs', 'string', 'max:191'],
            'batch_pairs.*.left.field_label' => ['nullable', 'string', 'max:255'],
            'batch_pairs.*.right.entity_type' => ['required_with:batch_pairs', 'string', 'max:64'],
            'batch_pairs.*.right.scope_key' => ['nullable', 'string', 'max:191'],
            'batch_pairs.*.right.field_key' => ['required_with:batch_pairs', 'string', 'max:191'],
            'batch_pairs.*.right.field_label' => ['nullable', 'string', 'max:255'],
        ]);

        if (! $suggestions->available()) {
            return response()->json([
                'message' => 'Configura OPENROUTER_API_KEY para usar sugerencias IA.',
            ], 422);
        }

        if ($data['left_provider'] === $data['right_provider'] && $data['left_provider'] !== 'monday') {
            return response()->json([
                'message' => 'Solo Monday permite comparar campos de la misma plataforma, siempre que provengan de tableros distintos.',
            ], 422);
        }

        $fieldOptions = $map->fieldOptions();
        $batchCandidates = $this->selectedBatchCandidates($data, $fieldOptions);
        if ($batchCandidates === false) {
            return response()->json([
                'message' => 'Una o más parejas del lote ya no existen en el catálogo local o mezclan entidades incompatibles. Actualiza el mapa y revisa el lote.',
            ], 422);
        }
        if (is_array($batchCandidates) && count($batchCandidates) > $this->batchCandidateLimit()) {
            return response()->json([
                'message' => 'El lote contiene '.count($batchCandidates).' parejas y supera el máximo configurado de '.$this->batchCandidateLimit().'. Divide la revisión o ajusta OPENROUTER_UNIFICATION_MAX_BATCH_CANDIDATES.',
            ], 422);
        }

        $selectedCandidate = is_array($batchCandidates)
            ? null
            : $this->selectedPairCandidate($data, $fieldOptions);
        if ($selectedCandidate === false) {
            return response()->json([
                'message' => 'Los campos seleccionados ya no existen en el catálogo local. Actualiza el mapa y selecciona ambos de nuevo.',
            ], 422);
        }
        if (is_array($selectedCandidate)
            && ! $this->canAuditEntityTypesBeMapped($selectedCandidate['left']['entity_type'], $selectedCandidate['right']['entity_type'])) {
            return response()->json([
                'message' => 'La IA no puede asociar Contacto/cliente con Negocio/Deal/Proyecto. Selecciona entidades equivalentes.',
            ], 422);
        }

        $candidates = is_array($batchCandidates)
            ? $batchCandidates
            : (is_array($selectedCandidate)
            ? [$selectedCandidate]
            : $this->automaticCandidatesForProviders($data, $map->inventory()['automatic_relations']));

        try {
            return response()->json([
                'suggestion' => is_array($batchCandidates)
                    ? $suggestions->suggestPlatformPairBatch(
                        $data['left_provider'],
                        $data['right_provider'],
                        $candidates,
                    )
                    : $suggestions->suggestPlatformPair(
                    $data['left_provider'],
                    $data['right_provider'],
                    $candidates,
                ),
            ]);
        } catch (\RuntimeException $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 502);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Error local al preparar la sugerencia IA. Revisa el log de Laravel para el detalle técnico.',
            ], 500);
        }
    }

    /**
     * Stores one generic App/HubSpot/Teamleader/Monday relation as a design
     * proposal. It does not create a real integration mapping.
     */
    public function storeRelation(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('unification_audit_relations')) {
            return back()->with('error', 'El registro de relaciones de auditoría aún no está disponible. No se ha modificado ningún proceso.');
        }

        $data = $request->validate([
            'left_provider' => ['required', Rule::in(UnificationAuditRelation::PROVIDERS)],
            'left_entity_type' => ['required', 'string', 'max:64'],
            'left_scope_key' => ['nullable', 'string', 'max:191'],
            'left_field_key' => ['required', 'string', 'max:191'],
            'left_field_label' => ['nullable', 'string', 'max:255'],
            'right_provider' => ['required', Rule::in(UnificationAuditRelation::PROVIDERS)],
            'right_entity_type' => ['required', 'string', 'max:64'],
            'right_scope_key' => ['nullable', 'string', 'max:191'],
            'right_field_key' => ['required', 'string', 'max:191'],
            'right_field_label' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $leftScope = $data['left_scope_key'] ?: '*';
        $rightScope = $data['right_scope_key'] ?: '*';
        if ($data['left_provider'] === $data['right_provider'] && $data['left_provider'] !== 'monday') {
            return back()->withErrors([
                'left_field_key' => 'Solo Monday permite asociar campos de la misma plataforma entre tableros distintos.',
            ])->withInput();
        }
        if (! $this->canAuditEntityTypesBeMapped($data['left_entity_type'], $data['right_entity_type'])) {
            return back()->withErrors([
                'left_field_key' => 'No se puede asociar un campo de contacto/cliente con un campo de negocio/proyecto. Selecciona Contacto ↔ Contacto o Negocio ↔ Deal/Proyecto. Monday se permite solo como tablero clasificado manualmente.',
            ])->withInput();
        }

        if ($data['left_provider'] === $data['right_provider']
            && $data['left_entity_type'] === $data['right_entity_type']
            && $leftScope === $rightScope
            && $data['left_field_key'] === $data['right_field_key']) {
            return back()->withErrors(['left_field_key' => 'Selecciona dos campos distintos para crear una relación.'])->withInput();
        }

        $sameDirection = [
            ['left_provider', $data['left_provider']], ['left_entity_type', $data['left_entity_type']], ['left_scope_key', $leftScope], ['left_field_key', $data['left_field_key']],
            ['right_provider', $data['right_provider']], ['right_entity_type', $data['right_entity_type']], ['right_scope_key', $rightScope], ['right_field_key', $data['right_field_key']],
        ];
        $reverseDirection = [
            ['left_provider', $data['right_provider']], ['left_entity_type', $data['right_entity_type']], ['left_scope_key', $rightScope], ['left_field_key', $data['right_field_key']],
            ['right_provider', $data['left_provider']], ['right_entity_type', $data['left_entity_type']], ['right_scope_key', $leftScope], ['right_field_key', $data['left_field_key']],
        ];
        $exists = collect([$sameDirection, $reverseDirection])->contains(function (array $conditions): bool {
            $query = UnificationAuditRelation::query();
            foreach ($conditions as [$column, $value]) {
                $query->where($column, $value);
            }

            return $query->exists();
        });
        if ($exists) {
            return back()->with('error', 'Esa relación ya existe en el registro de auditoría. Revisa su estado en lugar de duplicarla.');
        }

        UnificationAuditRelation::create([
            'entity_type' => $this->mappingEntityType($data['left_entity_type'], $data['right_entity_type']),
            'left_provider' => $data['left_provider'],
            'left_entity_type' => $data['left_entity_type'],
            'left_scope_key' => $leftScope,
            'left_field_key' => $data['left_field_key'],
            'left_field_label' => $data['left_field_label'] ?: null,
            'right_provider' => $data['right_provider'],
            'right_entity_type' => $data['right_entity_type'],
            'right_scope_key' => $rightScope,
            'right_field_key' => $data['right_field_key'],
            'right_field_label' => $data['right_field_label'] ?: null,
            'match_method' => 'manual',
            'status' => 'proposed',
            'notes' => $data['notes'] ?? null,
            'created_by_user_id' => $request->user()->id,
            'metadata' => ['created_from' => 'unification_map_relation_builder'],
        ]);

        return redirect()->route('unification-map.index')->with(
            'success',
            'Relación guardada para auditoría. No se creó ningún campo ni se activó una sincronización.'
        );
    }

    public function reviewRelation(Request $request, UnificationAuditRelation $auditRelation): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(UnificationAuditRelation::STATUSES)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $auditRelation->update([
            'status' => $data['status'],
            'notes' => $data['notes'] ?: null,
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Relación de auditoría actualizada. Una aprobación solo habilita relaciones derivadas en el mapa; no activa integraciones.');
    }

    /**
     * Persists an administrator-confirmed group of AI suggestions as audit
     * proposals only. It deliberately does not create active mappings.
     */
    public function storeRelationsBulk(Request $request, UnificationMapAuditService $map): JsonResponse
    {
        if (! Schema::hasTable('unification_audit_relations')) {
            return response()->json([
                'message' => 'El registro de relaciones de auditoría aún no está disponible. No se ha modificado ningún proceso.',
            ], 422);
        }

        $data = $request->validate([
            'relations' => ['required', 'array', 'min:1', 'max:1000'],
            'relations.*.left_provider' => ['required', Rule::in(UnificationAuditRelation::PROVIDERS)],
            'relations.*.left_entity_type' => ['required', 'string', 'max:64'],
            'relations.*.left_scope_key' => ['nullable', 'string', 'max:191'],
            'relations.*.left_field_key' => ['required', 'string', 'max:191'],
            'relations.*.left_field_label' => ['nullable', 'string', 'max:255'],
            'relations.*.right_provider' => ['required', Rule::in(UnificationAuditRelation::PROVIDERS)],
            'relations.*.right_entity_type' => ['required', 'string', 'max:64'],
            'relations.*.right_scope_key' => ['nullable', 'string', 'max:191'],
            'relations.*.right_field_key' => ['required', 'string', 'max:191'],
            'relations.*.right_field_label' => ['nullable', 'string', 'max:255'],
            'relations.*.confidence' => ['nullable', 'integer', 'between:0,100'],
            'relations.*.reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $configuredLimit = $this->batchCandidateLimit();
        if (count($data['relations']) > $configuredLimit) {
            return response()->json([
                'message' => "Seleccionaste más de {$configuredLimit} propuestas. Divide el guardado en lotes más pequeños o ajusta OPENROUTER_UNIFICATION_MAX_BATCH_CANDIDATES.",
            ], 422);
        }

        $fieldOptions = $map->fieldOptions();
        $records = [];
        $seen = [];
        $skippedExisting = 0;
        foreach ($data['relations'] as $index => $relation) {
            if ($relation['left_provider'] === $relation['right_provider'] && $relation['left_provider'] !== 'monday') {
                return response()->json([
                    'message' => 'Solo Monday permite asociar campos de la misma plataforma entre tableros distintos.',
                ], 422);
            }
            $left = $this->endpointForField(
                $relation['left_provider'],
                $relation['left_entity_type'],
                ($relation['left_scope_key'] ?? '*') ?: '*',
                $relation['left_field_key'],
                $fieldOptions,
                $relation['left_field_label'] ?? null,
            );
            $right = $this->endpointForField(
                $relation['right_provider'],
                $relation['right_entity_type'],
                ($relation['right_scope_key'] ?? '*') ?: '*',
                $relation['right_field_key'],
                $fieldOptions,
                $relation['right_field_label'] ?? null,
            );
            if (! $left || ! $right || ! $this->canAuditEntityTypesBeMapped($left['entity_type'], $right['entity_type'])) {
                return response()->json([
                    'message' => 'La propuesta '.($index + 1).' ya no existe en el catálogo local o mezcla entidades incompatibles. No se guardó ninguna asociación.',
                ], 422);
            }
            if ($left['identity'] === $right['identity']) {
                return response()->json([
                    'message' => 'Una propuesta del lote relaciona un campo consigo mismo. No se guardó ninguna asociación.',
                ], 422);
            }

            $identity = $this->relationIdentity($left, $right);
            if (isset($seen[$identity])) {
                continue;
            }
            $seen[$identity] = true;
            if ($this->auditRelationExists($left, $right)) {
                $skippedExisting++;
                continue;
            }

            $records[] = [
                'entity_type' => $this->mappingEntityType($left['entity_type'], $right['entity_type']),
                'left_provider' => $left['provider'],
                'left_entity_type' => $left['entity_type'],
                'left_scope_key' => $left['scope_key'],
                'left_field_key' => $left['key'],
                'left_field_label' => $left['label'],
                'right_provider' => $right['provider'],
                'right_entity_type' => $right['entity_type'],
                'right_scope_key' => $right['scope_key'],
                'right_field_key' => $right['key'],
                'right_field_label' => $right['label'],
                'match_method' => 'ai_batch',
                'confidence' => $relation['confidence'] ?? null,
                'status' => 'proposed',
                'notes' => ($data['notes'] ?? null) ?: ($relation['reason'] ?? null),
                'created_by_user_id' => $request->user()->id,
                'metadata' => ['created_from' => 'unification_map_ai_batch'],
            ];
        }

        DB::transaction(function () use ($records): void {
            foreach ($records as $record) {
                UnificationAuditRelation::create($record);
            }
        });

        return response()->json([
            'created' => count($records),
            'skipped_existing' => $skippedExisting,
            'message' => count($records).' propuesta(s) guardada(s) para auditoría. No se creó ningún mapeo activo ni se sincronizaron datos.',
        ]);
    }

    private function filterMapRows(array $rows, ?string $search): array
    {
        $term = Str::lower(trim((string) $search));
        if ($term === '') {
            return $rows;
        }

        return collect($rows)
            ->filter(function (array $row) use ($term): bool {
                $searchable = [
                    data_get($row, 'app.key'),
                    data_get($row, 'app.label'),
                    data_get($row, 'entity_type'),
                ];

                foreach (['hubspot', 'teamleader', 'monday_matches'] as $key) {
                    foreach ($row[$key] ?? [] as $field) {
                        $searchable[] = $field['key'] ?? null;
                        $searchable[] = $field['label'] ?? null;
                        $searchable[] = $field['scope_key'] ?? null;
                        $searchable[] = $field['scope_label'] ?? null;
                    }
                }

                return str_contains(Str::lower(implode(' ', array_filter($searchable))), $term);
            })
            ->values()
            ->all();
    }

    private function paginateItems(array $items, Request $request, string $pageName, int $perPage): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query($pageName, 1));
        $collection = collect($items);

        return (new LengthAwarePaginator(
            $collection->forPage($page, $perPage)->values(),
            $collection->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'pageName' => $pageName],
        ))->appends($request->except($pageName));
    }

    private function canAuditEntityTypesBeMapped(string $leftType, string $rightType): bool
    {
        $leftFamily = $this->entityFamily($leftType);
        $rightFamily = $this->entityFamily($rightType);

        if ($leftFamily === $rightFamily) {
            return in_array($leftFamily, ['contact', 'commercial', 'workflow'], true);
        }

        // The semantic type of a Monday item is declared manually by its
        // selected counterpart and board, never inferred from its name.
        return ($leftFamily === 'workflow' && in_array($rightFamily, ['contact', 'commercial'], true))
            || ($rightFamily === 'workflow' && in_array($leftFamily, ['contact', 'commercial'], true));
    }

    private function mappingEntityType(string $leftType, string $rightType): string
    {
        $families = [$this->entityFamily($leftType), $this->entityFamily($rightType)];

        return in_array('commercial', $families, true) ? 'business' : 'client';
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

    /**
     * When the administrator picked both fields, send only that pair to AI.
     * This bypasses name-similarity thresholds but still verifies that both
     * endpoints come from the local audit catalogue.
     */
    private function selectedPairCandidate(array $data, array $fieldOptions): array|bool|null
    {
        $keys = ['left_entity_type', 'left_field_key', 'right_entity_type', 'right_field_key'];
        $hasAnySelection = collect($keys)->contains(fn (string $key) => filled($data[$key] ?? null));
        if (! $hasAnySelection) {
            return null;
        }

        if (collect($keys)->contains(fn (string $key) => blank($data[$key] ?? null))) {
            return false;
        }

        $left = $this->selectedEndpoint('left', $data, $fieldOptions);
        $right = $this->selectedEndpoint('right', $data, $fieldOptions);
        if (! $left || ! $right) {
            return false;
        }

        return [
            'identity' => collect([$left['identity'], $right['identity']])->sort()->implode('↔'),
            'left' => $left,
            'right' => $right,
            'confidence' => 0,
            'match_method' => 'manual_selection',
            'reason' => 'Par de campos seleccionado explícitamente por el administrador; requiere evaluación semántica.',
            'left_source' => $left['source'] ?? null,
            'right_source' => $right['source'] ?? null,
        ];
    }

    private function selectedEndpoint(string $side, array $data, array $fieldOptions): ?array
    {
        $provider = $data[$side.'_provider'];
        $entityType = $data[$side.'_entity_type'];
        $scopeKey = $data[$side.'_scope_key'] ?: '*';
        $fieldKey = $data[$side.'_field_key'];

        return $this->endpointForField(
            $provider,
            $entityType,
            $scopeKey,
            $fieldKey,
            $fieldOptions,
            $data[$side.'_field_label'] ?? null,
        );
    }

    private function selectedBatchCandidates(array $data, array $fieldOptions): array|bool|null
    {
        if (! array_key_exists('batch_pairs', $data)) {
            return null;
        }
        if ($data['batch_pairs'] === []) {
            return false;
        }

        $candidates = [];
        foreach ($data['batch_pairs'] as $pair) {
            $left = $this->endpointForField(
                $data['left_provider'],
                $pair['left']['entity_type'],
                ($pair['left']['scope_key'] ?? '*') ?: '*',
                $pair['left']['field_key'],
                $fieldOptions,
                $pair['left']['field_label'] ?? null,
            );
            $right = $this->endpointForField(
                $data['right_provider'],
                $pair['right']['entity_type'],
                ($pair['right']['scope_key'] ?? '*') ?: '*',
                $pair['right']['field_key'],
                $fieldOptions,
                $pair['right']['field_label'] ?? null,
            );
            if (! $left || ! $right || ! $this->canAuditEntityTypesBeMapped($left['entity_type'], $right['entity_type'])) {
                return false;
            }

            $identity = $this->relationIdentity($left, $right);
            $candidates[$identity] = [
                'identity' => $identity,
                'left' => $left,
                'right' => $right,
                'confidence' => 0,
                'match_method' => 'manual_batch_selection',
                'reason' => 'Pareja de campos añadida explícitamente por el administrador al lote de revisión.',
                'left_source' => $left['source'] ?? null,
                'right_source' => $right['source'] ?? null,
            ];
        }

        return array_values($candidates);
    }

    private function endpointForField(
        string $provider,
        string $entityType,
        string $scopeKey,
        string $fieldKey,
        array $fieldOptions,
        ?string $selectedLabel = null,
    ): ?array
    {
        $field = collect($fieldOptions[$provider] ?? [])->first(fn (array $option) => $option['entity_type'] === $entityType
            && ($option['scope_key'] ?: '*') === $scopeKey
            && $option['key'] === $fieldKey);

        if (! $field && $provider === 'monday' && $entityType === 'item' && $scopeKey !== '*' && filled($selectedLabel)) {
            return [
                'identity' => implode('|', [$provider, $entityType, $scopeKey, $fieldKey]),
                'provider' => $provider,
                'entity_type' => $entityType,
                'scope_key' => $scopeKey,
                'key' => $fieldKey,
                'label' => $selectedLabel,
                'source' => 'Catálogo de Monday',
            ];
        }

        if (! $field) {
            return null;
        }

        return [
            'identity' => implode('|', [$provider, $entityType, $scopeKey, $fieldKey]),
            'provider' => $provider,
            'entity_type' => $entityType,
            'scope_key' => $scopeKey,
            'key' => $fieldKey,
            'label' => $field['label'] ?? $fieldKey,
            'source' => $field['source'] ?? null,
        ];
    }

    private function relationIdentity(array $left, array $right): string
    {
        return collect([$left['identity'], $right['identity']])->sort()->implode('↔');
    }

    private function auditRelationExists(array $left, array $right): bool
    {
        $forward = [
            ['left_provider', $left['provider']], ['left_entity_type', $left['entity_type']], ['left_scope_key', $left['scope_key']], ['left_field_key', $left['key']],
            ['right_provider', $right['provider']], ['right_entity_type', $right['entity_type']], ['right_scope_key', $right['scope_key']], ['right_field_key', $right['key']],
        ];
        $reverse = [
            ['left_provider', $right['provider']], ['left_entity_type', $right['entity_type']], ['left_scope_key', $right['scope_key']], ['left_field_key', $right['key']],
            ['right_provider', $left['provider']], ['right_entity_type', $left['entity_type']], ['right_scope_key', $left['scope_key']], ['right_field_key', $left['key']],
        ];

        return collect([$forward, $reverse])->contains(function (array $conditions): bool {
            $query = UnificationAuditRelation::query();
            foreach ($conditions as [$column, $value]) {
                $query->where($column, $value);
            }

            return $query->exists();
        });
    }

    private function batchCandidateLimit(): int
    {
        return max(40, min(1000, (int) config('services.openrouter.unification_max_batch_candidates', 200)));
    }

    private function automaticCandidatesForProviders(array $data, array $automaticRelations): array
    {
        return collect($automaticRelations)
            ->filter(fn (array $relation) => (
                $relation['left']['provider'] === $data['left_provider']
                && $relation['right']['provider'] === $data['right_provider']
            ) || (
                $relation['left']['provider'] === $data['right_provider']
                && $relation['right']['provider'] === $data['left_provider']
            ))
            ->map(function (array $relation) use ($data): array {
                if ($relation['left']['provider'] === $data['left_provider']) {
                    return $relation;
                }

                return array_merge($relation, [
                    'left' => $relation['right'],
                    'right' => $relation['left'],
                    'left_source' => $relation['right_source'] ?? null,
                    'right_source' => $relation['left_source'] ?? null,
                ]);
            })
            ->values()
            ->all();
    }
}
