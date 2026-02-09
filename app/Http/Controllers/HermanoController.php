<?php

namespace App\Http\Controllers;

use App\Models\Hermano;
use Illuminate\Http\Request;
use App\Mail\RegistroCliente;
use App\Mail\RegistroSefar;
use App\Models\Agcliente;
use App\Models\User;
use App\Models\Compras;
use App\Models\Factura;
use App\Models\Servicio;
use Illuminate\Support\Facades\Auth;
// use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Mail;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\DB;


class HermanoController extends Controller
{

    public function registrarhermanoscliente (Request $request)
    {
        $input = json_decode(json_encode($request->all()),true);

        $passport = $input['passport'];
        $email = $input['email'];

        $usercheck = User::where('passport','LIKE',$passport)->get();
        $usermail = User::where('email','LIKE',$email)->get();

        $sizecheck = sizeof($usercheck) + sizeof($usermail);

        if($sizecheck>0){

            if (sizeof($usercheck)>0) {
                return redirect()->back()->withInput()->with('error', 'El pasaporte ya se encuentra registrado.<br><br>Por favor, comuníquese con atención al cliente para solventar el problema.');
            }

            if (sizeof($usermail)>0) {
                return redirect()->back()->withInput()->with('error', 'El correo '.$email.' ya existe.<br><br>Por favor, prueba usar otro correo, o usa tu correo con un alias: <b>tucorreo+alias@correo.com</b>');
            }

        } else {
            $agcliente_v = Agcliente::where('IDCliente',trim($passport))->where('IDPersona',1)->count();
            if($agcliente_v == 0){
                // Incluir al cliente en la tabla agclientes
                Agcliente::create([
                    'IDCliente' => trim($input['passport']),
                    'IDPersona' => 1,
                    'Nombres' => trim($input['nombres']),
                    'Apellidos' => trim($input['apellidos']),
                    'NPasaporte' => trim($input['passport']),
                    'PNacimiento' => trim($input['pais_de_nacimiento']),
                    'PaisNac' => trim($input['pais_de_nacimiento']),
                    'referido' => auth()->user()->referido,
                    'FRegistro' => date('Y-m-d H:i:s'),
                    'FUpdate' => date('Y-m-d H:i:s'),
                    'Usuario' => trim($input['email']),
                ]);
            }

            $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

            $passwordhash = "sefardi_".generate_string_hermano($permitted_chars, 8);

            $user = User::create([
                'name' => $input['nombres'].' '.$input['apellidos'],
                'email' => $input['email'],
                'password' => Hash::make($passwordhash),
                'passport' => $input['passport'],
                'email_verified_at' => date('Y-m-d H:i:s'),
                'phone' => $input['phone'],
                'servicio' => auth()->user()->servicio,
                'pay' => 0,
                'nombres' => $input['nombres'],
                'apellidos' => $input['apellidos'],
                'pais_de_nacimiento' => $request['pais_de_nacimiento'],
                'cantidad_alzada' => 0,
                'contrato' => 0,
                'referido_por' => auth()->user()->referido,
            ]);

            $serviciohermano = Servicio::where('id_hubspot', 'LIKE', auth()->user()->servicio.' - Hermano')->get();

            $servicio = Servicio::where('id_hubspot', 'LIKE', auth()->user()->servicio)->get();

            $compra = Compras::create([
                'id_user' => $user["id"],
                'servicio_hs_id' => $user["servicio"],
                'descripcion' => 'Pago por registro: '.$servicio[0]["nombre"],
                'pagado' => 0,
                'monto' => $serviciohermano[0]['precio']
            ]);

            Hermano::create([
                'id_main' => auth()->user()->id,
                'id_hermano' => $user["id"],
            ]);

            Mail::send('mail.registrohermano', ['user' => $user, 'passwordhash' => $passwordhash], function ($m) use ($user) {
                $m->to([
                    $user['email']
                ])->subject('SEFAR UNIVERSAL - Tu hermano te ha registrado en nuestra App Sefar');
            });

            Mail::send('mail.registrohermanosat', ['user' => $user, 'passwordhash' => $passwordhash], function ($m) use ($user) {
                $m->to([
                    auth()->user()->email
                ])->subject('SEFAR UNIVERSAL - Has registrado a un hermano en nuestra App Sefar');
            });

            Alert::success('Registro exitoso', 'El hermano ha sido registrado correctamente.');

            return redirect()->route('cliente.hermanos')->with('success', 'Has registrado a tu hermano '.$user['name']. ' satisfactoriamente en nuestra app.');
        }

    }

}

function generate_string_hermano($input, $strength = 16) {
    $input_length = strlen($input);
    $random_string = '';
    for($i = 0; $i < $strength; $i++) {
        $random_character = $input[mt_rand(0, $input_length - 1)];
        $random_string .= $random_character;
    }

    return $random_string;
}
