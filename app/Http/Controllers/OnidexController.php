<?php

namespace App\Http\Controllers;

use App\Models\Onidex;
use Illuminate\Http\Request;

class OnidexController extends Controller
{
    public function index(){
        return view('consultas.onidex.index');
    }

    public function show(Request $request){
        $search = $request->search;
        $nombre1 = $request->nombre1;
        $nombre2 = $request->nombre2;
        $apellido1 = $request->apellido1;
        $apellido2 = $request->apellido2;
        $cedula = $request->cedula;
        $nacion = $request->nacion;
        $cbx_nombre1 = $request->cbx_nombre1;
        $cbx_nombre2 = $request->cbx_nombre2;
        $cbx_apellido1 = $request->cbx_apellido1;
        $cbx_apellido2 = $request->cbx_apellido2;
        $cbx_nombre = $request->cbx_nombre;
        $cbx_apellido = $request->cbx_apellido;
        $cbx_cedula = $request->cbx_cedula;
        $fec_nac = $request->fec_nac;
        $cbx_anho = $request->cbx_anho;
        $cbx_mes = $request->cbx_mes;
        $cbx_dia = $request->cbx_dia;
        $rangofecha = $request->rangofecha;
        $fechainicial = $request->fechainicial;
        $fechafinal = $request->fechafinal;

        return view('consultas.onidex.show', compact(
            'search',
            'nombre1',
            'nombre2',
            'apellido1',
            'apellido2',
            'cedula',
            'nacion',
            'cbx_nombre1',
            'cbx_nombre2',
            'cbx_apellido1',
            'cbx_apellido2',
            'cbx_nombre',
            'cbx_apellido',
            'cbx_cedula',
            'fec_nac',
            'cbx_anho',
            'cbx_mes',
            'cbx_dia',
            'rangofecha',
            'fechainicial',
            'fechafinal'
        ));
    }
}
