<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ServicioController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $servicios = Servicio::orderBy('created_at', 'desc')->get();
        return view('crud.servicios.index', compact('servicios'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('crud.servicios.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try { 
            Servicio::create([
                'id_hubspot' => trim($request->id_hubspot),
                'nombre' => trim($request->nombre),
                'precio' => trim($request->precio),
                'tipov' => trim($request->tipov)
            ]);
        } catch(\Illuminate\Database\QueryException $ex){ 
            Alert::error('Error', 'El servicio ya existe');
            return back();
        }

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha añadido el servicio: ' . $request->nombre);

        // Redireccionar a la vista que invocó este método
        $servicios = Servicio::orderBy('created_at', 'desc')->get();
        return view('crud.servicios.index', compact('servicios'));
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Servicio  $servicio
     * @return \Illuminate\Http\Response
     */
    public function show(Servicio $servicio)
    {
        return view('crud.servicios.edit', compact('servicio'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Servicio  $servicio
     * @return \Illuminate\Http\Response
     */
    public function edit(Servicio $servicio)
    {
        return view('crud.servicios.edit', compact('servicio'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Servicio  $servicio
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Servicio $servicio)
    {
        $servicio->id_hubspot = trim($request->id_hubspot);
        $servicio->nombre = trim($request->nombre);
        $servicio->precio = trim($request->precio);
        $servicio->tipov = trim($request->tipov);

        try { 
            $servicio->save();
        } catch(\Illuminate\Database\QueryException $ex){ 
            Alert::error('Error', 'El servicio ya existe. No puedes duplicarlo.');
            return back();
        }

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha actualizado el servicio: ' . $request->nombre);
        
        // Redireccionar a la vista que invocó este método
        $servicios = Servicio::orderBy('created_at', 'desc')->get();
        return view('crud.servicios.index', compact('servicios'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Servicio  $servicio
     * @return \Illuminate\Http\Response
     */
    public function destroy(Servicio $servicio)
    {
        $titulo = $servicio->couponcode;
        
        $servicio->delete();

        Alert::info('¡Advertencia!', 'Se ha eliminado el cupón: ' . $titulo);
        
        $servicios = Servicio::orderBy('created_at', 'desc')->get();
        return view('crud.servicios.index', compact('servicios'));
    }
}
