<?php

namespace App\Http\Controllers;

use App\Models\Treena;
use Illuminate\Http\Request;

class TreenaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Busca el registro con id = 1, o lo crea si no existe
        $treenaprompt = Treena::firstOrCreate(
            ['id' => 1], // Condición de búsqueda
            ['context_prompt' => ''] // Valores por defecto si se crea
        );

        return view('treena.index', compact('treenaprompt'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        // Validar la entrada
        $request->validate([
            'context_prompt' => 'required|string',
        ]);

        // Buscar o crear el registro con id = 1
        $treenaprompt = Treena::firstOrCreate(
            ['id' => 1], // Condición de búsqueda
            ['context_prompt' => ''] // Valores por defecto si se crea
        );

        // Actualizar el campo context_prompt
        $treenaprompt->context_prompt = $request->input('context_prompt');
        $treenaprompt->save();

        // Redireccionar con un mensaje de éxito
        return redirect()->back()->with('success', 'Prompt guardado correctamente.');
    }
}
