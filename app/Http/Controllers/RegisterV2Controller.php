<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Agcliente;
use App\Models\Servicio;
use App\Models\Compras;
use App\Models\Factura;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Mail\RegistroCliente;
use App\Mail\RegistroSefar;
use App\Mail\ClaveGeneradaMail;
use Laravel\Jetstream\Jetstream;
use App\Services\HubspotService;

class RegisterV2Controller extends Controller
{
    public function store(Request $request, HubspotService $hubspotService)
    {
        $input = $request->all();
        $rol = $input['rol'] ?? 'cliente';
        $passport = $input['passport'] ?? null;

        try {
            // -------------------------
            // VALIDACIONES
            // -------------------------
            if ($rol === 'cliente') {
                // Verificar si ya existe user con el mismo pasaporte
                $userCheck = User::where('passport', 'LIKE', $passport)->first();
                if (!$userCheck) {
                    $agcliente_v = Agcliente::where('IDCliente', trim($passport))
                        ->where('IDPersona', 1)
                        ->count();

                    if ($agcliente_v == 0) {
                        Agcliente::create([
                            'IDCliente'   => trim($passport),
                            'IDPersona'   => 1,
                            'Nombres'     => trim($input['nombres']),
                            'Apellidos'   => trim($input['apellidos']),
                            'NPasaporte'  => trim($passport),
                            'PNacimiento' => trim($input['pais_de_nacimiento']),
                            'PaisNac'     => trim($input['pais_de_nacimiento']),
                            'referido'    => trim($input['referido']),
                            'FRegistro'   => now(),
                            'FUpdate'     => now(),
                            'Usuario'     => trim($input['email']),
                        ]);
                    }
                }

                Validator::make($input, [
                    'nombres'   => ['required', 'string', 'max:255'],
                    'apellidos' => ['required', 'string', 'max:255'],
                    'email'     => ['required', 'string', 'email', 'max:255', 'unique:users'],
                    'passport'  => ['required', 'string', 'unique:users,passport', 'min:5', 'max:170'],
                    'phone'     => ['nullable', 'string', 'max:255'],
                    'pais_de_nacimiento' => ['required', 'string', 'max:255'],
                    'servicio'  => ['required', 'string'],
                    'referido'  => ['nullable', 'string', 'max:255'],
                    'tiene_hermanos' => ['required', 'in:0,1'],
                    'nombre_de_familiar_realizando_procesos' => [
                        'exclude_unless:tiene_hermanos,1', 'required', 'string', 'max:255'
                    ],
                ])->validate();
            } else {
                Validator::make($input, [
                    'email'     => ['required', 'string', 'email', 'max:255', 'unique:users'],
                ])->validate();
            }

            // -------------------------
            // GENERAR CONTRASEÑA
            // -------------------------
            $password = Str::random(10);

            // -------------------------
            // CREAR USER
            // -------------------------
            $servicio = Servicio::where('id_hubspot', "like", $input['servicio'] . "%")->first();

            $user = User::create([
                // básicos
                'name'       => $input['nombres'] . ' ' . $input['apellidos'],
                'email'      => $input['email'],
                'password'   => Hash::make($password),
                'email_verified_at' => now(),

                // identidad
                'nombres'    => $input['nombres'],
                'apellidos'  => $input['apellidos'],
                'passport'   => $input['passport'],
                'phone'      => $input['phone'] ?? null,
                'pais_de_nacimiento' => $input['pais_de_nacimiento'],

                // servicio / comercial
                'servicio'   => $servicio?->id_hubspot,
                'pay'        => (int)($input['pay'] ?? 0),

                // árbol / elegibilidad
                'cantidad_alzada'  => (int)($input['cantidad_alzada'] ?? 0),
                'antepasados'      => (int)($input['antepasados'] ?? 0),
                'vinculo_antepasados' => (int)($input['vinculo_antepasados'] ?? 0),
                'estado_de_datos_y_documentos_de_los_antepasados' => $input['estado_de_datos_y_documentos_de_los_antepasados'] ?? null,

                // referidos / familia
                'referido_por' => $input['referido'] ?? null,
                //'tiene_hermanos' => (int)($input['tiene_hermanos'] ?? 0),
                //'tiene_algun_familiar_que_este_o_haya_realizado_algun_proceso_con_nosotros_' => (int)($input['tiene_hermanos'] ?? 0),
                'nombre_de_familiar_realizando_procesos' => $input['nombre_de_familiar_realizando_procesos'] ?? null,

                // contrato
                'contrato'   => 0,
            ]);

            // -------------------------
            // COMPRAS / FACTURAS
            // -------------------------
            if (($input['pay'] ?? '0') === '1') {
                $compra = Compras::create([
                    'id_user' => $user->id,
                    'servicio_hs_id' => $servicio?->id_hubspot,
                    'descripcion' => 'Pago desde www.sefaruniversal.com usando formulario',
                    'pagado' => 0,
                    'monto' => $input['monto'] ?? 0,
                ]);

                $hash_factura = "sef_" . Str::random(50);

                Factura::create([
                    'id_cliente' => $user->id,
                    'hash_factura' => $hash_factura,
                    'met' => 'formulario',
                ]);

                DB::table('compras')
                    ->where('id', $compra->id)
                    ->update(['pagado' => 1, 'hash_factura' => $hash_factura]);
            }

            // -------------------------
            // CREAR/VERIFICAR CONTACTO HUBSPOT
            // -------------------------
            $hsContact = $hubspotService->searchContactByEmail($user->email);

            if (!$hsContact) {
                // Crear nuevo contacto usando la función del servicio
                $hsId = $hubspotService->createContact([
                    'email'                => $user->email,
                    'firstname'            => $user->nombres,
                    'lastname'             => $user->apellidos,
                    'phone'                => $user->phone ?? '',
                    'pais_de_nacimiento'   => $user->pais_de_nacimiento,
                    'numero_de_pasaporte'  => $user->passport,
                    'servicio_solicitado'  => $user->servicio,
                    'n000__referido_por__clonado_' => $user->referido_por ?? '',
                    //'tiene_hermanos' => (int)($input['tiene_hermanos'] ?? 0),
                    'tiene_algun_familiar_que_este_o_haya_realizado_algun_proceso_con_nosotros_' => (int)($input['tiene_hermanos'] ?? 0),
                    'nombre_de_familiar_realizando_procesos' => $user->nombre_de_familiar_realizando_procesos ?? '',
                ]);
            } else {
                $hsId = $hsContact['id'];
            }

            // Guardar el ID en el usuario
            $user->hs_id = $hsId;
            $user->save();

            // -------------------------
            // NOTIFICACIONES
            // -------------------------
            Mail::to($user->email)->send(new ClaveGeneradaMail($user, $password));

            if ($rol === 'cliente') {
                //Mail::to($user->email)->send(new RegistroCliente($user));
                Mail::to([
                    'pedro.bazo@sefarvzla.com',
                    'sistemasccs@sefarvzla.com',
                    'automatizacion@sefarvzla.com',
                    'sistemascol@sefarvzla.com',
                    'asistentedeproduccion@sefarvzla.com',
                    'organizacionrrhh@sefarvzla.com',
                    '20053496@bcc.hubspot.com'
                ])->send(new RegistroSefar($user));

                // asigna rol y permisos
                $user->assignRole('Cliente')->givePermissionTo(['pay.services', 'finish.register']);
            }

            // -------------------------
            // AUTOLOGIN
            // -------------------------
            Auth::login($user);

            // -------------------------
            // REDIRECCIÓN
            // -------------------------
            return redirect()->away('https://app.sefaruniversal.com/');

        } catch (ValidationException $e) {
            // Depuración: ver errores exactos
            dd($e->errors());
        } catch (\Exception $e) {
            // Loguear cualquier error general
            \Log::error('Error en el registro: ' . $e->getMessage(), [
                'input' => $input,
                'stack' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
