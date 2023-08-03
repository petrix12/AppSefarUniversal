<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Monday;

class MondayController extends Controller
{
    /*INFO DE MONDAY*/

    public function mondayreportes()
    {
        //Tablas que NO voy a llamar
        $analisis = [
            "878831315",
            "625187241",
            "3469085450",
            "708123651",
            "669590637",
            "1845706367",
            "1845701215",
            "1845710504",
            "708128239",
            "3950637564",
            "1162066037",
            "2213224176"
        ];

        $etiquetadovsefar = "765394861";

        //me traigo todas las tablas de monday
        $mondayboards_temp = json_decode(json_encode(Monday::customQuery("boards (limit: 1000) { id name }")),true);

        $mondayboards = $mondayboards_temp["boards"];

        $usuario_mdy = [];

        $vartest = 0;

        //proceso la informacion

        $stats = [];
        $eventas = [];

        foreach ($analisis as $value) {

            //me traigo toda la informacion de una tabla en especifico

            $mondayboards_temp = json_decode(json_encode(Monday::customQuery('boards (ids: ' . $value . ', limit: 500000)  { id name items { name column_values (ids: ["status"] ) { title text id } } }')),true);

            //proceso la informacion

            foreach ($mondayboards_temp["boards"][0]["items"] as $value) {

                $table = $mondayboards_temp["boards"][0]["name"];

                foreach ($value["column_values"] as $value2) {
                    if ($value2["id"]=="status") {
                        if (isset($stats[$mondayboards_temp["boards"][0]["name"]][$value2["text"]])){
                            $stats[$mondayboards_temp["boards"][0]["name"]][$value2["text"]]++;
                        } else {
                            $stats[$mondayboards_temp["boards"][0]["name"]][$value2["text"]] = 1;
                        }
                    }
                }
            }
        }

        //Etiquetado Ventas Sefar

        $mondayboards_temp = json_decode(json_encode(Monday::customQuery('boards (ids: ' . $etiquetadovsefar . ')  { id name items { name column_values (ids: ["status"] ) { title text id } } }')),true);

        //proceso la informacion

        foreach ($mondayboards_temp["boards"][0]["items"] as $value) {

            $table = $mondayboards_temp["boards"][0]["name"];

            foreach ($value["column_values"] as $value2) {
                if ($value2["id"]=="status") {
                    if (isset($eventas[$mondayboards_temp["boards"][0]["name"]][$value2["text"]])){
                        $eventas[$mondayboards_temp["boards"][0]["name"]][$value2["text"]]++;
                    } else {
                        $eventas[$mondayboards_temp["boards"][0]["name"]][$value2["text"]] = 1;
                    }
                }
            }
        }

        return view('crud.monday.stats', compact('stats', 'eventas'));

    }

    public function mondayregistrar()
    {
        return view('crud.monday.registrar');
    }

    public function validarMD(Request $request)
    {
        /*INFO DE MONDAY*/
        dd($request->all());

        $userpassport = "0000";

        //Tablas que NO voy a llamar
        $preventmondayids = [
            "3016568563",
            "3016439235",
            "3016427689",
            "3016425138",
            "2922023945",
            "2921955649",
            "2840594467",
            "2369283634",
            "2267403210",
            "2178303858",
            "2135021222",
            "1721146413",
            "1708668268",
            "1708668252",
            "1531350971",
            "1078272587",
            "1078272574",
            "1078272554",
            "1029708419",
            "867510225",
        ];

        //me traigo todas las tablas de monday
        $mondayboards_temp = json_decode(json_encode(Monday::customQuery("boards (limit: 500) { id name }")),true);

        $mondayboards = $mondayboards_temp["boards"];

        $usuario_mdy = [];

        $vartest = 0;

        //proceso la informacion

        foreach ($mondayboards as $key => $value) {
            if (!str_contains($value["name"], 'Subelementos de ')) {
                if( !in_array($value["id"], $preventmondayids) ){

                    //me traigo toda la informacion de una tabla en especifico

                    $mondayboards_temp = json_decode(json_encode(Monday::customQuery("boards (ids: " . $value["id"] . ")  { id name items { name column_values { title text id } } }")),true);

                    //proceso la informacion

                    foreach ($mondayboards_temp["boards"][0]["items"] as $key => $item) {
                        foreach ($item["column_values"] as $keycv => $cv) {
                            if ($cv["id"]=="enlace"){
                                if ($cv["text"]="https://app.universalsefar.com/tree/".$userpassport){
                                    $vartest = 1;
                                    $usuario_mdy = $item;
                                    $usuario_mdy["tabla_nombre"] = $mondayboards_temp["boards"][0]["name"];
                                }
                                break;
                            } 
                        }
                        if($vartest==1){
                            break;
                        }
                    };
                }
                
            }

            if($vartest==1){
                break;
            }

        }

        if (sizeof($usuario_mdy)>0){
            dd($usuario_mdy);
        } else {
            echo "0";
        }

        
    }

    public function registrarMD(){

    }
}
