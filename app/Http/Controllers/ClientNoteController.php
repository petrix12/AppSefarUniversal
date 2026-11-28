<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientNoteRequest;
use App\Models\User;
use App\Models\ClientNote;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientNoteController extends Controller
{
    public function listing(Request $request, User $user): View
    {
        abort_if($request->user()->hasRole('Cliente'), 403);
        abort_unless($user->hasRole('Cliente'), 404, 'Representado no encontrado.');

        return view('crud.users.notes.index', [
            'client' => $user,
            'notes' => $user->notes()->with('author:id,name')->latest()->orderByDesc('id')->paginate(20),
        ]);
    }

    public function store(StoreClientNoteRequest $request, User $user): RedirectResponse
    {
        abort_unless($user->hasRole('Cliente'), 404, 'Representado no encontrado.');

        $user->notes()->create([
            'user_id' => $request->user()->id,
            'note' => $request->validated('note'),
        ]);

        return redirect()->route('crud.users.notes.index', $user)
            ->with('success', 'Nota creada correctamente.');
    }

    public function destroy(Request $request, User $user, ClientNote $note): RedirectResponse
    {
        abort_unless($request->user()->isSuperadmin(), 403);
        abort_unless($user->hasRole('Cliente') && (int) $note->client_id === (int) $user->id, 404);
        DB::transaction(function () use ($note, $request, $user): void {
            $stored = ClientNote::where('client_id', $user->id)->lockForUpdate()->findOrFail($note->id);
            $stored->forceFill(['deleted_by' => $request->user()->id])->save();
            $stored->delete();
        });

        return redirect()->route('crud.users.notes.index', $user)->with('success', 'Nota eliminada correctamente.');
    }
}
