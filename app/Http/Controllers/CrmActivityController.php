<?php

namespace App\Http\Controllers;

use App\Models\CrmActivity;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CrmActivityController extends Controller
{
    public function listing(Request $request)
    {
        $type = (string) $request->query('type', '');
        $query = CrmActivity::query()->with(['lead:id,nombre,email', 'client:id,name,email', 'createdBy:id,name']);
        $query->when(isset(CrmActivity::TYPES[$type]), fn ($q) => $q->where('type', $type));

        return view('crm.activities.index', [
            'activities' => $query->latest('occurred_at')->paginate(25)->withQueryString(),
            'type' => $type,
            'types' => CrmActivity::TYPES,
        ]);
    }

    public function create(Request $request)
    {
        $lead = $request->filled('lead_id') ? Lead::find($request->integer('lead_id')) : null;
        $client = $request->filled('client_id') ? User::clients()->find($request->integer('client_id')) : null;
        abort_if($lead && $client, 422, 'Selecciona un único contacto.');
        return view('crm.activities.form', [
            'activity' => new CrmActivity(['type' => 'note', 'occurred_at' => now(), 'lead_id' => $lead?->id, 'client_id' => $client?->id]),
            'contactLabel' => $lead ? trim($lead->nombre . ' · ' . ($lead->email ?: '')) . ' (Lead)' : ($client ? trim($client->name . ' · ' . ($client->email ?: '')) . ' (Representado)' : ''),
            'action' => route('crm.activities.store'), 'method' => 'POST',
        ]);
    }

    public function edit(CrmActivity $activity)
    {
        $activity->load(['lead:id,nombre,email', 'client:id,name,email']);
        return view('crm.activities.form', [
            'activity' => $activity,
            'contactLabel' => $activity->lead
                ? trim($activity->lead->nombre . ' · ' . ($activity->lead->email ?: '')) . ' (Lead)'
                : ($activity->client ? trim($activity->client->name . ' · ' . ($activity->client->email ?: '')) . ' (Representado)' : ''),
            'action' => route('crm.activities.update', $activity), 'method' => 'PUT',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        [$data['lead_id'], $data['client_id']] = $this->contactIds($request);
        $data['created_by_user_id'] = $request->user()->id;
        CrmActivity::create($data);

        return redirect()->route('crm.activities.index')->with('success', 'Actividad registrada.');
    }

    public function update(Request $request, CrmActivity $activity)
    {
        $data = $this->validated($request);
        [$data['lead_id'], $data['client_id']] = $this->contactIds($request);
        $activity->update($data);

        return redirect()->route('crm.activities.index')->with('success', 'Actividad actualizada.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(array_keys(CrmActivity::TYPES))],
            'subject' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:10000'],
            'occurred_at' => ['required', 'date'],
            'follow_up_at' => ['nullable', 'date', 'after_or_equal:occurred_at'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'client_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
    }

    private function contactIds(Request $request): array
    {
        $leadId = $request->integer('lead_id') ?: null;
        $clientId = $request->integer('client_id') ?: null;
        if ((bool) $leadId === (bool) $clientId) {
            throw ValidationException::withMessages(['contact_search' => 'Selecciona exactamente un lead o un representado de la lista.']);
        }
        if ($clientId) {
            if (! User::clients()->whereKey($clientId)->exists()) {
                throw ValidationException::withMessages(['contact_search' => 'El contacto seleccionado ya no está disponible.']);
            }
        }
        if ($leadId) {
            if (! Lead::whereKey($leadId)->exists()) {
                throw ValidationException::withMessages(['contact_search' => 'El lead seleccionado ya no existe.']);
            }
        }
        return [$leadId, $clientId];
    }
}
