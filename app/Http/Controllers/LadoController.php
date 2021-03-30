<?php

namespace App\Http\Controllers;

use App\Models\Lado;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class LadoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('crud.lados.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('crud.lados.create');
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
            'Lado' => 'required|max:175|unique:lados,Lado',
            'Significado' => 'required'
        ]);

        // Creando lado
        Lado::create([
            'Lado' => $request->Lado,
            'Significado' => $request->Significado
        ]);

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha añadido a la lista el lado: ' . $request->Lado);
        
        // Redireccionar a la vista index
        return redirect()->route('crud.lados.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Lado  $lado
     * @return \Illuminate\Http\Response
     */
    public function show(Lado $lado)
    {
        return view('crud.lados.edit', compact('lado'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Lado  $lado
     * @return \Illuminate\Http\Response
     */
    public function edit(Lado $lado)
    {
        return view('crud.lados.edit', compact('lado'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Lado  $lado
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Lado $lado)
    {
        // Validación
        $request->validate([
            'Lado' => 'required|max:175|unique:lados,Lado,'.$lado->Lado.',Lado',
            'Significado' => 'required'
        ]);

        // Acutalizando parentesco
        $lado->Lado = $request->Lado;
        $lado->Significado = $request->Significado;
        $lado->save();

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha actualizado el lado: ' . $request->Lado);
        
        // Redireccionar a la vista index
        return redirect()->route('crud.lados.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Lado  $lado
     * @return \Illuminate\Http\Response
     */
    public function destroy(Lado $lado)
    {
        $nombre = $lado->Lado;
        
        $lado->delete();

        Alert::info('¡Advertencia!', 'Se ha eliminado el registro: ' . $nombre);

        return redirect()->route('crud.lados.index');
    }
}
