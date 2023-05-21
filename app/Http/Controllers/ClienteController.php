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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
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

class ClienteController extends Controller
{
    public function tree(){
        if (Auth::user()->roles->first()->name == "Cliente"){
            if(Auth::user()->pay==0){
                return redirect()->route('clientes.pay');
            }
            /*
            if(Auth::user()->contrato==0){
                return redirect()->route('cliente.contrato');
            }
            */
        }
        $IDCliente = Auth::user()->passport;
        return view('arboles.tree', compact('IDCliente'));
    }

    public function contrato(){
        if (Auth::user()->roles->first()->name == "Cliente"){
            if(Auth::user()->pay==0){
                return redirect()->route('clientes.pay');
            }
            if(Auth::user()->contrato==1){
                return redirect()->route('tree');
            }
        }
        return view('clientes.contrato');
    }

    public function checkContrato(){
        DB::table('users')->where('id', auth()->user()->id)->update(['contrato' => 1]); // no borrar esta linea
        return redirect()->route('tree');
    }

    public function salir(Request $request){
        // Envía un correo al cliente que ha culminado la carga
        $mail_cliente = new CargaCliente(Auth::user());
        Mail::to(Auth::user()->email)->send($mail_cliente);

        // Envía un correo al equipo de Sefar
        $mail_sefar = new CargaSefar(Auth::user());
        Mail::to([
            'pedro.bazo@sefarvzla.com',
            /* 'czanella@sefarvzla.com', */
            'gerenciait@sefarvzla.com',
            /* 'egonzalez@sefarvzla.com', */
            'analisisgenealogico@sefarvzla.com',
            /* 'arosales@sefarvzla.com', */
            'asistentedeproduccion@sefarvzla.com',
            'gcuriel@sefarvzla.com',
            'organizacionrrhh@sefarvzla.com',
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
    }

    public function pay(){
        if (Auth::user()->roles->first()->name == "Cliente"){
            if (Auth::user()->pay==2){
                $IDCliente = Auth::user()->passport;
                return redirect('/tree');
            } else if(Auth::user()->pay==1){
                return redirect()->route('clientes.getinfo');
            }
        }
        $compras = Compras::where('id_user', auth()->user()->id)->where('pagado', 0)->get();
        $servicio = Servicio::where('id_hubspot', auth()->user()->servicio)->get();

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
            } elseif (auth()->user()->servicio == 'Constitución de Empresa' || auth()->user()->servicio == 'Representante Fiscal' || auth()->user()->servicio == 'Codigo  Fiscal' || auth()->user()->servicio == 'Apertura de cuenta' || auth()->user()->servicio == 'Trimestre contable' || auth()->user()->servicio == 'Cooperativa 10 años' || auth()->user()->servicio == 'Cooperativa 5 años') {
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

        return view('clientes.pay', compact('servicio', 'compras'));
    }

    public function revisarcupon(Request $request){
        $compras = Compras::where('id_user', auth()->user()->id)->where('pagado', 0)->get();
        $servicio = Servicio::where('id_hubspot', auth()->user()->servicio)->get();
        $hubspot = HubSpot\Factory::createWithAccessToken(env('HUBSPOT_KEY'));
        $data = json_decode(json_encode($request->all()),true);

        $datos = json_decode(json_encode(DB::table('users')->where('id', auth()->user()->id)->get()),true);

        $cupones = json_decode(json_encode(Coupon::all()),true);

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
                    return response()->json([
                        'status' => "halftrue",
                        'percentage'=>$cupon["percentage"]
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

                    DB::table('users')->where('id', auth()->user()->id)->update(['pay' => 1, 'pago_registro_hist' => $pago_registro, 'pago_registro' => 0, 'id_pago' => $cargos, 'pago_cupon' => $cupones ]);

                    $setto2 = 1;

                    foreach ($compras as $key => $compra) {
                        if ($compra["servicio_hs_id"] == 'Recurso de Alzada' || $compra["servicio_hs_id"] == 'Gestión Documental' || $compra["servicio_hs_id"] == 'Constitución de Empresa' || $compra["servicio_hs_id"] == 'Representante Fiscal' || $compra["servicio_hs_id"] == 'Codigo  Fiscal' || $compra["servicio_hs_id"] == 'Apertura de cuenta' || $compra["servicio_hs_id"] == 'Trimestre contable' || $compra["servicio_hs_id"] == 'Cooperativa 10 años' || $compra["servicio_hs_id"] == 'Cooperativa 5 años')
                        {
                                                  
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

    public function procesarpay(Request $request) {
        $hubspot = HubSpot\Factory::createWithAccessToken(env('HUBSPOT_KEY'));
        //Lo que va dentro de la Funcion
        Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

        $variable = json_decode(json_encode($request->all()),true);

        $servicio = Servicio::where('id_hubspot', auth()->user()->servicio)->get();

        $cupones = json_decode(json_encode(Coupon::all()),true);

        $datos = json_decode(json_encode(DB::table('users')->where('id', auth()->user()->id)->get()),true);

        $finalcupon = "";

        $errorcod = "error";

        $compras = Compras::where('id_user', auth()->user()->id)->where('pagado', 0)->get();
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
                    'met' => 'stripe'
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

                DB::table('users')->where('id', auth()->user()->id)->update(['pay' => 1, 'pago_registro_hist' => $pago_registro, 'pago_registro' => $monto, 'id_pago' => $cargos, 'pago_cupon' => $cupones ]);

                $setto2 = 1;

                foreach ($compras as $key => $compra) {
                    if ($compra["servicio_hs_id"] == 'Recurso de Alzada' || $compra["servicio_hs_id"] == 'Gestión Documental' || $compra["servicio_hs_id"] == 'Constitución de Empresa' || $compra["servicio_hs_id"] == 'Representante Fiscal' || $compra["servicio_hs_id"] == 'Codigo  Fiscal' || $compra["servicio_hs_id"] == 'Apertura de cuenta' || $compra["servicio_hs_id"] == 'Trimestre contable' || $compra["servicio_hs_id"] == 'Cooperativa 10 años' || $compra["servicio_hs_id"] == 'Cooperativa 5 años')
                    {
                                              
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
                }
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
            $servicio = Servicio::where('id_hubspot', $userdata[0]["servicio"])->get();

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
                        $monto = 99;
                    }
                }
                if ($userdata[0]["servicio"] == "Italiana"){
                    if ($userdata[0]['antepasados']==1){
                        $desc = $desc . " + (Consulta Gratuita)";
                    }
                }
            } elseif ( $userdata[0]["servicio"] == "Gestión Documental" ) {
                $desc = $hss[0]["nombre"];
            } elseif ($userdata[0]["servicio"] == 'Constitución de Empresa' || $userdata[0]["servicio"] == 'Representante Fiscal' || $userdata[0]["servicio"] == 'Codigo  Fiscal' || $userdata[0]["servicio"] == 'Apertura de cuenta' || $userdata[0]["servicio"] == 'Trimestre contable' || $userdata[0]["servicio"] == 'Cooperativa 10 años' || $userdata[0]["servicio"] == 'Cooperativa 5 años') {
                $desc = "Servicios para Vinculaciones: " . $hss[0]["nombre"];
            } else {
                $desc = "Inicia tu Proceso: " . $hss[0]["nombre"];
            }

            Compras::create([
                'id_user' => $userdata[0]["id"],
                'servicio_hs_id' => $userdata[0]["servicio"],
                'descripcion' => $desc,
                'pagado' => 0,
                'monto' => $monto
            ]);

            $check = 1;
        }

        if ($check == 1){
            return redirect()->route('login')->with( ['warning' => 'Ya estabas registrado con el correo ' . $request->email . ' en nuestra plataforma. Por favor, inicia sesión.'] )->with( ['email' => $request->email] );
        } else {
            return redirect()->route( 'register' )->with( ['request' => $request->all()] );
        }
    }

    public function fixPayDataHubspot()
    {
        ini_set('max_execution_time', 3000000);
        ini_set('max_input_time', 3000000);

        $hubspot = HubSpot\Factory::createWithAccessToken(env('HUBSPOT_KEY'));
        $query = 'SELECT id, email, pago_cupon, pago_registro, hs_id FROM users where pago_cupon = "NOPAY" or id_pago = "NOPAY"';

        $globalcount = json_decode(json_encode(DB::select(DB::raw($query))),true);

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

            sleep(1);
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