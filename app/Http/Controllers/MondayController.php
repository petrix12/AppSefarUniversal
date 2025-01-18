<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Monday;
use App\Models\Factura;
use App\Models\Compras;
use App\Models\Agcliente;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
        $passport = $request->passport;

        $users = json_decode(json_encode(User::where("passport", "LIKE", $request->passport)->get()),true);

        if ($users[0]["pay"]!=2 || $users[0]["pay"]!="2"){
            return redirect()->route('mondayregistrar')->with("status","error2");
        }

        if (sizeof($users)>0){
            $query = "SELECT a.*, b.name, b.passport, b.email, b.phone, b.created_at as fecha_de_registro FROM facturas as a, users as b WHERE a.id_cliente = b.id AND b.passport='".$passport."' ORDER BY a.id DESC LIMIT 1;";

            $datos_factura = json_decode(json_encode(DB::select($query)),true);

            if (sizeof($datos_factura)>1){

                $productos = json_decode(json_encode(Compras::where("hash_factura", $datos_factura[0]["hash_factura"])->get()),true);
                $servicios = "";

                foreach ($productos as $key => $value) {
                    $servicios = $servicios . $value["servicio_hs_id"];
                    if ($key != count($productos)-1){
                        $servicios = $servicios . ", ";
                    }
                }
            } else {
                $servicios = $users[0]["servicio"];
            }

            $familiarescheck = json_decode(json_encode(Agcliente::where('IDCliente','LIKE',$passport)->get()),true);

            $nombres_y_apellidos_del_padre = "";
            $nombres_y_apellidos_de_madre = "";

            if (sizeof($familiarescheck) > 1) {
                if($familiarescheck[1]["Sexo"]=="M"){
                    $nombres_y_apellidos_del_padre = $familiarescheck[1]["Nombres"] . " " . $familiarescheck[1]["Apellidos"];
                    $nombres_y_apellidos_de_madre = $familiarescheck[2]["Nombres"] . " " . $familiarescheck[2]["Apellidos"];
                } else {
                    $nombres_y_apellidos_del_padre = $familiarescheck[2]["Nombres"] . " " . $familiarescheck[2]["Apellidos"];
                    $nombres_y_apellidos_de_madre = $familiarescheck[1]["Nombres"] . " " . $familiarescheck[1]["Apellidos"];
                }
            }


            $fechanacimiento = $familiarescheck[0]["AnhoNac"]."-".$familiarescheck[0]["MesNac"]."-".$familiarescheck[0]["DiaNac"];

            $token = env('MONDAY_TOKEN');
            $apiUrl = 'https://api.monday.com/v2';
            $headers = ['Content-Type: application/json', 'Authorization: ' . $token];

            $link = 'https://app.sefaruniversal.com/tree/' . $passport;

            $query = 'mutation ($myItemName: String!, $columnVals: JSON!) { create_item (board_id: 878831315, group_id: "duplicate_of_en_proceso", item_name:$myItemName, column_values:$columnVals) { id } }';

            if (is_null($users[0]["apellidos"]) || is_null($users[0]["nombres"])){
                $clientname = $users[0]["name"];
            } else {
                $clientname = $users[0]["apellidos"]." ".$users[0]["nombres"];
            }

            $vars = [
                'myItemName' => $clientname,
                'columnVals' => json_encode([
                    'texto' => $passport,
                    'fecha75' => ['date' => date("Y-m-d", strtotime($fechanacimiento))],
                    'texto_largo8' => $nombres_y_apellidos_del_padre,
                    'texto_largo75' => $nombres_y_apellidos_de_madre,
                    'enlace' => $link . " " . $link,
                    'estado54' => 'Arbol Incompleto',
                    'texto1' => $servicios,
                    'texto4' => $users[0]["hs_id"]
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

            return redirect()->route('mondayregistrar')->with("status","ok");
        } else {
            return redirect()->route('mondayregistrar')->with("status","error");
        }

    }
}
