<?php

namespace App\Http\Controllers;

use App\Models\Agcliente;
use App\Models\TFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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

        // Si el usuario tiene el rol German Fleitas
        if(Auth()->user()->hasRole('German-Fleitas')){
            $autorizado = Agcliente::where('referido','LIKE','German Fleitas')
                ->where('IDCliente','LIKE',$IDCliente)
                ->count();
            if($autorizado == 0){
                return view('crud.agclientes.index');
            }
        }

        // Si el usuario tiene el rol Soma Consultores
        if(Auth()->user()->hasRole('Soma-Consultores')){
            $autorizado = Agcliente::where('referido','LIKE','Soma Consultores')
                ->where('IDCliente','LIKE',$IDCliente)
                ->count();
            if($autorizado == 0){
                return view('crud.agclientes.index');
            }
        }
        
        // Si el usuario tiene el rol MG Tours
        if(Auth()->user()->hasRole('MG-Tours')){
            $autorizado = Agcliente::where('referido','LIKE','MG Tours')
                ->where('IDCliente','LIKE',$IDCliente)
                ->count();
            if($autorizado == 0){
                return view('crud.agclientes.index');
            }
        }

        $existe = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',1)->get();
        if($existe->count()){
            $people = json_decode(json_encode(Agcliente::where("IDCliente",$IDCliente)->get()),true);

            //Asignar ids de padres al nodo 0 (en caso de no tenerlo)
            if (count($people)>2){
                if(!isset($people[0]['IDMadre'])){
                    if($people[1]["Sexo"]=="F"){
                        $people[0]['IDMadre']=$people[1]['IDPersona'];
                        $people[0]['IDPadre']=$people[2]['IDPersona'];
                    } else {
                        $people[0]['IDMadre']=$people[2]['IDPersona'];
                        $people[0]['IDPadre']=$people[1]['IDPersona'];
                    }
                }
            }

            //Eliminar basura de los ids de los padres        
            foreach ($people as $key => $person) {
                if ($person['IDMadre']<1){
                    $people[$key]['IDMadre']=null;
                }
                if ($person['IDPadre']<1){
                    $people[$key]['IDPadre']=null;
                }
            }

            $idPersonaToIdMap = [];
            foreach ($people as $item) {
                $idPersonaToIdMap[$item['IDPersona']] = $item['id'];
            }

            foreach ($people as &$item) {
                if ($person["migradoNuevoID"]==0){
                    if (isset($item['IDPadre']) && isset($idPersonaToIdMap[$item['IDPadre']])) {
                        $item['idPadreNew'] = $idPersonaToIdMap[$item['IDPadre']];
                    } else {
                        $item['idPadreNew'] = null;
                    }
                    if (isset($item['IDMadre']) && isset($idPersonaToIdMap[$item['IDMadre']])) {
                        $item['idMadreNew'] = $idPersonaToIdMap[$item['IDMadre']];
                    } else {
                        $item['idMadreNew'] = null;
                    }
                }
            }

            foreach ($people as $person) {
                if($person["migradoNuevoID"]==0){
                    DB::table('agclientes')
                    ->where('id', $person['id'])
                    ->update([
                        'idPadreNew' => $person['idPadreNew'],
                        'idMadreNew' => $person['idMadreNew'],
                        'migradoNuevoID' => 1
                    ]);
                }
            }

            $arreglo = $people;
            $generaciones = array();

            foreach ($arreglo as $id => $persona) {
                if ($persona['idPadreNew'] === null && $persona['idMadreNew'] === null) {
                    $generaciones[$persona["id"]] = 1;
                }
            }
            
            $cambio = true;
            while ($cambio) {
                $cambio = false;
                foreach ($arreglo as $id => $persona) {
                    $generacionPadre = isset($generaciones[$persona['idPadreNew']]) ? $generaciones[$persona['idPadreNew']] : 0;
                    $generacionMadre = isset($generaciones[$persona['idMadreNew']]) ? $generaciones[$persona['idMadreNew']] : 0;
                    $generacionActual = max($generacionPadre, $generacionMadre) + 1;
                    
                    if (!isset($generaciones[$persona["id"]]) || $generaciones[$persona["id"]] != $generacionActual) {
                        $generaciones[$persona["id"]] = $generacionActual;
                        $cambio = true;
                    }
                }
            }

            $maxGeneraciones = max($generaciones);

            $maxGeneraciones++;

            $columnasparatabla = array();

            for ($i=0; $i<$maxGeneraciones; $i++){
                if ($i == 0){
                    if(!isset($columnasparatabla[$i])){
                        $columnasparatabla[$i] = [];
                    }

                    $columnasparatabla[$i][] =  $arreglo[0];
                    $columnasparatabla[$i][0]["showbtn"] = 2;  //2 es persona, 1 es boton de añadir, 0 es nada 
                } else {
                    foreach ($columnasparatabla[$i-1] as $key2 => $persona2){
                            
                        if(!isset($columnasparatabla[$i])){
                            $columnasparatabla[$i] = [];
                            $j = 0;
                        } else {
                            $j = sizeof($columnasparatabla[$i]);
                        }

                        //padre
                        
                        if (@$persona2["idPadreNew"]==null){

                            if ($persona2["showbtn"] == 0) {
                                $columnasparatabla[$i][$j]["showbtn"] = 0;
                            } else if ($persona2["showbtn"] == 1) {
                                $columnasparatabla[$i][$j]["showbtn"] = 0;
                            } else {
                                $columnasparatabla[$i][$j]["showbtn"] = 1;
                                $columnasparatabla[$i][$j]["showbtnsex"] = "m";
                                $columnasparatabla[$i][$j]["id_hijo"] = $persona2["id"];
                            }

                        } else {
                            foreach ($arreglo as $key => $persona) {
                                if ($persona2["idPadreNew"] == $arreglo[$key]["id"]){
                                    $columnasparatabla[$i][$j] = $arreglo[$key];
                                    $columnasparatabla[$i][$j]["showbtn"] = 2;
                                    break;
                                }
                            }

                        }

                        $j++;

                        // madre

                        if (@$persona2["idMadreNew"]==null){

                            if ($persona2["showbtn"] == 0) {
                                $columnasparatabla[$i][$j]["showbtn"] = 0;
                            } else if ($persona2["showbtn"] == 1) {
                                $columnasparatabla[$i][$j]["showbtn"] = 0;
                            } else {
                                $columnasparatabla[$i][$j]["showbtn"] = 1;
                                $columnasparatabla[$i][$j]["showbtnsex"] = "f";
                                $columnasparatabla[$i][$j]["id_hijo"] = $persona2["id"];
                            }

                        } else {

                            foreach ($arreglo as $key => $persona) {
                                if ($persona2["idMadreNew"] == $arreglo[$key]["id"]){
                                    $columnasparatabla[$i][$j] = $arreglo[$key];
                                    $columnasparatabla[$i][$j]["showbtn"] = 2;
                                    break;
                                }
                            }

                        }
                    }
                }
            }

            $tipoarchivos = TFile::all();

            $cliente = json_decode(json_encode(User::where("passport",$IDCliente)->get()),true);

            return view('arboles.tree', compact('IDCliente', 'people', 'columnasparatabla', 'cliente', 'tipoarchivos'));

        }else{
            return redirect()->route('crud.agclientes.index')->with('info','IDCliente: '.$IDCliente.' no encontrado');
        }
    }
}
