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
        // Validación
        $request->validate([
            'IDCliente' => 'required|unique:agclientes,IDCliente|max:50',
            'Nombres' => 'required|max:100',
            'Apellidos' => 'required|max:100',
            'IDPersona'  => 'required',

            'AnhoNac' => 'nullable|numeric',
            'MesNac' => 'nullable|numeric',
            'DiaNac' => 'nullable|numeric',

            'AnhoBtzo' => 'nullable|numeric',
            'MesBtzo' => 'nullable|numeric',
            'DiaBtzo' => 'nullable|numeric',
            
            'AnhoMatr' => 'nullable|numeric',
            'MesMatr' => 'nullable|numeric',
            'DiaMatr' => 'nullable|numeric',
            
            'AnhoDef' => 'nullable|numeric',
            'MesDef' => 'nullable|numeric',
            'DiaDef' => 'nullable|numeric'
        ]);

        
        $PNacimiento = $request->PaisNac;
        $LNacimiento = $request->LugarNac;
        $Familiares = $request->Familiaridad;
        $Usuario = Auth()->user()->email;
        $Generacion = GetGeneracion($request->IDPersona);
        $IDPadre = ($request->IDPersona);
        $IDMadre = $IDPadre + 1;
        $FUpdate = date('Y-m-d H:i:s');
        

        // Creando usuario
        Agcliente::create([
            'IDCliente' => $request->IDCliente, 
            'Nombres' => $request->Nombres,
            'Apellidos' => $request->Apellidos,

            'IDPersona' => $request->IDPersona,
            'NPasaporte' => $request->NPasaporte,
            'PaisPasaporte' => $request->PaisPasaporte,
            'NDocIdent' => $request->NDocIdent,
            'PaisDocIdent' => $request->PaisDocIdent,
            'Sexo' => $request->Sexo,

            'AnhoNac' => $request->AnhoNac,
            'MesNac' => $request->MesNac,
            'DiaNac' => $request->DiaNac,
            'LugarNac' => $request->LugarNac,
            'PaisNac' => $request->PaisNac,

            'AnhoBtzo' => $request->AnhoBtzo,
            'MesBtzo' => $request->MesBtzo,
            'DiaBtzo' => $request->DiaBtzo,
            'LugarBtzo' => $request->LugarBtzo,
            'PaisBtzo' => $request->PaisBtzo,

            'AnhoMatr' => $request->AnhoMatr,
            'MesMatr' => $request->MesMatr,
            'DiaMatr' => $request->DiaMatr,
            'LugarMatr' => $request->LugarMatr,
            'PaisMatr' => $request->PaisMatr,

            'AnhoDef' => $request->AnhoDef,
            'MesDef' => $request->MesDef,
            'DiaDef' => $request->DiaDef,
            'LugarDef' => $request->LugarDef,
            'PaisDef' => $request->PaisDef,

            'Familiaridad' => $request->Familiaridad,
            'NombresF' => $request->NombresF,
            'ApellidosF' => $request->ApellidosF,
            'ParentescoF' => $request->ParentescoF,
            'NPasaporteF' => $request->NPasaporteF,

            'FRegistro' => $request->FRegistro,
            'Observaciones' => $request->Observaciones,
            'Enlace' => $request->Enlace,

            'PNacimiento' => $PNacimiento,
            'IDPadre' => $IDPadre,
            'IDMadre' => $IDMadre,
            'Generacion' => $Generacion,
            'LNacimiento' => $LNacimiento,
            'Familiares' => $Familiares,
            'FUpdate' => $FUpdate,
            'Usuario' => $Usuario,  
        ]);

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha añadido a la lista: ' . $request->Nombres . ' ' . $request->Apellidos);
        
        // Redireccionar a la vista index
        return redirect()->route('crud.agclientes.index');
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

        //return redirect()->route('crud.agclientes.index');
        return back();
    }
}
