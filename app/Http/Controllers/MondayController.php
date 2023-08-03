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

    }

    public function registrarMD(Request $request){
        dd($request->passport);

        $query = "SELECT a.*, b.name, b.passport, b.email, b.phone, b.created_at as fecha_de_registro FROM facturas as a, users as b WHERE a.id_cliente = b.id AND b.passport='".$user->passport."' ORDER BY a.id DESC LIMIT 1;";

        $datos_factura = json_decode(json_encode(DB::select(DB::raw($query))),true);

        $productos = json_decode(json_encode(Compras::where("hash_factura", $datos_factura[0]["hash_factura"])->get()),true);

        $servicios = "";

        foreach ($productos as $key => $value) {
            $servicios = $servicios . $value["servicio_hs_id"];
            if ($key != count($productos)-1){
                $servicios = $servicios . ", ";
            }
        }

        $token = env('MONDAY_TOKEN');
        $apiUrl = 'https://api.monday.com/v2';
        $headers = ['Content-Type: application/json', 'Authorization: ' . $token];

        $link = 'https://app.universalsefar.com/tree/' . auth()->user()->passport;
        
        $query = 'mutation ($myItemName: String!, $columnVals: JSON!) { create_item (board_id: 878831315, group_id: "duplicate_of_en_proceso", item_name:$myItemName, column_values:$columnVals) { id } }';
         
        $vars = [
            'myItemName' => auth()->user()->apellidos." ".auth()->user()->nombres, 
            'columnVals' => json_encode([
                'texto' => auth()->user()->passport,
                'fecha75' => ['date' => date("Y-m-d", strtotime($input['fecha_nac']))],
                'texto_largo8' => $nombres_y_apellidos_del_padre,
                'texto_largo75' => $nombres_y_apellidos_de_madre,
                'enlace' => ['link' => $link],
                'estado54' => 'Arbol Incompleto',
                'texto1' => $servicios,
                'texto4' => auth()->user()->hs_id
            ])
        ];

        $data = @file_get_contents($apiUrl, false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => $headers,
                    'content' => json_encode(['query' => $query, 'variables' => $vars]),
                ]
            ]
        ));

        $obdata = json_decode($data,true);

        $regid = $obdata["data"]['create_item']['id'];

        $query2 = 'mutation ($myItemId:Int!, $myColumnValue: String!, $columnId: String!) { change_simple_column_value (item_id:$myItemId, board_id:878831315, column_id: $columnId, value: $myColumnValue) { id } }';

        $vars2 = [
            'myItemId' => intval($regid), 
            'columnId' => 'enlace',
            'myColumnValue' => $link . " " . $link,
        ];

        $data2 = @file_get_contents($apiUrl, false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => $headers,
                    'content' => json_encode(['query' => $query2, 'variables' => $vars2]),
                ]
            ]
        ));

        $responseContent = json_decode($data, true);

        echo json_encode($responseContent);
    }
}
