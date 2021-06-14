<?php

namespace App\Http\Controllers;

use App\Models\Miscelaneo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RealRashid\SweetAlert\Facades\Alert;

class MiscelaneoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('crud.miscelaneos.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('crud.miscelaneos.create');
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
            'titulo' => 'required|max:255|unique:books,titulo',
            'publicado' => 'nullable|max:255',
            'autor' => 'nullable|max:255',
            'editorial' => 'nullable|max:255',
            'volumen' => 'nullable|max:255',
            'material' => 'nullable|max:255',
            'enlace' => 'required|max:255|url',
            'edicion' => 'nullable|max:255',
            'paginacion' => 'nullable|max:255',
            'isbn' => 'nullable|max:255'
        ]);
       
        // Creando documento
        Miscelaneo::create([
            'titulo' => trim(str_replace(',','-',$request->titulo)),
            'publicado' => trim($request->publicado),
            'autor' => trim($request->autor),
            'editorial' => trim($request->editorial),
            'volumen' => trim($request->volumen),
            'material' => $request->material,
            'paginacion' => trim($request->paginacion),
            'isbn' => trim($request->isbn),
            'notas' => trim($request->notas),
            'enlace' => trim($request->enlace),
            'claves' => $request->claves,
            'catalogador' => Auth()->user()->email
        ]); 

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha añadido el documento: ' . $request->titulo);

        // Redireccionar a la vista que invocó este método
        return redirect($request->urlPrevia);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Miscelaneo  $miscelaneo
     * @return \Illuminate\Http\Response
     */
    public function show(Miscelaneo $miscelaneo)
    {
        return view('crud.miscelaneos.edit', compact('miscelaneo'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Miscelaneo  $miscelaneo
     * @return \Illuminate\Http\Response
     */
    public function edit(Miscelaneo $miscelaneo)
    {
        return view('crud.miscelaneos.edit', compact('miscelaneo'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Miscelaneo  $miscelaneo
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Miscelaneo $miscelaneo)
    { 
        // Validación
        $request->validate([
            'titulo' => 'required|max:255|unique:miscelaneos,titulo,'.$miscelaneo->titulo.',titulo',
            'publicado' => 'nullable|max:255',
            'autor' => 'nullable|max:255',
            'editorial' => 'nullable|max:255',
            'volumen' => 'nullable|max:255',
            'material' => 'nullable|max:255',
            'enlace' => 'required|max:255|url',
            'edicion' => 'nullable|max:255',
            'paginacion' => 'nullable|max:255',
            'isbn' => 'nullable|max:255'
            
        ]);

        // Actualizando documento
        $miscelaneo->titulo = trim(str_replace(',','-',$request->titulo));
        $miscelaneo->publicado = trim($request->publicado);
        $miscelaneo->autor = trim($request->autor);
        $miscelaneo->editorial = trim($request->editorial);
        $miscelaneo->volumen = trim($request->volumen);
        $miscelaneo->material = $request->material;

        $miscelaneo->paginacion = trim($request->paginacion);
        $miscelaneo->isbn = trim($request->isbn);
        $miscelaneo->notas = trim($request->notas);
        $miscelaneo->enlace = trim($request->enlace);
        $miscelaneo->claves = $request->claves;
        if(is_null($miscelaneo->catalogador)){
            $miscelaneo->catalogador = Auth()->user()->email;
        }
       
        $miscelaneo->save();

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha actualizado el documento: ' . $request->titulo);
        
        // Redireccionar a la vista que invocó este método
        return redirect($request->urlPrevia);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Miscelaneo  $miscelaneo
     * @return \Illuminate\Http\Response
     */
    public function destroy(Miscelaneo $miscelaneo)
    {
        $titulo = $miscelaneo->titulo;  
        $miscelaneo->delete();
        Alert::info('¡Advertencia!', 'Se ha eliminado el documento: ' . $titulo);
        return back();
    }
}
