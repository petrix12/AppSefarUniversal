<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class CrmInternalUserController extends Controller
{
    public function create()
    {
        abort_unless(auth()->user()?->isSuperadmin(), 403);

        return view('crm.internal-users.create', [
            'roles' => Role::query()->where('guard_name', 'web')->whereNotIn('name', ['Cliente', 'Administrador'])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()?->isSuperadmin(), 403);

        $roles = Role::query()->where('guard_name', 'web')->whereNotIn('name', ['Cliente', 'Administrador'])->pluck('name')->all();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:254'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:80'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', Rule::in($roles)],
        ]);

        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make(Str::random(64)),
                'email_verified_at' => now(),
            ]);
            // User's legacy creation hook defaults unassigned accounts to Cliente.
            // Replacing the roles inside this transaction ensures this flow always
            // finishes as an internal account before it becomes visible.
            $user->syncRoles($data['roles']);

            return $user;
        });

        try {
            $status = Password::broker()->sendResetLink(['email' => $user->email]);
        } catch (\Throwable $exception) {
            Log::warning('CRM: no se pudo enviar el enlace inicial a una cuenta interna', [
                'user_id' => $user->id,
                'exception_type' => $exception::class,
            ]);
            $status = Password::RESET_THROTTLED;
        }

        return redirect()->route('crm.internal-users.index')->with(
            $status === Password::RESET_LINK_SENT ? 'success' : 'warning',
            $status === Password::RESET_LINK_SENT
                ? 'Cuenta interna creada. Enviamos un enlace para que establezca su contraseña.'
                : 'La cuenta interna se creó, pero no se pudo enviar el enlace. Puede solicitarlo desde «Olvidé mi contraseña». '
        );
    }

    public function destroy(User $user)
    {
        abort_unless(auth()->user()?->isSuperadmin(), 403);
        abort_unless($user->isInternalUser(), 404);
        abort_if($user->isSuperadmin(), 403, 'Retira primero la marca de superadmin.');

        $name = $user->name;
        $user->delete();

        return redirect()->route('crm.internal-users.index')->with('success', "Se eliminó la cuenta interna de {$name}.");
    }
}
