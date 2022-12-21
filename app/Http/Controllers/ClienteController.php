<?php

namespace App\Http\Controllers;

use App\Mail\CargaCliente;
use App\Mail\CargaSefar;
use App\Models\Agcliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Stripe;
use Illuminate\Support\Facades\DB;

class ClienteController extends Controller
{
    public function tree(){
        if (Auth::user()->roles->first()->name == "Cliente"){
            if(Auth::user()->pay==0){
                return redirect()->route('clientes.pay');
            }
        }
        $IDCliente = Auth::user()->passport;
        return view('arboles.tree', compact('IDCliente'));
    }

    public function salir(Request $request){
        // Envía un correo al cliente que ha culminado la carga
        $mail_cliente = new CargaCliente(Auth::user());
        Mail::to(Auth::user()->email)->send($mail_cliente);

        // Envía un correo al equipo de Sefar
        $mail_sefar = new CargaSefar(Auth::user());
        Mail::to([
            'pedro.bazo@sefarvzla.com',
            'czanella@sefarvzla.com',
            'gerenciait@sefarvzla.com',
            /* 'egonzalez@sefarvzla.com', */
            'analisisgenealogico@sefarvzla.com',
            'arosales@sefarvzla.com',
            'asistentedeproduccion@sefarvzla.com',
            'gcuriel@sefarvzla.com'
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

        /* Aquí actualizo la base de datos */

        //print_r('de php');
        //print_r($input['referido_por']);
        $user = Auth()->user();
        // Actualizando el árbol genenalógico
        $agcliente = Agcliente::where('IDCliente',$user->passport)->where('IDPersona',1)->first();
        if($agcliente){
            $agcliente->Sexo = $input['genero'] == 'MASCULINO / MALE' ? 'M' : 'F';
            $user->genero = $agcliente->Sexo;
            //$agcliente->AnhoNac = date("Y", $$input['date_of_birth']);
            //$agcliente->MesNac = date("m", $$input['date_of_birth']);
            //$agcliente->DiaNac = date("d", $$input['date_of_birth']);
            /* try {
                $user->date_of_birth = $input['date_of_birth'];
            } catch (\Throwable $th) {
                // $user->date_of_birth = null;
            } */
            $agcliente->LugarNac = trim($input['ciudad_de_nacimiento']);
            $agcliente->PaisNac = trim($input['pais_de_nacimiento']);

            $agcliente->FRegistro = date('Y-m-d H:i:s');
            $agcliente->PNacimiento = trim($input['pais_de_nacimiento']);
            $user->pais_de_nacimiento = $agcliente->PNacimiento;
            $agcliente->LNacimiento = trim($input['ciudad_de_nacimiento']);
            $user->ciudad_de_nacimiento = $agcliente->LNacimiento;
            //$agcliente->referido = trim($input['referido_por']);
            //$user->referido_por = $agcliente->referido;
            $agcliente->PaisPasaporte = trim($input['pais_de_expedicion_del_pasaporte']);

            $agcliente->ParentescoF = trim($input['vinculo_miembro_de_familia_1']);
            $agcliente->NombresF = trim($input['nombre_miembro_de_familia_1']);
            $agcliente->ApellidosF = trim($input['apellidos_miembro_de_familia_1']);
            // $agcliente->NPasaporteF = trim($input['pasaporte_f']);

            $agcliente->Observaciones = (($agcliente->Observaciones == null) ? '' : $agcliente->Observaciones . '. ')
                . 'Phone: ' . trim($input['phone'])
                . ' E-mail:' . trim($input['email'])
                . ' Adress:' . trim($input['address']);
            $agcliente->save();
            $user->save();
        }

        /* Fin de la actualización en Base de Datos */

        DB::table('users')->where('id', auth()->user()->id)->update(['pay' => 2]); // no borrar esta linea
        auth()->user()->revokePermissionTo('finish.register');
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
        return view('clientes.pay');
    }

    public function revisarcupon(Request $request){
        $data = json_decode(json_encode($request->all()),true);

        $cupones = ["BYTR4563PO", "BYTR1946RA", "55677"];

        if( in_array($data["cpn"], $cupones)){
            DB::table('users')->where('id', auth()->user()->id)->update(['pay' => 1]);
                auth()->user()->revokePermissionTo('pay.services');
            return response()->json([
                'status' => "true"
            ]);
        } else {
            return response()->json([
                'status' => "false"
            ]);
        }
    }

    public function procesarpay(Request $request) {
        //Lo que va dentro de la Funcion
        Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

        $variable = json_decode(json_encode($request->all()),true);

        $servicio= array();

        $servicio["id"]=auth()->user()->servicio;
        $servicio["name"]="Nacionalidad Española por origen Sefardí";

        if(auth()->user()->servicio=="Española LMD"){
            $servicio["name"]="Ley de Memoria Democratica";
            $servicio["price"]=25;
        } else {
            if(auth()->user()->servicio=="Italiana"){
                $servicio["name"]="Nacionalidad Italiana";

            } else if(auth()->user()->servicio=="Española Sefardi"){
                $servicio["name"]="Nacionalidad Española por origen Sefardí";

            } else if(auth()->user()->servicio=="Portuguesa Sefardi"){
                $servicio["name"]="Nacionalidad Portuguesa por origen Sefardí";

            } else if(auth()->user()->servicio=="Portuguesa Sefardi - Subsanación") {
                $servicio["name"]="Subsanación de Expedientes (Portugal)";

            } else if(auth()->user()->servicio=="Española Sefardi - Subsanación") {
                $servicio["name"]="Subsanación de Expedientes (España)";

            }
            $servicio["price"]=50;
        }

        try {
            $customer = Stripe\Customer::create(array(
                "email" => auth()->user()->email,
                "name" => $request->nameoncard,
                "source" => $request->stripeToken
            ));

            $charged = Stripe\Charge::create ([
                "amount" => $servicio["price"]*100,
                "currency" => "eur",
                "customer" => $customer->id,
                "description" => "Sefar Universal: Inicia tu proceso (". $servicio["name"] .")"
            ]);

            if ($charged->status == "succeeded"){
                //Actualizar rol, o actualizar base de datos para decir que el usuario ya pagó
                DB::table('users')->where('id', auth()->user()->id)->update(['pay' => 1]);
                auth()->user()->revokePermissionTo('pay.services');
                return redirect()->route('clientes.getinfo')->with("status","exito");
            }
        } catch (Exception $e) {
            return redirect("/pay")->with("status", "error");
        }
    }
}
