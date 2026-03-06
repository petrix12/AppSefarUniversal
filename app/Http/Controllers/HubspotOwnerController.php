<?php

namespace App\Http\Controllers;

use App\Models\HubspotOwner;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\HubspotOwnerUser;

class HubspotOwnerController extends Controller
{
    public function index()
    {
        $owners = HubspotOwner::query()
            ->with(['ownerUserLink.user'])
            ->orderBy('name')
            ->paginate(20);
        return view('hubspot_owners.index', compact('owners'));
    }

    public function create()
    {
        return view('hubspot_owners.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'string', 'max:64', 'unique:hubspot_owners,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['active'] = $request->boolean('active', true);

        HubspotOwner::create($data);

        return redirect()->route('hubspot-owners.index')->with('status', 'Owner creado ✅');
    }

    public function edit(HubspotOwner $hubspot_owner)
    {
        return view('hubspot_owners.edit', ['owner' => $hubspot_owner]);
    }

    public function update(Request $request, HubspotOwner $hubspot_owner)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['active'] = $request->boolean('active', true);

        $hubspot_owner->update($data);

        return redirect()->route('hubspot-owners.index')->with('status', 'Owner actualizado ✅');
    }

    public function destroy(HubspotOwner $hubspot_owner)
    {
        $hubspot_owner->delete();
        return back()->with('status', 'Owner eliminado ✅');
    }

    public function searchUsers(Request $request)
    {
        $term = trim((string) $request->get('q', ''));

        $users = User::query()
            ->select(['id', 'name', 'email'])
            ->when($term !== '', function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json([
            'results' => $users->map(fn ($u) => [
                'id' => $u->id,
                'text' => trim(($u->name ?? '') . ' — ' . ($u->email ?? '')),
            ])->values(),
        ]);
    }

    // Guardar asociación owner -> user (1 owner a 1 user) y evitar que un user tenga 2 owners
    public function assign(Request $request, HubspotOwner $owner)
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $userId = $data['user_id'] ?? null;

        // Si envían null/vacío => desasociar
        if (!$userId) {
            HubspotOwnerUser::where('hubspot_owner_id', $owner->id)->delete();

            return response()->json([
                'ok' => true,
                'assigned' => false,
            ]);
        }

        $user = User::select(['id', 'name', 'email'])->findOrFail($userId);

        // Regla: un user solo puede estar asociado a un owner (si ya estaba, lo movemos)
        HubspotOwnerUser::where('user_id', $user->id)->delete();

        HubspotOwnerUser::updateOrCreate(
            ['hubspot_owner_id' => $owner->id],
            [
                'user_id' => $user->id,
                'hubspot_owner_name' => $owner->name, // snapshot opcional
            ]
        );

        return response()->json([
            'ok' => true,
            'assigned' => true,
            'user' => [
                'id' => $user->id,
                'text' => trim(($user->name ?? '') . ' — ' . ($user->email ?? '')),
            ],
        ]);
    }
}
