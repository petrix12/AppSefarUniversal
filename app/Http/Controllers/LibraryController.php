<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Format;
use App\Models\Library;
use App\Models\TFile;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Storage;

class LibraryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('crud.libraries.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $countries = Country::all();
        $formats = Format::all();
        $t_files = TFile::all();
        return view('crud.libraries.create', compact('countries','formats','t_files'));
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
            'documento' => 'required|max:255|unique:libraries,documento',
            'fuente' => 'nullable|max:255',
            'origen' => 'nullable|max:255',
            'ubicacion' => 'nullable|max:255',
            'ubicacion_ant' => 'nullable|max:255',
            'enlace' => 'required|max:255|url',
            'anho_ini' => 'nullable|numeric',
            'anho_fin' => 'nullable|numeric',
            'ciudad' => 'nullable|max:150',
            'responsabilidad' => 'nullable|max:150',
            'edicion' => 'nullable|max:150',
            'editorial' => 'nullable|max:150',
            'anho_publicacion' => 'nullable|numeric',
            'no_vol' => 'nullable|max:50',
            'coleccion' => 'nullable|max:100',
            'colacion' => 'nullable|max:50',
            'isbn' => 'nullable|max:50',
            'serie' => 'nullable|max:50',
            'no_clasificacion' => 'nullable|max:50',
            'titulo_revista' => 'nullable|max:255'
        ]);
       
        // Creando documento
        Library::create([
            'documento' => trim($request->documento),
            'formato' => $request->formato,
            'tipo' => $request->tipo,
            'fuente' => trim($request->fuente),
            'origen' => trim($request->origen),
            'ubicacion' => trim($request->ubicacion),
            'ubicacion_ant' => trim($request->ubicacion_ant),
            'busqueda' => $request->busqueda,
            'notas' => $request->notas,
            'enlace' => $request->enlace,
            'anho_ini' => $request->anho_ini,
            'anho_fin' => $request->anho_fin,
            'ciudad' => trim($request->ciudad),
            'FIncorporacion' => $request->FIncorporacion,
            'responsabilidad' => trim($request->responsabilidad),
            'edicion' => trim($request->edicion),
            'editorial' => trim($request->editorial),
            'anho_publicacion' => $request->anho_publicacion,
            'coleccion' => $request->coleccion,
            'colacion' => $request->colacion,
            'isbn' => $request->isbn,
            'serie' => $request->isbn,
            'no_clasificacion' => $request->no_clasificacion,
            'titulo_revista' => $request->titulo_revista,
            'resumen' => $request->resumen,
            'usuario' => Auth()->user()->email
        ]);

        // Pendiente guardar url de caratula

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha añadido el documento: ' . $request->documento);

        // Redireccionar a la vista que invocó este método
        return redirect($request->urlPrevia);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Library  $library
     * @return \Illuminate\Http\Response
     */
    public function show(Library $library)
    {
        $countries = Country::all();
        $formats = Format::all();
        $t_files = TFile::all();
        return view('crud.libraries.edit', compact('library','countries','formats','t_files'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Library  $library
     * @return \Illuminate\Http\Response
     */
    public function edit(Library $library)
    {
        $countries = Country::all();
        $formats = Format::all();
        $t_files = TFile::all();
        return view('crud.libraries.edit', compact('library','countries','formats','t_files'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Library  $library
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Library $library)
    { 
        // Validación
        $request->validate([
            'documento' => 'required|max:255|unique:libraries,documento,'.$library->documento.',documento',
            'fuente' => 'nullable|max:255',
            'origen' => 'nullable|max:255',
            'ubicacion' => 'nullable|max:255',
            'ubicacion_ant' => 'nullable|max:255',
            'enlace' => 'required|max:255|url',
            'anho_ini' => 'nullable|numeric',
            'anho_fin' => 'nullable|numeric',
            'ciudad' => 'nullable|max:150',
            'responsabilidad' => 'nullable|max:150',
            'edicion' => 'nullable|max:150',
            'editorial' => 'nullable|max:150',
            'anho_publicacion' => 'nullable|numeric',
            'no_vol' => 'nullable|max:50',
            'coleccion' => 'nullable|max:100',
            'colacion' => 'nullable|max:50',
            'isbn' => 'nullable|max:50',
            'serie' => 'nullable|max:50',
            'no_clasificacion' => 'nullable|max:50',
            'titulo_revista' => 'nullable|max:255'
        ]);

        // Actualizando documento
        $library->documento = trim($request->documento);
        $library->formato = $request->formato;
        $library->tipo = $request->tipo;
        $library->fuente = trim($request->fuente);
        $library->origen = trim($request->origen);
        $library->ubicacion = trim($request->ubicacion);
        $library->ubicacion_ant = trim($request->ubicacion_ant);
        $library->busqueda = $request->busqueda;
        $library->notas = $request->notas;
        $library->enlace = $request->enlace;
        $library->anho_ini = $request->anho_ini;
        $library->anho_fin = $request->anho_fin;
        $library->ciudad = trim($request->ciudad);
        $library->FIncorporacion = $request->FIncorporacion;
        $library->responsabilidad = trim($request->responsabilidad);
        $library->edicion = trim($request->edicion);
        $library->editorial = trim($request->editorial);
        $library->anho_publicacion = $request->anho_publicacion;
        $library->coleccion = $request->coleccion;
        $library->colacion = $request->colacion;
        $library->isbn = $request->isbn;
        $library->serie = $request->isbn;
        $library->no_clasificacion = $request->no_clasificacion;
        $library->titulo_revista = $request->titulo_revista;
        $library->resumen = $request->resumen;
        $library->usuario = Auth()->user()->email;
       
        $library->save();

        // Pendiente actualizar la url de la caratula y su imagen

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha actualizado el documento: ' . $request->documento);
        
        // Redireccionar a la vista que invocó este método
        return redirect($request->urlPrevia);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Library  $library
     * @return \Illuminate\Http\Response
     */
    public function destroy(Library $library)
    {
        $documento = $library->documento;
        
        $library->delete();

        Alert::info('¡Advertencia!', 'Se ha eliminado el documento: ' . $documento);

        // Pendiente eliminar la imagen de caratula del documento
        
        return back();
    }
}
