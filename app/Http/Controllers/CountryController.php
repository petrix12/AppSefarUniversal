<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Exception;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Storage;

class CountryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('crud.countries.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $countries = Country::all();
        return view('crud.countries.create', compact('countries'));
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
            'pais' => 'required|max:100',
            'file' => 'required'
        ]);

        // Guardando la imagen del país
        if($request->hasFile('file')){
            $fileImg = $request->pais.'.png';
            /* if(Storage::putFileAs('/imagenes/paises/' , $request->file, $fileImg)){ */
            /* if($request->file('file')->storePubliclyAs('public/imagenes/paises', $fileImg)){ */
            //$ruta = Storage::disk('s3')->put('imagenes/paises', $request->file, 'public');
            $ruta = Storage::disk('s3')->putFileAs('imagenes/paises', $request->file, $fileImg, 'public');
            if($ruta){
                // Añadiendo país
                Country::create([
                    'pais' => $request->pais,
                    'store' => $ruta
                ]);

                // Mensaje
                Alert::success('¡Éxito!', 'Se ha añadido el país: ' . $request->pais);

                // Redireccionar a la vista index
                return redirect()->route('crud.countries.index');
            }
        }else{
            Alert::error('¡Error!', 'No se pudo añadir el país');
            return back();
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Country  $country
     * @return \Illuminate\Http\Response
     */
    public function show(Country $country)
    {
        return view('crud.countries.edit', compact('country'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Country  $country
     * @return \Illuminate\Http\Response
     */
    public function edit(Country $country)
    {
        return view('crud.countries.edit', compact('country'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Country  $country
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Country $country)
    {
        // Validación
        $request->validate([
            'pais' => 'required|max:100',
            /* 'file' => 'required' */
        ]);

        if($request->file){
            // Guardando la imagen del país
            if($request->hasFile('file')){
                $fileImg = $request->pais.'.png';
                /* if(Storage::putFileAs('/imagenes/paises/' , $request->file, $fileImg)){ */
                /* if($request->file('file')->storePubliclyAs('public/imagenes/paises',$fileImg)){ */
                //$ruta = Storage::disk('s3')->put('imagenes/paises', $request->file, 'public');
                $ruta = Storage::disk('s3')->putFileAs('imagenes/paises', $request->file, $fileImg, 'public');
                if($ruta){
                    // Actualizando país
                    $country->pais = $request->pais;
                    /* $country->store = 'imagenes/paises/' . $fileImg; */
                    $country->store = $ruta;
                    $country->save();
                }
            }else{
                Alert::error('¡Error!', 'No se pudo actualizar el país');
                return back();
            }
        }else{
            try{
                $oldname = $country->store;
                $newName = 'imagenes/paises/'.$request->pais.'.png';
                Storage::disk('s3')->move($oldname, $newName);
                $country->store = $newName;
            } catch (Exception $e) {
                Alert::error('¡Error!', 'No se pudo guardar la bandera del país');
            }
            // Actualizando país
            $country->pais = $request->pais;
            /* $country->store = 'imagenes/paises/' . $request->pais.'.png' */;
            $country->save();
        }

        // Mensaje
        Alert::success('¡Éxito!', 'Se ha actualizado el país: ' . $request->pais);

        // Redireccionar a la vista index
        return redirect()->route('crud.countries.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Country  $country
     * @return \Illuminate\Http\Response
     */
    public function destroy(Country $country)
    {
        $pais = $country->pais;
        $ruta = $country->store;

        $country->delete();
        Storage::disk('s3')->delete($ruta);

        Alert::info('¡Advertencia!', 'Se ha eliminado de la lista el país: ' . $pais);

        return redirect()->route('crud.countries.index');
    }
}
