<?php

namespace App\Http\Controllers;

use App\Models\Agcliente;
use App\Models\Country;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class AgclienteController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('crud.agclientes.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $countries = Country::all();
        return view('crud.agclientes.create', compact('countries'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Agcliente  $agcliente
     * @return \Illuminate\Http\Response
     */
    public function show(Agcliente $agcliente)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Agcliente  $agcliente
     * @return \Illuminate\Http\Response
     */
    public function edit(Agcliente $agcliente)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Agcliente  $agcliente
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Agcliente $agcliente)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Agcliente  $agcliente
     * @return \Illuminate\Http\Response
     */
    public function destroy(Agcliente $agcliente)
    {
        $nombre = $agcliente->Nombres . ' ' . $agcliente->Apellidos;
        
        $agcliente->delete();

        Alert::info('¡Advertencia!', 'Se ha eliminado de la lista la persona: ' . $nombre);

        return redirect()->route('crud.agclientes.index');
    }
}
