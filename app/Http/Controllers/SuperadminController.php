<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SuperadminController extends Controller
{
    public function listing(Request $request)
    {
        abort_unless($request->user()->isSuperadmin(), 403);
        $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $search = trim((string) $request->query('search', ''));
        $users = User::whereDoesntHave('roles', fn ($query) => $query->where('name', 'Cliente'))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) =>
                $query->where('email', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')
            ))->orderByDesc('is_superadmin')->orderBy('name')->paginate(25)->withQueryString();

        return view('admin.superadmins', compact('users', 'search'));
    }

    public function update(Request $request, User $user)
    {
        abort_unless($request->user()->isSuperadmin(), 403);
        $request->validate(['is_superadmin' => ['required', 'boolean']]);
        abort_if($user->hasRole('Cliente'), 422, 'La marca solo está disponible para usuarios internos.');
        DB::transaction(function () use ($request, $user): void {
            $active = User::where('is_superadmin', true)->orderBy('id')->lockForUpdate()->get()
                ->filter(fn ($actor) => $actor->isSuperadmin());
            $target = User::lockForUpdate()->findOrFail($user->id);
            abort_if($target->hasRole('Cliente'), 422, 'La marca solo está disponible para usuarios internos.');
            // Recheck the actor under the same locks as concurrent revocations.
            abort_unless($active->contains('id', $request->user()->id), 403);
            if (! $request->boolean('is_superadmin') && $target->isSuperadmin() && $active->count() <= 1) {
                throw ValidationException::withMessages(['is_superadmin' => 'Debe permanecer al menos un superadmin activo.']);
            }
            $target->forceFill(['is_superadmin' => $request->boolean('is_superadmin')])->save();
        });

        return back()->with('success', 'Marca de superadmin actualizada.');
    }
}
