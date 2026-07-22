<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Agcliente;
use App\Models\Servicio;
use App\Models\Compras;
use App\Models\Factura;
use App\Models\Alert as Alertas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Mail\RegistroCliente;
use App\Mail\RegistroSefar;
use App\Mail\ClaveGeneradaMail;
use Laravel\Jetstream\Jetstream;
use App\Services\HubspotService;
use Carbon\Carbon;

class RegisterV2Controller extends Controller
{
    public function index()
    {
        if (Auth::check()) {
            Auth::logout();
        }

        return view('auth.registerv2');
    }

    public function create(Request $request)
    {
        $servicios = $this->registrationServices();
        $today = Carbon::today();
        $alertas = Alertas::where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->get();

        return view('auth.register', compact('servicios', 'alertas'));
    }

    public function prepareCheckout(Request $request, HubspotService $hubspotService)
    {
        if (Auth::check()) {
            $compras = $this->ensurePendingRegistrationPurchase(Auth::user());

            return response()->json([
                'success' => true,
                'message' => 'Registro listo para pago.',
                'csrf_token' => csrf_token(),
                'summary' => $this->checkoutSummaryPayload($compras),
                'next_url' => route('clientes.getinfo'),
            ]);
        }

        $input = $request->all();
        $rol = $input['rol'] ?? 'cliente';
        $passport = trim((string) ($input['passport'] ?? ''));

        if ($rol !== 'cliente') {
            return response()->json([
                'success' => false,
                'message' => 'Este registro solo esta disponible para clientes.',
            ], 422);
        }

        Validator::make($input, [
            'nombres' => ['required', 'string', 'max:255'],
            'apellidos' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'passport' => ['required', 'string', 'unique:users,passport', 'min:5', 'max:170'],
            'phone' => ['nullable', 'string', 'max:255'],
            'pais_de_nacimiento' => ['required', 'string', 'max:255'],
            'servicio' => ['required', 'string'],
            'referido' => ['nullable', 'string', 'max:255'],
            'tiene_hermanos' => ['required', 'in:0,1'],
            'nombre_de_familiar_realizando_procesos' => [
                'exclude_unless:tiene_hermanos,1', 'required', 'string', 'max:255'
            ],
            'cantidad_alzada' => ['nullable', 'integer', 'min:0', 'max:50'],
            'antepasados' => ['nullable', 'integer', 'min:0', 'max:5'],
            'vinculo_antepasados' => ['nullable', 'integer', 'min:0', 'max:10'],
            'estado_de_datos_y_documentos_de_los_antepasados' => ['nullable', 'string', 'max:255'],
            'acepta_comunicaciones' => ['accepted'],
            'acepta_datos' => ['accepted'],
        ])->validate();

        $servicio = $this->findRegistrationService($input['servicio']);

        if (! $servicio) {
            throw ValidationException::withMessages([
                'servicio' => 'Selecciona un servicio disponible para iniciar el registro.',
            ]);
        }

        if (User::where('passport', 'LIKE', $passport)->orWhere('email', 'LIKE', $input['email'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un usuario con ese email o identificacion. Inicia sesion para continuar el pago.',
                'login_url' => route('login') . '?alert=existe',
            ], 409);
        }

        DB::beginTransaction();

        try {
            if (Agcliente::where('IDCliente', $passport)->where('IDPersona', 1)->count() === 0) {
                Agcliente::create([
                    'IDCliente' => $passport,
                    'IDPersona' => 1,
                    'Nombres' => trim($input['nombres']),
                    'Apellidos' => trim($input['apellidos']),
                    'NPasaporte' => $passport,
                    'PNacimiento' => trim($input['pais_de_nacimiento']),
                    'PaisNac' => trim($input['pais_de_nacimiento']),
                    'referido' => trim($input['referido'] ?? ''),
                    'FRegistro' => now(),
                    'FUpdate' => now(),
                    'Usuario' => trim($input['email']),
                ]);
            }

            $password = Str::random(10);

            $user = User::create([
                'name' => trim($input['nombres'] . ' ' . $input['apellidos']),
                'email' => $input['email'],
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'nombres' => $input['nombres'],
                'apellidos' => $input['apellidos'],
                'passport' => $passport,
                'phone' => $input['phone'] ?? null,
                'pais_de_nacimiento' => $input['pais_de_nacimiento'],
                'servicio' => $servicio->id_hubspot,
                'pay' => 0,
                'cantidad_alzada' => (int) ($input['cantidad_alzada'] ?? 0),
                'antepasados' => (int) ($input['antepasados'] ?? 0),
                'vinculo_antepasados' => (int) ($input['vinculo_antepasados'] ?? 0),
                'estado_de_datos_y_documentos_de_los_antepasados' => $input['estado_de_datos_y_documentos_de_los_antepasados'] ?? null,
                'referido_por' => $input['referido'] ?? null,
                'tiene_hermanos' => (int) ($input['tiene_hermanos'] ?? 0),
                'tiene_algun_familiar_que_este_o_haya_realizado_algun_proceso_con_nosotros_' => (int) ($input['tiene_hermanos'] ?? 0),
                'nombre_de_familiar_realizando_procesos' => $input['nombre_de_familiar_realizando_procesos'] ?? null,
                'contrato' => 0,
                'cosready' => 1,
            ]);

            if (!empty($input['tiene_antepasados_espanoles'])) {
                $user->tiene_antepasados_espanoles = $input['tiene_antepasados_espanoles'] == '1' ? 'Si' : 'No';
            }

            if (!empty($input['tiene_antepasados_italianos'])) {
                $user->tiene_antepasados_italianos = $input['tiene_antepasados_italianos'] == '1' ? 'Si' : 'No';
            }

            $hsContact = $hubspotService->searchContactByEmail($user->email);

            if (!$hsContact) {
                $contactData = [
                    'email' => $user->email,
                    'firstname' => $user->nombres,
                    'lastname' => $user->apellidos,
                    'phone' => $user->phone ?? '',
                    'pais_de_nacimiento' => $user->pais_de_nacimiento,
                    'numero_de_pasaporte' => $user->passport,
                    'servicio_solicitado' => $user->servicio,
                    'n000__referido_por__clonado_' => $user->referido_por ?? '',
                    'tiene_algun_familiar_que_este_o_haya_realizado_algun_proceso_con_nosotros_' => $input['tiene_hermanos'] == '1' ? 'true' : 'false',
                    'nombre_de_familiar_realizando_procesos' => $user->nombre_de_familiar_realizando_procesos ?? '',
                ];

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

            $user->hs_id = $hsId;
            $user->save();

            $user->assignRole('Cliente')->givePermissionTo(['pay.services', 'finish.register']);

            $compras = $this->ensurePendingRegistrationPurchase($user);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Error preparando checkout de registro: ' . $e->getMessage(), [
                'input' => $input,
                'stack' => $e->getTraceAsString(),
            ]);

            throw $e;
        }

        Mail::to($user->email)->send(new ClaveGeneradaMail($user, $password));
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

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'message' => 'Registro creado. Ya puedes completar el pago.',
            'csrf_token' => csrf_token(),
            'summary' => $this->checkoutSummaryPayload($compras),
            'next_url' => route('clientes.getinfo'),
        ]);
    }

    public function checkoutSummary()
    {
        if (! Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Debes completar el registro antes de consultar el resumen.',
            ], 401);
        }

        $compras = $this->pendingRegistrationPurchasesQuery(Auth::id())->get();

        return response()->json([
            'success' => true,
            'csrf_token' => csrf_token(),
            'summary' => $this->checkoutSummaryPayload($compras),
        ]);
    }

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
                $userCheck = User::where('passport', 'LIKE', $passport)
                    ->orWhere('email', 'LIKE', $input['email'])
                    ->first();

                if ($userCheck) {
                    if ($userCheck->servicio == null){
                        $userCheck->servicio = $input['servicio'];
                        $userCheck->save();
                    }
                    // Actualizar datos del usuario existente
                    $userCheck->update([
                        'pay' => 0,
                    ]);

                    // Crear registro en Compras
                    $servicio = Servicio::where('id_hubspot', "like", $input['servicio'] . "%")->first();
                    $compras = Compras::where('id_user', $userCheck->id)->where('pagado', 0)->whereNull('deal_id')->get();

                    if ($userCheck->tiene_hermanos == 1 || $userCheck->tiene_hermanos == "1" || $userCheck->tiene_hermanos == "Si") {
                        $servicio = Servicio::where('id_hubspot', 'like', $userCheck->servicio . '% - Hermano')->get();
                    } else {
                        $servicio = Servicio::where('id_hubspot', "like", $userCheck->servicio . "%")->get();
                    }

                    $cps = json_decode(json_encode($compras), true);

                    if (count($cps) == 0) {
                        $hss = json_decode(json_encode($servicio), true);

                        if ($userCheck->servicio == "Recurso de Alzada") {
                            $monto = $hss[0]["precio"] * $userCheck->cantidad_alzada;
                        } else {
                            $monto = $hss[0]["precio"];
                        }

                        if ($userCheck->servicio == "Española LMD" || $userCheck->servicio == "Italiana") {
                            $desc = "Pago Fase Inicial: Investigación Preliminar y Preparatoria: " . $hss[0]["nombre"];
                            if ($userCheck->servicio == "Española LMD") {
                                if ($userCheck->antepasados == 0) {
                                    $monto = 99;
                                }
                            }
                            if ($userCheck->servicio == "Italiana") {
                                if ($userCheck->antepasados == 1) {
                                    $desc = $desc . " + (Consulta Gratuita)";
                                }
                            }
                        } elseif ($request->servicio == "Gestión Documental") {
                            $desc = $hss[0]["nombre"];
                        } elseif ($servicio[0]['tipov'] == 1) {
                            $desc = "Servicios para Vinculaciones: " . $hss[0]["nombre"];
                        } else {
                            $desc = "Análisis genealógico: " . $hss[0]["nombre"];
                        }

                        Compras::create([
                            'id_user' => $userCheck->id,
                            'servicio_hs_id' => $request->servicio,
                            'descripcion' => $desc,
                            'pagado' => 0,
                            'monto' => $monto
                        ]);
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
                $contactData = [
                    'email'                => $user->email,
                    'firstname'            => $user->nombres,
                    'lastname'             => $user->apellidos,
                    'phone'                => $user->phone ?? '',
                    'pais_de_nacimiento'   => $user->pais_de_nacimiento,
                    'numero_de_pasaporte'  => $user->passport,
                    'servicio_solicitado'  => $user->servicio,
                    'n000__referido_por__clonado_' => $user->referido_por ?? '',
                    'tiene_algun_familiar_que_este_o_haya_realizado_algun_proceso_con_nosotros_' => $input['tiene_hermanos'] == '1' ? 'true' : 'false',
                    'nombre_de_familiar_realizando_procesos' => $user->nombre_de_familiar_realizando_procesos ?? '',
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
            if ($request->routeIs('register')) {
                return redirect()->route(((int) $user->pay === 1 || (int) $user->pay === 3) ? 'clientes.getinfo' : 'clientes.pay');
            }

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

    private function registrationServices()
    {
        foreach (['sellable', 'active', 'all'] as $visibility) {
            $servicios = $this->registrationServiceQuery($visibility)->get();

            if ($servicios->isNotEmpty()) {
                return $this->organizeRegistrationServices($this->ensureFallbackNationalityServices($servicios));
            }
        }

        return $this->organizeRegistrationServices($this->fallbackRegistrationServices());
    }

    private function ensureFallbackNationalityServices($servicios)
    {
        $servicios = collect($servicios);

        return $this->fallbackRegistrationServices()
            ->map(function (Servicio $catalogService) use ($servicios) {
                $matchedService = $servicios->first(function ($servicio) use ($catalogService) {
                    return $this->serviceCatalogMatches($servicio, $catalogService);
                });

                return $matchedService
                    ? $this->mergeCatalogRegistrationMetadata($matchedService, $catalogService)
                    : $catalogService;
            })
            ->values();
    }

    private function serviceCatalogMatches($servicio, Servicio $catalogService): bool
    {
        $serviceKeys = $this->serviceSearchKeys($servicio);
        $catalogKeys = $this->serviceSearchKeys($catalogService);

        if ($serviceKeys->isEmpty() || $catalogKeys->isEmpty()) {
            return false;
        }

        return $serviceKeys->contains(function (string $serviceKey) use ($catalogKeys) {
            return $catalogKeys->contains(function (string $catalogKey) use ($serviceKey) {
                return $serviceKey === $catalogKey
                    || Str::startsWith($serviceKey, $catalogKey . ' -')
                    || Str::startsWith($serviceKey, $catalogKey . ' ');
            });
        });
    }

    private function mergeCatalogRegistrationMetadata(Servicio $servicio, Servicio $catalogService): Servicio
    {
        $metadata = array_merge(
            $this->metadataForService($servicio),
            $this->metadataForService($catalogService),
            ['catalog_match' => true]
        );

        $attributes = [
            'metadata' => $metadata,
        ];

        if (empty($servicio->categoria)) {
            $attributes['categoria'] = $catalogService->categoria;
        }

        if (empty($servicio->tipo)) {
            $attributes['tipo'] = $catalogService->tipo;
        }

        if (empty($servicio->moneda)) {
            $attributes['moneda'] = $catalogService->moneda;
        }

        if (empty($servicio->orden)) {
            $attributes['orden'] = $catalogService->orden;
        }

        if (empty($servicio->precio)) {
            $attributes['precio'] = $catalogService->precio;
        }

        $servicio->forceFill($attributes);

        return $servicio;
    }

    private function metadataForService($servicio): array
    {
        $metadata = $servicio->metadata ?? [];

        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        return is_array($metadata) ? $metadata : [];
    }

    private function serviceSearchKeys($servicio)
    {
        $metadata = $this->metadataForService($servicio);

        return collect([
            $servicio->id_hubspot ?? null,
            $servicio->nombre ?? null,
            $servicio->categoria ?? null,
            $metadata['public_title'] ?? null,
        ])
            ->merge($metadata['aliases'] ?? [])
            ->map(fn ($value) => $this->serviceLookupKey((string) $value))
            ->filter()
            ->unique()
            ->values();
    }

    private function organizeRegistrationServices($servicios)
    {
        return $servicios
            ->reject(function ($servicio) {
                $label = Str::lower(trim(($servicio->id_hubspot ?? '') . ' ' . ($servicio->nombre ?? '')));

                return Str::contains($label, [' - hermano', 'hermano']);
            })
            ->sortBy(function ($servicio) {
                return [
                    $this->isNationalityService($servicio) ? 0 : 1,
                    $servicio->orden ?? 999,
                    Str::lower($servicio->nombre ?? $servicio->id_hubspot ?? ''),
                ];
            })
            ->values();
    }

    private function isNationalityService($servicio): bool
    {
        $label = $this->serviceSearchKeys($servicio)->implode(' ');

        return Str::contains($label, [
            'nacionalidad',
            'espanol',
            'español',
            'espanola',
            'española',
            'portugues',
            'portugués',
            'portuguesa',
            'italian',
            'italiana',
            'lmd',
            'sefard',
            'subsanacion',
            'conyuge',
            'familiares',
        ]);
    }

    private function nationalityBucket($servicio): ?string
    {
        $label = $this->serviceSearchKeys($servicio)->implode(' ');

        if (Str::contains($label, ['italia', 'italian', 'italiana'])) {
            return 'italy';
        }

        if (Str::contains($label, ['portugal', 'portugues', 'portuguesa'])) {
            return 'portugal';
        }

        if (Str::contains($label, ['espana', 'espanol', 'espanola', 'lmd', 'memoria democratica', 'carta de naturaleza'])) {
            return 'spain';
        }

        return null;
    }

    private function findRegistrationService(string $idHubspot): ?Servicio
    {
        if ($this->serviciosHasColumn('id_hubspot')) {
            foreach (['sellable', 'active', 'all'] as $visibility) {
                $exact = $this->registrationServiceQuery($visibility)
                    ->where('id_hubspot', $idHubspot)
                    ->first();

                if ($exact) {
                    return $exact;
                }

                $prefix = $this->registrationServiceQuery($visibility)
                    ->where('id_hubspot', 'like', $idHubspot . '%')
                    ->first();

                if ($prefix) {
                    return $prefix;
                }
            }
        }

        return $this->findFallbackRegistrationService($idHubspot);
    }

    private function ensurePendingRegistrationPurchase(User $user)
    {
        $compras = $this->pendingRegistrationPurchasesQuery($user->id)->get();

        if ($compras->isNotEmpty()) {
            return $compras;
        }

        $servicio = $this->serviceForUser($user);

        if (! $servicio) {
            return collect();
        }

        [$description, $amount] = $this->purchaseDescriptionFor($user, $servicio);

        $payload = [
            'id_user' => $user->id,
            'servicio_hs_id' => $user->servicio,
            'descripcion' => $description,
            'pagado' => 0,
            'monto' => $amount,
        ];

        if ($this->comprasHasColumn('servicio_id') && $servicio->getKey()) {
            $payload['servicio_id'] = $servicio->id;
        }

        if ($this->comprasHasColumn('source')) {
            $payload['source'] = 'registro_checkout';
        }

        Compras::create($payload);

        return $this->pendingRegistrationPurchasesQuery($user->id)->get();
    }

    private function serviceForUser(User $user): ?Servicio
    {
        if ($this->serviciosHasColumn('id_hubspot')) {
            if ($user->tiene_hermanos == 1 || $user->tiene_hermanos == "1" || $user->tiene_hermanos == "Si") {
                $servicioHermano = $this->registrationServiceQuery('all')
                    ->where('id_hubspot', 'like', $user->servicio . '% - Hermano')
                    ->first();

                if ($servicioHermano) {
                    return $servicioHermano;
                }
            }

            $servicio = $this->registrationServiceQuery('all')
                ->where('id_hubspot', 'like', $user->servicio . '%')
                ->first();

            if ($servicio) {
                return $servicio;
            }
        }

        return $this->findFallbackRegistrationService((string) $user->servicio);
    }

    private function registrationServiceQuery(string $visibility = 'all')
    {
        $query = Servicio::query();

        if (in_array($visibility, ['sellable', 'active'], true) && $this->serviciosHasColumn('activo')) {
            $query->where('activo', true);
        }

        if ($visibility === 'sellable' && $this->serviciosHasColumn('visible_cliente')) {
            $query->where('visible_cliente', true);
        }

        if ($this->serviciosHasColumn('categoria')) {
            $query->where('categoria', '!=', config('banca_online.category', 'banca_online_2026'));
        }

        if ($this->serviciosHasColumn('id_hubspot')) {
            $query->whereNotNull('id_hubspot');
        }

        if ($this->serviciosHasColumn('orden')) {
            $query->orderBy('orden');
        }

        return $query->orderBy('nombre');
    }

    private function serviciosHasColumn(string $column): bool
    {
        static $columns = [];

        if (! array_key_exists($column, $columns)) {
            try {
                $columns[$column] = Schema::hasColumn('servicios', $column);
            } catch (\Throwable $e) {
                $columns[$column] = false;
            }
        }

        return $columns[$column];
    }

    private function comprasHasColumn(string $column): bool
    {
        static $columns = [];

        if (! array_key_exists($column, $columns)) {
            try {
                $columns[$column] = Schema::hasColumn('compras', $column);
            } catch (\Throwable $e) {
                $columns[$column] = false;
            }
        }

        return $columns[$column];
    }

    private function pendingRegistrationPurchasesQuery(int $userId)
    {
        $query = Compras::where('id_user', $userId)
            ->where('pagado', 0);

        if ($this->comprasHasColumn('deal_id')) {
            $query->whereNull('deal_id');
        }

        if ($this->comprasHasColumn('source')) {
            $query->where(function ($innerQuery) {
                $innerQuery->where('source', 'registro_checkout')
                    ->orWhereNull('source');
            });
        }

        return $query;
    }

    private function fallbackRegistrationServices()
    {
        return collect([
            $this->fallbackRegistrationService(
                'Italiana',
                'Nacionalidad italiana para descendientes de italianos',
                299,
                'nacionalidad_italiana',
                10,
                ['nacionalidad italiana', 'italiana', 'italian', 'descendientes de italianos'],
                [
                    'public_title' => 'Nacionalidad italiana para descendientes de italianos',
                    'pitch' => 'Si tienes antepasados italianos, analizamos tu linea por Ius Sanguinis y definimos la mejor ruta para tu pasaporte italiano.',
                    'best_for' => 'Ideal si tienes origen italiano directo o por generaciones anteriores.',
                    'proof' => 'Incluye preanalisis genealogico, estudio juridico-genealogico y asesoria Intuitu Personae.',
                    'landing_url' => 'https://sefaruniversal.com/landing-registro-nacionalidad-italiana/',
                ]
            ),
            $this->fallbackRegistrationService(
                'Espanola Sefardi',
                'Nacionalidad española por origen sefardí',
                299,
                'nacionalidad_espanola',
                20,
                ['espanola sefardi', 'española sefardi', 'origen sefardi', 'origen sefardí'],
                [
                    'public_title' => 'Nacionalidad española por origen sefardí',
                    'pitch' => 'Estudiamos tu origen sefardi a nivel genealogico, legal y migratorio para definir la mejor opcion hacia la nacionalidad española.',
                    'best_for' => 'Para quienes buscan acreditar ascendencia sefardi vinculada a España.',
                    'proof' => 'El equipo aplica el metodo Intuitu Personae para adaptar la estrategia a tu situacion.',
                    'landing_url' => 'https://sefaruniversal.com/landing-registro-nacionalidad-espanola-sefardi/',
                ]
            ),
            $this->fallbackRegistrationService(
                'Espanola Carta de Naturaleza',
                'Nacionalidad española por Carta de Naturaleza',
                299,
                'nacionalidad_espanola',
                30,
                ['carta de naturaleza', 'espanola carta naturaleza', 'española carta naturaleza', 'española carta de naturaleza'],
                [
                    'public_title' => 'Nacionalidad española por Carta de Naturaleza',
                    'pitch' => 'Evaluamos si tu vinculacion con España puede justificar circunstancias excepcionales para esta via.',
                    'best_for' => 'Para casos con origen sefardi, ascendencia española cercana o una conexion extraordinaria con España.',
                    'proof' => 'Se construye una base genealogica y juridica para sustentar la solicitud.',
                    'landing_url' => 'https://sefaruniversal.com/landing-registro-nacionalidad-espanola-carta-de-naturaleza/',
                ]
            ),
            $this->fallbackRegistrationService(
                'Subsanacion Espanola Sefardi',
                'Subsanación de expediente de nacionalidad española',
                299,
                'nacionalidad_espanola',
                40,
                ['subsanacion espanola', 'subsanación española', 'subsanacion expediente nacionalidad espanola', 'revision expediente espanol'],
                [
                    'public_title' => 'Subsanación de expediente de nacionalidad española',
                    'pitch' => 'Revisamos y fortalecemos tu expediente antes de una denegacion o para responder debilidades documentales.',
                    'best_for' => 'Para quienes ya iniciaron o tienen un expediente español que necesita revision.',
                    'proof' => 'El analisis busca subsanar el expediente y acreditar mejor tus origenes ante las autoridades.',
                    'landing_url' => 'https://sefaruniversal.com/landing-registro-subsanacion-de-la-nacionalidad-espanola-sefardi/',
                ]
            ),
            $this->fallbackRegistrationService(
                'Espanola Conyuge',
                'Nacionalidad Española por cónyuge',
                299,
                'nacionalidad_espanola',
                50,
                ['espanola conyuge', 'española conyuge', 'conyuge espanol', 'cónyuge español'],
                [
                    'public_title' => 'Nacionalidad Española por cónyuge',
                    'pitch' => 'Estudiamos si tu vinculo matrimonial abre una ruta viable hacia la nacionalidad española.',
                    'best_for' => 'Para personas casadas con ciudadano español o con una situacion familiar que debe evaluarse legalmente.',
                    'proof' => 'Nuestros especialistas revisan el caso legal y migratorio para indicar la mejor opcion.',
                    'landing_url' => 'https://sefaruniversal.com/landing-registro-nacionalidad-espanola-por-conyuge/',
                ]
            ),
            $this->fallbackRegistrationService(
                'Espanola Familiares',
                'Nacionalidad Española para familiares',
                299,
                'nacionalidad_espanola',
                60,
                ['espanola familiares', 'española familiares', 'familiares espanoles', 'familiares españoles'],
                [
                    'public_title' => 'Nacionalidad Española para familiares',
                    'pitch' => 'Orientamos a familiares que desean entender si pueden incorporarse a una ruta española ya existente o derivada.',
                    'best_for' => 'Para familiares de clientes o personas con vinculo familiar español que necesitan validar su opcion.',
                    'proof' => 'El equipo contrasta datos familiares, documentos y viabilidad legal antes de avanzar.',
                    'landing_url' => 'https://sefaruniversal.com/landing-registro-nacionalidad-espanola-para-familiares/',
                ]
            ),
            $this->fallbackRegistrationService(
                'Portuguesa Sefardi',
                'Nacionalidad portuguesa por origen sefardí',
                299,
                'nacionalidad_portuguesa',
                70,
                ['portuguesa sefardi', 'portuguesa sefardí', 'origen sefardi portugal'],
                [
                    'public_title' => 'Nacionalidad portuguesa por origen sefardí',
                    'pitch' => 'Estudiamos tu posible descendencia sefardi de la Peninsula Iberica y la ruta ante autoridades portuguesas.',
                    'best_for' => 'Para quienes tienen indicios de origen sefardi y quieren evaluar Portugal.',
                    'proof' => 'Se analiza tu caso a nivel genealogico, legal y migratorio con Intuitu Personae.',
                    'landing_url' => 'https://sefaruniversal.com/landing-registro-nacionalidad-portuguesa-sefardi/',
                ]
            ),
            $this->fallbackRegistrationService(
                'Subsanacion Portuguesa Sefardi',
                'Subsanación de expediente de nacionalidad portuguesa',
                299,
                'nacionalidad_portuguesa',
                80,
                ['subsanacion portuguesa', 'subsanación portuguesa', 'subsanacion expediente nacionalidad portuguesa'],
                [
                    'public_title' => 'Subsanación de expediente de nacionalidad portuguesa',
                    'pitch' => 'Revisamos tu expediente portugues para corregir debilidades antes de una denegacion.',
                    'best_for' => 'Para expedientes portugueses iniciados, observados o con riesgo documental.',
                    'proof' => 'El objetivo es subsanar y acreditar mejor tus origenes ante las autoridades portuguesas.',
                    'landing_url' => 'https://sefaruniversal.com/landing-registro-subsanacion-de-la-nacionalidad-portuguesa-sefardi/',
                ]
            ),
            $this->fallbackRegistrationService(
                'Formalizacion Anticipada Portuguesa Sefardi',
                'Formalización Anticipada de Nacionalidad Portuguesa por Origen Sefardí',
                299,
                'nacionalidad_portuguesa',
                90,
                ['formalizacion anticipada portuguesa', 'formalización anticipada portuguesa', 'formalizacion anticipada'],
                [
                    'public_title' => 'Formalización Anticipada de Nacionalidad Portuguesa por Origen Sefardí',
                    'pitch' => 'Permite formalizar tu solicitud antes de cambios normativos que podrian exigir residencia en Portugal.',
                    'best_for' => 'Para quienes quieren asegurar su oportunidad cuanto antes por la via portuguesa sefardi.',
                    'proof' => 'Se formaliza con datos esenciales y luego se realiza el preanalisis genealogico para continuar con mayor seguridad.',
                    'landing_url' => 'https://sefaruniversal.com/formalizacion-anticipada-de-nacionalidad-portuguesa-por-origen-sefardi/',
                ]
            ),
            $this->fallbackRegistrationService(
                'Portuguesa Conyuge',
                'Nacionalidad Portuguesa por cónyuge',
                299,
                'nacionalidad_portuguesa',
                100,
                ['portuguesa conyuge', 'portuguesa cónyuge', 'conyuge portugues', 'cónyuge portugués'],
                [
                    'public_title' => 'Nacionalidad Portuguesa por cónyuge',
                    'pitch' => 'Si tu conyuge tiene nacionalidad portuguesa, evaluamos si tambien puedes solicitarla.',
                    'best_for' => 'Para personas casadas o en union reconocible con ciudadano portugues.',
                    'proof' => 'El estudio revisa tu situacion legal y migratoria para indicarte la mejor opcion.',
                    'landing_url' => 'https://sefaruniversal.com/landing-registro-nacionalidad-portuguesa-por-conyuge/',
                ]
            ),
            $this->fallbackRegistrationService(
                'Portuguesa Familiares',
                'Nacionalidad Portuguesa para familiares',
                299,
                'nacionalidad_portuguesa',
                110,
                ['portuguesa familiares', 'familiares portugueses'],
                [
                    'public_title' => 'Nacionalidad Portuguesa para familiares',
                    'pitch' => 'Validamos si tu vinculo familiar permite incorporarte o abrir una ruta portuguesa viable.',
                    'best_for' => 'Para familiares que necesitan entender su opcion portuguesa y documentos necesarios.',
                    'proof' => 'El equipo revisa relacion familiar, documentos y viabilidad legal antes de avanzar.',
                    'landing_url' => 'https://sefaruniversal.com/landing-registro-nacionalidad-portuguesa-para-familiares/',
                ]
            ),
        ]);
    }

    private function fallbackRegistrationService(
        string $idHubspot,
        string $nombre,
        int $precio,
        string $categoria,
        int $orden,
        array $aliases = [],
        array $metadata = []
    ): Servicio {
        $servicio = new Servicio();
        $servicio->forceFill([
            'id_hubspot' => $idHubspot,
            'nombre' => $nombre,
            'precio' => $precio,
            'categoria' => $categoria,
            'tipo' => 'registro',
            'activo' => true,
            'visible_cliente' => true,
            'moneda' => 'EUR',
            'orden' => $orden,
            'metadata' => array_merge([
                'fallback' => true,
                'aliases' => $aliases,
                'source' => 'nacionalidades',
            ], $metadata),
        ]);

        return $servicio;
    }

    private function findFallbackRegistrationService(string $idHubspot): ?Servicio
    {
        $requestedKey = $this->serviceLookupKey($idHubspot);

        if ($requestedKey === '') {
            return null;
        }

        return $this->fallbackRegistrationServices()->first(function (Servicio $servicio) use ($requestedKey) {
            $keys = collect([
                $servicio->id_hubspot,
                $servicio->nombre,
            ])->merge($servicio->metadata['aliases'] ?? [])
                ->map(fn ($value) => $this->serviceLookupKey((string) $value))
                ->filter();

            return $keys->contains(function (string $key) use ($requestedKey) {
                return $key === $requestedKey
                    || Str::startsWith($key, $requestedKey)
                    || Str::startsWith($requestedKey, $key);
            });
        });
    }

    private function serviceLookupKey(string $value): string
    {
        $value = strtr($value, [
            'Ã' => 'A', 'Ã‰' => 'E', 'Ã' => 'I', 'Ã“' => 'O', 'Ãš' => 'U', 'Ãœ' => 'U', 'Ã‘' => 'N',
            'Ã¡' => 'a', 'Ã©' => 'e', 'Ã­' => 'i', 'Ã³' => 'o', 'Ãº' => 'u', 'Ã¼' => 'u', 'Ã±' => 'n',
            'ÃƒÂ¡' => 'a', 'ÃƒÂ©' => 'e', 'ÃƒÂ­' => 'i', 'ÃƒÂ³' => 'o', 'ÃƒÂº' => 'u', 'ÃƒÂ±' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        $value = Str::ascii($value);

        return trim(preg_replace('/\s+/', ' ', Str::lower($value)));
    }

    private function serviceMatches(string $actual, string $expected): bool
    {
        return $this->serviceLookupKey($actual) === $this->serviceLookupKey($expected);
    }

    private function serviceMatchesAny(string $actual, array $expected): bool
    {
        foreach ($expected as $serviceName) {
            if ($this->serviceMatches($actual, $serviceName)) {
                return true;
            }
        }

        return false;
    }

    private function purchaseDescriptionFor(User $user, Servicio $servicio): array
    {
        $amount = (float) $servicio->precio;

        if ($this->serviceMatches((string) $user->servicio, 'Recurso de Alzada')) {
            $amount = $amount * max(1, (int) $user->cantidad_alzada);
        }

        if ($this->isNationalityService($servicio)) {
            $description = "Pago Fase Inicial: Investigacion Preliminar y Preparatoria: " . $servicio->nombre;

            if ($this->serviceMatchesAny((string) $user->servicio, [
                'Espanola LMD',
                'Espanola Sefardi',
            ]) && (int) $user->antepasados === 0) {
                $amount = 299;
            }

            if ($this->serviceMatches((string) $user->servicio, 'Italiana') && (int) $user->antepasados === 1) {
                $description .= " + (Consulta Gratuita)";
            }
        } elseif ($this->serviceMatches((string) $user->servicio, 'Gestion Documental')) {
            $description = $servicio->nombre;
        } elseif ((int) ($servicio->tipov ?? 0) === 1) {
            $description = "Servicios para Vinculaciones: " . $servicio->nombre;
        } else {
            $description = "Analisis genealogico: " . $servicio->nombre;
        }

        return [$description, $amount];
    }

    private function checkoutSummaryPayload($compras): array
    {
        $items = collect($compras)->map(function ($compra) {
            return [
                'id' => $compra->id,
                'description' => $compra->descripcion,
                'amount' => (float) $compra->monto,
                'amount_label' => number_format((float) $compra->monto, 2, ',', '.') . ' EUR',
                'coupon_applied' => (bool) $compra->cuponaplicado,
                'discount_label' => $compra->porcentajedescuento,
            ];
        })->values();

        $total = $items->sum('amount');

        return [
            'items' => $items,
            'total' => $total,
            'total_label' => number_format((float) $total, 2, ',', '.') . ' EUR',
            'has_items' => $items->isNotEmpty(),
        ];
    }
}
