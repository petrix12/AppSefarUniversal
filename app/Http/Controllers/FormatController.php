<?php

namespace App\Http\Controllers;

use App\Models\Format;
use Exception;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Storage;

class FormatController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('crud.formats.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $formats = Format::all();
        return view('crud.formats.create', compact('formats'));
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
            'formato' => 'required|max:100',
            'file' => 'required'
        ]);

        // Guardando la imagen del país
        if($request->hasFile('file')){
            $fileImg = $request->formato.'.png';
            if($request->file('file')->storePubliclyAs('public/imagenes/formatos',$fileImg)){
                // Añadiendo formato
                Format::create([
                    'formato' => $request->formato,
                    'ubicacion' => 'imagenes/formatos/' . $fileImg,
                ]);
        
                // Mensaje 
                Alert::success('¡Éxito!', 'Se ha añadido el formato: ' . $request->formato);
                
                // Redireccionar a la vista index
                return redirect()->route('crud.formats.index');
            }
        }else{
            Alert::error('¡Error!', 'No se pudo añadir el formato');
            return back();
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Format  $format
     * @return \Illuminate\Http\Response
     */
    public function show(Format $format)
    {
        return view('crud.formats.edit', compact('format'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Format  $format
     * @return \Illuminate\Http\Response
     */
    public function edit(Format $format)
    {
        return view('crud.formats.edit', compact('format'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Format  $format
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Format $format)
    {
        // Validación
        $request->validate([
            'formato' => 'required|max:100',
        ]);

        if($request->file){
            // Guardando la imagen del formato
            if($request->hasFile('file')){
                $fileImg = $request->formato.'.png';
                if($request->file('file')->storePubliclyAs('public/imagenes/formatos',$fileImg)){
                    // Actualizando país
                    $format->formato = $request->formato;
                    $format->store = 'imagenes/formatos/' . $fileImg;
                    $format->save();
                }
            }else{
                Alert::error('¡Error!', 'No se pudo actualizar el formato');
                return back();
            }
        }else{
            try{
                $oldname = $format->ubicacion;
                $newName = 'imagenes/formatos/'.$request->formato.'.png';
                Storage::disk('public')->move($oldname, $newName);
            } catch (Exception $e) {
                Alert::error('¡Error!', 'No se pudo guardar la imagen del formato');
            }
            // Actualizando país
            $format->formato = $request->formato;
            $format->ubicacion = 'imagenes/formatos/' . $request->formato.'.png';
            $format->save();
        }
            
        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha actualizado el formato: ' . $request->formato);
        
        // Redireccionar a la vista index
        return redirect()->route('crud.formats.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Format  $format
     * @return \Illuminate\Http\Response
     */
    public function destroy(Format $format)
    {
        $formato = $format->pais;
        
        $format->delete();

        Alert::info('¡Advertencia!', 'Se ha eliminado de la lista el formato: ' . $formato);

        return redirect()->route('crud.formats.index');
    }
}
