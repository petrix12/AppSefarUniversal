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
        $mondayboards_temp = json_decode(json_encode(Monday::customQuery("boards (limit: 500) { id name }")),true);

        $mondayboards = $mondayboards_temp["boards"];

        $usuario_mdy = [];

        $vartest = 0;

        //proceso la informacion

        $stats = [];
        $eventas = [];

        foreach ($analisis as $value) {

            //me traigo toda la informacion de una tabla en especifico

            $mondayboards_temp = json_decode(json_encode(Monday::customQuery('boards (ids: ' . $value . ')  { id name items { name column_values (ids: ["status"] ) { title text id } } }')),true);

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
}
