<?php

namespace App\Http\Controllers;

use App\Mail\CargaCliente;
use App\Mail\CargaSefar;
use App\Models\Agcliente;
use App\Models\Coupon;
use App\Models\Servicio;
use App\Models\Compras;
use App\Models\HsReferido;
use App\Models\Factura;
use App\Models\User;
use App\Models\File;
use App\Models\Negocio;
use App\Models\TFile;
use App\Models\Hermano;
use App\Models\Alert as Alertas;
use App\Models\GeneralCoupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stripe;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\CardException;
use Stripe\Exception\RateLimitException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use RealRashid\SweetAlert\Facades\Alert;
use Exception;
use HubSpot;
use HubSpot\Client\Crm\Deals\Model\AssociationSpec;
use HubSpot\Client\Crm\Associations\Model\BatchInputPublicObjectId;
use HubSpot\Client\Crm\Associations\Model\PublicObjectId;
use HubSpot\Factory;
use HubSpot\Client\Crm\Contacts\ApiException;
use HubSpot\Client\Crm\Contacts\Model\SimplePublicObjectInput;
use Barryvdh\DomPDF\Facade\Pdf;
use Mail;
use Illuminate\Support\Facades\Mail as Mail2;
use Monday;
use Carbon\Carbon;
use App\Services\TeamleaderService;
use App\Services\HubspotService;

class ClienteController extends Controller
{
    protected $teamleaderService;
    protected $hubspotService;

    public function __construct(TeamleaderService $teamleaderService, HubspotService $hubspotService)
    {
        $this->teamleaderService = $teamleaderService;
        $this->hubspotService = $hubspotService;
    }

    public function tree(){
        if (Auth::user()->roles->first()->name == "Cliente"){
            if(Auth::user()->pay==0){
                return redirect()->route('clientes.pay');
            }
            if(Auth::user()->pay==1 || auth()->user()->pay == 3){
                return redirect()->route('clientes.getinfo');
            }
            if(Auth::user()->pay==11 || Auth::user()->pay==12){
                return redirect()->route('clientes.payfases');
            }
            if(Auth::user()->contrato==0){
                return redirect()->route('cliente.contrato');
            }
        }
        $IDCliente = Auth::user()->passport;

        $cliente[0] = Auth::user();

        //revisar padres

        $searchCliente = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',1)->first();
        $existe = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',1)->get();

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
        echo "El árbol genealógico tiene " . $maxGeneraciones . " generaciones.";
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

        $columnasparatabla = $temparr;

        $checkBtn = "no";
        $generacionBase = 0;

        $parentnumber = 0;

        $htmlGenerado = view('arboles.vistatree', compact('generacionBase', 'columnasparatabla', 'parentescos', 'checkBtn', 'parentnumber'))->render();

        return view('arboles.tree', compact('IDCliente', 'people', 'columnasparatabla', 'cliente', 'tipoarchivos', 'parentescos', 'htmlGenerado', 'checkBtn', 'generacionBase', 'parentnumber'));
    }

    public function hermanoscliente(){
        if (Auth::user()->roles->first()->name == "Cliente"){
            if(Auth::user()->pay==0){
                return redirect()->route('clientes.pay');
            }
            if(Auth::user()->pay==1 || auth()->user()->pay == 3){
                return redirect()->route('clientes.getinfo');
            }
            if(Auth::user()->contrato==0){
                return redirect()->route('cliente.contrato');
            }
        }

        $usermain = User::where('id', '=', auth()->user()->id)->get();
        $hermanos = Hermano::with('usuarioPrincipal', 'hermano')->where('id_main', '=', auth()->user()->id)->get();

        return view('clientes.hermanos', compact('usermain', 'hermanos'));
    }

    public function contrato(){
        if (Auth::user()->roles->first()->name == "Cliente"){
            if(Auth::user()->pay==0){
                return redirect()->route('clientes.pay');
            }
            if(Auth::user()->pay==1 || auth()->user()->pay == 3){
                return redirect()->route('clientes.getinfo');
            }
            if(Auth::user()->contrato==1){
                return redirect('/tree');
            }
        }
        return view('clientes.contrato');
    }

    public function checkContrato(){
        DB::table('users')->where('id', auth()->user()->id)->update(['contrato' => 1]); // no borrar esta linea
        return redirect('/tree')->with('exito', 'contrato enviado');
    }

    public function salir(Request $request){
        // Envía un correo al cliente que ha culminado la carga
        $mail_cliente = new CargaCliente(Auth::user());
        Mail2::to(Auth::user()->email)->send($mail_cliente);

        // Envía un correo al equipo de Sefar
        $mail_sefar = new CargaSefar(Auth::user());
        Mail2::to([
            'pedro.bazo@sefarvzla.com',
            'sistemasccs@sefarvzla.com',
            'automatizacion@sefarvzla.com',
            'sistemascol@sefarvzla.com',
            'asistentedeproduccion@sefarvzla.com',
            'organizacionrrhh@sefarvzla.com',
            'arodriguez@sefarvzla.com',
            '20053496@bcc.hubspot.com'
            /* 'organizacionrrhh@sefarvzla.com' */
        ])->send($mail_sefar);

        // Realiza logout de la aplicación
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    public function procesar(Request $request){
        $user = Auth()->user();
        // Validación
        $request->validate([
            'passport' => 'required|min:6|unique:users,passport,'.$user->id,
            'nombres' => 'required',
            'apellidos' => 'required',
            'email' => 'email|required|unique:users,email,'.$user->id,
            'fnacimiento' => 'required',
            'cnacimiento' => 'required',
            'pnacimiento' => 'required',
            'sexo' => 'required'
        ]);

        // Actualizar usuario
        $user->name = trim($request->nombres) . ' ' . trim($request->apellidos);
        $user->email = $request->email;
        $user->passport = trim($request->passport);
        $user->save();

        // Verificar si el usuario esta registrado en agclientes
        $agcliente = Agcliente::where('IDCliente',$user->passport)->where('IDPersona',1)->count();
        if($agcliente == 0){
            // Si no existe crea el árbol del cliente
            $fnacimiento = $request->fnacimiento;
            $fnacimiento_entero = strtotime($fnacimiento);
            Agcliente::create([
                'IDCliente' => trim($user->passport),
                'IDPersona' => 1,
                'Nombres' => trim($request->nombres),
                'Apellidos' => trim($request->apellidos),
                'NPasaporte' => trim($user->passport),
                'Sexo' => trim($request->sexo),
                'AnhoNac' => date("Y", $fnacimiento_entero),
                'MesNac' => date("m", $fnacimiento_entero),
                'DiaNac' => date("d", $fnacimiento_entero),
                'LugarNac' => trim($request->cnacimiento),
                'PaisNac' => trim($request->pnacimiento),
                'NombresF' => trim($request->nombre_f),
                'NPasaporteF' => trim($request->pasaporte_f),
                'FRegistro' => date('Y-m-d H:i:s'),
                'PNacimiento' => trim($request->pnacimiento),
                'LNacimiento' => trim($request->cnacimiento),
                'FUpdate' => date('Y-m-d H:i:s'),
                'referido' => trim($request->referido),
                'Usuario' => trim($request->email),
            ]);
        }

        // Asignar rol de cliente
        $user->assignRole('Cliente');

        return redirect()->route('clientes.tree', $user->passport);
    }

    public function getinfo(){
        if (Auth::user()->roles->first()->name == "Cliente"){
            if(Auth::user()->pay==0){
                return redirect()->route('clientes.pay');
            }
            if(Auth::user()->pay==2){
                return redirect('/tree');
            }

            if(Auth::user()->pay==11 || Auth::user()->pay==12){
                return redirect()->route('clientes.payfases');
            }
        }
        return view('clientes.getinfo');
    }

    public function gracias(){
        if (Auth::user()->roles->first()->name == "Cliente"){
            if(Auth::user()->pay==0){
                return redirect()->route('clientes.pay');
            }
        }
        return view('clientes.gracias');
    }

    public function procesargetinfo(Request $request){
        /*

            Aqui recibo y organizo el arreglo que viene del Jquery

        */

        if (auth()->user()->pay == 3){
            DB::table('users')->where('id', auth()->user()->id)->update(['pay' => 2]); // no borrar esta linea
            auth()->user()->revokePermissionTo('finish.register');
        } else {
            $inputdata = json_decode(json_encode($request->all()),true);

            $input_u = $inputdata["data"];

            $input = array();

            foreach ($input_u as $key => $value) {
                if($input_u[$key]["name"]!="hs_context") {
                    $input[$input_u[$key]["name"]] = $input_u[$key]["value"];
                }
            }

            DB::table('users')->where('id', auth()->user()->id)->update(['pay' => 2]); // no borrar esta linea
            auth()->user()->revokePermissionTo('finish.register');

            /* Aquí actualizo la base de datos */

            //print_r('de php');
            //print_r($input['referido_por']);
            $user = Auth()->user();

            // Actualizando el árbol genenalógico
            // Cliente
            $agcliente = Agcliente::where('IDCliente',$user->passport)->where('IDPersona',1)->first();
            if($agcliente){
                @$agcliente->Sexo = $input['genero'] == 'MASCULINO / MALE' ? 'M' : 'F';
                @$user->genero = $agcliente->Sexo;

                @$user->date_of_birth = $input['fecha_nac'];
                if($user->date_of_birth){
                    @$agcliente->AnhoNac = date("Y", strtotime($user->date_of_birth));
                    @$agcliente->MesNac = date("m", strtotime($user->date_of_birth));
                    @$agcliente->DiaNac = date("d", strtotime($user->date_of_birth));
                }

                @$agcliente->LugarNac = trim($input['ciudad_de_nacimiento']);
                @$agcliente->PaisNac = trim($input['pais_de_nacimiento']);

                @$agcliente->FRegistro = date('Y-m-d H:i:s');
                @$agcliente->PNacimiento = trim($input['pais_de_nacimiento']);
                @$agcliente->LNacimiento = trim($input['ciudad_de_nacimiento']);
                @$user->ciudad_de_nacimiento = $agcliente->LNacimiento;
                @$agcliente->PaisPasaporte = trim($input['pais_de_expedicion_del_pasaporte']);

                @$agcliente->ParentescoF = trim($input['vinculo_miembro_de_familia_1']);
                @$agcliente->NombresF = trim($input['nombre_miembro_de_familia_1']);
                @$agcliente->ApellidosF = trim($input['apellidos_miembro_de_familia_1']);
                // $agcliente->NPasaporteF = trim($input['pasaporte_f']);

                @$agcliente->Observaciones = (($agcliente->Observaciones == null) ? '' : $agcliente->Observaciones . '. ')
                    . 'Phone: ' . trim($input['phone'])
                    . ' E-mail:' . trim($input['email'])
                    . ' Adress:' . trim($input['address']);
                $agcliente->save();
                $user->save();
            }

            // Padre
            @$nombres_y_apellidos_del_padre = trim($input['nombres_y_apellidos_del_padre']);
            if($nombres_y_apellidos_del_padre){
                @$padre = Agcliente::where('IDCliente',$user->passport)->where('IDPersona',2)->first();
                if(!$padre) {
                    $agcliente = Agcliente::create([
                        'IDCliente' => $user->passport,
                        'Nombres' => $nombres_y_apellidos_del_padre,
                        'IDPersona' => 2,
                        'Sexo' => 'M',
                        'IDPadre' => 4,
                        'IDMadre' => 5,
                        'Generacion' => 2,
                        'FUpdate' => date('Y-m-d H:i:s'),
                        'Usuario' => $user->email,
                    ]);
                }
            }

            // Madre
            @$nombres_y_apellidos_de_madre = trim($input['nombres_y_apellidos_de_madre']);
            if($nombres_y_apellidos_de_madre){
                @$madre = Agcliente::where('IDCliente',$user->passport)->where('IDPersona',3)->first();
                if(!$madre) {
                    $agcliente = Agcliente::create([
                        'IDCliente' => $user->passport,
                        'Nombres' => $nombres_y_apellidos_de_madre,
                        'IDPersona' => 3,
                        'Sexo' => 'F',
                        'IDPadre' => 6,
                        'IDMadre' => 7,
                        'Generacion' => 2,
                        'FUpdate' => date('Y-m-d H:i:s'),
                        'Usuario' => $user->email,
                    ]);
                }
            }

            /* Fin de la actualización en Base de Datos */

            /* Añade info a Monday */
            $query = "SELECT a.*, b.name, b.passport, b.email, b.phone, b.created_at as fecha_de_registro FROM facturas as a, users as b WHERE a.id_cliente = b.id AND b.passport='".$user->passport."' ORDER BY a.id DESC LIMIT 1;";

            $datos_factura = json_decode(json_encode(DB::select($query)),true);

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

            $link = 'https://app.sefaruniversal.com/tree/' . auth()->user()->passport;

            $query = 'mutation ($myItemName: String!, $columnVals: JSON!) { create_item (board_id: 878831315, group_id: "duplicate_of_en_proceso", item_name:$myItemName, column_values:$columnVals) { id } }';

            if (is_null(auth()->user()->apellidos) || is_null(auth()->user()->nombres)){
                $clientname = auth()->user()->name;
            } else {
                $clientname = auth()->user()->apellidos." ".auth()->user()->nombres;
            }

            $vars = [
                'myItemName' => $clientname,
                'columnVals' => json_encode([
                    'texto' => auth()->user()->passport,
                    'fecha75' => ['date' => date("Y-m-d", strtotime($input['fecha_nac']))],
                    'texto_largo8' => $nombres_y_apellidos_del_padre,
                    'texto_largo75' => $nombres_y_apellidos_de_madre,
                    'enlace' => $link . " " . $link,
                    'estado54' => 'Arbol Incompleto',
                    'texto1' => $servicios,
                    'texto4' => auth()->user()->hs_id
                ])
            ];

            foreach ($productos as $key => $value) {
                if (isset($value)) {
                    $servicio_hs_id = $value['servicio_hs_id'];

                    if (isset($servicio_hs_id) && ($servicio_hs_id === "Española LMD" || $servicio_hs_id == "Española LMD")) {
                        $query = 'mutation ($myItemName: String!, $columnVals: JSON!) { create_item (board_id: 765394861, group_id: "grupo_nuevo97011", item_name:$myItemName, column_values:$columnVals) { id } }';

                        $vars = [
                            'myItemName' => $clientname,
                            'columnVals' => json_encode([
                                'texto' => auth()->user()->passport,
                                'fecha75' => ['date' => date("Y-m-d", strtotime($input['fecha_nac']))],
                                'texto_largo8' => $nombres_y_apellidos_del_padre,
                                'texto_largo75' => $nombres_y_apellidos_de_madre,
                                'enlace' => $link . " " . $link,
                                'estado54' => 'Arbol Incompleto',
                                'texto1' => $servicios,
                                'texto6' => auth()->user()->hs_id
                            ])
                        ];
                    }
                }
            }

            $data = @file_get_contents($apiUrl, false, stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => $headers,
                        'content' => json_encode(['query' => $query, 'variables' => $vars]),
                    ]
                ]
            ));

            $responseContent = json_decode($data,true);

            echo json_encode($responseContent);
        }



    }

    public function payfases(){
        if (Auth::user()->roles->first()->name == "Cliente"){
            if (Auth::user()->pay==2){
                $IDCliente = Auth::user()->passport;
                return redirect('/tree');
            } else if(Auth::user()->pay==1 || Auth::user()->pay==3){
                return redirect()->route('clientes.getinfo');
            } else if(Auth::user()->pay==0){
                return redirect()->route('clientes.pay');
            }
        }
        $compras = Compras::where('id_user', auth()->user()->id)->where('pagado', 0)->whereNotNull('deal_id')->get();

        if (auth()->user()->tiene_hermanos==1 || auth()->user()->tiene_hermanos=="1" || auth()->user()->tiene_hermanos=="Si") {
            $servicio = Servicio::where('id_hubspot', auth()->user()->servicio." - Hermano")->get();
        } else {
            $servicio = Servicio::where('id_hubspot', auth()->user()->servicio)->get();
        }

        $cps = json_decode(json_encode($compras),true);

        if (count($cps)==0){
            $hss = json_decode(json_encode($servicio),true);

            if(auth()->user()->servicio == "Recurso de Alzada"){
                $monto = $hss[0]["precio"] * auth()->user()->cantidad_alzada;
            } else {
                $monto = $hss[0]["precio"];
            }

            if( auth()->user()->servicio == "Española LMD" || auth()->user()->servicio == "Italiana" ) {
                $desc = "Pago Fase Inicial: Investigación Preliminar y Preparatoria: " . $hss[0]["nombre"];
                if (auth()->user()->servicio == "Española LMD"){
                    if (auth()->user()->antepasados==0){
                        $monto = 99;
                    }
                }
                if (auth()->user()->servicio == "Italiana"){
                    if (auth()->user()->antepasados==1){
                        $desc = $desc . " + (Consulta Gratuita)";
                    }
                }
            } elseif ( auth()->user()->servicio == "Gestión Documental" ) {
                $desc = $hss[0]["nombre"];
            } elseif ($servicio[0]['tipov'] == 1) {
                $desc = "Servicios para Vinculaciones: " . $hss[0]["nombre"];
            } else {
                $desc = "Inicia tu Proceso: " . $hss[0]["nombre"];
            }

            Compras::create([
                'id_user' => auth()->user()->id,
                'servicio_hs_id' => auth()->user()->servicio,
                'descripcion' => $desc,
                'pagado' => 0,
                'monto' => $monto
            ]);

            $compras = Compras::where('id_user', auth()->user()->id)->where('pagado', 0)->get();
        }

        $alertas = $today = Carbon::today();
        $alertas = Alertas::where('start_date', '<=', $today)
                        ->where('end_date', '>=', $today)
                        ->get();

        return view('clientes.payfases', compact('servicio', 'compras', 'alertas'));
    }

    public function pay(){
        if (Auth::user()->roles->first()->name == "Cliente"){
            if (Auth::user()->pay==2){
                $IDCliente = Auth::user()->passport;
                return redirect('/tree');
            } else if(Auth::user()->pay==1 || Auth::user()->pay==3){
                return redirect()->route('clientes.getinfo');
            } else if(Auth::user()->pay==11 || Auth::user()->pay==12){
                return redirect()->route('clientes.payfases');
            }
        }
        $compras = Compras::where('id_user', auth()->user()->id)->where('pagado', 0)->whereNull('deal_id')->get();

        if (auth()->user()->tiene_hermanos==1 || auth()->user()->tiene_hermanos=="1" || auth()->user()->tiene_hermanos=="Si") {
            $servicio = Servicio::where('id_hubspot', auth()->user()->servicio." - Hermano")->get();
        } else {
            $servicio = Servicio::where('id_hubspot', auth()->user()->servicio)->get();
        }

        $cps = json_decode(json_encode($compras),true);

        if (count($cps)==0){
            $hss = json_decode(json_encode($servicio),true);

            if(auth()->user()->servicio == "Recurso de Alzada"){
                $monto = $hss[0]["precio"] * auth()->user()->cantidad_alzada;
            } else {
                $monto = $hss[0]["precio"];
            }

            if( auth()->user()->servicio == "Española LMD" || auth()->user()->servicio == "Italiana" ) {
                $desc = "Pago Fase Inicial: Investigación Preliminar y Preparatoria: " . $hss[0]["nombre"];
                if (auth()->user()->servicio == "Española LMD"){
                    if (auth()->user()->antepasados==0){
                        $monto = 99;
                    }
                }
                if (auth()->user()->servicio == "Italiana"){
                    if (auth()->user()->antepasados==1){
                        $desc = $desc . " + (Consulta Gratuita)";
                    }
                }
            } elseif ( auth()->user()->servicio == "Gestión Documental" ) {
                $desc = $hss[0]["nombre"];
            } elseif ($servicio[0]['tipov'] == 1) {
                $desc = "Servicios para Vinculaciones: " . $hss[0]["nombre"];
            } else {
                $desc = "Inicia tu Proceso: " . $hss[0]["nombre"];
            }

            Compras::create([
                'id_user' => auth()->user()->id,
                'servicio_hs_id' => auth()->user()->servicio,
                'descripcion' => $desc,
                'pagado' => 0,
                'monto' => $monto
            ]);

            $compras = Compras::where('id_user', auth()->user()->id)->where('pagado', 0)->get();
        }

        $alertas = $today = Carbon::today();
        $alertas = Alertas::where('start_date', '<=', $today)
                        ->where('end_date', '>=', $today)
                        ->get();

        return view('clientes.pay', compact('servicio', 'compras', 'alertas'));
    }

    public function revisarcupon(Request $request){
        $compras = Compras::where('id_user', auth()->user()->id)->where('pagado', 0)->whereNull('deal_id')->get();
        $servicio = Servicio::where('id_hubspot', auth()->user()->servicio)->get();
        $hubspot = HubSpot\Factory::createWithAccessToken(env('HUBSPOT_KEY'));
        $data = json_decode(json_encode($request->all()),true);

        $datos = json_decode(json_encode(DB::table('users')->where('id', auth()->user()->id)->get()),true);

        $cupones = json_decode(json_encode(Coupon::all()),true);

        $cupontest = strtoupper(str_replace(' ', '', $data["cpn"]));

        $couponGENERAL = GeneralCoupon::where('title', $cupontest)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->first();

        if ($couponGENERAL) {
            foreach ($compras as $compra) {
                $compra->update([
                    'monto' => $couponGENERAL->newdiscount,
                    'cuponaplicado' => 1,
                    'montooriginal' => $compra->monto,
                    'porcentajedescuento' => "Oferta: {$couponGENERAL->title}"
                ]);
            }
            return response()->json([
                'status' => "promo",
                'percentage' => "Oferta: {$couponGENERAL->title}"
            ]);
        }

        foreach ($cupones as $cupon) {
            if( $data["cpn"] == $cupon["couponcode"] ){
                if(!is_null($cupon["expire"]) && $cupon["expire"]<date('Y-m-d')){
                    return response()->json([
                        'status' => "fechabad"
                    ]);
                }
                if($cupon["enabled"] == 0){
                    return response()->json([
                        'status' => "false"
                    ]);
                }
                if($cupon["percentage"]<100){
                    foreach ($compras as $compra) {
                        if ($compra->cuponaplicado != 1){
                            $montoDescuento = $compra->monto - ($compra->monto * ($cupon["percentage"] / 100));
                            $montoFinal = round($montoDescuento, 2);

                            $compra->update([
                                'monto' => $montoFinal,
                                'cuponaplicado' => 1,
                                'montooriginal' => $compra->monto,
                                'porcentajedescuento' => $cupon["percentage"]
                            ]);
                        }
                    }

                    DB::table('coupons')->where('couponcode', $cupon["couponcode"])->update(['enabled' => 0]);

                    return response()->json([
                        'status' => "halftrue",
                        'percentage' => $cupon["percentage"]."%"
                    ]);
                } else {
                    $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

                    $hash_factura = "sef_".generate_string($permitted_chars, 50);

                    Factura::create([
                        'id_cliente' => auth()->user()->id,
                        'hash_factura' => $hash_factura,
                        'met' => 'cupon',
                    ]);

                    foreach ($compras as $key => $compra) {
                        DB::table('compras')->where('id', $compra['id'])->update(['pagado' => 1, 'hash_factura' => $hash_factura]);
                    }

                    if (isset($datos[0]["id_pago"])){
                        if(is_array(json_decode($datos[0]["id_pago"],true))) {
                            $cargostemp = json_decode($datos[0]["id_pago"],true);
                            $cargostemp[] = '';
                            $cargos = json_encode($cargostemp);
                        } else {
                            $cargostemp[] = $datos[0]["id_pago"];
                            $cargostemp[] = '';
                            $cargos = json_encode($cargostemp);
                        }
                    } else {
                        $cargostemp[] = '';
                        $cargos = json_encode($cargostemp);
                    }

                    if (isset($datos[0]["pago_cupon"])){
                        if(is_array(json_decode($datos[0]["pago_cupon"],true))) {
                            $cuponestemp = json_decode($datos[0]["pago_cupon"],true);
                            $cuponestemp[] = $data["cpn"];
                            $cupones = json_encode($cuponestemp);
                        } else {
                            $cuponestemp[] = $datos[0]["pago_cupon"];
                            $cuponestemp[] = $data["cpn"];
                            $cupones = json_encode($cuponestemp);
                        }
                    } else {
                        $cuponestemp[] = $data["cpn"];
                        $cupones = json_encode($cuponestemp);
                    }

                    if (isset($datos[0]["pago_registro_hist"])){
                        if(is_array(json_decode($datos[0]["pago_registro_hist"],true))) {
                            $pago_registrotemp = json_decode($datos[0]["pago_registro_hist"],true);
                            $pago_registrotemp[] = 0;
                            $pago_registro = json_encode($pago_registrotemp);
                        } else {
                            $pago_registrotemp[] = $datos[0]["pago_registro_hist"];
                            $pago_registrotemp[] = 0;
                            $pago_registro = json_encode($pago_registrotemp);
                        }
                    } else {
                        $pago_registrotemp[] = 0;
                        $pago_registro = json_encode($pago_registrotemp);
                    }

                    DB::table('users')->where('id', auth()->user()->id)->update(['pay' => 1, 'pago_registro_hist' => $pago_registro, 'pago_registro' => 0, 'id_pago' => $cargos, 'pago_cupon' => $cupones, 'contrato' => 0 ]);

                    $setto2 = 1;

                    foreach ($compras as $key => $compra) {
                        $servicio = Servicio::where('id_hubspot', $compra["servicio_hs_id"])->get();
                        if ($compra["servicio_hs_id"] == 'Árbol genealógico de Deslinde' || $compra["servicio_hs_id"] == 'Acumulación de linajes' || $compra["servicio_hs_id"] == 'Procedimiento de Urgencia' || $compra["servicio_hs_id"] == 'Recurso de Alzada' || $compra["servicio_hs_id"] == 'Gestión Documental' || $servicio[0]['tipov']==1){
                            $setto2 = 1;
                        } else {
                            $setto2 = 0;
                            break;
                        }
                    }

                    if ($setto2==1) {
                        DB::table('users')->where('id', auth()->user()->id)->update(['pay' => 2]);
                        auth()->user()->revokePermissionTo('finish.register');
                    }

                    DB::table('coupons')->where('couponcode', $cupon["couponcode"])->update(['enabled' => 0]);
                    auth()->user()->revokePermissionTo('pay.services');

                    $idcontact = "";

                    $filter = new \HubSpot\Client\Crm\Contacts\Model\Filter();
                    $filter
                        ->setOperator('EQ')
                        ->setPropertyName('email')
                        ->setValue(auth()->user()->email);

                    $filterGroup = new \HubSpot\Client\Crm\Contacts\Model\FilterGroup();
                    $filterGroup->setFilters([$filter]);

                    $searchRequest = new \HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest();
                    $searchRequest->setFilterGroups([$filterGroup]);

                    //Llamo a todas las propiedades de Hubspot (Si el dia de mañana hay que añadir algo nuevo, la ventaja que tenemos es que ya me estoy trayendo todos los elementos del arreglo)

                    $searchRequest->setProperties([
                        "registro_pago",
                        "registro_cupon"
                    ]);

                    //Hago la busqueda del cliente
                    $contactHS = $hubspot->crm()->contacts()->searchApi()->doSearch($searchRequest);

                    if ($contactHS['total'] != 0){
                        $valuehscupon = "";
                        //sago solo el id del contacto:
                        $idcontact = $contactHS['results'][0]['id'];

                        DB::table('users')->where('id', auth()->user()->id)->update(['hs_id' => $idcontact]);

                        $properties1 = [
                            'registro_pago' => '0',
                            'registro_cupon' => $cupones,
                            'transaction_id' => $cargos,
                            'hist_pago_registro' => $pago_registro
                        ];
                        $simplePublicObjectInput = new SimplePublicObjectInput([
                            'properties' => $properties1,
                        ]);

                        $apiResponse = $hubspot->crm()->contacts()->basicApi()->update($idcontact, $simplePublicObjectInput);

                        $dealInput = new \HubSpot\Client\Crm\Deals\Model\SimplePublicObjectInput();

                        foreach ($compras as $key => $compra) {
                            $dealInput->setProperties([
                                'dealname' => auth()->user()->name . ' - ' . $compra['servicio_hs_id'],
                                'pipeline' => "94794",
                                'dealstage' => "429097",
                                'servicio_solicitado' => $compra['servicio_hs_id'],
                                'servicio_solicitado2' => $compra['servicio_hs_id'],
                            ]);

                            $apiResponse = json_decode(json_encode($hubspot->crm()->deals()->basicApi()->create($dealInput)),true);

                            $iddeal = $apiResponse["id"];

                            $associationSpec1 = new AssociationSpec([
                                'association_category' => 'HUBSPOT_DEFINED',
                                'association_type_id' => 3
                            ]);

                            $asocdeal = $hubspot->crm()->deals()->associationsApi()->create($iddeal, 'contacts', $idcontact, [$associationSpec1]);
                            sleep(2);
                        }
                    }

                    $user = User::findOrFail(auth()->user()->id);
                    $pdfContent = createPDF($hash_factura);

                    Mail::send('mail.comprobante-mail', ['user' => $user], function ($m) use ($pdfContent, $request, $user) {
                        $m->to([
                            auth()->user()->email
                        ])->subject('SEFAR UNIVERSAL - Hemos procesado su pago satisfactoriamente')->attachData($pdfContent, 'Comprobante.pdf', ['mime' => 'application/pdf']);
                    });

                    Mail::send('mail.comprobante-mail-intel', ['user' => $user], function ($m) use ($pdfContent, $request, $user) {
                        $m->to([
                            'pedro.bazo@sefarvzla.com',
                            'sistemasccs@sefarvzla.com',
                            'crisantoantonio@gmail.com',
                            'automatizacion@sefarvzla.com',
                            'sistemascol@sefarvzla.com',
                            'asistentedeproduccion@sefarvzla.com',
                            'organizacionrrhh@sefarvzla.com',
                            'organizacionrrhh@sefarvzla.com',
                            '20053496@bcc.hubspot.com',
                            'contabilidad@sefaruniversal.com',
                        ])->subject(strtoupper($user->name) . ' (ID: ' .
                            strtoupper($user->passport) . ') HA REALIZADO UN PAGO EN App Sefar Universal')->attachData($pdfContent, 'Comprobante.pdf', ['mime' => 'application/pdf']);
                    });

                    if ($setto2==1) {

                        $query = "SELECT a.*, b.name, b.passport, b.email, b.phone, b.created_at as fecha_de_registro FROM facturas as a, users as b WHERE a.id_cliente = b.id AND b.passport='".$user->passport."' ORDER BY a.id DESC LIMIT 1;";

                        $datos_factura = json_decode(json_encode(DB::select($query)),true);

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

                        $query = 'mutation ($myItemName: String!, $columnVals: JSON!) { create_item (board_id: 878831315, group_id: "duplicate_of_en_proceso", item_name:$myItemName, column_values:$columnVals) { id } }';

                        $link = 'https://app.sefaruniversal.com/tree/' . auth()->user()->passport;

                        $vars = [
                            'myItemName' => auth()->user()->apellidos." ".auth()->user()->nombres,
                            'columnVals' => json_encode([
                                'texto' => auth()->user()->passport,
                                'enlace' => $link . " " . $link,
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

                        $responseContent = json_decode($data, true);

                    }

                    return response()->json([
                        'status' => "true"
                    ]);
                }

            }
        }
        return response()->json([
            'status' => "false"
        ]);
    }

    public function procesarPaypal(Request $request){
        $hubspot = HubSpot\Factory::createWithAccessToken(env('HUBSPOT_KEY'));

        $servicio = Servicio::where('id_hubspot', auth()->user()->servicio)->get();

        $cupones = json_decode(json_encode(Coupon::all()),true);

        $datos = json_decode(json_encode(DB::table('users')->where('id', auth()->user()->id)->get()),true);

        $finalcupon = "";

        $errorcod = "error";

        $compras = Compras::where('id_user', auth()->user()->id)->where('pagado', 0)->whereNull('deal_id')->get();
        $servicio = Servicio::where('id_hubspot', auth()->user()->servicio)->get();

        $cps = json_decode(json_encode($compras),true);

        $hss = json_decode(json_encode($servicio),true);

        $monto = 0;

        foreach ($compras as $key => $compra) {
            $monto = $monto + $compra["monto"];
        }

        $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $hash_factura = "sef_".generate_string($permitted_chars, 50);

        Factura::create([
            'id_cliente' => auth()->user()->id,
            'hash_factura' => $hash_factura,
            'met' => 'paypal'
        ]);

        foreach ($compras as $key => $compra) {
            DB::table('compras')->where('id', $compra['id'])->update(['pagado' => 1, 'hash_factura' => $hash_factura]);
        }

        $cargostemp = [];

        if (isset($datos[0]["id_pago"])){
            if(is_array(json_decode($datos[0]["id_pago"],true))) {
                $cargostemp = json_decode($datos[0]["id_pago"],true);
                $cargos = json_encode($cargostemp);
            } else {
                $cargostemp[] = $datos[0]["id_pago"];
                $cargos = json_encode($cargostemp);
            }
        } else {
            $cargos = json_encode($cargostemp);
        }

        $cuponestemp = [];

        if (isset($finalcupon)){
            DB::table('coupons')->where('couponcode', $finalcupon)->update(['enabled' => 0]);
            if (isset($datos[0]["pago_cupon"])){
                if(is_array(json_decode($datos[0]["pago_cupon"],true))) {
                    $cuponestemp = json_decode($datos[0]["pago_cupon"],true);
                    $cuponestemp[] = $finalcupon;
                    $cupones = json_encode($cuponestemp);
                } else {
                    $cuponestemp[] = $datos[0]["pago_cupon"];
                    $cuponestemp[] = $finalcupon;
                    $cupones = json_encode($cuponestemp);
                }
            } else {
                $cuponestemp[] = $finalcupon;
                $cupones = json_encode($cuponestemp);
            }
        } else {
            if (isset($datos[0]["pago_cupon"])){
                if(is_array(json_decode($datos[0]["pago_cupon"],true))) {
                    $cuponestemp = json_decode($datos[0]["pago_cupon"],true);
                    $cuponestemp[] = '';
                    $cupones = json_encode($cuponestemp);
                } else {
                    $cuponestemp[] = $datos[0]["pago_cupon"];
                    $cuponestemp[] = '';
                    $cupones = json_encode($cuponestemp);
                }
            } else {
                $cuponestemp[] = '';
                $cupones = json_encode($cuponestemp);
            }
        }

        $pago_registrotemp = [];

        if (isset($datos[0]["pago_registro_hist"])){
            if(is_array(json_decode($datos[0]["pago_registro_hist"],true))) {
                $pago_registrotemp = json_decode($datos[0]["pago_registro_hist"],true);
                $pago_registrotemp[] = $monto;
                $pago_registro = json_encode($pago_registrotemp);
            } else {
                $pago_registrotemp[] = $datos[0]["pago_registro"];
                $pago_registrotemp[] = $monto;
                $pago_registro = json_encode($pago_registrotemp);
            }
        } else {
            $pago_registrotemp[] = $monto;
            $pago_registro = json_encode($pago_registrotemp);
        }

        DB::table('users')->where('id', auth()->user()->id)->update(['pay' => 1, 'pago_registro_hist' => $pago_registro, 'pago_registro' => $monto, 'id_pago' => $cargos, 'pago_cupon' => $cupones, 'contrato' => 0]);

        $setto2 = 1;

        foreach ($compras as $key => $compra) {
            $servicio = Servicio::where('id_hubspot', $compra["servicio_hs_id"])->get();
            if ($compra["servicio_hs_id"] == 'Árbol genealógico de Deslinde' || $compra["servicio_hs_id"] == 'Acumulación de linajes' || $compra["servicio_hs_id"] == 'Procedimiento de Urgencia' || $compra["servicio_hs_id"] == 'Recurso de Alzada' || $compra["servicio_hs_id"] == 'Gestión Documental' || $servicio[0]['tipov']==1){
                $setto2 = 1;
            } else {
                $setto2 = 0;
                break;
            }
        }

        if ($setto2==1) {
            DB::table('users')->where('id', auth()->user()->id)->update(['pay' => 2]);
            auth()->user()->revokePermissionTo('finish.register');
        }

        auth()->user()->revokePermissionTo('pay.services');
        $idcontact = "";

        $filter = new \HubSpot\Client\Crm\Contacts\Model\Filter();
        $filter
            ->setOperator('EQ')
            ->setPropertyName('email')
            ->setValue(auth()->user()->email);

        $filterGroup = new \HubSpot\Client\Crm\Contacts\Model\FilterGroup();
        $filterGroup->setFilters([$filter]);

        $searchRequest = new \HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest();
        $searchRequest->setFilterGroups([$filterGroup]);

        //Llamo a todas las propiedades de Hubspot (Si el dia de mañana hay que añadir algo nuevo, la ventaja que tenemos es que ya me estoy trayendo todos los elementos del arreglo)

        $searchRequest->setProperties([
            "registro_pago",
            "registro_cupon",
            "transaction_id"
        ]);

        //Hago la busqueda del cliente
        $contactHS = $hubspot->crm()->contacts()->searchApi()->doSearch($searchRequest);

        if ($contactHS['total'] != 0){
            $valuehscupon = "";
            //sago solo el id del contacto:
            $idcontact = $contactHS['results'][0]['id'];

            DB::table('users')->where('id', auth()->user()->id)->update(['hs_id' => $idcontact]);

            $properties1 = [
                'registro_pago' => $servicio[0]["precio"],
                'registro_cupon' => $cupones,
                'transaction_id' => $cargos,
                'hist_pago_registro' => $pago_registro
            ];
            $simplePublicObjectInput = new SimplePublicObjectInput([
                'properties' => $properties1,
            ]);

            $apiResponse = $hubspot->crm()->contacts()->basicApi()->update($idcontact, $simplePublicObjectInput);

            $dealInput = new \HubSpot\Client\Crm\Deals\Model\SimplePublicObjectInput();

            foreach ($compras as $key => $compra) {
                $dealInput->setProperties([
                    'dealname' => auth()->user()->name . ' - ' . $compra['servicio_hs_id'],
                    'pipeline' => "94794",
                    'dealstage' => "429097",
                    'servicio_solicitado' => $compra['servicio_hs_id'],
                    'servicio_solicitado2' => $compra['servicio_hs_id'],
                ]);

                $apiResponse = json_decode(json_encode($hubspot->crm()->deals()->basicApi()->create($dealInput)),true);

                $iddeal = $apiResponse["id"];

                $associationSpec1 = new AssociationSpec([
                    'association_category' => 'HUBSPOT_DEFINED',
                    'association_type_id' => 3
                ]);

                $asocdeal = $hubspot->crm()->deals()->associationsApi()->create($iddeal, 'contacts', $idcontact, [$associationSpec1]);
                sleep(2);
            }

        }
        $user = User::findOrFail(auth()->user()->id);
        $pdfContent = createPDF($hash_factura);

        Mail::send('mail.comprobante-mail', ['user' => $user], function ($m) use ($pdfContent, $request, $user) {
            $m->to([
                auth()->user()->email
            ])->subject('SEFAR UNIVERSAL - Hemos procesado su pago satisfactoriamente')->attachData($pdfContent, 'Comprobante.pdf', ['mime' => 'application/pdf']);
        });

        $pdfContent2 = createPDFintel($hash_factura);

        Mail::send('mail.comprobante-mail-intel', ['user' => $user], function ($m) use ($pdfContent2, $request, $user) {
            $m->to([
                'pedro.bazo@sefarvzla.com',
                'crisantoantonio@gmail.com',
                'sistemasccs@sefarvzla.com',
                'automatizacion@sefarvzla.com',
                'sistemascol@sefarvzla.com',
                'asistentedeproduccion@sefarvzla.com',
                'organizacionrrhh@sefarvzla.com',
                'organizacionrrhh@sefarvzla.com',
                '20053496@bcc.hubspot.com',
                'contabilidad@sefaruniversal.com',
            ])->subject(strtoupper($user->name) . ' (ID: ' .
                strtoupper($user->passport) . ') HA REALIZADO UN PAGO EN App Sefar Universal')->attachData($pdfContent2, 'Comprobante.pdf', ['mime' => 'application/pdf']);
        });

        if ($setto2==1) {

            $query = "SELECT a.*, b.name, b.passport, b.email, b.phone, b.created_at as fecha_de_registro FROM facturas as a, users as b WHERE a.id_cliente = b.id AND b.passport='".$user->passport."' ORDER BY a.id DESC LIMIT 1;";

            $datos_factura = json_decode(json_encode(DB::select($query)),true);

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

            $query = 'mutation ($myItemName: String!, $columnVals: JSON!) { create_item (board_id: 878831315, group_id: "duplicate_of_en_proceso", item_name:$myItemName, column_values:$columnVals) { id } }';

            $link = 'https://app.sefaruniversal.com/tree/' . auth()->user()->passport;

            $vars = [
                'myItemName' => auth()->user()->apellidos." ".auth()->user()->nombres,
                'columnVals' => json_encode([
                    'texto' => auth()->user()->passport,
                    'enlace' => $link . " " . $link,
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

            $responseContent = json_decode($data, true);

            echo json_encode($responseContent);

        }
    }

    public function procesarpaypalfases(Request $request) {
        $compras = Compras::where('id_user', auth()->user()->id)->where('pagado', 0)->whereNotNull('deal_id')->get();

        $monto = 0;

        foreach ($compras as $key => $compra) {
            $monto = $monto + $compra["monto"];
        }

        $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $hash_factura = "sef_".generate_string($permitted_chars, 50);

        Factura::create([
            'id_cliente' => auth()->user()->id,
            'hash_factura' => $hash_factura,
            'met' => 'paypal',
        ]);

        foreach ($compras as $key => $compra) {
            DB::table('compras')->where('id', $compra['id'])->update(['pagado' => 1, 'hash_factura' => $hash_factura]);

            $deal = Negocio::find($compra->deal_id);
            $fechaActual = Carbon::now()->format('Y/m/d');

            if($compra->phasenum == 1){
                $deal->fase_1_pagado = $compra->monto . " " . $fechaActual;
                $deal->fecha_fase_1_pagado = $fechaActual;
                $deal->monto_fase_1_pagado = $compra->monto;
                $deal->save();

                if ($deal->teamleader_id) {
                    $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

                    // Conservar los campos existentes
                    $existingFields = $currentProject['custom_fields'];

                    // Actualizar solo el campo necesario sin borrar los demás
                    $updatedFields = array_map(function($field) use ($compra, $fechaActual) {
                        if ($field['definition']['id'] === "a1b50c58-8175-0d13-9856-f661e783dc08") {
                            $field['value'] = $compra->monto . " " . $fechaActual;
                        }

                        $field['id'] = $field['definition']['id'];

                        unset($field['definition']);

                        return $field;
                    }, $existingFields);

                    $campoTeamleader = ['custom_fields' => $updatedFields];

                    $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
                }

                if ($deal->hubspot_id) {
                    // Establecer la zona horaria en UTC
                    $utcTimezone = new \DateTimeZone('UTC');

                    // Obtener la fecha actual a medianoche en UTC
                    $midnightUTC = new \DateTime('now', $utcTimezone);
                    $midnightUTC->setTime(0, 0, 0);  // Establecer hora en 00:00:00

                    // Convertir a timestamp en milisegundos
                    $timestamp = $midnightUTC->getTimestamp() * 1000;

                    // Datos para enviar a HubSpot
                    $campoHubspot = [
                        'monto_fase_1_pagado' => $compra->monto,
                        'fecha_fase_1_pagado' => $timestamp,
                        'fase_1_pagado' =>  $compra->monto . " " . $fechaActual
                    ];

                    $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
                }
            } else if ($compra->phasenum == 2) {
                $deal->fase_2_pagado = $compra->monto . " " . $fechaActual;
                $deal->fecha_fase_2_pagado = $fechaActual;
                $deal->monto_fase_2_pagado = $compra->monto;
                $deal->save();

                if ($deal->teamleader_id) {
                    $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

                    // Conservar los campos existentes
                    $existingFields = $currentProject['custom_fields'];

                    // Actualizar solo el campo necesario sin borrar los demás
                    $updatedFields = array_map(function($field) use ($compra, $fechaActual) {
                        if ($field['definition']['id'] === "a5b94ccc-3ea8-06fc-b259-0a487073dc0d") {
                            $field['value'] = $compra->monto . " " . $fechaActual;
                        }

                        $field['id'] = $field['definition']['id'];

                        unset($field['definition']);

                        return $field;
                    }, $existingFields);

                    $campoTeamleader = ['custom_fields' => $updatedFields];

                    $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
                }

                if ($deal->hubspot_id) {
                    // Establecer la zona horaria en UTC
                    $utcTimezone = new \DateTimeZone('UTC');

                    // Obtener la fecha actual a medianoche en UTC
                    $midnightUTC = new \DateTime('now', $utcTimezone);
                    $midnightUTC->setTime(0, 0, 0);  // Establecer hora en 00:00:00

                    // Convertir a timestamp en milisegundos
                    $timestamp = $midnightUTC->getTimestamp() * 1000;

                    // Datos para enviar a HubSpot
                    $campoHubspot = [
                        'monto_fase_2_pagado' => $compra->monto,
                        'fecha_fase_2_pagado' => $timestamp,
                        'fase_2_pagado' =>  $compra->monto . " " . $fechaActual
                    ];

                    $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
                }
            } else if ($compra->phasenum == 3) {
                $deal->fase_3_pagado = $compra->monto . " " . $fechaActual;
                $deal->fecha_fase_3_pagado = $fechaActual;
                $deal->monto_fase_3_pagado = $compra->monto;
                $deal->save();

                if ($deal->teamleader_id) {
                    $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

                    // Conservar los campos existentes
                    $existingFields = $currentProject['custom_fields'];

                    // Actualizar solo el campo necesario sin borrar los demás
                    $updatedFields = array_map(function($field) use ($compra, $fechaActual) {
                        if ($field['definition']['id'] === "9a1df9b7-c92f-09e5-b156-96af3f83dc0e") {
                            $field['value'] = $compra->monto . " " . $fechaActual;
                        }

                        $field['id'] = $field['definition']['id'];

                        unset($field['definition']);

                        return $field;
                    }, $existingFields);

                    $campoTeamleader = ['custom_fields' => $updatedFields];

                    $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
                }

                if ($deal->hubspot_id) {
                    // Establecer la zona horaria en UTC
                    $utcTimezone = new \DateTimeZone('UTC');

                    // Obtener la fecha actual a medianoche en UTC
                    $midnightUTC = new \DateTime('now', $utcTimezone);
                    $midnightUTC->setTime(0, 0, 0);  // Establecer hora en 00:00:00

                    // Convertir a timestamp en milisegundos
                    $timestamp = $midnightUTC->getTimestamp() * 1000;

                    // Datos para enviar a HubSpot
                    $campoHubspot = [
                        'monto_fase_3_pagado' => $compra->monto,
                        'fecha_fase_3_pagado' => $timestamp,
                        'fase_3_pagado' =>  $compra->monto . " " . $fechaActual
                    ];

                    $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
                }
            } else if ($compra->phasenum == 99) { //cil
                $deal->cil___fcje_pagado = $compra->monto . " " . $fechaActual;
                $deal->cilfcje_fechapagado = $fechaActual;
                $deal->cilfcje_montopagado = $compra->monto;
                $deal->save();

                if ($deal->teamleader_id) {
                    $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

                    // Conservar los campos existentes
                    $existingFields = $currentProject['custom_fields'];

                    // Actualizar solo el campo necesario sin borrar los demás
                    $updatedFields = array_map(function($field) use ($compra, $fechaActual) {
                        if ($field['definition']['id'] === "f23fbe3b-5d13-0a41-a857-e9ab1c63dc42") {
                            $field['value'] = $compra->monto . " " . $fechaActual;
                        }

                        $field['id'] = $field['definition']['id'];

                        unset($field['definition']);

                        return $field;
                    }, $existingFields);

                    $campoTeamleader = ['custom_fields' => $updatedFields];

                    $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
                }

                if ($deal->hubspot_id) {
                    // Establecer la zona horaria en UTC
                    $utcTimezone = new \DateTimeZone('UTC');

                    // Obtener la fecha actual a medianoche en UTC
                    $midnightUTC = new \DateTime('now', $utcTimezone);
                    $midnightUTC->setTime(0, 0, 0);  // Establecer hora en 00:00:00

                    // Convertir a timestamp en milisegundos
                    $timestamp = $midnightUTC->getTimestamp() * 1000;

                    // Datos para enviar a HubSpot
                    $campoHubspot = [
                        'cil___fcje_pagado' =>  $compra->monto . " " . $fechaActual
                    ];

                    $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
                }
            } else if ($compra->phasenum == 98) { //cnat
                $deal->carta_nat_preestab = $compra->monto . " " . $fechaActual;
                $deal->carta_nat_fechapagado = $fechaActual;
                $deal->carta_nat_montopagado = $compra->monto;
                $deal->save();

                if ($deal->teamleader_id) {
                    $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

                    // Conservar los campos existentes
                    $existingFields = $currentProject['custom_fields'];

                    // Actualizar solo el campo necesario sin borrar los demás
                    $updatedFields = array_map(function($field) use ($compra, $fechaActual) {
                        if ($field['definition']['id'] === "4339375f-ed77-02d9-a157-7da9f9e4bfac") {
                            $field['value'] = $compra->monto . " " . $fechaActual;
                        }

                        $field['id'] = $field['definition']['id'];

                        unset($field['definition']);

                        return $field;
                    }, $existingFields);

                    $campoTeamleader = ['custom_fields' => $updatedFields];

                    $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
                }

                if ($deal->hubspot_id) {
                    // Establecer la zona horaria en UTC
                    $utcTimezone = new \DateTimeZone('UTC');

                    // Obtener la fecha actual a medianoche en UTC
                    $midnightUTC = new \DateTime('now', $utcTimezone);
                    $midnightUTC->setTime(0, 0, 0);  // Establecer hora en 00:00:00

                    // Convertir a timestamp en milisegundos
                    $timestamp = $midnightUTC->getTimestamp() * 1000;

                    // Datos para enviar a HubSpot
                    $campoHubspot = [
                        'carta_nat_pagado' =>  $compra->monto . " " . $fechaActual
                    ];

                    $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
                }
            }
        }

        $user = User::find(auth()->user()->id);
        $user->pay = $user->pay-10;
        $user->save();

        $pdfContent = createPDF($hash_factura);

        Mail::send('mail.comprobante-mail', ['user' => $user], function ($m) use ($pdfContent, $request, $user) {
            $m->to([
                auth()->user()->email
            ])->subject('SEFAR UNIVERSAL - Hemos procesado su pago satisfactoriamente')->attachData($pdfContent, 'Comprobante.pdf', ['mime' => 'application/pdf']);
        });

        $pdfContent2 = createPDFintel($hash_factura);

        Mail::send('mail.comprobante-mail-intel', ['user' => $user], function ($m) use ($pdfContent2, $request, $user) {
            $m->to([
                'pedro.bazo@sefarvzla.com',
                'crisantoantonio@gmail.com',
                'sistemasccs@sefarvzla.com',
                'automatizacion@sefarvzla.com',
                'sistemascol@sefarvzla.com',
                'asistentedeproduccion@sefarvzla.com',
                'organizacionrrhh@sefarvzla.com',
                'organizacionrrhh@sefarvzla.com',
                '20053496@bcc.hubspot.com',
                'contabilidad@sefaruniversal.com',
            ])->subject(strtoupper($user->name) . ' (ID: ' .
                strtoupper($user->passport) . ') HA REALIZADO UN PAGO EN App Sefar Universal')->attachData($pdfContent2, 'Comprobante.pdf', ['mime' => 'application/pdf']);
        });
    }

    public function procesarpay(Request $request) {
        $hubspot = HubSpot\Factory::createWithAccessToken(env('HUBSPOT_KEY'));
        //Lo que va dentro de la Funcion

        if (auth()->user()->servicio == 'Constitución de Empresa' || auth()->user()->servicio == 'Representante Fiscal' || auth()->user()->servicio == 'Codigo  Fiscal' || auth()->user()->servicio == 'Apertura de cuenta' || auth()->user()->servicio == 'Trimestre contable' || auth()->user()->servicio == 'Cooperativa 10 años' || auth()->user()->servicio == 'Cooperativa 5 años' || auth()->user()->servicio == 'Portuguesa Sefardi' || auth()->user()->servicio == 'Portuguesa Sefardi - Subsanación' || auth()->user()->servicio == 'Certificación de Documentos - Portugal') {
            Stripe\Stripe::setApiKey(env('STRIPE_SECRET_PORT'));
        } else {
            Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
        }


        $variable = json_decode(json_encode($request->all()),true);

        $servicio = Servicio::where('id_hubspot', auth()->user()->servicio)->get();

        $cupones = json_decode(json_encode(Coupon::all()),true);

        $datos = json_decode(json_encode(DB::table('users')->where('id', auth()->user()->id)->get()),true);

        $finalcupon = "";

        $errorcod = "error";

        $compras = Compras::where('id_user', auth()->user()->id)->where('pagado', 0)->whereNull('deal_id')->get();
        $servicio = Servicio::where('id_hubspot', auth()->user()->servicio)->get();

        $cps = json_decode(json_encode($compras),true);

        $hss = json_decode(json_encode($servicio),true);

        $monto = 0;

        foreach ($compras as $key => $compra) {
            $monto = $monto + $compra["monto"];
        }

        try {
            $customer = Stripe\Customer::create(array(
                "email" => auth()->user()->email,
                "name" => $request->nameoncard,
                "source" => $request->stripeToken
            ));
            $charged = Stripe\Charge::create ([
                "amount" => $monto*100,
                "currency" => "eur",
                "customer" => $customer->id,
                "description" => "Sefar Universal: Gestión de Pago Múltiple (Carrito)"
            ]);
        } catch(CardException $e) {
            $errorcod= "errorx";
        } catch (RateLimitException $e) {
            $errorcod= "error1";
        } catch (InvalidRequestException $e) {
            $errorcod= "error2";
        } catch (AuthenticationException $e) {
            $errorcod= "error3";
        } catch (ApiConnectionException $e) {
            $errorcod= "error4";
        } catch (ApiErrorException $e) {
            $errorcod= "error5";
        } catch (Exception $e) {
            $errorcod= "error6";
        }

        if ($errorcod== "errorx"){
            return redirect()->route('clientes.pay')->with("status","errorx")->with("code",$e->getError()->code);
        }

        if ($errorcod== "error1"){
            return redirect()->route('clientes.pay')->with("status","error1");
        }

        if ($errorcod== "error2"){
            return redirect()->route('clientes.pay')->with("status","error2");
        }

        if ($errorcod== "error3"){
            return redirect()->route('clientes.pay')->with("status","error3");
        }

        if ($errorcod== "error4"){
            return redirect()->route('clientes.pay')->with("status","error4");
        }

        if ($errorcod== "error5"){
            return redirect()->route('clientes.pay')->with("status","error5");
        }

        if ($errorcod== "error6"){
            return redirect()->route('clientes.pay')->with("status","error6");
        }

        if ($charged->status == "succeeded"){
            if (isset($charged->id)){

                $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

                $hash_factura = "sef_".generate_string($permitted_chars, 50);

                Factura::create([
                    'id_cliente' => auth()->user()->id,
                    'hash_factura' => $hash_factura,
                    'met' => 'stripe',
                    'idcus' => $charged->customer,
                    'idcharge' => $charged->id
                ]);

                foreach ($compras as $key => $compra) {
                    DB::table('compras')->where('id', $compra['id'])->update(['pagado' => 1, 'hash_factura' => $hash_factura]);
                }

                $cargostemp = [];

                if (isset($datos[0]["id_pago"])){
                    if(is_array(json_decode($datos[0]["id_pago"],true))) {
                        $cargostemp = json_decode($datos[0]["id_pago"],true);
                        $cargostemp[] = $charged->id;
                        $cargos = json_encode($cargostemp);
                    } else {
                        $cargostemp[] = $datos[0]["id_pago"];
                        $cargostemp[] = $charged->id;
                        $cargos = json_encode($cargostemp);
                    }
                } else {
                    $cargostemp[] = $charged->id;
                    $cargos = json_encode($cargostemp);
                }

                $cuponestemp = [];

                if (isset($finalcupon)){
                    DB::table('coupons')->where('couponcode', $finalcupon)->update(['enabled' => 0]);
                    if (isset($datos[0]["pago_cupon"])){
                        if(is_array(json_decode($datos[0]["pago_cupon"],true))) {
                            $cuponestemp = json_decode($datos[0]["pago_cupon"],true);
                            $cuponestemp[] = $finalcupon;
                            $cupones = json_encode($cuponestemp);
                        } else {
                            $cuponestemp[] = $datos[0]["pago_cupon"];
                            $cuponestemp[] = $finalcupon;
                            $cupones = json_encode($cuponestemp);
                        }
                    } else {
                        $cuponestemp[] = $finalcupon;
                        $cupones = json_encode($cuponestemp);
                    }
                } else {
                    if (isset($datos[0]["pago_cupon"])){
                        if(is_array(json_decode($datos[0]["pago_cupon"],true))) {
                            $cuponestemp = json_decode($datos[0]["pago_cupon"],true);
                            $cuponestemp[] = '';
                            $cupones = json_encode($cuponestemp);
                        } else {
                            $cuponestemp[] = $datos[0]["pago_cupon"];
                            $cuponestemp[] = '';
                            $cupones = json_encode($cuponestemp);
                        }
                    } else {
                        $cuponestemp[] = '';
                        $cupones = json_encode($cuponestemp);
                    }
                }

                $pago_registrotemp = [];

                if (isset($datos[0]["pago_registro_hist"])){
                    if(is_array(json_decode($datos[0]["pago_registro_hist"],true))) {
                        $pago_registrotemp = json_decode($datos[0]["pago_registro_hist"],true);
                        $pago_registrotemp[] = $monto;
                        $pago_registro = json_encode($pago_registrotemp);
                    } else {
                        $pago_registrotemp[] = $datos[0]["pago_registro"];
                        $pago_registrotemp[] = $monto;
                        $pago_registro = json_encode($pago_registrotemp);
                    }
                } else {
                    $pago_registrotemp[] = $monto;
                    $pago_registro = json_encode($pago_registrotemp);
                }

                DB::table('users')->where('id', auth()->user()->id)->update(['pay' => 1, 'pago_registro_hist' => $pago_registro, 'pago_registro' => $monto, 'id_pago' => $cargos, 'pago_cupon' => $cupones, 'stripe_cus_id' => $charged->customer, 'contrato' => 0]);

                $setto2 = 1;

                foreach ($compras as $key => $compra) {
                    $servicio = Servicio::where('id_hubspot', $compra["servicio_hs_id"])->get();
                    if ($compra["servicio_hs_id"] == 'Árbol genealógico de Deslinde' || $compra["servicio_hs_id"] == 'Acumulación de linajes' || $compra["servicio_hs_id"] == 'Procedimiento de Urgencia' || $compra["servicio_hs_id"] == 'Recurso de Alzada' || $compra["servicio_hs_id"] == 'Gestión Documental' || $servicio[0]['tipov']==1){
                        $setto2 = 1;
                    } else {
                        $setto2 = 0;
                        break;
                    }
                }

                if ($setto2==1) {
                    DB::table('users')->where('id', auth()->user()->id)->update(['pay' => 2]);
                    auth()->user()->revokePermissionTo('finish.register');
                }

                auth()->user()->revokePermissionTo('pay.services');
                $idcontact = "";

                $filter = new \HubSpot\Client\Crm\Contacts\Model\Filter();
                $filter
                    ->setOperator('EQ')
                    ->setPropertyName('email')
                    ->setValue(auth()->user()->email);

                $filterGroup = new \HubSpot\Client\Crm\Contacts\Model\FilterGroup();
                $filterGroup->setFilters([$filter]);

                $searchRequest = new \HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest();
                $searchRequest->setFilterGroups([$filterGroup]);

                //Llamo a todas las propiedades de Hubspot (Si el dia de mañana hay que añadir algo nuevo, la ventaja que tenemos es que ya me estoy trayendo todos los elementos del arreglo)

                $searchRequest->setProperties([
                    "registro_pago",
                    "registro_cupon",
                    "transaction_id"
                ]);

                //Hago la busqueda del cliente
                $contactHS = $hubspot->crm()->contacts()->searchApi()->doSearch($searchRequest);

                if ($contactHS['total'] != 0){
                    $valuehscupon = "";
                    //sago solo el id del contacto:
                    $idcontact = $contactHS['results'][0]['id'];

                    DB::table('users')->where('id', auth()->user()->id)->update(['hs_id' => $idcontact]);

                    $properties1 = [
                        'registro_pago' => $servicio[0]["precio"],
                        'registro_cupon' => $cupones,
                        'transaction_id' => $cargos,
                        'hist_pago_registro' => $pago_registro
                    ];
                    $simplePublicObjectInput = new SimplePublicObjectInput([
                        'properties' => $properties1,
                    ]);

                    $apiResponse = $hubspot->crm()->contacts()->basicApi()->update($idcontact, $simplePublicObjectInput);

                    $dealInput = new \HubSpot\Client\Crm\Deals\Model\SimplePublicObjectInput();

                    foreach ($compras as $key => $compra) {
                        $dealInput->setProperties([
                            'dealname' => auth()->user()->name . ' - ' . $compra['servicio_hs_id'],
                            'pipeline' => "94794",
                            'dealstage' => "429097",
                            'servicio_solicitado' => $compra['servicio_hs_id'],
                            'servicio_solicitado2' => $compra['servicio_hs_id'],
                        ]);

                        $apiResponse = json_decode(json_encode($hubspot->crm()->deals()->basicApi()->create($dealInput)),true);

                        $iddeal = $apiResponse["id"];

                        $associationSpec1 = new AssociationSpec([
                            'association_category' => 'HUBSPOT_DEFINED',
                            'association_type_id' => 3
                        ]);

                        $asocdeal = $hubspot->crm()->deals()->associationsApi()->create($iddeal, 'contacts', $idcontact, [$associationSpec1]);
                        sleep(2);
                    }

                }
                $user = User::findOrFail(auth()->user()->id);
                $pdfContent = createPDF($hash_factura);

                Mail::send('mail.comprobante-mail', ['user' => $user], function ($m) use ($pdfContent, $request, $user) {
                    $m->to([
                        auth()->user()->email
                    ])->subject('SEFAR UNIVERSAL - Hemos procesado su pago satisfactoriamente')->attachData($pdfContent, 'Comprobante.pdf', ['mime' => 'application/pdf']);
                });

                $pdfContent2 = createPDFintel($hash_factura);

                Mail::send('mail.comprobante-mail-intel', ['user' => $user], function ($m) use ($pdfContent2, $request, $user) {
                    $m->to([
                        'pedro.bazo@sefarvzla.com',
                        'crisantoantonio@gmail.com',
                        'sistemasccs@sefarvzla.com',
                        'automatizacion@sefarvzla.com',
                        'sistemascol@sefarvzla.com',
                        'asistentedeproduccion@sefarvzla.com',
                        'organizacionrrhh@sefarvzla.com',
                        'organizacionrrhh@sefarvzla.com',
                        '20053496@bcc.hubspot.com',
                        'contabilidad@sefaruniversal.com',
                    ])->subject(strtoupper($user->name) . ' (ID: ' .
                        strtoupper($user->passport) . ') HA REALIZADO UN PAGO EN App Sefar Universal')->attachData($pdfContent2, 'Comprobante.pdf', ['mime' => 'application/pdf']);
                });

                if ($setto2==1) {

                    $query = "SELECT a.*, b.name, b.passport, b.email, b.phone, b.created_at as fecha_de_registro FROM facturas as a, users as b WHERE a.id_cliente = b.id AND b.passport='".$user->passport."' ORDER BY a.id DESC LIMIT 1;";

                    $datos_factura = json_decode(json_encode(DB::select($query)),true);

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

                    $query = 'mutation ($myItemName: String!, $columnVals: JSON!) { create_item (board_id: 878831315, group_id: "duplicate_of_en_proceso", item_name:$myItemName, column_values:$columnVals) { id } }';

                    $link = 'https://app.sefaruniversal.com/tree/' . auth()->user()->passport;

                    $vars = [
                        'myItemName' => auth()->user()->apellidos." ".auth()->user()->nombres,
                        'columnVals' => json_encode([
                            'texto' => auth()->user()->passport,
                            'enlace' => $link . " " . $link,
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

                    $responseContent = json_decode($data, true);

                    echo json_encode($responseContent);

                }

                return redirect()->route('gracias')->with("status","exito");
            } else {
                return redirect()->route('clientes.pay')->with("status","error6");
            }
        }
    }

    public function procesarpayfases(Request $request) {
        $hubspot = HubSpot\Factory::createWithAccessToken(env('HUBSPOT_KEY'));
        //Lo que va dentro de la Funcion

        if (auth()->user()->servicio == 'Constitución de Empresa' || auth()->user()->servicio == 'Representante Fiscal' || auth()->user()->servicio == 'Codigo  Fiscal' || auth()->user()->servicio == 'Apertura de cuenta' || auth()->user()->servicio == 'Trimestre contable' || auth()->user()->servicio == 'Cooperativa 10 años' || auth()->user()->servicio == 'Cooperativa 5 años' || auth()->user()->servicio == 'Portuguesa Sefardi' || auth()->user()->servicio == 'Portuguesa Sefardi - Subsanación' || auth()->user()->servicio == 'Certificación de Documentos - Portugal') {
            Stripe\Stripe::setApiKey(env('STRIPE_SECRET_PORT'));
        } else {
            Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
        }

        $servicio = Servicio::where('id_hubspot', auth()->user()->servicio)->get();

        $cupones = json_decode(json_encode(Coupon::all()),true);

        $errorcod = "error";

        $compras = Compras::where('id_user', auth()->user()->id)->where('pagado', 0)->whereNotNull('deal_id')->get();
        $servicio = Servicio::where('id_hubspot', auth()->user()->servicio)->get();

        $monto = 0;

        foreach ($compras as $key => $compra) {
            $monto = $monto + $compra["monto"];
        }

        try {
            $customer = Stripe\Customer::create(array(
                "email" => auth()->user()->email,
                "name" => $request->nameoncard,
                "source" => $request->stripeToken
            ));
            $charged = Stripe\Charge::create ([
                "amount" => $monto*100,
                "currency" => "eur",
                "customer" => $customer->id,
                "description" => "Sefar Universal: Gestión de Pago de Fases"
            ]);
        } catch(CardException $e) {
            $errorcod= "errorx";
        } catch (RateLimitException $e) {
            $errorcod= "error1";
        } catch (InvalidRequestException $e) {
            $errorcod= "error2";
        } catch (AuthenticationException $e) {
            $errorcod= "error3";
        } catch (ApiConnectionException $e) {
            $errorcod= "error4";
        } catch (ApiErrorException $e) {
            $errorcod= "error5";
        } catch (Exception $e) {
            $errorcod= "error6";
        }

        if ($errorcod== "errorx"){
            return redirect()->route('clientes.pay')->with("status","errorx")->with("code",$e->getError()->code);
        }

        if ($errorcod== "error1"){
            return redirect()->route('clientes.pay')->with("status","error1");
        }

        if ($errorcod== "error2"){
            return redirect()->route('clientes.pay')->with("status","error2");
        }

        if ($errorcod== "error3"){
            return redirect()->route('clientes.pay')->with("status","error3");
        }

        if ($errorcod== "error4"){
            return redirect()->route('clientes.pay')->with("status","error4");
        }

        if ($errorcod== "error5"){
            return redirect()->route('clientes.pay')->with("status","error5");
        }

        if ($errorcod== "error6"){
            return redirect()->route('clientes.pay')->with("status","error6");
        }

        if ($charged->status == "succeeded"){
            if (isset($charged->id)){

                $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

                $hash_factura = "sef_".generate_string($permitted_chars, 50);

                Factura::create([
                    'id_cliente' => auth()->user()->id,
                    'hash_factura' => $hash_factura,
                    'met' => 'stripe',
                    'idcus' => $charged->customer,
                    'idcharge' => $charged->id
                ]);

                foreach ($compras as $key => $compra) {
                    DB::table('compras')->where('id', $compra['id'])->update(['pagado' => 1, 'hash_factura' => $hash_factura]);

                    $deal = Negocio::find($compra->deal_id);
                    $fechaActual = Carbon::now()->format('Y/m/d');

                    if($compra->phasenum == 1){
                        $deal->fase_1_pagado = $compra->monto . " " . $fechaActual;
                        $deal->fecha_fase_1_pagado = $fechaActual;
                        $deal->monto_fase_1_pagado = $compra->monto;
                        $deal->save();

                        if ($deal->teamleader_id) {
                            $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

                            // Conservar los campos existentes
                            $existingFields = $currentProject['custom_fields'];

                            // Actualizar solo el campo necesario sin borrar los demás
                            $updatedFields = array_map(function($field) use ($compra, $fechaActual) {
                                if ($field['definition']['id'] === "a1b50c58-8175-0d13-9856-f661e783dc08") {
                                    $field['value'] = $compra->monto . " " . $fechaActual;
                                }

                                $field['id'] = $field['definition']['id'];

                                unset($field['definition']);

                                return $field;
                            }, $existingFields);

                            $campoTeamleader = ['custom_fields' => $updatedFields];

                            $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
                        }

                        if ($deal->hubspot_id) {
                            // Establecer la zona horaria en UTC
                            $utcTimezone = new \DateTimeZone('UTC');

                            // Obtener la fecha actual a medianoche en UTC
                            $midnightUTC = new \DateTime('now', $utcTimezone);
                            $midnightUTC->setTime(0, 0, 0);  // Establecer hora en 00:00:00

                            // Convertir a timestamp en milisegundos
                            $timestamp = $midnightUTC->getTimestamp() * 1000;

                            // Datos para enviar a HubSpot
                            $campoHubspot = [
                                'monto_fase_1_pagado' => $compra->monto,
                                'fecha_fase_1_pagado' => $timestamp,
                                'fase_1_pagado' =>  $compra->monto . " " . $fechaActual
                            ];

                            $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
                        }
                    } else if ($compra->phasenum == 2) {
                        $deal->fase_2_pagado = $compra->monto . " " . $fechaActual;
                        $deal->fecha_fase_2_pagado = $fechaActual;
                        $deal->monto_fase_2_pagado = $compra->monto;
                        $deal->save();

                        if ($deal->teamleader_id) {
                            $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

                            // Conservar los campos existentes
                            $existingFields = $currentProject['custom_fields'];

                            // Actualizar solo el campo necesario sin borrar los demás
                            $updatedFields = array_map(function($field) use ($compra, $fechaActual) {
                                if ($field['definition']['id'] === "a5b94ccc-3ea8-06fc-b259-0a487073dc0d") {
                                    $field['value'] = $compra->monto . " " . $fechaActual;
                                }

                                $field['id'] = $field['definition']['id'];

                                unset($field['definition']);

                                return $field;
                            }, $existingFields);

                            $campoTeamleader = ['custom_fields' => $updatedFields];

                            $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
                        }

                        if ($deal->hubspot_id) {
                            // Establecer la zona horaria en UTC
                            $utcTimezone = new \DateTimeZone('UTC');

                            // Obtener la fecha actual a medianoche en UTC
                            $midnightUTC = new \DateTime('now', $utcTimezone);
                            $midnightUTC->setTime(0, 0, 0);  // Establecer hora en 00:00:00

                            // Convertir a timestamp en milisegundos
                            $timestamp = $midnightUTC->getTimestamp() * 1000;

                            // Datos para enviar a HubSpot
                            $campoHubspot = [
                                'monto_fase_2_pagado' => $compra->monto,
                                'fecha_fase_2_pagado' => $timestamp,
                                'fase_2_pagado' =>  $compra->monto . " " . $fechaActual
                            ];

                            $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
                        }
                    } else if ($compra->phasenum == 3) {
                        $deal->fase_3_pagado = $compra->monto . " " . $fechaActual;
                        $deal->fecha_fase_3_pagado = $fechaActual;
                        $deal->monto_fase_3_pagado = $compra->monto;
                        $deal->save();

                        if ($deal->teamleader_id) {
                            $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

                            // Conservar los campos existentes
                            $existingFields = $currentProject['custom_fields'];

                            // Actualizar solo el campo necesario sin borrar los demás
                            $updatedFields = array_map(function($field) use ($compra, $fechaActual) {
                                if ($field['definition']['id'] === "9a1df9b7-c92f-09e5-b156-96af3f83dc0e") {
                                    $field['value'] = $compra->monto . " " . $fechaActual;
                                }

                                $field['id'] = $field['definition']['id'];

                                unset($field['definition']);

                                return $field;
                            }, $existingFields);

                            $campoTeamleader = ['custom_fields' => $updatedFields];

                            $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
                        }

                        if ($deal->hubspot_id) {
                            // Establecer la zona horaria en UTC
                            $utcTimezone = new \DateTimeZone('UTC');

                            // Obtener la fecha actual a medianoche en UTC
                            $midnightUTC = new \DateTime('now', $utcTimezone);
                            $midnightUTC->setTime(0, 0, 0);  // Establecer hora en 00:00:00

                            // Convertir a timestamp en milisegundos
                            $timestamp = $midnightUTC->getTimestamp() * 1000;

                            // Datos para enviar a HubSpot
                            $campoHubspot = [
                                'monto_fase_3_pagado' => $compra->monto,
                                'fecha_fase_3_pagado' => $timestamp,
                                'fase_3_pagado' =>  $compra->monto . " " . $fechaActual
                            ];

                            $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
                        }
                    } else if ($compra->phasenum == 99) { //cil
                        $deal->cil___fcje_pagado = $compra->monto . " " . $fechaActual;
                        $deal->cilfcje_fechapagado = $fechaActual;
                        $deal->cilfcje_montopagado = $compra->monto;
                        $deal->save();

                        if ($deal->teamleader_id) {
                            $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

                            // Conservar los campos existentes
                            $existingFields = $currentProject['custom_fields'];

                            // Actualizar solo el campo necesario sin borrar los demás
                            $updatedFields = array_map(function($field) use ($compra, $fechaActual) {
                                if ($field['definition']['id'] === "f23fbe3b-5d13-0a41-a857-e9ab1c63dc42") {
                                    $field['value'] = $compra->monto . " " . $fechaActual;
                                }

                                $field['id'] = $field['definition']['id'];

                                unset($field['definition']);

                                return $field;
                            }, $existingFields);

                            $campoTeamleader = ['custom_fields' => $updatedFields];

                            $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
                        }

                        if ($deal->hubspot_id) {
                            // Establecer la zona horaria en UTC
                            $utcTimezone = new \DateTimeZone('UTC');

                            // Obtener la fecha actual a medianoche en UTC
                            $midnightUTC = new \DateTime('now', $utcTimezone);
                            $midnightUTC->setTime(0, 0, 0);  // Establecer hora en 00:00:00

                            // Convertir a timestamp en milisegundos
                            $timestamp = $midnightUTC->getTimestamp() * 1000;

                            // Datos para enviar a HubSpot
                            $campoHubspot = [
                                'cil___fcje_pagado' =>  $compra->monto . " " . $fechaActual
                            ];

                            $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
                        }
                    } else if ($compra->phasenum == 98) { //cnat
                        $deal->carta_nat_preestab = $compra->monto . " " . $fechaActual;
                        $deal->carta_nat_fechapagado = $fechaActual;
                        $deal->carta_nat_montopagado = $compra->monto;
                        $deal->save();

                        if ($deal->teamleader_id) {
                            $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

                            // Conservar los campos existentes
                            $existingFields = $currentProject['custom_fields'];

                            // Actualizar solo el campo necesario sin borrar los demás
                            $updatedFields = array_map(function($field) use ($compra, $fechaActual) {
                                if ($field['definition']['id'] === "4339375f-ed77-02d9-a157-7da9f9e4bfac") {
                                    $field['value'] = $compra->monto . " " . $fechaActual;
                                }

                                $field['id'] = $field['definition']['id'];

                                unset($field['definition']);

                                return $field;
                            }, $existingFields);

                            $campoTeamleader = ['custom_fields' => $updatedFields];

                            $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
                        }

                        if ($deal->hubspot_id) {
                            // Establecer la zona horaria en UTC
                            $utcTimezone = new \DateTimeZone('UTC');

                            // Obtener la fecha actual a medianoche en UTC
                            $midnightUTC = new \DateTime('now', $utcTimezone);
                            $midnightUTC->setTime(0, 0, 0);  // Establecer hora en 00:00:00

                            // Convertir a timestamp en milisegundos
                            $timestamp = $midnightUTC->getTimestamp() * 1000;

                            // Datos para enviar a HubSpot
                            $campoHubspot = [
                                'carta_nat_pagado' =>  $compra->monto . " " . $fechaActual
                            ];

                            $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
                        }
                    }
                }

                $user = User::find(auth()->user()->id);
                $user->pay = $user->pay-10;
                $user->save();



                $pdfContent = createPDF($hash_factura);

                Mail::send('mail.comprobante-mail', ['user' => $user], function ($m) use ($pdfContent, $request, $user) {
                    $m->to([
                        auth()->user()->email
                    ])->subject('SEFAR UNIVERSAL - Hemos procesado su pago satisfactoriamente')->attachData($pdfContent, 'Comprobante.pdf', ['mime' => 'application/pdf']);
                });

                $pdfContent2 = createPDFintel($hash_factura);

                Mail::send('mail.comprobante-mail-intel', ['user' => $user], function ($m) use ($pdfContent2, $request, $user) {
                    $m->to([
                        'pedro.bazo@sefarvzla.com',
                        'crisantoantonio@gmail.com',
                        'sistemasccs@sefarvzla.com',
                        'automatizacion@sefarvzla.com',
                        'sistemascol@sefarvzla.com',
                        'asistentedeproduccion@sefarvzla.com',
                        'organizacionrrhh@sefarvzla.com',
                        'organizacionrrhh@sefarvzla.com',
                        '20053496@bcc.hubspot.com',
                        'contabilidad@sefaruniversal.com',
                    ])->subject(strtoupper($user->name) . ' (ID: ' .
                        strtoupper($user->passport) . ') HA REALIZADO UN PAGO EN App Sefar Universal')->attachData($pdfContent2, 'Comprobante.pdf', ['mime' => 'application/pdf']);
                });

                return redirect()->route('gracias')->with("status","exito");
            } else {
                return redirect()->route('clientes.pay')->with("status","error6");
            }
        }
    }

    public function checkRegAlzada(Request $request) {
        $mailpass = json_decode(json_encode(DB::table('users')->where('email', $request->email)->where('passport', $request->numero_de_pasaporte)->get()),true);
        $mail = json_decode(json_encode(DB::table('users')->where('email', $request->email)->get()),true);

        $check = 0;

        //dd(json_decode(json_encode($request->all()), true));

        $cantidad = 0;

        if(isset($request->cantidad_alzada) && $request->cantidad_alzada>=0){
            $cantidad = $cantidad + $request->cantidad_alzada;
        }

        $antepasados = 0;

        if (isset($request->tiene_antepasados_espanoles) && $request->tiene_antepasados_espanoles == "Si"){
            $antepasados = 1;
        }

        if (isset($request->tiene_antepasados_italianos) && $request->tiene_antepasados_italianos == "Si"){
            $antepasados = 2;
        }

        if(isset($request->vinculo_antepasados)){
            $vinculo_antepasados = $request->vinculo_antepasados;
        } else {
            $vinculo_antepasados = '';
        }

        if(isset($request->estado_de_datos_y_documentos_de_los_antepasados)){
            $estado_de_datos_y_documentos_de_los_antepasados = $request->estado_de_datos_y_documentos_de_los_antepasados;
        } else {
            $estado_de_datos_y_documentos_de_los_antepasados = '';
        }


        if (count($mailpass)>0 || count($mail)>0) {
            $preusercheck = json_decode( json_encode( DB::table('users')->where('email', $request->email)->get()),true);

            $comprasexistentes = json_decode( json_encode( DB::table('compras')->where('id_user', $preusercheck[0]['id'])->where('servicio_hs_id', $request->nacionalidad_solicitada)->get()),true);

            if (count($comprasexistentes) > 0){
                $check = 2;
            } else {

                $familiares = 1 + $request->cantidad_alzada;
                DB::table('users')->where('email', $request->email)->update([
                    'pay' => 0,
                    'servicio' => $request->nacionalidad_solicitada,
                    'cantidad_alzada' => $cantidad + 1,
                    "antepasados" => $antepasados,
                    'vinculo_antepasados' => $vinculo_antepasados,
                    'estado_de_datos_y_documentos_de_los_antepasados' => $estado_de_datos_y_documentos_de_los_antepasados
                ]);

                if(count($mailpass)>0){
                    $userdata = json_decode(json_encode(DB::table('users')->where('email', $request->email)->where('passport', $request->numero_de_pasaporte)->get()),true);
                } elseif (count($mail)>0){
                    $userdata = json_decode(json_encode(DB::table('users')->where('email', $request->email)->get()),true);
                }


                $compras = Compras::where('id_user', $userdata[0]["id"])->where('pagado', 0)->get();

                if ($request->tiene_hermanos == 1 || $request->tiene_hermanos == "1" || $request->tiene_hermanos == "Si"){
                    $servicio = Servicio::where('id_hubspot', $userdata[0]["servicio"]." - Hermano")->get();
                } else {
                    $servicio = Servicio::where('id_hubspot', $userdata[0]["servicio"])->get();
                }

                $cps = json_decode(json_encode($compras),true);

                $hss = json_decode(json_encode($servicio),true);

                if($userdata[0]["servicio"] == "Recurso de Alzada"){
                    $monto = $hss[0]["precio"] * ($cantidad+1);
                } else {
                    $monto = $hss[0]["precio"];
                }

                if( $userdata[0]["servicio"] == "Española LMD" || $userdata[0]["servicio"] == "Italiana" ) {
                    $desc = "Pago Fase Inicial: Investigación Preliminar y Preparatoria: " . $hss[0]["nombre"];
                    if ($userdata[0]["servicio"] == "Española LMD"){
                        if ($userdata[0]['antepasados']==0){
                            $monto = 299;
                        }
                    }
                    if ($userdata[0]["servicio"] == "Italiana"){
                        if ($userdata[0]['antepasados']==1){
                            $desc = $desc . " + (Consulta Gratuita)";
                        }
                    }
                } elseif ( $userdata[0]["servicio"] == "Gestión Documental" ) {
                    $desc = $hss[0]["nombre"];
                } elseif ($servicio[0]['tipov']==1) {
                    $desc = "Servicios para Vinculaciones: " . $hss[0]["nombre"];
                } else {
                    $desc = "Inicia tu Proceso: " . $hss[0]["nombre"];
                }

                if (isset($request->pay)){
                    if ($request->pay==='1'){
                        $usuariofinal = DB::table('users')->where('email', $request->email)->update([
                            'pay' => 1
                        ]);

                        if (isset($request->monto)){
                            $compra = Compras::create([
                                'id_user' => $userdata[0]["id"],
                                'servicio_hs_id' => $userdata[0]["servicio"],
                                'descripcion' => 'Pago desde www.sefaruniversal.com usando Jotform',
                                'pagado' => 0,
                                'monto' =>$request->monto
                            ]);
                        } else {
                            $compra = Compras::create([
                                'id_user' => $userdata[0]["id"],
                                'servicio_hs_id' => $userdata[0]["servicio"],
                                'descripcion' => 'Pago desde www.sefaruniversal.com usando Jotform',
                                'pagado' => 0,
                                'monto' => 0
                            ]);
                        }



                        $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

                        $hash_factura = "sef_".generate_string($permitted_chars, 50);

                        Factura::create([
                            'id_cliente' => $usuariofinal->id,
                            'hash_factura' => $hash_factura,
                            'met' => 'jotform'
                        ]);

                        DB::table('compras')->where('id', $compra->id)->update(['pagado' => 1, 'hash_factura' => $hash_factura]);
                    } else {
                        Compras::create([
                            'id_user' => $userdata[0]["id"],
                            'servicio_hs_id' => $userdata[0]["servicio"],
                            'descripcion' => $desc,
                            'pagado' => 0,
                            'monto' => $monto
                        ]);
                    }
                } else {
                    Compras::create([
                        'id_user' => $userdata[0]["id"],
                        'servicio_hs_id' => $userdata[0]["servicio"],
                        'descripcion' => $desc,
                        'pagado' => 0,
                        'monto' => $monto
                    ]);
                }

                $check = 1;
            }
        }

        if ($check == 1){
            return redirect()->route('login')->with( ['warning' => 'Ya estabas registrado con el correo ' . $request->email . ' en nuestra plataforma. Por favor, inicia sesión.'] )->with( ['email' => $request->email] );
        } elseif ($check == 2) {
            return redirect()->route('login')->with( ['warning' => 'Ya estabas registrado con el correo ' . $request->email . ' en nuestra plataforma y ya habias solicitado este servicio en el pasado. Por favor, inicia sesión.'] )->with( ['email' => $request->email] );
        } else {
            return redirect()->route( 'register' )->with( ['request' => $request->all()] );
        }
    }

    public function vinculaciones() {
        $servicios = Servicio::where('tipov', 1)->get();
        $compras = DB::table('compras')->where('id_user', auth()->user()->id)->get();
        return view('clientes.vinculaciones', compact('compras', 'servicios'));
    }

    public function regvinculaciones(Request $request) {
        $servicios = Servicio::where('id_hubspot', $request->id)->get();
        $desc = "Servicios para Vinculaciones: " . $servicios[0]["nombre"];
        Compras::create([
            'id_user' => auth()->user()->id,
            'servicio_hs_id' => $servicios[0]["id_hubspot"],
            'descripcion' => $desc,
            'pagado' => 0,
            'monto' => $servicios[0]["precio"]
        ]);
        return redirect()->route('clientes.pay');
    }

    public function fixPayDataHubspot()
    {
        ini_set('max_execution_time', 3000000);
        ini_set('max_input_time', 3000000);

        $hubspot = HubSpot\Factory::createWithAccessToken(env('HUBSPOT_KEY'));
        $query = 'SELECT id, email, pago_cupon, pago_registro, hs_id FROM users where pago_cupon = "NOPAY" or id_pago = "NOPAY"';

        $globalcount = json_decode(json_encode(DB::select($query)),true);

        foreach ($globalcount as $key => $value) {
            $idcontact = "";

            $filter = new \HubSpot\Client\Crm\Contacts\Model\Filter();
            $filter
                ->setOperator('EQ')
                ->setPropertyName('email')
                ->setValue($value["email"]);

            $filterGroup = new \HubSpot\Client\Crm\Contacts\Model\FilterGroup();
            $filterGroup->setFilters([$filter]);

            $searchRequest = new \HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest();
            $searchRequest->setFilterGroups([$filterGroup]);

            //Llamo a todas las propiedades de Hubspot (Si el dia de mañana hay que añadir algo nuevo, la ventaja que tenemos es que ya me estoy trayendo todos los elementos del arreglo)

            $searchRequest->setProperties([
                "registro_pago",
                "registro_cupon",
                "transaction_id"
            ]);

            //Hago la busqueda del cliente
            $contactHS = $hubspot->crm()->contacts()->searchApi()->doSearch($searchRequest);

            if ($contactHS['total'] != 0){
                $valuehscupon = "";
                //sago solo el id del contacto:
                $idcontact = $contactHS['results'][0]['id'];

                DB::table('users')->where('id', $value['id'])->update(['hs_id' => $idcontact]);
                $properties1 = [
                    'registro_pago' => '0',
                    'registro_cupon' => 'NOPAY',
                    'transaction_id' => 'NOPAY'
                ];

                $simplePublicObjectInput = new SimplePublicObjectInput([
                    'properties' => $properties1,
                ]);

                $apiResponse = $hubspot->crm()->contacts()->basicApi()->update($idcontact, $simplePublicObjectInput);
            }

            sleep(2);
        }

        print_r($globalcount);
    }

    public function destroypayelement(Request $request)
    {
        $data = $request->all();
        Compras::where('id', $data["id"])->delete();
        $compras = Compras::where('id_user', auth()->user()->id)->where('pagado', 0)->get();
        if(count($compras)>0){
            echo(1);
        } else {
            echo(0);
        }
    }

    public function checkMondayTest()
    {
        $token = env('MONDAY_TOKEN');
        $apiUrl = 'https://api.monday.com/v2';
        $headers = ['Content-Type: application/json', 'Authorization: ' . $token];

        $query = 'mutation ($myItemName: String!, $columnVals: JSON!) { create_item (board_id: 878831315, group_id: "duplicate_of_en_proceso", item_name:$myItemName, column_values:$columnVals) { id } }';

        $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $hash_factura = "PRUEBA".generate_string($permitted_chars, 10);

        $link = 'https://app.sefaruniversal.com/tree/' . $hash_factura;

        $vars = [
            'myItemName' => 'PRUEBAS PRUEBAS',
            'columnVals' => json_encode([
                'texto' => $hash_factura,
                'link' => $link . " " . $link,
                'estado54' => 'Arbol Incompleto',
                'texto1' => 'PRUEBA',
                'texto4' => $hash_factura
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
    }
}

function generate_string($input, $strength = 16) {
    $input_length = strlen($input);
    $random_string = '';
    for($i = 0; $i < $strength; $i++) {
        $random_character = $input[mt_rand(0, $input_length - 1)];
        $random_string .= $random_character;
    }

    return $random_string;
}

function createPDF($dato){
    $query = "SELECT a.*, b.name, b.passport, b.email, b.phone, b.created_at as fecha_de_registro FROM facturas as a, users as b WHERE a.id_cliente = b.id AND a.hash_factura='$dato';";
    $datos_factura = json_decode(json_encode(DB::select($query)),true);

    $productos = json_decode(json_encode(Compras::where("hash_factura", $datos_factura[0]["hash_factura"])->get()),true);

    $pdf = PDF::loadView('crud.comprobantes.pdf', compact('datos_factura', 'productos'));

    return $pdf->output();
}

function createPDFintel($dato){
    $query = "SELECT a.*, b.name, b.passport, b.email, b.phone, b.created_at as fecha_de_registro FROM facturas as a, users as b WHERE a.id_cliente = b.id AND a.hash_factura='$dato';";
    $datos_factura = json_decode(json_encode(DB::select($query)),true);

    $productos = json_decode(json_encode(Compras::where("hash_factura", $datos_factura[0]["hash_factura"])->get()),true);

    $pdf = PDF::loadView('crud.comprobantes.pdfintel', compact('datos_factura', 'productos'));

    return $pdf->output();
}
