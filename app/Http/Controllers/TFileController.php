<?php

namespace App\Http\Controllers;

use App\Models\TFile;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class TFileController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('crud.t_files.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('crud.t_files.create');
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
            'tipo' => 'required|max:100|unique:t_files,tipo',
            'notas' => 'max:255'
        ]);

        // Creando lado
        TFile::create([
            'tipo' => $request->tipo,
            'notas' => $request->notas
        ]);

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha añadido a la lista el registro: ' . $request->tipo);
        
        // Redireccionar a la vista index
        return redirect()->route('crud.t_files.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\TFile  $tFile
     * @return \Illuminate\Http\Response
     */
    public function show(TFile $t_file)
    {
        return view('crud.t_files.edit', compact('t_file'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\TFile  $tFile
     * @return \Illuminate\Http\Response
     */
    public function edit(TFile $t_file)
    {
        return view('crud.t_files.edit', compact('t_file'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\TFile  $tFile
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, TFile $t_file)
    {
        // Validación
        $request->validate([
            'tipo' => 'required|max:100|unique:t_files,tipo,'.$t_file->tipo.',tipo',
            'notas' => 'max:255'
        ]);

        // Acutalizando parentesco
        $t_file->tipo = $request->tipo;
        $t_file->notas = $request->notas;
        $t_file->save();

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha actualizado el registro: ' . $request->tipo);
        
        // Redireccionar a la vista index
        return redirect()->route('crud.t_files.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\TFile  $tFile
     * @return \Illuminate\Http\Response
     */
    public function destroy(TFile $t_file)
    {
        $nombre = $t_file->tipo;
        
        $t_file->delete();

        Alert::info('¡Advertencia!', 'Se ha eliminado el registro: ' . $nombre);

        return redirect()->route('crud.t_files.index');
    }
}
