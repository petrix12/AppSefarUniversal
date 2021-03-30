<?php

namespace App\Http\Controllers;

use App\Models\Parentesco;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class ParentescoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('crud.parentescos.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('crud.parentescos.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validación
        $request->validate([
            'Parentesco' => 'required|max:175|unique:parentescos,Parentesco',
            'Inverso' => 'required|max:175'
        ]);

        // Creando parentesco
        $parentesco = Parentesco::create([
            'Parentesco' => $request->Parentesco,
            'Inverso' => $request->Inverso
        ]);

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha añadido a la lista el parentesco: ' . $request->Parentesco);
        
        // Redireccionar a la vista index
        return redirect()->route('crud.parentescos.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Parentesco  $parentesco
     * @return \Illuminate\Http\Response
     */
    public function show(Parentesco $parentesco)
    {
        return view('crud.parentescos.edit', compact('parentesco'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Parentesco  $parentesco
     * @return \Illuminate\Http\Response
     */
    public function edit(Parentesco $parentesco)
    {
        return view('crud.parentescos.edit', compact('parentesco'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Parentesco  $parentesco
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Parentesco $parentesco)
    {
        // Validación
        $request->validate([
            'Parentesco' => 'required|max:175|unique:parentescos,Parentesco,'.$parentesco->Parentesco.',Parentesco',
            'Inverso' => 'required|max:175'
        ]);

        // Acutalizando parentesco
        $parentesco->Parentesco = $request->Parentesco;
        $parentesco->Inverso = $request->Inverso;
        $parentesco->save();

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha actualizado el parentesco: ' . $request->Parentesco);
        
        // Redireccionar a la vista index
        return redirect()->route('crud.parentescos.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Parentesco  $parentesco
     * @return \Illuminate\Http\Response
     */
    public function destroy(Parentesco $parentesco)
    {
        $nombre = $parentesco->name;
        
        $parentesco->delete();

        Alert::info('¡Advertencia!', 'Se ha eliminado el registro: ' . $nombre);

        return redirect()->route('crud.parentescos.index');
    }
}
