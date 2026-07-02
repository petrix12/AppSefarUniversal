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
use Illuminate\Validation\Rule;
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
            'country' => ['nullable', 'string', 'max:80'],
            'plan' => ['nullable', 'string', 'max:120'],
        ]);

        $user = $this->findUserByEmail($data['email']);
        $countrySlug = $this->catalog->normalizeCountry($data['country'] ?? $request->query('servicio'));
        $planSlug = trim((string) ($data['plan'] ?? ''));
        $hasPaidSimilarPlan = $user && $this->hasPaidSimilarPlan($user, $countrySlug, $planSlug);

        return response()->json([
            'exists' => (bool) $user,
            'has_paid_similar_plan' => $hasPaidSimilarPlan,
            'message' => $hasPaidSimilarPlan ? $this->paidSimilarPlanMessage() : null,
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
        $package = $compras->first()->servicio;
        $paymentOptions = $package ? $this->checkoutPaymentOptions($package) : [];
        $total = (float) ($metadata['package_total'] ?? $compras->sum('monto'));

        return view('banca-online.payment', compact('token', 'compras', 'user', 'metadata', 'countrySlug', 'stripeKey', 'total', 'paymentOptions'));
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
            'payment_mode' => ['nullable', Rule::in(['full', 'installments'])],
            'payment_period' => ['nullable', 'string', 'max:40'],
            'initial_percent' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'installments_count' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $compras = $this->purchasesForToken($token);

        if ($compras->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No hay compras pendientes para este checkout.',
            ], 404);
        }

        $package = $compras->first()->servicio;

        if (! $package) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontro la modalidad asociada a este checkout.',
            ], 422);
        }

        $paymentPlan = $this->paymentPlanForPackage(
            $package,
            (string) ($data['payment_mode'] ?? 'full'),
            $data['payment_period'] ?? null,
            isset($data['initial_percent']) ? (float) $data['initial_percent'] : null,
            isset($data['installments_count']) ? (int) $data['installments_count'] : null
        );

        if (! $paymentPlan['valid']) {
            return response()->json([
                'success' => false,
                'message' => $paymentPlan['message'],
            ], 422);
        }

        $total = (float) $paymentPlan['amount_due_now'];

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
        $metadata['payment_plan'] = $paymentPlan;
        $isInstallmentPayment = ($paymentPlan['mode'] ?? 'full') === 'installments';
        $stripeInstallmentSchedule = null;

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

            if ($isInstallmentPayment) {
                $stripeInstallmentSchedule = $this->createStripeInstallmentSchedule($user, $metadata, $customer, $data['payment_method_id']);

                if (empty($stripeInstallmentSchedule['schedule_id'])) {
                    throw new \RuntimeException('No se pudo crear el calendario de cuotas en Stripe.');
                }
            }

            $paymentIntentPayload = [
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
                    'payment_mode' => $paymentPlan['mode'] ?? 'full',
                ],
            ];

            if ($isInstallmentPayment) {
                $paymentIntentPayload['setup_future_usage'] = 'off_session';
                $paymentIntentPayload['metadata']['installments_count'] = (string) ($paymentPlan['installments_count'] ?? '');
                $paymentIntentPayload['metadata']['payment_period'] = (string) ($paymentPlan['period'] ?? '');
                $paymentIntentPayload['metadata']['stripe_schedule_id'] = $stripeInstallmentSchedule['schedule_id'] ?? '';
            }

            $paymentIntent = \Stripe\PaymentIntent::create($paymentIntentPayload);
        } catch (\Stripe\Exception\CardException $e) {
            $this->cancelStripeInstallmentSchedule($stripeInstallmentSchedule['schedule_id'] ?? null);

            return response()->json([
                'success' => false,
                'message' => $this->stripeErrorMessage($e->getError()->code),
            ], 400);
        } catch (\Stripe\Exception\RateLimitException $e) {
            $this->cancelStripeInstallmentSchedule($stripeInstallmentSchedule['schedule_id'] ?? null);

            return response()->json([
                'success' => false,
                'message' => 'Se realizaron varios intentos sin exito. Por favor, comunicarse con el emisor de su tarjeta.',
            ], 429);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            $this->cancelStripeInstallmentSchedule($stripeInstallmentSchedule['schedule_id'] ?? null);
            Log::error('Banca Online Stripe InvalidRequestException: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Stripe rechazo la solicitud: ' . $e->getMessage(),
            ], 400);
        } catch (\Stripe\Exception\AuthenticationException $e) {
            $this->cancelStripeInstallmentSchedule($stripeInstallmentSchedule['schedule_id'] ?? null);

            return response()->json([
                'success' => false,
                'message' => 'Error de autenticacion con Stripe. Por favor, comunicar este error a Sistemas.',
            ], 401);
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            $this->cancelStripeInstallmentSchedule($stripeInstallmentSchedule['schedule_id'] ?? null);

            return response()->json([
                'success' => false,
                'message' => 'Error conectandose a la pasarela de pago. Por favor, intente mas tarde.',
            ], 500);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->cancelStripeInstallmentSchedule($stripeInstallmentSchedule['schedule_id'] ?? null);

            return response()->json([
                'success' => false,
                'message' => 'La pasarela de pago esta en mantenimiento. Por favor, intente mas tarde.',
            ], 503);
        } catch (\Throwable $e) {
            $this->cancelStripeInstallmentSchedule($stripeInstallmentSchedule['schedule_id'] ?? null);
            Log::error('Banca Online payment error: ' . $e->getMessage(), ['token' => $token]);

            return response()->json([
                'success' => false,
                'message' => 'Ha ocurrido un error desconocido al realizar el pago.',
            ], 500);
        }

        if (! $paymentIntent || $paymentIntent->status !== 'succeeded') {
            $this->cancelStripeInstallmentSchedule($stripeInstallmentSchedule['schedule_id'] ?? null);

            return response()->json([
                'success' => false,
                'message' => 'El pago no pudo ser completado. Estado: ' . ($paymentIntent->status ?? 'desconocido'),
            ], 400);
        }

        DB::transaction(function () use ($compras, $user, $customer, $paymentIntent, $total, $stripeInstallmentSchedule, $paymentPlan) {
            $hashFactura = 'sef_' . Str::random(50);
            $paidAt = now();

            Factura::create([
                'id_cliente' => $user->id,
                'hash_factura' => $hashFactura,
                'met' => 'stripe',
                'idcus' => $customer->id,
                'idcharge' => $paymentIntent->id,
            ]);

            $compras->each(function (Compras $compra) use ($hashFactura, $paidAt, $customer, $paymentIntent, $stripeInstallmentSchedule, $paymentPlan, $total) {
                $metadata = $compra->metadata ?? [];
                $metadata['stripe_customer_id'] = $customer->id;
                $metadata['stripe_payment_intent_id'] = $paymentIntent->id;

                if ($stripeInstallmentSchedule) {
                    $metadata['stripe_installment_schedule'] = $stripeInstallmentSchedule;
                }

                $metadata['payment_plan'] = $paymentPlan;

                $compra->fill([
                    'pagado' => 1,
                    'monto' => $total,
                    'hash_factura' => $hashFactura,
                    'paid_at' => $paidAt,
                    'metadata' => $metadata,
                ])->save();
            });

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

        $paidItems = collect($metadata['components'] ?? [])->map(function (array $component) {
            $item = [
                'name' => $component['name'] ?? 'Servicio incluido',
                'description' => $component['description'] ?? null,
            ];

            if (array_key_exists('price', $component)) {
                $item['price'] = (float) $component['price'];
            }

            return $item;
        })->values();

        if ($paidItems->isEmpty()) {
            $paidItems = $compras->map(fn (Compras $compra) => [
                'name' => $compra->servicio?->nombre ?? $compra->descripcion,
            ])->values();
        }

        return response()->json([
            'success' => true,
            'message' => 'Pago procesado exitosamente.',
            'redirect_url' => route('banca-online.thank-you', $token),
            'thank_you' => [
                'title' => 'Pago recibido',
                'name' => trim($data['first_name']),
                'total' => $total,
                'total_label' => number_format($total, 0, ',', '.'),
                'currency' => 'EUR',
                'payment_plan' => $paymentPlan,
                'items' => $paidItems,
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
        $packages = $this->catalog->packagesForPlan($countrySlug, $planSlug, false);

        return view('banca-online.configurator', compact('planSlug', 'plan', 'countries', 'countrySlug', 'country', 'packages'));
    }

    private function storeCheckout(Request $request, string $countrySlug, string $planSlug)
    {
        abort_unless($this->catalog->isCountryPublic($countrySlug), 404);

        $plan = $this->catalog->planForCountry($countrySlug, $planSlug);
        abort_unless($plan, 404);

        $baseData = $request->validate([
            'email' => ['required', 'email', 'max:175'],
            'package_id' => ['required', 'integer'],
        ]);

        $package = $this->catalog->packagesForPlan($countrySlug, $planSlug)
            ->firstWhere('id', (int) $baseData['package_id']);

        if (! $package) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selecciona una modalidad disponible.',
                    'errors' => ['package_id' => ['Selecciona una modalidad disponible.']],
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['package_id' => 'Selecciona una modalidad disponible.']);
        }

        $items = $this->catalog->packageDisplayItems($package);
        $subtotal = $this->catalog->packageSubtotal($package);
        $discount = $this->catalog->packageDiscount($package);
        $total = $this->catalog->packageTotal($package);
        $paymentPlan = $this->paymentPlanForPackage($package, 'full');

        if ($items->isEmpty() || $total <= 0) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta modalidad aun no esta disponible para contratar.',
                    'errors' => ['package_id' => ['Administracion debe definir sus beneficios y precio.']],
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['package_id' => 'Administracion debe definir los beneficios y el precio de la modalidad.']);
        }

        $email = Str::lower(trim($baseData['email']));
        $user = $this->findUserByEmail($email);
        $generatedPassword = null;
        $newUserData = [];

        if ($user && $this->hasPaidSimilarPlan($user, $countrySlug, $planSlug)) {
            $message = $this->paidSimilarPlanMessage();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'errors' => ['email' => [$message]],
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['email' => $message]);
        }

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

        DB::transaction(function () use (&$user, &$generatedPassword, $newUserData, $email, $serviceName, $package, $items, $subtotal, $discount, $total, $paymentPlan, $planSlug, $plan, $countrySlug, $token) {
            if (! $user) {
                $generatedPassword = Str::random(10);
                $user = $this->createCheckoutUser($newUserData, $email, $serviceName, $generatedPassword);
            } elseif (! $user->servicio) {
                $user->forceFill(['servicio' => $serviceName])->save();
            }

            $metadata = $this->catalog->metadata($package);
            $componentRows = $items->values()->all();

            Compras::create([
                'id_user' => $user->id,
                'servicio_id' => $package->id,
                'source' => $this->catalog->source(),
                'servicio_hs_id' => $package->id_hubspot,
                'descripcion' => 'Banca Online 2026 - ' . ($plan['title'] ?? $planSlug) . ': ' . $package->nombre . ($paymentPlan['mode'] === 'installments' ? ' - pago inicial' : ''),
                'pagado' => 0,
                'monto' => $paymentPlan['amount_due_now'],
                'metadata' => array_merge($metadata, [
                    'checkout_token' => $token,
                    'country_slug' => $countrySlug,
                    'requested_service' => $serviceName,
                    'package_title' => $package->nombre,
                    'package_subtotal' => $subtotal,
                    'package_discount' => $discount,
                    'package_total' => $total,
                    'package_id' => $package->id,
                    'components' => $componentRows,
                    'payment_plan' => $paymentPlan,
                ]),
            ]);
        });

        if ($generatedPassword) {
            $this->notifyNewUser($user, $generatedPassword);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'checkout' => $this->checkoutPayload($token, $package, $items, $subtotal, $discount, $paymentPlan, $user, $plan, $countrySlug, $serviceName),
            ]);
        }

        return redirect()->route('banca-online.payment', $token);
    }

    private function checkoutPayload(string $token, Servicio $package, Collection $items, float $subtotal, float $discount, array $paymentPlan, User $user, array $plan, string $countrySlug, string $serviceName): array
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
            'package_title' => $package->nombre,
            'currency' => 'EUR',
            'subtotal' => $subtotal,
            'subtotal_label' => number_format($subtotal, 0, ',', '.'),
            'discount' => $discount,
            'discount_label' => number_format($discount, 0, ',', '.'),
            'total' => $paymentPlan['amount_due_now'],
            'total_label' => number_format($paymentPlan['amount_due_now'], 0, ',', '.'),
            'contract_total' => $paymentPlan['contract_total'],
            'contract_total_label' => number_format($paymentPlan['contract_total'], 0, ',', '.'),
            'payment_plan' => $paymentPlan,
            'payment_options' => $this->checkoutPaymentOptions($package),
            'items' => $items->values(),
            'billing' => [
                'email' => $user->email,
            ],
        ];
    }

    private function paymentPlanForPackage(Servicio $package, string $mode, ?string $periodSlug = null, ?float $initialPercent = null, ?int $installments = null): array
    {
        $contractTotal = $this->catalog->packageTotal($package);

        if ($mode !== 'installments') {
            return [
                'valid' => true,
                'mode' => 'full',
                'contract_total' => $contractTotal,
                'amount_due_now' => $contractTotal,
                'remaining_amount' => 0.0,
                'installments_count' => 0,
                'installment_amount' => 0.0,
                'initial_amount' => $contractTotal,
                'initial_percent' => 100.0,
                'period' => null,
                'period_label' => null,
                'period_plural_label' => null,
                'surcharge_percent' => 0.0,
                'surcharge_amount' => 0.0,
                'financed_amount' => 0.0,
                'stripe_schedule_required' => false,
            ];
        }

        $settings = $this->catalog->packageInstallmentSettings($package);

        if (! $settings['enabled']) {
            return [
                'valid' => false,
                'message' => 'Esta modalidad no tiene pago por cuotas disponible.',
            ];
        }

        $periods = collect($settings['periods']);
        $period = $periods->firstWhere('slug', $periodSlug);

        if (! $period) {
            return [
                'valid' => false,
                'message' => 'Selecciona una periodicidad de pago disponible.',
            ];
        }

        $initialPercent = $initialPercent ?? (float) $settings['min_initial_percent'];

        if ($initialPercent < (float) $settings['min_initial_percent'] || $initialPercent >= 100) {
            return [
                'valid' => false,
                'message' => 'Selecciona una inicial valida para activar las cuotas.',
            ];
        }

        $quote = $this->catalog->packageInstallmentQuote($package, $period['slug'], $initialPercent, $installments);

        if ($installments !== null && ($installments < 1 || $installments > $quote['max_count'])) {
            return [
                'valid' => false,
                'message' => "Con esta inicial puedes seleccionar hasta {$quote['max_count']} cuotas.",
            ];
        }

        return [
            'valid' => true,
            'mode' => 'installments',
            'contract_total' => $quote['contract_total'],
            'base_total' => $quote['base_total'],
            'amount_due_now' => $quote['amount_due_now'],
            'remaining_amount' => $quote['remaining_amount'],
            'financed_amount' => $quote['financed_amount'],
            'installments_count' => $quote['selected_count'],
            'installment_amount' => $quote['installment_amount'],
            'initial_amount' => $quote['amount_due_now'],
            'max_count' => $quote['max_count'],
            'initial_percent' => $quote['initial_percent'],
            'period' => $period['slug'],
            'period_label' => $period['label'],
            'period_plural_label' => $period['plural_label'],
            'surcharge_percent' => $quote['surcharge_percent'],
            'surcharge_amount' => $quote['surcharge_amount'],
            'stripe_schedule_required' => true,
        ];
    }

    private function checkoutPaymentOptions(Servicio $package): array
    {
        $settings = $this->catalog->packageInstallmentSettings($package);

        return [
            'base_total' => $this->catalog->packageTotal($package),
            'installments_enabled' => (bool) ($settings['enabled'] ?? false),
            'min_initial_percent' => (float) ($settings['min_initial_percent'] ?? 100),
            'max_initial_percent' => (float) ($settings['max_initial_percent'] ?? 99),
            'max_installments' => (int) ($settings['max_installments'] ?? 1),
            'periods' => array_values($settings['periods'] ?? []),
            'rules' => array_values($settings['rules'] ?? []),
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

    private function hasPaidSimilarPlan(User $user, string $countrySlug, string $planSlug): bool
    {
        $requestedPlanFamily = $this->bancaOnlinePlanFamily($planSlug);

        if ($requestedPlanFamily === '') {
            return false;
        }

        $countrySlug = $this->catalog->normalizeCountry($countrySlug);

        return Compras::where('id_user', $user->id)
            ->where('source', $this->catalog->source())
            ->where('pagado', 1)
            ->get()
            ->contains(function (Compras $compra) use ($countrySlug, $requestedPlanFamily) {
                $metadata = $compra->metadata ?? [];
                $paidPlanFamily = $this->bancaOnlinePlanFamily(
                    (string) ($metadata['plan_slug'] ?? ''),
                    (string) ($metadata['plan_title'] ?? ''),
                    (string) ($compra->descripcion ?? '')
                );

                return $paidPlanFamily === $requestedPlanFamily
                    && $this->catalog->normalizeCountry($metadata['country_slug'] ?? $countrySlug) === $countrySlug;
            });
    }

    private function paidSimilarPlanMessage(): string
    {
        return 'Este correo ya tiene un pago registrado para este tipo de plan. Puede pagar un plan estrategico, uno administrativo y uno judicial; para registrar a otro familiar, usa un correo diferente.';
    }

    private function bancaOnlinePlanFamily(string $planSlug, string $planTitle = '', string $description = ''): string
    {
        $value = Str::lower(Str::ascii(trim($planSlug . ' ' . $planTitle . ' ' . $description)));

        if ($value === '') {
            return '';
        }

        if (Str::contains($value, ['administrativo'])) {
            return 'administrativo';
        }

        if (Str::contains($value, ['judicial', 'contencioso'])) {
            return 'judicial';
        }

        if (Str::contains($value, ['solicitud-estrategica', 'solicitud estrategica', 'estrategic'])) {
            return 'solicitud-estrategica';
        }

        return Str::slug($planSlug);
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

    private function createStripeInstallmentSchedule(User $user, array $metadata, $customer, string $paymentMethodId): array
    {
        $paymentPlan = $metadata['payment_plan'] ?? [];
        $installments = (int) ($paymentPlan['installments_count'] ?? 0);
        $installmentAmount = (float) ($paymentPlan['installment_amount'] ?? 0);
        $period = (string) ($paymentPlan['period'] ?? 'monthly');
        $periodLabel = (string) ($paymentPlan['period_label'] ?? 'Mensual');
        $periodPluralLabel = (string) ($paymentPlan['period_plural_label'] ?? 'mensuales');

        if (($paymentPlan['mode'] ?? 'full') !== 'installments' || $installments < 1 || $installmentAmount <= 0) {
            return [];
        }

        $package = Servicio::find((int) ($metadata['package_id'] ?? 0));
        $periodSettings = $package
            ? collect($this->catalog->enabledInstallmentPeriods($package))->firstWhere('slug', $period)
            : null;
        $interval = $periodSettings['stripe_interval'] ?? 'month';
        $intervalCount = max(1, (int) ($periodSettings['stripe_interval_count'] ?? 1));
        $startAfterDays = $periodSettings['start_after_days'] ?? null;
        $checkoutToken = (string) ($metadata['checkout_token'] ?? '');
        $packageTitle = (string) ($metadata['package_title'] ?? 'Banca Online 2026');
        $startDate = $this->stripeInstallmentStartTimestamp($interval, $intervalCount, $startAfterDays);
        $stripeMetadata = [
            'checkout_token' => $checkoutToken,
            'user_id' => (string) $user->id,
            'user_email' => (string) $user->email,
            'plan' => (string) ($metadata['plan_slug'] ?? ''),
            'country' => (string) ($metadata['country_slug'] ?? ''),
            'payment_mode' => 'installments',
            'payment_period' => $period,
            'installments_count' => (string) $installments,
        ];

        $product = \Stripe\Product::create([
            'name' => 'Banca Online 2026 - ' . $packageTitle,
            'metadata' => $stripeMetadata,
        ]);

        $price = \Stripe\Price::create([
            'currency' => 'eur',
            'unit_amount' => (int) round($installmentAmount * 100),
            'recurring' => [
                'interval' => $interval,
                'interval_count' => $intervalCount,
            ],
            'product' => $product->id,
            'metadata' => $stripeMetadata,
        ]);

        $installments = max(1, (int) $installments);

        $schedule = \Stripe\SubscriptionSchedule::create([
            'customer' => $customer->id,
            'start_date' => $startDate,
            'end_behavior' => 'cancel',
            'default_settings' => [
                'collection_method' => 'charge_automatically',
                'default_payment_method' => $paymentMethodId,
                'description' => 'Cuotas ' . $periodPluralLabel . ' Banca Online 2026 - ' . $packageTitle,
            ],
            'phases' => [
                [
                    'items' => [
                        [
                            'price' => $price->id,
                            'quantity' => 1,
                        ],
                    ],
                    'iterations' => $installments,
                    'metadata' => $stripeMetadata,
                ],
            ],
            'metadata' => $stripeMetadata,
        ]);

        return [
            'schedule_id' => $schedule->id,
            'product_id' => $product->id,
            'price_id' => $price->id,
            'start_date' => $startDate,
            'installments_count' => $installments,
            'installment_amount' => $installmentAmount,
            'period' => $period,
            'period_label' => $periodLabel,
            'period_plural_label' => $periodPluralLabel,
        ];
    }

    private function stripeInstallmentStartTimestamp(string $interval, int $intervalCount, ?int $startAfterDays): int
    {
        if ($startAfterDays !== null && $startAfterDays > 0) {
            return now()->addDays($startAfterDays)->timestamp;
        }

        return match ($interval) {
            'day' => now()->addDays($intervalCount)->timestamp,
            'week' => now()->addWeeks($intervalCount)->timestamp,
            'year' => now()->addYears($intervalCount)->timestamp,
            default => now()->addMonthsNoOverflow($intervalCount)->timestamp,
        };
    }

    private function cancelStripeInstallmentSchedule(?string $scheduleId): void
    {
        if (! $scheduleId) {
            return;
        }

        try {
            \Stripe\SubscriptionSchedule::retrieve($scheduleId)->cancel();
        } catch (\Throwable $e) {
            Log::warning('No se pudo cancelar el calendario de cuotas de Stripe.', [
                'schedule_id' => $scheduleId,
                'error' => $e->getMessage(),
            ]);
        }
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
