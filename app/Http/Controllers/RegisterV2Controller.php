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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Mail\RegistroCliente;
use App\Mail\RegistroSefar;
use App\Mail\ClaveGeneradaMail;
use Laravel\Jetstream\Jetstream;
use App\Services\HubspotService;
use App\Services\MondayRegistrationService;

class RegisterV2Controller extends Controller
{
    public function index()
    {
        if (Auth::check()) {
            Auth::logout();
        }

        return view('auth.registerv2');
    }

    public function store(
        Request $request,
        HubspotService $hubspotService,
        MondayRegistrationService $mondayRegistrationService
    )
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
                $userCheck = User::where('passport', 'LIKE', $passport)
                    ->orWhere('email', 'LIKE', $input['email'])
                    ->first();

                if ($userCheck) {
                    $servicioSolicitado = $this->resolveServicioSolicitado((string) ($input['servicio'] ?? ''));

                    if (! $servicioSolicitado) {
                        throw ValidationException::withMessages([
                            'servicio' => 'El servicio solicitado no existe.',
                        ]);
                    }

                    $userCheck->servicio = $servicioSolicitado->id_hubspot;
                    $userCheck->save();

                    // Actualizar datos del usuario existente
                    $userCheck->update([
                        'pay' => 0,
                    ]);

                    $compraPendiente = Compras::where('id_user', $userCheck->id)
                        ->where('pagado', 0)
                        ->whereNull('deal_id')
                        ->where('servicio_hs_id', $servicioSolicitado->id_hubspot)
                        ->exists();

                    if (! $compraPendiente) {
                        Compras::create($this->initialPurchaseData($userCheck, $servicioSolicitado, $input));
                    }

                    Mail::to([
                        'pedro.bazo@sefarvzla.com',
                        'sistemasccs@sefarvzla.com',
                        'automatizacion@sefarvzla.com',
                        'sistemascol@sefarvzla.com',
                        'asistentedeproduccion@sefarvzla.com',
                        'organizacionrrhh@sefarvzla.com',
                        'operacionesc@sefarvzla.com',
                        '20053496@bcc.hubspot.com'
                    ])->send(new RegistroSefar($userCheck));

                    // asigna rol y permisos
                    $userCheck->assignRole('Cliente')->givePermissionTo(['pay.services', 'finish.register']);

                    // Siempre redirigir a app.sefaruniversal.com
                    return view('redirect', ['redirect_url' => 'https://app.sefaruniversal.com/login?alert=existe']);
                }

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
                            'referido'    => '',
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
            $servicio = $this->resolveServicioSolicitado((string) ($input['servicio'] ?? ''));

            if (! $servicio) {
                throw ValidationException::withMessages([
                    'servicio' => 'El servicio solicitado no existe.',
                ]);
            }

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

                // contrato
                'contrato'   => 0,
                'cosready'  => 1,
            ]);

            if (!empty($input['tiene_antepasados_espanoles'])) {
                $userData['tiene_antepasados_espanoles'] = $input['tiene_antepasados_espanoles'] == '1' ? 'Si' : 'No';
            }

            if (!empty($input['tiene_antepasados_italianos'])) {
                $userData['tiene_antepasados_italianos'] = $input['tiene_antepasados_italianos'] == '1' ? 'Si' : 'No';
            }

            // -------------------------
            // COMPRAS / FACTURAS
            // -------------------------
            $registrarAuditoriaFormularioPostPagoEnMonday = false;

            if (($input['pay'] ?? '0') === '1') {
                $descripcionCompra = $this->isAuditoriaProcedimientos($servicio->id_hubspot)
                    ? $servicio->nombre
                    : 'Pago desde www.sefaruniversal.com usando formulario';

                $compra = Compras::create([
                    'id_user' => $user->id,
                    'servicio_id' => $servicio->id,
                    'servicio_hs_id' => $servicio?->id_hubspot,
                    'descripcion' => $descripcionCompra,
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

                $compra->forceFill([
                    'pagado' => 1,
                    'hash_factura' => $hash_factura,
                ]);

                $registrarAuditoriaFormularioPostPagoEnMonday = $this->isAuditoriaProcedimientos($servicio->id_hubspot);
            }

            // -------------------------
            // CREAR/VERIFICAR CONTACTO HUBSPOT
            // -------------------------
            $hsContact = $hubspotService->searchContactByEmail($user->email);

            if (!$hsContact) {
                $contactData = [
                    'email'                => $user->email,
                    'firstname'            => $user->nombres,
                    'lastname'             => $user->apellidos,
                    'phone'                => $user->phone ?? '',
                    'pais_de_nacimiento'   => $user->pais_de_nacimiento,
                    'numero_de_pasaporte'  => $user->passport,
                    'servicio_solicitado'  => $user->servicio,
                ];

                // Solo agregar si no es vacío o null
                if (!empty($input['tiene_antepasados_espanoles'])) {
                    $contactData['tiene_antepasados_espanoles'] = $input['tiene_antepasados_espanoles'] == '1' ? 'Si' : 'No';
                }

                if (!empty($input['tiene_antepasados_italianos'])) {
                    $contactData['tiene_antepasados_italianos'] = $input['tiene_antepasados_italianos'] == '1' ? 'Si' : 'No';
                }

                $hsId = $hubspotService->createContact($contactData);
            } else {
                $hsId = $hsContact['id'];
            }

            // Guardar el ID en el usuario
            $user->hs_id = $hsId;
            $user->save();

            if (($input['pay'] ?? '0') === '1' && ! $this->isAuditoriaProcedimientos($servicio->id_hubspot)) {
                $mondayRegistrationService->syncAfterPayment($user, [$servicio]);
            }

            if ($registrarAuditoriaFormularioPostPagoEnMonday) {
                $this->registrarAuditoriaFormularioEnMonday($user, $compra, $servicio, $hash_factura);
                $this->registrarAuditoriaFormularioEtiquetadoVentasSefar($user, $compra, $servicio, $hash_factura);
            }

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
                    'operacionesc@sefarvzla.com',
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
            return view('redirect', ['redirect_url' => 'https://app.sefaruniversal.com/']);

        } catch (ValidationException $e) {
            // Depuración: ver errores exactos
             return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            // Loguear cualquier error general
            \Log::error('Error en el registro: ' . $e->getMessage(), [
                'input' => $input,
                'stack' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function resolveServicioSolicitado(string $servicio): ?Servicio
    {
        $servicio = trim($servicio);

        if ($servicio === '') {
            return null;
        }

        return Servicio::where('id_hubspot', $servicio)
            ->orWhere('nombre', $servicio)
            ->first()
            ?? Servicio::where('id_hubspot', 'like', $servicio . '%')
                ->orWhere('nombre', 'like', $servicio . '%')
                ->first();
    }

    private function initialPurchaseData(User $user, Servicio $servicio, array $input): array
    {
        $serviceCode = $servicio->id_hubspot;
        $monto = (float) $servicio->precio;

        if ($serviceCode === 'Recurso de Alzada') {
            $cantidadAlzada = (int) ($input['cantidad_alzada'] ?? $user->cantidad_alzada ?? 1);
            $monto = $monto * max(1, $cantidadAlzada);
        }

        if ($serviceCode === 'Española LMD' || $serviceCode === 'Italiana') {
            $desc = 'Pago Fase Inicial: Investigación Preliminar y Preparatoria: ' . $servicio->nombre;

            if ($serviceCode === 'Española LMD' && (int) $user->antepasados === 0) {
                $monto = 99;
            }

            if ($serviceCode === 'Italiana' && (int) $user->antepasados === 1) {
                $desc .= ' + (Consulta Gratuita)';
            }
        } elseif ($serviceCode === 'Gestión Documental' || $this->isAuditoriaProcedimientos($serviceCode)) {
            $desc = $servicio->nombre;
        } elseif ((int) $servicio->tipov === 1) {
            $desc = 'Servicios para Vinculaciones: ' . $servicio->nombre;
        } else {
            $desc = 'Análisis genealógico: ' . $servicio->nombre;
        }

        return [
            'id_user' => $user->id,
            'servicio_id' => $servicio->id,
            'servicio_hs_id' => $serviceCode,
            'descripcion' => $desc,
            'pagado' => 0,
            'monto' => $monto,
        ];
    }

    private function isAuditoriaProcedimientos(?string $servicio): bool
    {
        $normalized = Str::lower(Str::ascii(trim((string) $servicio)));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';

        return str_contains($normalized, 'auditoria')
            && str_contains($normalized, 'procedimiento');
    }

    private function registrarAuditoriaFormularioEnMonday(User $user, Compras $compra, Servicio $servicio, string $hashFactura): void
    {
        $token = env('MONDAY_TOKEN');

        if (! $token) {
            \Log::warning('No se registro Auditoria de Procedimientos en Monday: falta MONDAY_TOKEN', [
                'user_id' => $user->id,
                'hash_factura' => $hashFactura,
            ]);

            return;
        }

        $serviceName = 'Auditoría de Procedimientos';
        $mondayDropdownServiceName = 'Auditoría de Procesos';
        $columnValues = [
            'text_mkqswz4p' => $user->name,
            'text_mkzaptd3' => $user->passport ?? 'N/A',
            'date' => now()->format('Y-m-d'),
            'text_mkrd13sa' => 'Formulario',
            'dropdown_mkt4dwyq' => $mondayDropdownServiceName,
            'numeric_mkqsn730' => (float) $compra->monto,
            'text_mkza4s9z' => "ID Usuario: {$user->id} | Factura: {$hashFactura} | Servicios: {$servicio->id_hubspot}",
        ];

        $query = 'mutation ($myItemName: String!, $columnVals: JSON!) {
            create_item (
                board_id: 18393840903,
                group_id: "group_mkzadajd",
                item_name: $myItemName,
                column_values: $columnVals
            ) {
                id
                name
            }
        }';

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => $token,
            ])->post('https://api.monday.com/v2', [
                'query' => $query,
                'variables' => [
                    'myItemName' => $serviceName,
                    'columnVals' => json_encode($columnValues),
                ],
            ]);

            $responseData = $response->json();

            if (! $response->successful() || ! empty($responseData['errors'])) {
                \Log::error('Error registrando Auditoria de Procedimientos en Monday', [
                    'user_id' => $user->id,
                    'hash_factura' => $hashFactura,
                    'status' => $response->status(),
                    'response' => $responseData,
                ]);

                return;
            }

            \Log::info('Auditoria de Procedimientos registrada en Monday', [
                'user_id' => $user->id,
                'hash_factura' => $hashFactura,
                'monday_item_id' => data_get($responseData, 'data.create_item.id'),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Error conectando con Monday para Auditoria de Procedimientos', [
                'user_id' => $user->id,
                'hash_factura' => $hashFactura,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function registrarAuditoriaFormularioEtiquetadoVentasSefar(User $user, Compras $compra, Servicio $servicio, string $hashFactura): void
    {
        $token = env('MONDAY_TOKEN');

        if (! $token) {
            \Log::warning('No se registro Auditoria de Procedimientos en ETIQUETADO VENTAS SEFAR: falta MONDAY_TOKEN', [
                'user_id' => $user->id,
                'hash_factura' => $hashFactura,
            ]);

            return;
        }

        $serviceName = 'Auditoría de Procedimientos';
        $clientName = trim(($user->apellidos ?? '') . ' ' . ($user->nombres ?? '')) ?: $user->name;
        $link = $user->passport ? 'https://app.sefaruniversal.com/tree/' . $user->passport : null;
        $columnValues = [
            'texto' => $user->passport ?? 'N/A',
            'estado2' => 'SI',
            'fecha' => now()->format('Y-m-d'),
            'status' => 'Ventas',
            'texto_largo' => "Forma de pago: Formulario | Monto: {$compra->monto} | Factura: {$hashFactura} | Servicios: {$serviceName}",
            'servicio_solicitado' => $serviceName,
            'servicio_solicitado35' => $serviceName,
            'texto6' => $user->hs_id ?? '',
        ];

        if ($link) {
            $columnValues['enlace'] = ['url' => $link, 'text' => $link];
        }

        if (! empty($user->nombre_de_familiar_realizando_procesos)) {
            $columnValues['texto_largo2'] = $user->nombre_de_familiar_realizando_procesos;
        }

        if (! empty($user->date_of_birth)) {
            $columnValues['fecha75'] = ['date' => \Carbon\Carbon::parse($user->date_of_birth)->format('Y-m-d')];
        }

        $query = 'mutation ($myItemName: String!, $columnVals: JSON!) {
            create_item (
                board_id: 765394861,
                group_id: "grupo_nuevo_mkmvznae",
                item_name: $myItemName,
                column_values: $columnVals
            ) {
                id
                name
            }
        }';

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => $token,
            ])->post('https://api.monday.com/v2', [
                'query' => $query,
                'variables' => [
                    'myItemName' => $clientName,
                    'columnVals' => json_encode($columnValues),
                ],
            ]);

            $responseData = $response->json();

            if (! $response->successful() || ! empty($responseData['errors'])) {
                \Log::error('Error registrando Auditoria de Procedimientos en ETIQUETADO VENTAS SEFAR', [
                    'user_id' => $user->id,
                    'hash_factura' => $hashFactura,
                    'status' => $response->status(),
                    'response' => $responseData,
                ]);

                return;
            }

            $mondayItemId = data_get($responseData, 'data.create_item.id');

            if ($mondayItemId && empty($user->monday_id)) {
                $user->forceFill(['monday_id' => $mondayItemId])->save();
            }

            \Log::info('Auditoria de Procedimientos registrada en ETIQUETADO VENTAS SEFAR', [
                'user_id' => $user->id,
                'hash_factura' => $hashFactura,
                'monday_item_id' => $mondayItemId,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Error conectando con ETIQUETADO VENTAS SEFAR para Auditoria de Procedimientos', [
                'user_id' => $user->id,
                'hash_factura' => $hashFactura,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
