<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientNoteRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientNoteController extends Controller
{
    public function listing(Request $request, User $user): View
    {
        abort_if($request->user()->hasRole('Cliente'), 403);
        abort_unless($user->hasRole('Cliente'), 404, 'Cliente no encontrado.');

        return view('crud.users.notes.index', [
            'client' => $user,
            'notes' => $user->notes()->with('author:id,name')->latest()->orderByDesc('id')->paginate(20),
        ]);
    }

    public function store(StoreClientNoteRequest $request, User $user): RedirectResponse
    {
        abort_unless($user->hasRole('Cliente'), 404, 'Cliente no encontrado.');

        $user->notes()->create([
            'user_id' => $request->user()->id,
            'note' => $request->validated('note'),
        ]);

        return redirect()->route('crud.users.notes.index', $user)
            ->with('success', 'Nota creada correctamente.');
    }
}
