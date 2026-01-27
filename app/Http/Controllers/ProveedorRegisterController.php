<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class ProveedorRegisterController extends Controller
{
    public function create()
    {
        return view('auth.register-proveedores');
    }

    public function store(Request $request)
    {
        $input = $request->all();

        Validator::make($input, [
            'nombres' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:175', 'unique:users,email'],
            'email_2' => ['nullable', 'string', 'email', 'max:175'],
            'phone' => ['required', 'string', 'max:32'],

            'pais_de_residencia' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],

            'metodo_pago_preferido' => ['required', 'string', 'max:80'],
            'motivo_coordinador' => ['required', 'string', 'max:255'],
            'tiene_contactos_sociales' => ['required', 'in:0,1'],

            'acepta_politicas_comisiones' => ['accepted'],

            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ])->validate();

        $user = User::create([
            'name' => $input['nombres'],
            'nombres' => $input['nombres'],

            'email' => $input['email'],
            'email_2' => $input['email_2'] ?? null,

            'phone' => $input['phone'],
            'pais_de_residencia' => $input['pais_de_residencia'],
            'city' => $input['city'],
            'address' => $input['address'],

            'metodo_pago_preferido' => $input['metodo_pago_preferido'],
            'motivo_coordinador' => $input['motivo_coordinador'],
            'tiene_contactos_sociales' => (int) $input['tiene_contactos_sociales'],
            'acepta_politicas_comisiones' => 1,

            'estado_vendedor' => 'Pendiente',
            'fecha_activacion_proveedor' => null,

            // si quieres que queden “no verificados” hasta aprobación, deja email_verified_at null
            // 'email_verified_at' => now(),
            'password' => Hash::make($input['password']),

            // opcional si tu sistema lo usa
            'cosready' => 1,
            'contrato' => 0,
        ]);

        // Asignar rol Coordinador (por nombre o por id)
        // Recomendado:
        if (Role::where('name', 'Coord. Ventas')->exists()) {
            $user->assignRole('Coord. Ventas');
        } else {
            $role = Role::find(17);
            if ($role) $user->assignRole($role);
        }

        // Opcional: loguear o NO loguear
        // Si quieres que entre a dashboard:
        // Auth::login($user);

        return redirect()->route('login')
            ->with('status', 'Tu registro fue recibido. Un administrador validará tu cuenta.');
    }
}
