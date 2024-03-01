<?php

namespace App\Actions\Fortify;

use App\Mail\RegistroCliente;
use App\Mail\RegistroSefar;
use App\Models\Agcliente;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
// use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;
use RealRashid\SweetAlert\Facades\Alert;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array  $input
     * @return \App\Models\User
     */
    public function create(array $input)
    {
        // Verificar que el número de pasoporte no exista
        $rol = $input['rol'];
        $passport = $input['passport'];

        if($rol == 'cliente'){
            $user = User::where('passport','LIKE',$passport)->get();
            if(empty($user[0]->passport)){
                // Verificar si el usuario esta registrado en agclientes
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
                        'referido' => trim($input['referido']),
                        'FRegistro' => date('Y-m-d H:i:s'),
                        'FUpdate' => date('Y-m-d H:i:s'),
                        'Usuario' => trim($input['email']),
                    ]);
                }
            }
            Validator::make($input, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'passport' => ['required','unique:users', 'min:5', 'max:170'],
                'password' => $this->passwordRules(),
                'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['required', 'accepted'] : '',
            ])->validate();
        }else{
            Validator::make($input, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password' => $this->passwordRules(),
                'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['required', 'accepted'] : '',
            ])->validate();
        }
        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'passport' => $input['passport'],
            'email_verified_at' => date('Y-m-d H:i:s'),
            'phone' => $input['phone'],
            'servicio' => $input['servicio'],
            'pay' => $input['pay'],
            'nombres' => $input['nombres'],
            'apellidos' => $input['apellidos'],
            'pais_de_nacimiento' => $input['pais_de_nacimiento'],
            'cantidad_alzada' => $input['cantidad_alzada'],
            'antepasados' => $input['antepasados'],
            'vinculo_antepasados' => $input['vinculo_antepasados'],
            'estado_de_datos_y_documentos_de_los_antepasados' => $input['estado_de_datos_y_documentos_de_los_antepasados'],
            'contrato' => 0,
            'referido_por' => $input['referido'],
        ]);
        if($rol == 'cliente'){
            //$user->email_verified_at = date('Y-m-d H:i:s');
            //Artisan::call('view:clear');
            // Enviar un correo al cliente indicando que se ha registrado con exito
            $mail_cliente = new RegistroCliente($user);
            Mail::to($user->email)->send($mail_cliente);
            // Enviar un correo al equipo se Sefar indicando que se ha registrado un cliente
            $mail_sefar = new RegistroSefar($user);
            Mail::to([
                'pedro.bazo@sefarvzla.com',
                'gerenciait@sefarvzla.com',
                'sistemasccs@sefarvzla.com',
                'automatizacion@sefarvzla.com',
                'sistemascol@sefarvzla.com',
                'analisisgenealogico@sefarvzla.com',
                'asistentedeproduccion@sefarvzla.com',
                'organizacionrrhh@sefarvzla.com',
                'gcuriel@sefarvzla.com',
                'organizacionrrhh@sefarvzla.com',
                '20053496@bcc.hubspot.com'
            ])->send($mail_sefar);

            return $user->assignRole('Cliente')->givePermissionTo(['pay.services', 'finish.register']);
        }else{
            return $user;
        }
    }
}
