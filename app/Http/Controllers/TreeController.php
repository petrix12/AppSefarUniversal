<?php

namespace App\Http\Controllers;

use App\Models\Agcliente;
use App\Models\TFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class TreeController extends Controller
{
    public function treepart($IDCliente, $idToCheck, $gentocheck, $parenttocheck){
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

        $searchCliente = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',1)->first();
        $existe = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',1)->get();
        if($existe->count()){

            //revisar padres
            $padreQuery = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',2)->first();

            $madreQuery = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',3)->first();

            if ($padreQuery || $madreQuery) {
                $data = [
                    'migradoNuevoID' => 1,
                ];

                // Si existe el padre, añade 'idPadreNew' y 'IDPadre' al array de actualización
                if ($padreQuery) {
                    $data['idPadreNew'] = $padreQuery->id;
                    $data['IDPadre'] = 2;
                }

                // Si existe la madre, añade 'idMadreNew' y 'IDMadre' al array de actualización
                if ($madreQuery) {
                    $data['idMadreNew'] = $madreQuery->id;
                    $data['IDMadre'] = 3;
                }

                // Ejecuta la actualización
                DB::table('agclientes')
                    ->where('id', $searchCliente->id)
                    ->update($data);
            }


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

            $personaInicio = [];
            foreach ($people as $key => $persona) {
                if ($persona['id'] == $idToCheck) {
                    $personaInicio = $people[$key];
                    break;
                }
            }

            // Inicializar el arreglo con la persona seleccionada
            $arreglo = [$personaInicio];
            $generaciones = [$personaInicio['id'] => 1];

            $padresPorRevisar = [$personaInicio];

            // Iterar sobre cada generación hacia atrás (padres, abuelos, bisabuelos, etc.)
            while (!empty($padresPorRevisar)) {
                $nuevosPadres = [];
                
                foreach ($padresPorRevisar as $persona) {
                    // Verificar y añadir el padre a la lista si existe
                    if ($persona['idPadreNew'] !== null) {
                        foreach ($people as $key => $potencialPadre) {
                            if ($potencialPadre['id'] == $persona['idPadreNew']) {
                                $nuevosPadres[] = $potencialPadre;
                                $arreglo[] = $potencialPadre;
                                $generaciones[$potencialPadre['id']] = $generaciones[$persona['id']] + 1;
                                break;
                            }
                        }
                    }

                    // Verificar y añadir la madre a la lista si existe
                    if ($persona['idMadreNew'] !== null) {
                        foreach ($people as $key => $potencialMadre) {
                            if ($potencialMadre['id'] == $persona['idMadreNew']) {
                                $nuevosPadres[] = $potencialMadre;
                                $arreglo[] = $potencialMadre;
                                $generaciones[$potencialMadre['id']] = $generaciones[$persona['id']] + 1;
                                break;
                            }
                        }
                    }
                }
                
                // Continuar con los nuevos padres encontrados
                $padresPorRevisar = $nuevosPadres;
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

            $parentescos = [];
            $parentescos_post_padres = [
                "Abuel", 
                "Bisabuel", 
                "Tatarabuel", 
                "Trastatarabuel", 
                "Retatarabuel", 
                "Sestarabuel", 
                "Setatarabuel", 
                "Octatarabuel", 
                "Nonatarabuel",
                "Decatarabuel",
                "Undecatarabuel",
                "Duodecatarabuel",
                "Trececatarabuel",
                "Catorcatarabuel",
                "Quincecatarabuel",
                "Deciseiscatarabuel",
                "Decisietecatarabuel",
                "Deciochocatarabuel",
                "Decinuevecatarabuel",
                "Vigecatarabuel",
                "Vigecimoprimocatarabuel",
                "Vigecimosegundocatarabuel",
                "Vigecimotercercatarabuel",
                "Vigecimocuartocatarabuel",
                "Vigecimoquintocatarabuel",
                "Vigecimosextocatarabuel",
                "Vigecimoseptimocatarabuel",
                "Vigecimooctavocatarabuel",
                "Vigecimonovenocatarabuel",
                "Trigecatarabuel",
                "Trigecimoprimocatarabuel",
                "Trigecimosegundocatarabuel",
                "Trigecimotercercatarabuel",
                "Trigecimocuartocatarabuel",
                "Trigecimoquintocatarabuel",
                "Trigecimosextocatarabuel",
                "Trigecimoseptimocatarabuel",
                "Trigecimooctavocatarabuel",
                "Trigecimonovenocatarabuel",
                "Cuarentacatarabuel",
                "Cuarentaprimocatarabuel",
                "Cuarentasegundocatarabuel",
                "Cuarentatercercatarabuel",
            ];
            $prepar = 4;

            function generarTexto($i, $key) {
                $text = "";
                $multiplicador = 4;

                for ($j = 1; $j <= $key; $j++) {
                    $text .= (($i % $multiplicador) < ($multiplicador / 2) ? "P " : "M ");
                    $multiplicador *= 2;
                }

                $text .= ($i < 2 * ($key + 1) ? "P" : "M");
                return $text;
            }

            foreach ($parentescos_post_padres as $key => $parentesco) {
                if($key <= sizeof($columnasparatabla)){
                    $parentescos[$key] = [];

                    for ($i = 0; $i < $prepar; $i++) {
                        $textparentesco = $parentesco . ($i % 2 == 0 ? "o" : "a");
                        $text = generarTexto($i, $key);
                        $parentescos[$key][] = $textparentesco . " " . $text;
                    }

                    $prepar *= 2;
                }
            }

            $tipoarchivos = TFile::all();

            $cliente = json_decode(json_encode(User::where("passport",$IDCliente)->get()),true);

            $temparr = [];
            $var = 7;
            foreach ($columnasparatabla as $key => $columna){
                if($key<$var){
                    $temparr[] = $columna;
                }
            }

            $generacionBase = $gentocheck;

            $columnasparatabla = $temparr;

            $checkBtn = "si";

            $parentnumber = $parenttocheck;

            $htmlGenerado = view('arboles.vistatree', compact('generacionBase', 'columnasparatabla', 'parentescos', 'checkBtn', 'parentnumber'))->render();

            return view('arboles.tree', compact('IDCliente', 'people', 'columnasparatabla', 'cliente', 'tipoarchivos', 'parentescos', 'htmlGenerado', 'checkBtn', 'generacionBase', 'parentnumber'));

        }else{
            return redirect()->route('crud.agclientes.index')->with('info','IDCliente: '.$IDCliente.' no encontrado');
        }
    }

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

        $searchCliente = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',1)->first();
        $existe = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',1)->get();
        if($existe->count()){

            //revisar padres
            $padreQuery = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',2)->first();

            $madreQuery = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',3)->first();

            if ($padreQuery || $madreQuery) {
                $data = [
                    'migradoNuevoID' => 1,
                ];

                // Si existe el padre, añade 'idPadreNew' y 'IDPadre' al array de actualización
                if ($padreQuery) {
                    $data['idPadreNew'] = $padreQuery->id;
                    $data['IDPadre'] = 2;
                }

                // Si existe la madre, añade 'idMadreNew' y 'IDMadre' al array de actualización
                if ($madreQuery) {
                    $data['idMadreNew'] = $madreQuery->id;
                    $data['IDMadre'] = 3;
                }

                // Ejecuta la actualización
                DB::table('agclientes')
                    ->where('id', $searchCliente->id)
                    ->update($data);
            }


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

            $parentescos = [];
            $parentescos_post_padres = [
                "Abuel", 
                "Bisabuel", 
                "Tatarabuel", 
                "Trastatarabuel", 
                "Retatarabuel", 
                "Sestarabuel", 
                "Setatarabuel", 
                "Octatarabuel", 
                "Nonatarabuel",
                "Decatarabuel",
                "Undecatarabuel",
                "Duodecatarabuel",
                "Trececatarabuel",
                "Catorcatarabuel",
                "Quincecatarabuel",
                "Deciseiscatarabuel",
                "Decisietecatarabuel",
                "Deciochocatarabuel",
                "Decinuevecatarabuel",
                "Vigecatarabuel",
                "Vigecimoprimocatarabuel",
                "Vigecimosegundocatarabuel",
                "Vigecimotercercatarabuel",
                "Vigecimocuartocatarabuel",
                "Vigecimoquintocatarabuel",
                "Vigecimosextocatarabuel",
                "Vigecimoseptimocatarabuel",
                "Vigecimooctavocatarabuel",
                "Vigecimonovenocatarabuel",
                "Trigecatarabuel",
                "Trigecimoprimocatarabuel",
                "Trigecimosegundocatarabuel",
                "Trigecimotercercatarabuel",
                "Trigecimocuartocatarabuel",
                "Trigecimoquintocatarabuel",
                "Trigecimosextocatarabuel",
                "Trigecimoseptimocatarabuel",
                "Trigecimooctavocatarabuel",
                "Trigecimonovenocatarabuel",
                "Cuarentacatarabuel",
                "Cuarentaprimocatarabuel",
                "Cuarentasegundocatarabuel",
                "Cuarentatercercatarabuel",
            ];
            $prepar = 4;

            function generarTexto($i, $key) {
                $text = "";
                $multiplicador = 4;

                for ($j = 1; $j <= $key; $j++) {
                    $text .= (($i % $multiplicador) < ($multiplicador / 2) ? "P " : "M ");
                    $multiplicador *= 2;
                }

                $text .= ($i < 2 * ($key + 1) ? "P" : "M");
                return $text;
            }

            foreach ($parentescos_post_padres as $key => $parentesco) {
                if($key <= sizeof($columnasparatabla)){
                    $parentescos[$key] = [];

                    for ($i = 0; $i < $prepar; $i++) {
                        $textparentesco = $parentesco . ($i % 2 == 0 ? "o" : "a");
                        $text = generarTexto($i, $key);
                        $parentescos[$key][] = $textparentesco . " " . $text;
                    }

                    $prepar *= 2;
                }
            }

            $tipoarchivos = TFile::all();



            $cliente = json_decode(json_encode(User::where("passport",$IDCliente)->get()),true);

            $temparr = [];
            $var = 5;
            foreach ($columnasparatabla as $key => $columna){
                if($key<$var){
                    $temparr[] = $columna;
                }
                foreach ($columna as $key2 => $persona) {
                    if ($persona["showbtn"] == 2) {
                        if ($persona["PersonaIDNew"] == null || $persona["PersonaIDNew"] == "null"){
                            DB::table('agclientes')
                            ->where('id', $persona['id'])
                            ->update([
                                'PersonaIDNew' => $key2
                            ]);
                            $columnasparatabla[$key][$key2]["PersonaIDNew"] = $key2;
                        }
                    }
                }
            }

            $generacionBase = 0;

            $columnasparatabla = $temparr;

            $checkBtn = "no";

            $parentnumber = 0;

            $htmlGenerado = view('arboles.vistatree', compact('generacionBase', 'columnasparatabla', 'parentescos', 'checkBtn', 'parentnumber'))->render();

            return view('arboles.tree', compact('IDCliente', 'people', 'columnasparatabla', 'cliente', 'tipoarchivos', 'parentescos', 'htmlGenerado', 'checkBtn', 'generacionBase', 'parentnumber'));

        }else{
            return redirect()->route('crud.agclientes.index')->with('info','IDCliente: '.$IDCliente.' no encontrado');
        }
    }
}
