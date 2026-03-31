<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContratoCoordinadorController extends Controller
{
    public function form()
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->hasRole('Coord. de Nacionalidad y Genealogía')) {
            return redirect('/')->with('error', 'No autorizado.');
        }

        if ((int) $user->contrato === 1) {
            return redirect('/')->with('success', 'Ya tienes el contrato firmado.');
        }

        return view('contratos.coordinador', [
            'user' => $user,
            'email' => $user->email,
        ]);
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        $email = mb_strtolower(trim($request->email));
        $userEmail = mb_strtolower(trim($user->email));

        if (!$user->hasRole('Coord. de Nacionalidad y Genealogía')) {
            return redirect('/')->with('error', 'No autorizado.');
        }

        if ($email !== $userEmail) {
            return redirect()
                ->route('contrato.coordinador.form')
                ->with('error', 'El correo recibido no coincide con el usuario autenticado.');
        }

        $user->contrato = 1;
        $user->save();

        return redirect('/')->with('success', 'Contrato firmado correctamente.');
    }
}
