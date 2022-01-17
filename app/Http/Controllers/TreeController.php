<?php

namespace App\Http\Controllers;

use App\Models\Agcliente;
use Illuminate\Http\Request;

class TreeController extends Controller
{
    public function tree($IDCliente){
        // Si el usuario tiene el rol Traviesoevans
        if(Auth()->user()->hasRole('Traviesoevans')){
            $autorizado = Agcliente::where('referido','LIKE','Travieso Evans')
                ->where('IDCliente','LIKE',$IDCliente)
                ->count();
            if($autorizado == 0){
                return view('crud.agclientes.index');
            }
        }

        // Si el usuario tiene el rol Vargassequera
        if(Auth()->user()->hasRole('Vargassequera')){
            $autorizado = Agcliente::where('referido','LIKE','Patricia Vargas Sequera')
                ->where('IDCliente','LIKE',$IDCliente)
                ->count();
            if($autorizado == 0){
                return view('crud.agclientes.index');
            }
        }
        
        // Si el usuario tiene el rol BadellLaw
        if(Auth()->user()->hasRole('BadellLaw')){
            $autorizado = Agcliente::where('referido','LIKE','Badell Law')
                ->where('IDCliente','LIKE',$IDCliente)
                ->count();
            if($autorizado == 0){
                return view('crud.agclientes.index');
            }
        }
        
        // Si el usuario tiene el rol P&V-Abogados
        if(Auth()->user()->hasRole('P&V-Abogados')){
            $autorizado = Agcliente::where('referido','LIKE','P & V Abogados')
                ->where('IDCliente','LIKE',$IDCliente)
                ->count();
            if($autorizado == 0){
                return view('crud.agclientes.index');
            }
        }
        
        // Si el usuario tiene el rol Mujica-Coto
        if(Auth()->user()->hasRole('Mujica-Coto')){
            $autorizado = Agcliente::where('referido','LIKE','Mujica y Coto Abogados')
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