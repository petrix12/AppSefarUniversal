<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CrmLeadController extends Controller
{
    public function listing(Request $request)
    {
        $query = Lead::query()->with(['assignee:id,name', 'convertedUser:id,name']);
        $search = trim((string) $request->input('q', ''));
        $estado = (string) $request->input('estado', Lead::STATUS_OPEN);
        $etapa = (string) $request->input('etapa', '');

        $query->when($search !== '', function ($leads) use ($search): void {
            $like = '%' . $search . '%';
            $leads->where(function ($match) use ($like): void {
                $match->where('nombre', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('telefono', 'like', $like)
                    ->orWhere('passport', 'like', $like);
            });
        });

        $query->when($estado !== '', fn ($leads) => $leads->where('estado', $estado));
        $query->when(isset(Lead::STAGES[$etapa]), fn ($leads) => $leads->where('etapa', $etapa));

        return view('crm.leads.index', [
            'leads' => $query->latest()->paginate(25)->withQueryString(),
            'search' => $search,
            'estado' => $estado,
            'etapa' => $etapa,
            'stages' => Lead::STAGES,
        ]);
    }

    public function create()
    {
        return view('crm.leads.form', [
            'lead' => new Lead(['etapa' => Lead::STAGE_NEW, 'estado' => Lead::STATUS_OPEN]),
            'internalUsers' => User::internalUsers()->orderBy('name')->get(['id', 'name', 'email']),
            'action' => route('crm.leads.store'),
            'method' => 'POST',
        ]);
    }

    public function store(Request $request)
    {
        $lead = Lead::create($this->validatedData($request));

        return redirect()->route('crm.leads.edit', $lead)->with('success', 'Lead creado correctamente.');
    }

    public function edit(Lead $lead)
    {
        return view('crm.leads.form', [
            'lead' => $lead,
            'internalUsers' => User::internalUsers()->orderBy('name')->get(['id', 'name', 'email']),
            'action' => route('crm.leads.update', $lead),
            'method' => 'PUT',
        ]);
    }

    public function update(Request $request, Lead $lead)
    {
        $lead->update($this->validatedData($request, $lead));

        return redirect()->route('crm.leads.edit', $lead)->with('success', 'Lead actualizado correctamente.');
    }

    private function validatedData(Request $request, ?Lead $lead = null): array
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:80'],
            'passport' => ['nullable', 'string', 'max:175'],
            'origen' => ['nullable', 'string', 'max:120'],
            'etapa' => ['required', 'string', 'max:80'],
            'estado' => ['required', Rule::in($lead?->estado === Lead::STATUS_CONVERTED
                ? [Lead::STATUS_OPEN, Lead::STATUS_CONVERTED, Lead::STATUS_LOST]
                : [Lead::STATUS_OPEN, Lead::STATUS_LOST])],
            'etapa' => ['required', Rule::in(array_keys(Lead::STAGES))],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if (! empty($data['user_id'])) {
            abort_unless(User::internalUsers()->whereKey($data['user_id'])->exists(), 422, 'El responsable debe ser un usuario interno.');
        }

        if ($data['estado'] === Lead::STATUS_CONVERTED && ! $lead?->converted_at) {
            $data['converted_at'] = now();
        }

        return $data;
    }
}
