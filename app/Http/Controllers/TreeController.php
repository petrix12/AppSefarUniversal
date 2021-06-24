<?php

namespace App\Http\Controllers;

use App\Models\Agcliente;
use Illuminate\Http\Request;

class TreeController extends Controller
{
    public function tree($IDCliente){
        if(Auth()->user()->hasRole('Traviesoevans')){
            $autorizado = Agcliente::where('referido','LIKE','Traviesoevans')
                ->where('IDCliente','LIKE',$IDCliente)
                ->count();
            if($autorizado == 0){
                return view('crud.agclientes.index');
            }
        }
        $existe = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',1)->get();
        if($existe->count()){
            return view('arboles.tree', compact('IDCliente'));
        }else{
            return redirect()->route('crud.agclientes.index')->with('info','IDCliente: '.$IDCliente.' no encontrado');
        }
    }
}
