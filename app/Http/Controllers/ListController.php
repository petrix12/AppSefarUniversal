<?php

namespace App\Http\Controllers;

use App\Models\Lista;
use App\Models\User;
use Illuminate\Http\Request;

class ListController extends Controller
{

    private function isVentasUser(): bool
    {
        $u = auth()->user();
        return $u && $u->hasAnyRole(['Coord. Ventas', 'Ventas']);
    }

    public function get(Request $request)
    {
        $q = trim((string)$request->get('q'));
        $isVentas = $this->isVentasUser();
        $userId = auth()->id();

        $lists = Lista::query()
            ->when($q !== '', fn($qq) => $qq->where('name', 'like', "%{$q}%"))

            // ✅ FILTRO POR ROL VENTAS (qué listas ve)
            ->when($isVentas, function ($query) use ($userId) {
                $query->where(function ($q) use ($userId) {
                    $q->where('owner_id', $userId)
                    ->orWhereHas('users', function ($sub) use ($userId) {
                        $sub->where('users.owner_id', $userId);
                    });
                });
            })

            // ✅ COUNT condicionado (cuántos miembros ve en esa lista)
            ->when($isVentas, function ($query) use ($userId) {
                $query->withCount([
                    'users as users_count' => function ($sub) use ($userId) {
                        $sub->where('users.owner_id', $userId);
                    }
                ]);
            }, function ($query) {
                // para admins / otros roles: count total normal
                $query->withCount('users');
            })

            ->latest()
            ->paginate(15);

        return view('crud.lists.index', compact('lists', 'q'));
    }

    public function create()
    {
        $owners = User::query()->orderBy('name')->get(['id','name','email']);
        return view('crud.lists.create', compact('owners'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'owner_id' => 'nullable|exists:users,id',
        ]);

        $data['created_by'] = auth()->id();

        $lista = Lista::create($data);

        return redirect()->route('crud.lists.show', $lista)->with('success', 'Lista creada.');
    }

    public function show(Request $request, Lista $lista)
    {
        $filter = $request->get('filter');
        $q = trim((string)$request->get('q'));

        $members = $lista->users()
            ->with([
                'compras:id,id_user,servicio_hs_id',
            ])
            ->when($this->isVentasUser(), function ($qq) {
                $qq->where('users.owner_id', auth()->id());
            })
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($s) use ($q) {
                    $s->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('passport', 'like', "%{$q}%");
                });
            })
            ->when($filter === 'contacted', fn($qq) => $qq->wherePivot('contacted', true))
            ->when($filter === 'not_contacted', fn($qq) => $qq->wherePivot('contacted', false))
            ->orderBy('name')
            ->paginate(20);

        return view('crud.lists.show', compact('lista', 'members', 'filter', 'q'));
    }

    private function authorizeVentasMember(User $user): void
    {
        if ($this->isVentasUser() && (int)$user->owner_id !== (int)auth()->id()) {
            abort(403);
        }
    }

    public function edit(Lista $lista)
    {
        $owners = User::query()->orderBy('name')->get(['id','name','email']);
        return view('crud.lists.edit', compact('lista', 'owners'));
    }

    public function update(Request $request, Lista $lista)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'owner_id' => 'nullable|exists:users,id',
        ]);

        $lista->update($data);

        return redirect()->route('crud.lists.show', $lista)->with('success', 'Lista actualizada.');
    }

    public function destroy(Lista $lista)
    {
        $lista->delete();
        return redirect()->route('crud.lists.index')->with('success', 'Lista eliminada.');
    }

    /**
     * Agregar miembros por IDs.
     * body: user_ids[] (array)
     */
    public function addMembers(Request $request, Lista $lista)
    {
        $data = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        // attach sin duplicar: syncWithoutDetaching
        $attach = [];
        foreach ($data['user_ids'] as $uid) {
            $attach[$uid] = [
                'contacted' => false,
                'contacted_at' => null,
                'contact_note' => null,
            ];
        }

        $lista->users()->syncWithoutDetaching($attach);

        return back()->with('success', 'Usuarios añadidos a la lista.');
    }

    public function removeMember(Lista $lista, User $user)
    {
        $this->authorizeVentasMember($user);

        $lista->users()->detach($user->id);
        return back()->with('success', 'Usuario removido de la lista.');
    }

    /**
     * PATCH contacted
     * body: contacted (0/1), contact_note (optional)
     */
    public function setContacted(Request $request, Lista $lista, User $user)
    {
        $this->authorizeVentasMember($user);

        $data = $request->validate([
            'contacted' => ['required', 'boolean'],
            // ✅ obligatorio si contacted=1
            'contact_note' => ['required_if:contacted,1', 'nullable', 'string', 'max:2000'],
        ]);

        $lista->users()->updateExistingPivot($user->id, [
            'contacted'     => (bool)$data['contacted'],
            'contacted_at'  => $data['contacted'] ? now() : null,
            // si desmarcan, puedes limpiar nota o mantenerla (yo la limpio)
            'contact_note'  => $data['contacted'] ? ($data['contact_note'] ?? null) : null,
        ]);

        return back()->with('success', 'Estado de contacto actualizado.');
    }
}
