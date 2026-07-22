<?php

namespace App\Http\Controllers;

use App\Models\Compras;
use App\Models\BancaOnlineEvent;
use App\Models\Factura;
use App\Models\Servicio;
use App\Models\User;
use App\Notifications\ClientAppNotification;
use App\Services\BancaOnlineCatalog;
use App\Services\BancaOnlineCosContext;
use App\Services\BancaOnlineExpedienteAdvisor;
use App\Services\BancaOnlineFlow;
use App\Services\ClientStageResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class BancaOnlineController extends Controller
{
    public function __construct(
        private BancaOnlineCatalog $catalog,
        private BancaOnlineFlow $flow,
        private ClientStageResolver $clientStages,
        private BancaOnlineCosContext $cosContext,
        private BancaOnlineExpedienteAdvisor $expedienteAdvisor
    )
    {
    }

    public function landing(Request $request)
    {
        $countrySlug = $this->catalog->normalizeCountry($request->query('servicio', $request->query('pais')));

        if (! $this->catalog->isCountryPublic($countrySlug)) {
            $countrySlug = array_key_first($this->catalog->publicCountries()) ?? 'espana';
        }

        return redirect()->route('banca-online.country', array_merge(
            ['country' => $countrySlug],
            $this->flowQuery($request)
        ));
    }

    public function landingForCountry(Request $request, string $country)
    {
        $countrySlug = $this->catalog->normalizeCountry($country);
        abort_unless($this->catalog->isCountryPublic($countrySlug), 404);

        $country = $this->catalog->country($countrySlug);
        $plans = $this->catalog->plansForCountry($countrySlug);
        $countries = $this->catalog->publicCountries();
        $clientStage = $this->clientStages->resolve(auth()->user());
        $cosContext = $this->cosContext->forUser(auth()->user());
        $entryPoint = $this->flow->entryPoint($request, auth()->user());
        $requestedCaseStatus = $this->requestedCaseStatus($request);
        $selectedCaseStatus = $requestedCaseStatus ?? $this->selectedCaseStatus($request, $clientStage);
        $expedienteContext = $this->expedienteAdvisor->forUser(auth()->user(), $countrySlug, $selectedCaseStatus, $plans, $entryPoint);
        $clientStage = $expedienteContext['client_stage'] ?? $clientStage;

        if (! $requestedCaseStatus && ! empty($expedienteContext['recommended_case_status'])) {
            $selectedCaseStatus = $expedienteContext['recommended_case_status'];
            $expedienteContext = $this->expedienteAdvisor->forUser(auth()->user(), $countrySlug, $selectedCaseStatus, $plans, $entryPoint);
            $clientStage = $expedienteContext['client_stage'] ?? $clientStage;
        }

        $caseStatusOptions = $this->flow->caseStatusOptions();
        $recommendation = $this->flow->recommendation($selectedCaseStatus, $plans, $clientStage);
        $flowQuery = $this->flowQuery($request, $selectedCaseStatus, $entryPoint);

        $this->recordFlowEvent('bo_strategy_recommended', $request, [
            'country' => $countrySlug,
            'case_status' => $selectedCaseStatus,
            'entry_point' => $entryPoint,
            'recommended_plan' => $recommendation['plan_slug'] ?? null,
            'detected_case_status' => $expedienteContext['detected_case_status'] ?? null,
        ], auth()->user());

        return view('banca-online.index', compact(
            'plans',
            'countries',
            'countrySlug',
            'country',
            'clientStage',
            'entryPoint',
            'selectedCaseStatus',
            'caseStatusOptions',
            'recommendation',
            'cosContext',
            'expedienteContext',
            'flowQuery'
        ));
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

    public function rationaleForCountry(Request $request, string $country, string $plan)
    {
        return $this->rationaleView($request, $this->catalog->normalizeCountry($country), $plan);
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
        $pendingSimilarActivation = $user
            ? $this->pendingSimilarActivation($user, $countrySlug, $planSlug)
            : null;
        $clientStage = $user ? $this->clientStages->resolve($user) : null;
        $activationBlocker = $this->bancaOnlineActivationBlocker($user);
        $expedienteContext = $user
            ? $this->expedienteAdvisor->forUser(
                $user,
                $countrySlug,
                null,
                $this->catalog->plansForCountry($countrySlug),
                $this->flow->entryPoint($request, auth()->user())
            )
            : null;

        if ($expedienteContext && isset($expedienteContext['client_stage'])) {
            $clientStage = $expedienteContext['client_stage'];
        }

        return response()->json([
            'exists' => (bool) $user,
            'has_paid_similar_plan' => $hasPaidSimilarPlan,
            'has_pending_similar_activation' => (bool) $pendingSimilarActivation,
            'pending_activation' => $this->pendingActivationPayload($pendingSimilarActivation),
            'can_activate_banca_online' => $activationBlocker === null,
            'activation_blocker' => $activationBlocker,
            'client_stage' => $clientStage,
            'suggested_case_status' => $clientStage ? $this->flow->defaultCaseStatusForStage($clientStage) : 'not_started',
            'expediente_context' => $this->expedienteAdvisor->publicLookupContext($user, $expedienteContext ?? []),
            'message' => $hasPaidSimilarPlan ? $this->paidSimilarPlanMessage() : null,
        ]);
    }

    public function trackEvent(Request $request)
    {
        $data = $request->validate([
            'event' => ['required', Rule::in([
                'bo_case_status_selected',
                'bo_nationality_selected',
                'bo_strategy_recommended',
                'bo_strategy_rationale_viewed',
                'bo_activation_requested',
                'bo_activation_payment_started',
            ])],
            'payload' => ['nullable', 'array'],
        ]);

        $this->recordFlowEvent($data['event'], $request, $data['payload'] ?? [], auth()->user());

        return response()->noContent();
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

        DB::transaction(function () use ($compras, $user, $customer, $paymentIntent, $total, $stripeInstallmentSchedule, $paymentPlan, $data) {
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
                $metadata['activation_status'] = 'paid_pending_operations';
                $metadata['activation_status_label'] = 'Activacion pagada, pendiente de gestion operativa';
                $metadata['activated_at'] = now()->toIso8601String();
                $metadata['flow_events'] = collect($metadata['flow_events'] ?? [])
                    ->push([
                        'event' => 'bo_activation_payment_completed',
                        'occurred_at' => now()->toIso8601String(),
                        'amount' => $total,
                        'payment_mode' => $paymentPlan['mode'] ?? 'full',
                    ])
                    ->values()
                    ->all();

                $compra->fill([
                    'pagado' => 1,
                    'monto' => $total,
                    'hash_factura' => $hashFactura,
                    'paid_at' => $paidAt,
                    'metadata' => $metadata,
                ])->save();
            });

            $userUpdates = [
                'stripe_cus_id' => $customer->id,
            ];

            if (! $user->nombres && ! empty($data['first_name'])) {
                $userUpdates['nombres'] = $data['first_name'];
            }

            if (! $user->apellidos && ! empty($data['last_name'])) {
                $userUpdates['apellidos'] = $data['last_name'];
            }

            if ($this->isPlaceholderBancaOnlineName($user) && (! empty($data['first_name']) || ! empty($data['last_name']))) {
                $userUpdates['name'] = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
            }

            if (! $user->phone && ! empty($data['phone'])) {
                $userUpdates['phone'] = $data['phone'];
            }

            $user->forceFill($userUpdates)->save();
        });

        $nextUrl = $this->nextUrlAfterBancaOnlinePayment($user, $token);
        $nextLabel = $this->nextLabelAfterBancaOnlinePayment($user);

        $this->recordFlowEvent('bo_activation_payment_completed', $request, [
            'country' => $countrySlug,
            'plan' => $metadata['plan_slug'] ?? null,
            'package_id' => $metadata['package_id'] ?? null,
            'compra_id' => $compras->first()?->id,
            'checkout_token' => $token,
            'amount' => $total,
            'payment_mode' => $paymentPlan['mode'] ?? 'full',
            'next_url' => $nextUrl,
        ], $user);

        $this->notifyBancaOnlineActivationPaid($user, $metadata, $nextUrl);

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
            'next_url' => $nextUrl,
            'thank_you' => [
                'title' => 'Pago recibido',
                'name' => trim($data['first_name']),
                'total' => $total,
                'total_label' => number_format($total, 0, ',', '.'),
                'currency' => 'EUR',
                'payment_plan' => $paymentPlan,
                'items' => $paidItems,
                'next_url' => $nextUrl,
                'next_label' => $nextLabel,
            ],
        ]);
    }

    public function thankYou(string $token)
    {
        $compras = $this->purchasesForToken($token, false)->where('pagado', 1);

        abort_if($compras->isEmpty(), 404);

        $user = $compras->first()->user;
        $total = (float) $compras->sum('monto');
        $nextUrl = $this->nextUrlAfterBancaOnlinePayment($user, $token);
        $nextLabel = $this->nextLabelAfterBancaOnlinePayment($user);

        return view('banca-online.thank-you', compact('compras', 'user', 'total', 'nextUrl', 'nextLabel'));
    }

    private function rationaleView(Request $request, string $countrySlug, string $planSlug)
    {
        abort_unless($this->catalog->isCountryPublic($countrySlug), 404);

        $plan = $this->catalog->planForCountry($countrySlug, $planSlug);
        abort_unless($plan, 404);

        $countries = $this->catalog->publicCountries();
        $country = $this->catalog->country($countrySlug);
        $clientStage = $this->clientStages->resolve(auth()->user());
        $cosContext = $this->cosContext->forUser(auth()->user());
        $entryPoint = $this->flow->entryPoint($request, auth()->user());
        $requestedCaseStatus = $this->requestedCaseStatus($request);
        $selectedCaseStatus = $requestedCaseStatus ?? $this->selectedCaseStatus($request, $clientStage);
        $expedienteContext = $this->expedienteAdvisor->forUser(auth()->user(), $countrySlug, $selectedCaseStatus, $this->catalog->plansForCountry($countrySlug), $entryPoint);
        $clientStage = $expedienteContext['client_stage'] ?? $clientStage;

        if (! $requestedCaseStatus && ! empty($expedienteContext['recommended_case_status'])) {
            $selectedCaseStatus = $expedienteContext['recommended_case_status'];
            $expedienteContext = $this->expedienteAdvisor->forUser(auth()->user(), $countrySlug, $selectedCaseStatus, $this->catalog->plansForCountry($countrySlug), $entryPoint);
            $clientStage = $expedienteContext['client_stage'] ?? $clientStage;
        }

        $caseStatusOptions = $this->flow->caseStatusOptions();
        $selectedStatus = $caseStatusOptions[$selectedCaseStatus] ?? null;
        $recommendation = $this->flow->recommendation($selectedCaseStatus, $this->catalog->plansForCountry($countrySlug), $clientStage);
        $rationale = $this->flow->rationale($selectedCaseStatus, $plan, $recommendation, $planSlug);
        $flowQuery = $this->flowQuery($request, $selectedCaseStatus, $entryPoint);

        $this->recordFlowEvent('bo_strategy_rationale_viewed', $request, [
            'country' => $countrySlug,
            'plan' => $planSlug,
            'case_status' => $selectedCaseStatus,
            'entry_point' => $entryPoint,
            'recommended_plan' => $recommendation['plan_slug'] ?? null,
        ], auth()->user());

        return view('banca-online.rationale', compact(
            'planSlug',
            'plan',
            'countries',
            'countrySlug',
            'country',
            'clientStage',
            'entryPoint',
            'selectedCaseStatus',
            'selectedStatus',
            'recommendation',
            'rationale',
            'cosContext',
            'expedienteContext',
            'flowQuery'
        ));
    }

    private function configurationView(Request $request, string $countrySlug, string $planSlug)
    {
        abort_unless($this->catalog->isCountryPublic($countrySlug), 404);

        $plan = $this->catalog->planForCountry($countrySlug, $planSlug);
        abort_unless($plan, 404);

        $countries = $this->catalog->publicCountries();
        $country = $this->catalog->country($countrySlug);
        $packages = $this->catalog->packagesForPlan($countrySlug, $planSlug, false);
        $clientStage = $this->clientStages->resolve(auth()->user());
        $cosContext = $this->cosContext->forUser(auth()->user());
        $entryPoint = $this->flow->entryPoint($request, auth()->user());
        $requestedCaseStatus = $this->requestedCaseStatus($request);
        $selectedCaseStatus = $requestedCaseStatus ?? $this->selectedCaseStatus($request, $clientStage);
        $expedienteContext = $this->expedienteAdvisor->forUser(auth()->user(), $countrySlug, $selectedCaseStatus, $this->catalog->plansForCountry($countrySlug), $entryPoint);
        $clientStage = $expedienteContext['client_stage'] ?? $clientStage;

        if (! $requestedCaseStatus && ! empty($expedienteContext['recommended_case_status'])) {
            $selectedCaseStatus = $expedienteContext['recommended_case_status'];
            $expedienteContext = $this->expedienteAdvisor->forUser(auth()->user(), $countrySlug, $selectedCaseStatus, $this->catalog->plansForCountry($countrySlug), $entryPoint);
            $clientStage = $expedienteContext['client_stage'] ?? $clientStage;
        }

        $caseStatusOptions = $this->flow->caseStatusOptions();
        $recommendation = $this->flow->recommendation($selectedCaseStatus, $this->catalog->plansForCountry($countrySlug), $clientStage);
        $flowQuery = $this->flowQuery($request, $selectedCaseStatus, $entryPoint);
        $quoteContext = $this->flow->quoteContext($request, auth()->user());

        return view('banca-online.configurator', compact(
            'planSlug',
            'plan',
            'countries',
            'countrySlug',
            'country',
            'packages',
            'clientStage',
            'entryPoint',
            'selectedCaseStatus',
            'caseStatusOptions',
            'recommendation',
            'cosContext',
            'expedienteContext',
            'flowQuery',
            'quoteContext'
        ));
    }

    private function storeCheckout(Request $request, string $countrySlug, string $planSlug)
    {
        abort_unless($this->catalog->isCountryPublic($countrySlug), 404);

        $plan = $this->catalog->planForCountry($countrySlug, $planSlug);
        abort_unless($plan, 404);

        $baseData = $request->validate([
            'email' => ['required', 'email', 'max:175'],
            'package_id' => ['required', 'integer'],
            'selected_case_status' => ['nullable', Rule::in(array_keys($this->flow->caseStatusOptions()))],
            'entry_point' => ['nullable', Rule::in(BancaOnlineFlow::ENTRY_POINTS)],
            'quote_id' => ['nullable', 'string', 'max:160'],
            'cotizacion_id' => ['nullable', 'string', 'max:160'],
            'quote_source' => ['nullable', 'string', 'max:160'],
            'quote_reference' => ['nullable', 'string', 'max:160'],
            'process_id' => ['nullable', 'string', 'max:160'],
            'deal_id' => ['nullable', 'string', 'max:160'],
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
        $serviceName = $this->catalog->serviceNameForCountry($countrySlug);
        $user = $this->findUserByEmail($email) ?: $this->createBancaOnlineCandidate($email, $serviceName);
        $initialClientStage = $this->clientStages->resolve($user);
        $entryPoint = $this->flow->entryPoint($request, auth()->user());
        $selectedCaseStatus = $this->selectedCaseStatus($request, $initialClientStage);
        $caseStatusOption = $this->flow->caseStatusOptions()[$selectedCaseStatus] ?? null;
        $quoteContext = $this->flow->quoteContext($request, auth()->user());
        $recommendation = $this->flow->recommendation($selectedCaseStatus, $this->catalog->plansForCountry($countrySlug), $initialClientStage);
        $activationCosContext = $this->cosContext->forUser($user);
        $activationExpedienteContext = $this->expedienteAdvisor->forUser($user, $countrySlug, $selectedCaseStatus, $this->catalog->plansForCountry($countrySlug), $entryPoint);

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

        if ($blocker = $this->bancaOnlineActivationBlocker($user)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $blocker['message'],
                    'next_url' => $blocker['next_url'],
                    'next_label' => $blocker['next_label'],
                    'errors' => ['email' => [$blocker['message']]],
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['email' => $blocker['message']]);
        }

        $pendingSimilarActivation = $this->pendingSimilarActivation($user, $countrySlug, $planSlug, (int) $package->id);
        $pendingCheckout = $this->checkoutPayloadFromPurchase($pendingSimilarActivation);

        if ($pendingCheckout) {
            $this->recordFlowEvent('bo_activation_existing_checkout_returned', $request, [
                'country' => $countrySlug,
                'plan' => $planSlug,
                'package_id' => $package->id,
                'compra_id' => $pendingSimilarActivation->id,
                'checkout_token' => $pendingCheckout['token'] ?? null,
                'entry_point' => $entryPoint,
                'case_status' => $selectedCaseStatus,
                'quote_context' => $quoteContext,
            ], $user);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Ya existe una activacion pendiente para este alcance. Te llevamos al pago guardado.',
                    'reused_pending_checkout' => true,
                    'checkout' => $pendingCheckout,
                ]);
            }

            return redirect($pendingCheckout['payment_url']);
        }

        $token = Str::random(64);

        $purchase = DB::transaction(function () use ($user, $serviceName, $package, $items, $subtotal, $discount, $total, $paymentPlan, $planSlug, $plan, $countrySlug, $token, $entryPoint, $selectedCaseStatus, $caseStatusOption, $quoteContext, $recommendation, $activationCosContext, $activationExpedienteContext) {
            if (! $user->servicio) {
                $user->forceFill(['servicio' => $serviceName])->save();
            }

            $metadata = $this->catalog->metadata($package);
            $componentRows = $items->values()->all();
            $clientStage = $this->clientStages->resolve($user, ['has_pending_purchase' => true]);

            return Compras::create([
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
                    'activation_status' => 'pending_payment',
                    'activation_status_label' => 'Activacion pendiente de pago',
                    'client_stage' => $clientStage['stage'],
                    'client_profile' => $clientStage['profile'],
                    'client_next_action' => $clientStage['next_action'],
                    'entry_point' => $entryPoint,
                    'selected_case_status' => $selectedCaseStatus,
                    'selected_case_status_label' => $caseStatusOption['label'] ?? null,
                    'quote_context' => $quoteContext,
                    'recommendation' => $recommendation,
                    'cos_context' => [
                        'available' => $activationCosContext['available'] ?? false,
                        'fresh' => $activationCosContext['fresh'] ?? false,
                        'summary' => $activationCosContext['summary'] ?? null,
                    ],
                    'expediente_context' => [
                        'stage' => $activationExpedienteContext['stage'] ?? null,
                        'stage_label' => $activationExpedienteContext['stage_label'] ?? null,
                        'summary' => $activationExpedienteContext['summary'] ?? null,
                        'next_action_type' => $activationExpedienteContext['next_action']['type'] ?? null,
                        'pending_documents' => $activationExpedienteContext['documents']['pending_count'] ?? 0,
                        'missing_documents' => $activationExpedienteContext['documents']['missing_count'] ?? 0,
                        'detected_case_status' => $activationExpedienteContext['detected_case_status'] ?? null,
                    ],
                    'flow_events' => [[
                        'event' => 'bo_activation_requested',
                        'occurred_at' => now()->toIso8601String(),
                        'entry_point' => $entryPoint,
                        'selected_case_status' => $selectedCaseStatus,
                        'plan_slug' => $planSlug,
                        'country_slug' => $countrySlug,
                    ]],
                    'flow_version' => 'sprint_2',
                ]),
            ]);
        });

        $this->recordFlowEvent('bo_activation_requested', $request, [
            'country' => $countrySlug,
            'plan' => $planSlug,
            'package_id' => $package->id,
            'compra_id' => $purchase->id,
            'checkout_token' => $token,
            'entry_point' => $entryPoint,
            'case_status' => $selectedCaseStatus,
            'quote_context' => $quoteContext,
            'cos_available' => $activationCosContext['available'] ?? false,
            'expediente_stage' => $activationExpedienteContext['stage'] ?? null,
            'pending_documents' => $activationExpedienteContext['documents']['pending_count'] ?? 0,
            'missing_documents' => $activationExpedienteContext['documents']['missing_count'] ?? 0,
        ], $user);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'checkout' => $this->checkoutPayload($token, $package, $items, $subtotal, $discount, $paymentPlan, $user, $plan, $countrySlug, $serviceName, [
                    'entry_point' => $entryPoint,
                    'selected_case_status' => $selectedCaseStatus,
                    'selected_case_status_label' => $caseStatusOption['label'] ?? null,
                    'recommendation' => $recommendation,
                    'plan_slug' => $planSlug,
                    'quote_context' => $quoteContext,
                ]),
            ]);
        }

        return redirect()->route('banca-online.payment', $token);
    }

    private function checkoutPayload(string $token, Servicio $package, Collection $items, float $subtotal, float $discount, array $paymentPlan, User $user, array $plan, string $countrySlug, string $serviceName, array $flowMetadata = []): array
    {
        return [
            'token' => $token,
            'payment_url' => route('banca-online.payment', $token),
            'process_url' => route('banca-online.payment.process', $token),
            'thank_you_url' => route('banca-online.thank-you', $token),
            'stripe_key' => $this->stripePublicKey($countrySlug),
            'country_slug' => $countrySlug,
            'requested_service' => $serviceName,
            'plan_slug' => $flowMetadata['plan_slug'] ?? null,
            'plan_title' => $plan['title'] ?? 'Plan estrategico',
            'package_id' => $package->id,
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
            'flow' => $flowMetadata,
            'items' => $items->values(),
            'billing' => [
                'email' => $user->email,
            ],
        ];
    }

    private function recordFlowEvent(string $event, Request $request, array $payload = [], ?User $user = null): void
    {
        $context = [
            'event' => $event,
            'user_id' => $user?->id,
            'email' => $user?->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'payload' => $payload,
            'occurred_at' => now()->toIso8601String(),
        ];

        Log::info('Banca Online event', $context);

        try {
            if (! Schema::hasTable('banca_online_events')) {
                return;
            }

            BancaOnlineEvent::create([
                'user_id' => $user?->id,
                'compra_id' => $payload['compra_id'] ?? null,
                'event' => $event,
                'email' => $user?->email ?? ($payload['email'] ?? null),
                'country_slug' => $payload['country'] ?? $payload['country_slug'] ?? data_get($payload, 'quote_context.country'),
                'plan_slug' => $payload['plan'] ?? $payload['plan_slug'] ?? data_get($payload, 'quote_context.plan'),
                'package_id' => $payload['package_id'] ?? null,
                'entry_point' => $payload['entry_point'] ?? null,
                'case_status' => $payload['case_status'] ?? $payload['selected_case_status'] ?? null,
                'quote_id' => $payload['quote_id'] ?? $payload['cotizacion_id'] ?? data_get($payload, 'quote_context.quote_id'),
                'checkout_token' => $payload['checkout_token'] ?? data_get($payload, 'quote_context.checkout_token'),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
                'url' => $request->fullUrl(),
                'payload' => $payload,
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo persistir evento de Banca Online.', [
                'event' => $event,
                'user_id' => $user?->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function nextUrlAfterBancaOnlinePayment(User $user, string $token): string
    {
        $stage = $this->clientStages->resolve($user);
        $pay = (int) ($stage['signals']['pay'] ?? ($user->pay ?? 0));

        if ($stage['profile'] === 'candidate') {
            if ($pay === 0) {
                return route('clientes.pay');
            }

            if (in_array($pay, [1, 3], true)) {
                return route('clientes.getinfo');
            }

            if ($pay === 2 && (int) ($user->contrato ?? 0) !== 1) {
                return route('cliente.contrato');
            }
        }

        if ($stage['profile'] === 'represented') {
            return route('clientes.tree');
        }

        return route('banca-online.thank-you', $token);
    }

    private function nextLabelAfterBancaOnlinePayment(User $user): string
    {
        $stage = $this->clientStages->resolve($user);
        $pay = (int) ($stage['signals']['pay'] ?? ($user->pay ?? 0));

        if ($stage['profile'] === 'represented') {
            return 'Ver estatus del expediente';
        }

        if ($stage['profile'] === 'candidate') {
            if ($pay === 0) {
                return 'Pagar registro inicial';
            }

            if (in_array($pay, [1, 3], true)) {
                return 'Completar informacion';
            }

            if ($pay === 2 && (int) ($user->contrato ?? 0) !== 1) {
                return 'Firmar contrato';
            }
        }

        return $stage['next_action'] ?? 'Continuar en la plataforma';
    }

    private function notifyBancaOnlineActivationPaid(User $user, array $metadata, string $nextUrl): void
    {
        try {
            $packageTitle = $metadata['package_title'] ?? 'Banca Online 2026';
            $planTitle = $metadata['plan_title'] ?? 'estrategia seleccionada';

            $user->notify(new ClientAppNotification(
                title: 'Activacion de Banca Online recibida',
                body: "Recibimos el pago de {$packageTitle} para {$planTitle}. El equipo de Sefar Universal continuara la gestion operativa del alcance activado.",
                actionUrl: $nextUrl,
                actionText: 'Ver estatus',
                category: 'banca_online',
                sendEmail: false,
            ));
        } catch (\Throwable $e) {
            Log::warning('No se pudo notificar la activacion de Banca Online.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function bancaOnlineActivationBlocker(?User $user): ?array
    {
        // Banca Online puede activarse antes del pago del registro base.
        // /pay, /getinfo y contrato se regularizan despues con el mismo cliente.
        return null;
    }

    private function createBancaOnlineCandidate(string $email, string $serviceName): User
    {
        $user = User::create([
            'name' => $this->placeholderBancaOnlineName($email),
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
            'email_verified_at' => now(),
            'servicio' => $serviceName,
            'pay' => 0,
            'contrato' => 0,
            'cosready' => 1,
        ]);

        try {
            $user->assignRole('Cliente');
            $user->givePermissionTo(['pay.services', 'finish.register']);
        } catch (\Throwable $e) {
            Log::warning('No se pudo asignar rol/permisos al candidato creado desde Banca Online.', [
                'user_id' => $user->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        return $user;
    }

    private function placeholderBancaOnlineName(string $email): string
    {
        $localPart = trim(Str::before($email, '@'));

        return $localPart !== '' ? $localPart : 'Cliente Banca Online';
    }

    private function isPlaceholderBancaOnlineName(User $user): bool
    {
        return $user->name === $this->placeholderBancaOnlineName((string) $user->email)
            || $user->name === 'Cliente Banca Online';
    }

    private function selectedCaseStatus(Request $request, array $clientStage): string
    {
        return $this->requestedCaseStatus($request) ?? $this->flow->defaultCaseStatusForStage($clientStage);
    }

    private function requestedCaseStatus(Request $request): ?string
    {
        return $this->flow->normalizeCaseStatus(
            $request->input('selected_case_status')
                ?: $request->query('selected_case_status')
                ?: $request->query('status')
        );
    }

    private function flowQuery(Request $request, ?string $selectedCaseStatus = null, ?string $entryPoint = null): array
    {
        $query = [
            'entry_point' => $entryPoint ?? $this->flow->entryPoint($request, auth()->user()),
        ];

        $clientStage = $this->clientStages->resolve(auth()->user());
        $caseStatus = $selectedCaseStatus ?? $this->selectedCaseStatus($request, $clientStage);

        if ($caseStatus) {
            $query['status'] = $caseStatus;
        }

        foreach ($this->flow->quoteContext($request, auth()->user()) as $key => $value) {
            if ($key !== 'authenticated_user_id') {
                $query[$key] = $value;
            }
        }

        return array_filter($query, fn ($value) => $value !== null && $value !== '');
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
            ->contains(fn (Compras $compra) => $this->purchaseMatchesPlanFamilyAndCountry($compra, $countrySlug, $requestedPlanFamily));
    }

    private function pendingSimilarActivation(User $user, string $countrySlug, string $planSlug, ?int $packageId = null): ?Compras
    {
        $requestedPlanFamily = $this->bancaOnlinePlanFamily($planSlug);

        if ($requestedPlanFamily === '') {
            return null;
        }

        $countrySlug = $this->catalog->normalizeCountry($countrySlug);

        return Compras::with(['servicio', 'user'])
            ->where('id_user', $user->id)
            ->where('source', $this->catalog->source())
            ->where(function ($query) {
                $query->whereNull('pagado')->orWhere('pagado', '!=', 1);
            })
            ->latest('updated_at')
            ->get()
            ->first(function (Compras $compra) use ($countrySlug, $requestedPlanFamily, $packageId) {
                $metadata = $compra->metadata ?? [];
                $samePackage = $packageId === null
                    || (int) ($metadata['package_id'] ?? $compra->servicio_id) === $packageId;

                return $samePackage
                    && $this->purchaseMatchesPlanFamilyAndCountry($compra, $countrySlug, $requestedPlanFamily);
            });
    }

    private function purchaseMatchesPlanFamilyAndCountry(Compras $compra, string $countrySlug, string $requestedPlanFamily): bool
    {
        $metadata = $compra->metadata ?? [];
        $paidPlanFamily = $this->bancaOnlinePlanFamily(
            (string) ($metadata['plan_slug'] ?? ''),
            (string) ($metadata['plan_title'] ?? ''),
            (string) ($compra->descripcion ?? '')
        );

        return $paidPlanFamily === $requestedPlanFamily
            && $this->catalog->normalizeCountry($metadata['country_slug'] ?? $countrySlug) === $countrySlug;
    }

    private function pendingActivationPayload(?Compras $purchase): ?array
    {
        if (! $purchase) {
            return null;
        }

        $metadata = $purchase->metadata ?? [];
        $token = (string) ($metadata['checkout_token'] ?? '');

        return [
            'compra_id' => $purchase->id,
            'checkout_token' => $token ?: null,
            'payment_url' => $token ? route('banca-online.payment', $token) : null,
            'package_id' => $metadata['package_id'] ?? $purchase->servicio_id,
            'package_title' => $metadata['package_title'] ?? $purchase->servicio?->nombre,
            'plan_slug' => $metadata['plan_slug'] ?? null,
            'plan_title' => $metadata['plan_title'] ?? null,
            'country_slug' => $metadata['country_slug'] ?? null,
            'total' => $metadata['package_total'] ?? $purchase->monto,
            'total_label' => number_format((float) ($metadata['package_total'] ?? $purchase->monto), 0, ',', '.'),
        ];
    }

    private function checkoutPayloadFromPurchase(?Compras $purchase): ?array
    {
        if (! $purchase) {
            return null;
        }

        $purchase->loadMissing(['servicio', 'user']);

        $metadata = $purchase->metadata ?? [];
        $token = (string) ($metadata['checkout_token'] ?? '');
        $package = $purchase->servicio;
        $user = $purchase->user;

        if ($token === '' || ! $package || ! $user) {
            return null;
        }

        $items = collect($metadata['components'] ?? []);
        if ($items->isEmpty()) {
            $items = $this->catalog->packageDisplayItems($package);
        }

        $paymentPlan = $metadata['payment_plan'] ?? $this->paymentPlanForPackage($package, 'full');
        $countrySlug = $this->catalog->normalizeCountry($metadata['country_slug'] ?? '');
        $planSlug = (string) ($metadata['plan_slug'] ?? '');
        $plan = ['title' => $metadata['plan_title'] ?? Str::title(str_replace('-', ' ', $planSlug ?: 'Plan estrategico'))];

        return $this->checkoutPayload(
            $token,
            $package,
            $items,
            (float) ($metadata['package_subtotal'] ?? $this->catalog->packageSubtotal($package)),
            (float) ($metadata['package_discount'] ?? $this->catalog->packageDiscount($package)),
            $paymentPlan,
            $user,
            $plan,
            $countrySlug,
            (string) ($metadata['requested_service'] ?? $user->servicio ?? $this->catalog->serviceNameForCountry($countrySlug)),
            [
                'entry_point' => $metadata['entry_point'] ?? null,
                'selected_case_status' => $metadata['selected_case_status'] ?? null,
                'selected_case_status_label' => $metadata['selected_case_status_label'] ?? null,
                'recommendation' => $metadata['recommendation'] ?? null,
                'quote_context' => $metadata['quote_context'] ?? [],
                'plan_slug' => $planSlug,
                'reused_pending_checkout' => true,
            ]
        );
    }

    private function paidSimilarPlanMessage(): string
    {
        return 'Este correo ya tiene una activacion pagada para este tipo de estrategia. Puede activar una estrategia inicial, una administrativa y una judicial; para registrar a otro familiar, usa un correo diferente.';
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
