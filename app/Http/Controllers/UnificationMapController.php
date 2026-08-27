<?php

namespace App\Http\Controllers;

use App\Models\UnificationAuditLink;
use App\Services\UnificationMapAuditService;
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
}
