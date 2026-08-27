<?php

namespace App\Http\Controllers;

use App\Models\UnificationAuditLink;
use App\Models\UnificationAuditRelation;
use App\Services\UnificationAiSuggestionService;
use App\Services\UnificationMapAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UnificationMapController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'can:administrador']);
    }

    public function map(UnificationMapAuditService $map): mixed
    {
        return view('unification-map.index', $map->inventory());
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
            'notes' => $data['notes'] ?: null,
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
            'notes' => $data['notes'] ?: null,
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Decisión de auditoría actualizada. Sigue sin existir ningún mapeo activo a partir de esta decisión.');
    }

    /**
     * An administrator explicitly invokes this endpoint from the selected map
     * row. The suggestion is returned to the browser and is never persisted.
     */
    public function suggest(
        Request $request,
        UnificationMapAuditService $map,
        UnificationAiSuggestionService $suggestions,
    ): JsonResponse {
        $data = $request->validate([
            'map_identity' => ['required', 'string', 'max:255'],
        ]);

        if (! $suggestions->available()) {
            return response()->json([
                'message' => 'Configura OPENROUTER_API_KEY para usar sugerencias IA.',
            ], 422);
        }

        $inventory = $map->inventory();
        $row = collect($inventory['map_rows'])->firstWhere('identity', $data['map_identity']);

        if (! $row) {
            return response()->json(['message' => 'No se encontró el campo seleccionado.'], 404);
        }

        try {
            return response()->json([
                'suggestion' => $suggestions->suggest($row, $inventory['field_options']['app']),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'No fue posible generar la sugerencia. Intenta de nuevo o revisa la configuración de OpenRouter.',
            ], 502);
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
            'entity_type' => 'client',
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
            'notes' => $data['notes'] ?: null,
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
}
