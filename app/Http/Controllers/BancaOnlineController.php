<?php

namespace App\Http\Controllers;

use App\Mail\ClaveGeneradaMail;
use App\Mail\RegistroSefar;
use App\Models\Agcliente;
use App\Models\Compras;
use App\Models\Factura;
use App\Models\Servicio;
use App\Models\User;
use App\Services\BancaOnlineCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BancaOnlineController extends Controller
{
    public function __construct(private BancaOnlineCatalog $catalog)
    {
    }

    public function landing(Request $request)
    {
        $countrySlug = $this->catalog->normalizeCountry($request->query('servicio', $request->query('pais')));

        if (! $this->catalog->isCountryPublic($countrySlug)) {
            $countrySlug = array_key_first($this->catalog->publicCountries()) ?? 'espana';
        }

        return redirect()->route('banca-online.country', $countrySlug);
    }

    public function landingForCountry(string $country)
    {
        $countrySlug = $this->catalog->normalizeCountry($country);
        abort_unless($this->catalog->isCountryPublic($countrySlug), 404);

        $country = $this->catalog->country($countrySlug);
        $plans = $this->catalog->plansForCountry($countrySlug);
        $countries = $this->catalog->publicCountries();

        return view('banca-online.index', compact('plans', 'countries', 'countrySlug', 'country'));
    }

    public function configure(Request $request, string $plan)
    {
        $countrySlug = $this->catalog->normalizeCountry($request->query('servicio', $request->query('pais')));

        return $this->configurationView($request, $countrySlug, $plan);
    }

    public function configureForCountry(Request $request, string $country, string $plan)
    {
        return $this->configurationView($request, $this->catalog->normalizeCountry($country), $plan);
    }

    public function lookupClient(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:175'],
        ]);

        $user = $this->findUserByEmail($data['email']);

        return response()->json([
            'exists' => (bool) $user,
        ]);
    }

    public function checkout(Request $request, string $plan)
    {
        $countrySlug = $this->catalog->normalizeCountry($request->input('country', $request->query('servicio')));

        return $this->storeCheckout($request, $countrySlug, $plan);
    }

    public function checkoutForCountry(Request $request, string $country, string $plan)
    {
        return $this->storeCheckout($request, $this->catalog->normalizeCountry($country), $plan);
    }

    public function payment(string $token)
    {
        $compras = $this->purchasesForToken($token);

        abort_if($compras->isEmpty(), 404);

        $user = $compras->first()->user;
        $metadata = $compras->first()->metadata ?? [];
        $countrySlug = $metadata['country_slug'] ?? 'espana';
        $stripeKey = $this->stripePublicKey($countrySlug);
        $total = (float) $compras->sum('monto');

        return view('banca-online.payment', compact('token', 'compras', 'user', 'metadata', 'countrySlug', 'stripeKey', 'total'));
    }

    public function processPayment(Request $request, string $token)
    {
        $data = $request->validate([
            'payment_method_id' => ['required', 'string'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:175'],
            'phone' => ['nullable', 'string', 'max:80'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:40'],
            'country' => ['required', 'string', 'size:2'],
        ]);

        $compras = $this->purchasesForToken($token);

        if ($compras->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No hay compras pendientes para este checkout.',
            ], 404);
        }

        $total = (float) $compras->sum('monto');

        if ($total <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'El total debe ser mayor a 0. Administracion debe configurar los precios antes de cobrar.',
            ], 422);
        }

        $user = $compras->first()->user;
        $metadata = $compras->first()->metadata ?? [];
        $countrySlug = $metadata['country_slug'] ?? 'espana';
        $secret = $this->stripeSecretKey($countrySlug);

        if (! $secret) {
            return response()->json([
                'success' => false,
                'message' => 'No esta configurada la clave secreta de Stripe para este servicio.',
            ], 500);
        }

        try {
            \Stripe\Stripe::setApiKey($secret);

            $customer = $this->stripeCustomer($user, $data);
            $paymentMethod = \Stripe\PaymentMethod::retrieve($data['payment_method_id']);
            $paymentMethod->attach(['customer' => $customer->id]);

            \Stripe\Customer::update($customer->id, [
                'invoice_settings' => [
                    'default_payment_method' => $data['payment_method_id'],
                ],
            ]);

            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => (int) round($total * 100),
                'currency' => 'eur',
                'customer' => $customer->id,
                'payment_method' => $data['payment_method_id'],
                'off_session' => false,
                'confirm' => true,
                'description' => 'Sefar Universal: Banca Online 2026',
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never',
                ],
                'metadata' => [
                    'checkout_token' => $token,
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'plan' => $metadata['plan_slug'] ?? null,
                    'country' => $countrySlug,
                ],
            ]);
        } catch (\Stripe\Exception\CardException $e) {
            return response()->json([
                'success' => false,
                'message' => $this->stripeErrorMessage($e->getError()->code),
            ], 400);
        } catch (\Stripe\Exception\RateLimitException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Se realizaron varios intentos sin exito. Por favor, comunicarse con el emisor de su tarjeta.',
            ], 429);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Log::error('Banca Online Stripe InvalidRequestException: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Stripe rechazo la solicitud: ' . $e->getMessage(),
            ], 400);
        } catch (\Stripe\Exception\AuthenticationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de autenticacion con Stripe. Por favor, comunicar este error a Sistemas.',
            ], 401);
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error conectandose a la pasarela de pago. Por favor, intente mas tarde.',
            ], 500);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return response()->json([
                'success' => false,
                'message' => 'La pasarela de pago esta en mantenimiento. Por favor, intente mas tarde.',
            ], 503);
        } catch (\Throwable $e) {
            Log::error('Banca Online payment error: ' . $e->getMessage(), ['token' => $token]);

            return response()->json([
                'success' => false,
                'message' => 'Ha ocurrido un error desconocido al realizar el pago.',
            ], 500);
        }

        if (! $paymentIntent || $paymentIntent->status !== 'succeeded') {
            return response()->json([
                'success' => false,
                'message' => 'El pago no pudo ser completado. Estado: ' . ($paymentIntent->status ?? 'desconocido'),
            ], 400);
        }

        DB::transaction(function () use ($compras, $user, $customer, $paymentIntent, $total) {
            $hashFactura = 'sef_' . Str::random(50);

            Factura::create([
                'id_cliente' => $user->id,
                'hash_factura' => $hashFactura,
                'met' => 'stripe',
                'idcus' => $customer->id,
                'idcharge' => $paymentIntent->id,
            ]);

            Compras::whereIn('id', $compras->pluck('id'))->update([
                'pagado' => 1,
                'hash_factura' => $hashFactura,
                'paid_at' => now(),
            ]);

            $user->forceFill([
                'pay' => 1,
                'pago_registro' => $total,
                'pago_registro_hist' => $this->appendJsonValue($user->pago_registro_hist, $total),
                'id_pago' => $this->appendJsonValue($user->id_pago, $paymentIntent->id),
                'pago_cupon' => $this->appendJsonValue($user->pago_cupon, ''),
                'stripe_cus_id' => $customer->id,
                'contrato' => 0,
            ])->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Pago procesado exitosamente.',
            'redirect_url' => route('banca-online.thank-you', $token),
            'thank_you' => [
                'title' => 'Pago recibido',
                'name' => trim($data['first_name']),
                'total' => $total,
                'total_label' => number_format($total, 2, ',', '.'),
                'currency' => 'EUR',
                'items' => $compras->map(fn (Compras $compra) => [
                    'name' => $compra->servicio?->nombre ?? $compra->descripcion,
                ])->values(),
            ],
        ]);
    }

    public function thankYou(string $token)
    {
        $compras = $this->purchasesForToken($token, false)->where('pagado', 1);

        abort_if($compras->isEmpty(), 404);

        $user = $compras->first()->user;
        $total = (float) $compras->sum('monto');

        return view('banca-online.thank-you', compact('compras', 'user', 'total'));
    }

    private function configurationView(Request $request, string $countrySlug, string $planSlug)
    {
        abort_unless($this->catalog->isCountryPublic($countrySlug), 404);

        $plan = $this->catalog->planForCountry($countrySlug, $planSlug);
        abort_unless($plan, 404);

        $countries = $this->catalog->publicCountries();
        $country = $this->catalog->country($countrySlug);
        $groupedServices = $this->catalog->groupedServicesForPlan($countrySlug, $planSlug);
        $totalDefault = $this->catalog->checkoutTotal(
            $groupedServices->flatten(1)->filter(function (Servicio $servicio) {
                $metadata = $this->catalog->metadata($servicio);

                return (bool) ($metadata['required'] ?? false) || (bool) ($metadata['default_selected'] ?? false);
            })
        );

        return view('banca-online.configurator', compact('planSlug', 'plan', 'countries', 'countrySlug', 'country', 'groupedServices', 'totalDefault'));
    }

    private function storeCheckout(Request $request, string $countrySlug, string $planSlug)
    {
        abort_unless($this->catalog->isCountryPublic($countrySlug), 404);

        $plan = $this->catalog->planForCountry($countrySlug, $planSlug);
        abort_unless($plan, 404);

        $baseData = $request->validate([
            'email' => ['required', 'email', 'max:175'],
            'selected_items' => ['nullable', 'array'],
            'selected_items.*' => ['integer'],
        ]);

        $selectedServices = $this->catalog->selectedServices($countrySlug, $planSlug, $baseData['selected_items'] ?? []);
        $total = $this->catalog->checkoutTotal($selectedServices);

        if ($selectedServices->isEmpty()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selecciona al menos un servicio del plan.',
                    'errors' => ['selected_items' => ['Selecciona al menos un servicio del plan.']],
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['selected_items' => 'Selecciona al menos un servicio del plan.']);
        }

        if ($total <= 0) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El total del plan esta en 0 EUR. Administracion debe cargar los precios antes de cobrar.',
                    'errors' => ['selected_items' => ['El total del plan esta en 0 EUR. Administracion debe cargar los precios antes de cobrar.']],
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['selected_items' => 'El total del plan esta en 0 EUR. Administracion debe cargar los precios antes de cobrar.']);
        }

        $email = Str::lower(trim($baseData['email']));
        $user = $this->findUserByEmail($email);
        $generatedPassword = null;
        $newUserData = [];

        if (! $user) {
            $newUserData = $request->validate([
                'nombres' => ['required', 'string', 'max:255'],
                'apellidos' => ['required', 'string', 'max:255'],
                'phone' => ['required', 'string', 'max:255'],
                'passport' => ['required', 'string', 'min:5', 'max:170', 'unique:users,passport'],
                'pais_de_nacimiento' => ['required', 'string', 'max:255'],
                'referido' => ['required', 'string', 'max:255'],
                'tiene_hermanos' => ['required', 'in:0,1'],
                'nombre_de_familiar_realizando_procesos' => ['exclude_unless:tiene_hermanos,1', 'required', 'string', 'max:255'],
                'acepta_comunicaciones' => ['accepted'],
                'acepta_datos' => ['accepted'],
            ]);
        }

        $token = Str::random(64);
        $serviceName = $this->catalog->serviceNameForCountry($countrySlug);

        DB::transaction(function () use (&$user, &$generatedPassword, $newUserData, $email, $serviceName, $selectedServices, $planSlug, $plan, $countrySlug, $token) {
            if (! $user) {
                $generatedPassword = Str::random(10);
                $user = $this->createCheckoutUser($newUserData, $email, $serviceName, $generatedPassword);
            } elseif (! $user->servicio) {
                $user->forceFill(['servicio' => $serviceName])->save();
            }

            foreach ($selectedServices as $servicio) {
                $metadata = $this->catalog->metadata($servicio);

                Compras::create([
                    'id_user' => $user->id,
                    'servicio_id' => $servicio->id,
                    'source' => $this->catalog->source(),
                    'servicio_hs_id' => $servicio->id_hubspot,
                    'descripcion' => 'Banca Online 2026 - ' . ($plan['title'] ?? $planSlug) . ': ' . $servicio->nombre,
                    'pagado' => 0,
                    'monto' => (float) $servicio->precio,
                    'metadata' => array_merge($metadata, [
                        'checkout_token' => $token,
                        'country_slug' => $countrySlug,
                        'requested_service' => $serviceName,
                    ]),
                ]);
            }
        });

        if ($generatedPassword) {
            $this->notifyNewUser($user, $generatedPassword);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'checkout' => $this->checkoutPayload($token, $selectedServices, $user, $plan, $countrySlug, $serviceName),
            ]);
        }

        return redirect()->route('banca-online.payment', $token);
    }

    private function checkoutPayload(string $token, Collection $services, User $user, array $plan, string $countrySlug, string $serviceName): array
    {
        return [
            'token' => $token,
            'payment_url' => route('banca-online.payment', $token),
            'process_url' => route('banca-online.payment.process', $token),
            'thank_you_url' => route('banca-online.thank-you', $token),
            'stripe_key' => $this->stripePublicKey($countrySlug),
            'country_slug' => $countrySlug,
            'requested_service' => $serviceName,
            'plan_title' => $plan['title'] ?? 'Plan estrategico',
            'currency' => 'EUR',
            'total' => (float) $services->sum('precio'),
            'total_label' => number_format((float) $services->sum('precio'), 2, ',', '.'),
            'items' => $services->map(fn (Servicio $servicio) => [
                'name' => $servicio->nombre,
            ])->values(),
            'billing' => [
                'email' => $user->email,
            ],
        ];
    }

    private function createCheckoutUser(array $data, string $email, string $serviceName, string $password): User
    {
        $passport = trim($data['passport']);

        if (! Agcliente::where('IDCliente', $passport)->where('IDPersona', 1)->exists()) {
            Agcliente::create([
                'IDCliente' => $passport,
                'IDPersona' => 1,
                'Nombres' => trim($data['nombres']),
                'Apellidos' => trim($data['apellidos']),
                'NPasaporte' => $passport,
                'PNacimiento' => trim($data['pais_de_nacimiento']),
                'PaisNac' => trim($data['pais_de_nacimiento']),
                'referido' => trim($data['referido']),
                'FRegistro' => now(),
                'FUpdate' => now(),
                'Usuario' => $email,
            ]);
        }

        $user = User::create([
            'name' => trim($data['nombres'] . ' ' . $data['apellidos']),
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'nombres' => trim($data['nombres']),
            'apellidos' => trim($data['apellidos']),
            'passport' => $passport,
            'phone' => trim($data['phone']),
            'pais_de_nacimiento' => trim($data['pais_de_nacimiento']),
            'servicio' => $serviceName,
            'pay' => 0,
            'referido_por' => $data['referido'],
            'tiene_hermanos' => (int) $data['tiene_hermanos'],
            'nombre_de_familiar_realizando_procesos' => $data['nombre_de_familiar_realizando_procesos'] ?? null,
            'contrato' => 0,
            'cosready' => 1,
        ]);

        try {
            $user->assignRole('Cliente')->givePermissionTo(['pay.services', 'finish.register']);
        } catch (\Throwable $e) {
            Log::warning('No se pudieron asignar permisos al usuario de Banca Online.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $user;
    }

    private function findUserByEmail(string $email): ?User
    {
        return User::whereRaw('LOWER(email) = ?', [Str::lower(trim($email))])->first();
    }

    private function purchasesForToken(string $token, bool $pendingOnly = true): Collection
    {
        $query = Compras::with('user')
            ->where('source', $this->catalog->source());

        if ($pendingOnly) {
            $query->where('pagado', 0);
        }

        try {
            return (clone $query)->where('metadata->checkout_token', $token)->get();
        } catch (\Throwable $e) {
            Log::warning('Falling back to PHP checkout token filtering.', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            return $query->where('created_at', '>=', now()->subDays(14))
                ->get()
                ->filter(fn (Compras $compra) => ($compra->metadata['checkout_token'] ?? null) === $token)
                ->values();
        }
    }

    private function stripeCustomer(User $user, array $data)
    {
        $payload = [
            'name' => trim($data['first_name'] . ' ' . $data['last_name']),
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'address' => [
                'line1' => $data['address_line1'],
                'line2' => $data['address_line2'] ?? null,
                'city' => $data['city'],
                'state' => $data['state'] ?? null,
                'postal_code' => $data['postal_code'],
                'country' => strtoupper($data['country']),
            ],
        ];

        if ($user->stripe_cus_id) {
            return \Stripe\Customer::update($user->stripe_cus_id, $payload);
        }

        $customer = \Stripe\Customer::create($payload);

        $user->forceFill(['stripe_cus_id' => $customer->id])->save();

        return $customer;
    }

    private function stripePublicKey(string $countrySlug): ?string
    {
        if ($this->catalog->stripeAccountForCountry($countrySlug) === 'portugal') {
            return env('STRIPE_KEY_PORT') ?: env('STRIPE_KEY');
        }

        return env('STRIPE_KEY');
    }

    private function stripeSecretKey(string $countrySlug): ?string
    {
        if ($this->catalog->stripeAccountForCountry($countrySlug) === 'portugal') {
            return env('STRIPE_SECRET_PORT') ?: env('STRIPE_SECRET');
        }

        return env('STRIPE_SECRET');
    }

    private function appendJsonValue($current, $value): string
    {
        $items = [];

        if ($current) {
            $decoded = json_decode($current, true);
            $items = is_array($decoded) ? $decoded : [$current];
        }

        $items[] = $value;

        return json_encode($items);
    }

    private function notifyNewUser(User $user, string $password): void
    {
        try {
            Mail::to($user->email)->send(new ClaveGeneradaMail($user, $password));
            Mail::to($this->teamRecipients())->send(new RegistroSefar($user));
        } catch (\Throwable $e) {
            Log::warning('No se pudieron enviar correos de registro de Banca Online.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function teamRecipients(): array
    {
        return [
            'pedro.bazo@sefarvzla.com',
            'sistemasccs@sefarvzla.com',
            'automatizacion@sefarvzla.com',
            'sistemascol@sefarvzla.com',
            'asistentedeproduccion@sefarvzla.com',
            'organizacionrrhh@sefarvzla.com',
            'operacionesc@sefarvzla.com',
            '20053496@bcc.hubspot.com',
        ];
    }

    private function stripeErrorMessage(?string $code): string
    {
        return match ($code) {
            'incorrect_number' => 'El numero de tarjeta es incorrecto.',
            'invalid_number' => 'El numero de tarjeta no es valido.',
            'invalid_expiry_month' => 'El mes de vencimiento no es valido.',
            'invalid_expiry_year' => 'El año de vencimiento no es valido.',
            'invalid_cvc' => 'El codigo CVC no es valido.',
            'expired_card' => 'La tarjeta esta vencida.',
            'incorrect_cvc' => 'El codigo CVC es incorrecto.',
            'card_declined' => 'La tarjeta fue rechazada por el banco.',
            'processing_error' => 'Ocurrio un error procesando la tarjeta.',
            default => 'No se pudo procesar la tarjeta. Verifica los datos e intenta nuevamente.',
        };
    }
}
