<?php

namespace App\Http\Controllers;

use App\Models\Agcliente;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
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
        // Validación de persona duplicada
        $persona = Agcliente::where('IDCliente','LIKE',$request->IDCliente)
            ->where('IDPersona','LIKE',$request->IDPersona)
            ->get();
        if($persona->count()>0){
            Alert::error('¡Warning!', 'La persona que intenta añadir ya existe');
            return back();
        }

        // Validación
        $request->validate([
            'IDCliente' => 'required|max:50',
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

        
        $PNacimiento = trim($request->PaisNac);
        $LNacimiento = trim($request->LugarNac);
        $Familiares = trim($request->Familiaridad);
        $Usuario = Auth()->user()->email;
        $Generacion = GetGeneracion($request->IDPersona);
        $IDPadre = GetIDPadre($request->IDPersona);
        $IDMadre = $IDPadre + 1;
        $FUpdate = date('Y-m-d H:i:s');
        
        // Creando usuario
        Agcliente::create([
            'IDCliente' => trim($request->IDCliente), 
            'Nombres' => trim($request->Nombres),
            'Apellidos' => trim($request->Apellidos),

            'IDPersona' => $request->IDPersona,
            'NPasaporte' => trim($request->NPasaporte),
            'PaisPasaporte' => $request->PaisPasaporte,
            'NDocIdent' => trim($request->NDocIdent),
            'PaisDocIdent' => $request->PaisDocIdent,
            'Sexo' => $request->Sexo,

            'AnhoNac' => $request->AnhoNac,
            'MesNac' => $request->MesNac,
            'DiaNac' => $request->DiaNac,
            'LugarNac' => trim($request->LugarNac),
            'PaisNac' => $request->PaisNac,

            'AnhoBtzo' => $request->AnhoBtzo,
            'MesBtzo' => $request->MesBtzo,
            'DiaBtzo' => $request->DiaBtzo,
            'LugarBtzo' => trim($request->LugarBtzo),
            'PaisBtzo' => $request->PaisBtzo,

            'AnhoMatr' => $request->AnhoMatr,
            'MesMatr' => $request->MesMatr,
            'DiaMatr' => $request->DiaMatr,
            'LugarMatr' => trim($request->LugarMatr),
            'PaisMatr' => $request->PaisMatr,

            'AnhoDef' => $request->AnhoDef,
            'MesDef' => $request->MesDef,
            'DiaDef' => $request->DiaDef,
            'LugarDef' => trim($request->LugarDef),
            'PaisDef' => $request->PaisDef,

            'Familiaridad' => $request->Familiaridad,
            'NombresF' => trim($request->NombresF),
            'ApellidosF' => trim($request->ApellidosF),
            'ParentescoF' => trim($request->ParentescoF),
            'NPasaporteF' => trim($request->NPasaporteF),

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
        //return redirect()->route('crud.agclientes.index');
        //return back();

        // Redireccionar a la vista que invocó este método
        return redirect($request->urlPrevia);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Agcliente  $agcliente
     * @return \Illuminate\Http\Response
     */
    public function show(Agcliente $agcliente)
    {
        $countries = Country::all();
        return view('crud.agclientes.edit', compact('agcliente','countries'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Agcliente  $agcliente
     * @return \Illuminate\Http\Response
     */
    public function edit(Agcliente $agcliente)
    {
        $countries = Country::all();
        return view('crud.agclientes.edit', compact('agcliente','countries'));
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
        // Validación
        $request->validate([
            'IDCliente' => 'required|max:50',
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

        // Actualizando persona
        $agcliente->PNacimiento = trim($request->PaisNac);
        $agcliente->LNacimiento = trim($request->LugarNac);
        $agcliente->Familiares = trim($request->Familiaridad);
        $agcliente->Usuario = Auth()->user()->email;
        $agcliente->Generacion = GetGeneracion($request->IDPersona);
        $agcliente->IDPadre = GetIDPadre($request->IDPersona);
        $agcliente->IDMadre = $agcliente->IDPadre + 1;
        $agcliente->FUpdate = date('Y-m-d H:i:s');
        
        $agcliente->IDCliente = trim($request->IDCliente);
        $agcliente->Nombres = trim($request->Nombres);
        $agcliente->Apellidos = trim($request->Apellidos);

        $agcliente->IDPersona = $request->IDPersona;
        $agcliente->NPasaporte = trim($request->NPasaporte);
        $agcliente->PaisPasaporte = $request->PaisPasaporte;
        $agcliente->NDocIdent = trim($request->NDocIdent);
        $agcliente->PaisDocIdent = $request->PaisDocIdent;
        $agcliente->Sexo = $request->Sexo;

        $agcliente->AnhoNac = $request->AnhoNac;
        $agcliente->MesNac = $request->MesNac;
        $agcliente->DiaNac = $request->DiaNac;
        $agcliente->LugarNac = trim($request->LugarNac);
        $agcliente->PaisNac = $request->PaisNac;

        $agcliente->AnhoBtzo = $request->AnhoBtzo;
        $agcliente->MesBtzo = $request->MesBtzo;
        $agcliente->DiaBtzo = $request->DiaBtzo;
        $agcliente->LugarBtzo = trim($request->LugarBtzo);
        $agcliente->PaisBtzo = $request->PaisBtzo;

        $agcliente->AnhoMatr = $request->AnhoMatr;
        $agcliente->MesMatr = $request->MesMatr;
        $agcliente->DiaMatr = $request->DiaMatr;
        $agcliente->LugarMatr = trim($request->LugarMatr);
        $agcliente->PaisMatr = $request->PaisMatr;

        $agcliente->AnhoDef = $request->AnhoDef;
        $agcliente->MesDef = $request->MesDef;
        $agcliente->DiaDef = $request->DiaDef;
        $agcliente->LugarDef = trim($request->LugarDef);
        $agcliente->PaisDef = $request->PaisDef;

        $agcliente->Familiaridad = $request->Familiaridad;
        $agcliente->NombresF = trim($request->NombresF);
        $agcliente->ApellidosF = trim($request->ApellidosF);
        $agcliente->ParentescoF = trim($request->ParentescoF);
        $agcliente->NPasaporteF = trim($request->NPasaporteF);

        $agcliente->FRegistro = $request->FRegistro;
        $agcliente->Observaciones = $request->Observaciones;
        $agcliente->Enlace = $request->Enlace;

        $agcliente->save();

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha actualizado la información de: ' . $request->Nombres . ' ' . $request->Apellidos);
        
        // Redireccionar a la vista que invocó este método
        return redirect($request->urlPrevia);
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
