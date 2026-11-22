<?php

namespace Tests\Feature;

use App\Http\Controllers\{BancaOnlineController, ClienteController};
use App\Models\{Compras, User};
use Illuminate\Support\Facades\{Cache, Http, Mail};
use Stripe\{ApiRequestor, Stripe};
use Stripe\Exception\{ApiConnectionException, ApiErrorException};
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class PrimaryStripePaymentsTest extends TestCase
{
    use \Tests\Concerns\RegistrationSchema;

    private $originalTransport;
    private $originalApiKey;
    private int $originalRetries;
    private array $requests = [];
    private \Closure $handler;

    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->useEnvironmentPath('/dev')->loadEnvironmentFrom('null');
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'session.driver' => 'array',
            'services.stripe.key' => 'pk_test_primary', 'services.stripe.secret' => 'sk_test_primary',
            'services.hubspot.key' => 'test-only', 'app.key' => 'base64:'.base64_encode(str_repeat('s', 32))]);
        Http::preventStrayRequests();
        Mail::fake();
        $this->createRegistrationSchema();
        $this->originalTransport = (new \ReflectionProperty(ApiRequestor::class, '_httpClient'))->getValue();
        $this->originalApiKey = Stripe::getApiKey();
        $this->originalRetries = Stripe::getMaxNetworkRetries();
        Stripe::setApiKey(config('services.stripe.secret'));
        Stripe::setMaxNetworkRetries(0);
        $this->handler = fn () => throw new \RuntimeException('Unexpected Stripe request in test');
        $transport = \Mockery::mock(ClientInterface::class);
        $transport->shouldReceive('request')->andReturnUsing(function ($method, $url, $headers, $params) {
            $request = compact('method', 'url', 'headers', 'params');
            $request['path'] = parse_url($url, PHP_URL_PATH);
            $this->requests[] = $request;

            return ($this->handler)($request);
        });
        ApiRequestor::setHttpClient($transport);
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        ApiRequestor::setHttpClient($this->originalTransport);
        Stripe::setApiKey($this->originalApiKey);
        Stripe::setMaxNetworkRetries($this->originalRetries);
        parent::tearDown();
    }

    private function response(array $data, int $status = 200): array
    {
        return [json_encode($data), $status, []];
    }

    private function billing(): array
    {
        return ['first_name' => 'Cliente', 'last_name' => 'Portugal', 'email' => 'client@example.invalid',
            'phone' => '+0000', 'address_line1' => 'Dirección de prueba', 'city' => 'Lisboa',
            'postal_code' => '1000', 'country' => 'PT'];
    }

    private function customer($controller, User $user)
    {
        if ($controller instanceof ClienteController) {
            return (new \ReflectionMethod($controller, 'primaryStripeCustomer'))->invoke($controller, $user, [
                'name' => 'Cliente Portugal', 'email' => $user->email, 'phone' => '+0000',
                'address' => ['line1' => 'Dirección de prueba', 'city' => 'Lisboa', 'postal_code' => '1000', 'country' => 'PT'],
            ]);
        }

        return (new \ReflectionMethod($controller, 'stripeCustomer'))->invoke($controller, $user, $this->billing());
    }

    private function user(): User
    {
        return User::factory()->create(['servicio' => 'Portuguesa Sefardi', 'stripe_cus_id' => 'cus_previous']);
    }

    public function test_missing_historical_customers_are_replaced_once_on_the_primary_account_in_both_flows(): void
    {
        foreach ([ClienteController::class, BancaOnlineController::class] as $class) {
            $this->requests = [];
            $user = $this->user();
            $this->handler = function (array $request) {
                if ($request['path'] === '/v1/customers/cus_previous') {
                    return $this->response(['error' => ['type' => 'invalid_request_error', 'code' => 'resource_missing', 'message' => 'No such customer']], 404);
                }

                return $this->response(['id' => 'cus_primary', 'object' => 'customer']);
            };
            $controller = app($class);
            $this->assertSame('cus_primary', $this->customer($controller, $user)->id);
            $this->assertSame('cus_primary', $user->fresh()->stripe_cus_id);
            $this->customer($controller, $user->fresh());

            $creates = array_values(array_filter($this->requests, fn ($request) => $request['path'] === '/v1/customers'));
            $this->assertCount(1, $creates);
            $this->assertNotEmpty(array_filter($creates[0]['headers'], fn ($header) => str_starts_with($header, 'Idempotency-Key: sefar-primary-customer-')));
            foreach ($this->requests as $request) $this->assertContains('Authorization: Bearer sk_test_primary', $request['headers']);
        }
        Http::assertNothingSent();
    }

    public function test_other_customer_failures_never_create_a_replacement_or_change_the_local_reference(): void
    {
        foreach ([ClienteController::class, BancaOnlineController::class] as $class) {
            foreach ([[401, 'invalid_api_key'], [429, 'rate_limit'], [404, 'other_error'], [400, 'resource_missing'], [0, 'timeout']] as [$status, $code]) {
                $user = $this->user();
                $this->requests = [];
                $this->handler = function () use ($status, $code) {
                    if ($status === 0) throw ApiConnectionException::factory('Simulated connection failure');

                    return $this->response(['error' => ['type' => 'invalid_request_error', 'code' => $code, 'message' => 'Synthetic provider failure']], $status);
                };
                try {
                    $this->customer(app($class), $user);
                    $this->fail('The provider failure must propagate');
                } catch (ApiErrorException) {
                    $this->assertSame('cus_previous', $user->fresh()->stripe_cus_id);
                    $this->assertCount(1, $this->requests);
                    $this->assertSame('get', $this->requests[0]['method']);
                }
            }
        }
    }

    public function test_losing_a_customer_creation_response_reuses_the_same_primary_idempotency_key(): void
    {
        foreach ([ClienteController::class, BancaOnlineController::class] as $class) {
            $user = $this->user();
            $this->requests = [];
            $creates = 0;
            $this->handler = function (array $request) use (&$creates) {
                if ($request['method'] === 'get') return $this->response(['error' => [
                    'type' => 'invalid_request_error', 'code' => 'resource_missing', 'message' => 'No such customer']], 404);
                $creates++;
                if ($creates === 1) throw ApiConnectionException::factory('Simulated response lost');

                return $this->response(['id' => 'cus_primary', 'object' => 'customer']);
            };
            $controller = app($class);
            try {
                $this->customer($controller, $user);
                $this->fail('The lost response must remain an error');
            } catch (ApiConnectionException) {
                $this->assertSame('cus_previous', $user->fresh()->stripe_cus_id);
            }
            $this->customer($controller, $user->fresh());
            $posts = array_values(array_filter($this->requests, fn ($request) => $request['method'] === 'post'));
            $keys = fn ($request) => array_values(array_filter($request['headers'], fn ($header) => str_starts_with($header, 'Idempotency-Key:')));
            $this->assertCount(2, $posts);
            $this->assertNotEmpty($keys($posts[0]));
            $this->assertSame($keys($posts[0]), $keys($posts[1]));
            $this->assertSame('cus_primary', $user->fresh()->stripe_cus_id);
        }
    }

    public function test_a_lost_confirmation_is_read_back_without_another_charge_even_with_a_new_payment_method(): void
    {
        foreach ([ClienteController::class, BancaOnlineController::class] as $class) {
            $user = $this->user();
            $purchase = Compras::create(['id_user' => $user->id, 'monto' => 51.36, 'pagado' => 0,
                'metadata' => ['stripe_payment_intent_id' => 'pi_historical', 'stripe_customer_id' => 'cus_previous']]);
            $this->requests = [];
            $remoteSucceeded = false;
            $this->handler = function (array $request) use (&$remoteSucceeded) {
                if (str_ends_with($request['path'], '/confirm')) {
                    $remoteSucceeded = true;
                    throw ApiConnectionException::factory('Confirmation accepted, response lost');
                }

                return $this->response(['id' => 'pi_primary', 'object' => 'payment_intent', 'amount' => 5136,
                    'currency' => 'eur', 'customer' => 'cus_primary', 'status' => $remoteSucceeded ? 'succeeded' : 'requires_payment_method']);
            };
            $controller = app($class);
            $method = new \ReflectionMethod($controller, 'primaryStripePaymentIntent');
            $payload = ['amount' => 5136, 'currency' => 'eur', 'customer' => 'cus_primary',
                'payment_method' => 'pm_first', 'confirm' => true, 'off_session' => false];
            try {
                $method->invoke($controller, collect([$purchase]), $payload, 'checkout:'.$purchase->id);
                $this->fail('The lost confirmation response must remain uncertain');
            } catch (ApiConnectionException) {
                $this->assertSame('pi_primary', data_get($purchase->fresh()->metadata, 'stripe_primary_checkout.id'));
                $this->assertSame('main', data_get($purchase->fresh()->metadata, 'stripe_primary_checkout.account'));
            }

            $payload['payment_method'] = 'pm_retry_new';
            $result = $method->invoke($controller, collect([$purchase->fresh()]), $payload, 'checkout:'.$purchase->id);
            $this->assertSame('succeeded', $result->status);
            $creates = array_values(array_filter($this->requests, fn ($request) => $request['method'] === 'post' && $request['path'] === '/v1/payment_intents'));
            $confirms = array_filter($this->requests, fn ($request) => str_ends_with($request['path'], '/confirm'));
            $this->assertCount(1, $creates);
            $this->assertArrayNotHasKey('confirm', $creates[0]['params']);
            $this->assertArrayNotHasKey('payment_method', $creates[0]['params']);
            $this->assertSame(5136, $creates[0]['params']['amount']);
            $this->assertSame('eur', $creates[0]['params']['currency']);
            $this->assertCount(1, $confirms);
            $this->assertFalse((bool) array_filter($this->requests, fn ($request) => str_contains($request['path'], 'pi_historical')));
            $this->assertDatabaseCount('facturas', 0);
        }
    }

    public function test_a_primary_intent_missing_from_the_provider_is_not_replaced_by_another_payment(): void
    {
        foreach ([ClienteController::class, BancaOnlineController::class] as $class) {
            $user = $this->user();
            $reference = 'checkout:'.$user->id;
            $purchase = Compras::create(['id_user' => $user->id, 'monto' => 10, 'pagado' => 0,
                'metadata' => ['stripe_primary_checkout' => ['account' => 'main', 'mode' => 'test',
                    'reference' => hash('sha256', $reference), 'id' => 'pi_primary_missing']]]);
            $this->requests = [];
            $this->handler = fn () => $this->response(['error' => ['type' => 'invalid_request_error',
                'code' => 'resource_missing', 'message' => 'No such intent']], 404);
            try {
                $controller = app($class);
                (new \ReflectionMethod($controller, 'primaryStripePaymentIntent'))->invoke($controller, collect([$purchase]),
                    ['amount' => 1000, 'currency' => 'eur', 'customer' => 'cus_primary', 'payment_method' => 'pm_one'], $reference);
                $this->fail('An unresolved primary intent must not create another payment');
            } catch (ApiErrorException) {
                $this->assertCount(1, $this->requests);
                $this->assertSame('get', $this->requests[0]['method']);
                $this->assertSame('pi_primary_missing', data_get($purchase->fresh()->metadata, 'stripe_primary_checkout.id'));
            }
        }
    }

    public function test_processing_captured_or_canceled_primary_intents_are_never_confirmed_again(): void
    {
        foreach ([ClienteController::class, BancaOnlineController::class] as $class) {
            foreach (['processing', 'requires_capture', 'canceled'] as $status) {
                $user = $this->user();
                $reference = 'checkout:'.$user->id;
                $purchase = Compras::create(['id_user' => $user->id, 'monto' => 10, 'pagado' => 0,
                    'metadata' => ['stripe_primary_checkout' => ['account' => 'main', 'mode' => 'test',
                        'reference' => hash('sha256', $reference), 'id' => 'pi_primary']]]);
                $this->requests = [];
                $this->handler = fn () => $this->response(['id' => 'pi_primary', 'object' => 'payment_intent',
                    'amount' => 1000, 'currency' => 'eur', 'customer' => 'cus_primary', 'status' => $status]);
                $controller = app($class);
                $result = (new \ReflectionMethod($controller, 'primaryStripePaymentIntent'))->invoke($controller,
                    collect([$purchase]), ['amount' => 1000, 'currency' => 'eur', 'customer' => 'cus_primary', 'payment_method' => 'pm_new'], $reference);
                $this->assertSame($status, $result->status);
                $this->assertCount(1, $this->requests);
                $this->assertSame('get', $this->requests[0]['method']);
            }
        }
    }

    public function test_an_uncertain_installment_schedule_reuses_its_date_parameters_and_idempotency_keys(): void
    {
        $user = $this->user();
        $metadata = ['checkout_token' => 'checkout_schedule', 'country_slug' => 'portugal', 'package_title' => 'Plan de prueba',
            'payment_plan' => ['mode' => 'installments', 'installments_count' => 3, 'installment_amount' => 100,
                'period' => 'monthly', 'period_label' => 'Mensual', 'period_plural_label' => 'mensuales']];
        $purchase = Compras::create(['id_user' => $user->id, 'monto' => 100, 'pagado' => 0, 'metadata' => $metadata]);
        $scheduleCalls = 0;
        $this->handler = function (array $request) use (&$scheduleCalls) {
            if ($request['path'] === '/v1/products') return $this->response(['id' => 'prod_primary', 'object' => 'product']);
            if ($request['path'] === '/v1/prices') return $this->response(['id' => 'price_primary', 'object' => 'price']);
            if ($request['path'] === '/v1/subscription_schedules') {
                $scheduleCalls++;
                if ($scheduleCalls === 1) throw ApiConnectionException::factory('Schedule accepted, response lost');

                return $this->response(['id' => 'sub_sched_primary', 'object' => 'subscription_schedule']);
            }
            throw new \RuntimeException('Unexpected schedule request');
        };
        $controller = app(BancaOnlineController::class);
        $method = new \ReflectionMethod($controller, 'createStripeInstallmentSchedule');
        try {
            $method->invoke($controller, $user, $metadata, (object) ['id' => 'cus_primary'], 'pm_first', collect([$purchase]));
            $this->fail('The uncertain schedule must keep its persisted request');
        } catch (ApiConnectionException) {
            $this->assertNotEmpty(data_get($purchase->fresh()->metadata, 'stripe_primary_schedule.request.schedule.start_date'));
        }
        \Carbon\Carbon::setTestNow(now()->addHour());
        $result = $method->invoke($controller, $user, $metadata, (object) ['id' => 'cus_primary'], 'pm_retry', collect([$purchase->fresh()]));
        $this->assertSame('sub_sched_primary', $result['schedule_id']);
        $callsBeforeReplay = count($this->requests);
        $again = $method->invoke($controller, $user, $metadata, (object) ['id' => 'cus_primary'], 'pm_again', collect([$purchase->fresh()]));
        $this->assertSame($result, $again);
        $this->assertCount($callsBeforeReplay, $this->requests);
        foreach (['/v1/products', '/v1/prices', '/v1/subscription_schedules'] as $path) {
            $calls = array_values(array_filter($this->requests, fn ($request) => $request['path'] === $path));
            $this->assertCount(2, $calls);
            $this->assertSame($calls[0]['params'], $calls[1]['params']);
            $keys = fn ($request) => array_values(array_filter($request['headers'], fn ($header) => str_starts_with($header, 'Idempotency-Key:')));
            $this->assertNotEmpty($keys($calls[0]));
            $this->assertSame($keys($calls[0]), $keys($calls[1]));
        }
        $schedules = array_values(array_filter($this->requests, fn ($request) => $request['path'] === '/v1/subscription_schedules'));
        $this->assertSame('pm_first', $schedules[1]['params']['default_settings']['default_payment_method']);
    }

    public function test_schedule_cancellation_never_touches_a_confirmed_processing_or_unreadable_payment(): void
    {
        $user = $this->user();
        $purchase = Compras::create(['id_user' => $user->id, 'monto' => 100, 'pagado' => 0, 'metadata' => [
            'stripe_primary_checkout' => ['account' => 'main', 'mode' => 'test', 'id' => 'pi_primary'],
            'stripe_primary_schedule' => ['account' => 'main', 'mode' => 'test', 'result' => ['schedule_id' => 'sub_sched_primary']],
        ]]);
        $controller = app(BancaOnlineController::class);
        $method = new \ReflectionMethod($controller, 'cancelStripeInstallmentSchedule');
        foreach (['succeeded', 'processing', 'requires_capture', 'unavailable'] as $status) {
            $this->requests = [];
            $this->handler = function () use ($status) {
                if ($status === 'unavailable') return $this->response(['error' => [
                    'type' => 'rate_limit_error', 'code' => 'rate_limit', 'message' => 'Synthetic unavailable']], 429);

                return $this->response(['id' => 'pi_primary', 'object' => 'payment_intent', 'status' => $status]);
            };
            $method->invoke($controller, 'sub_sched_primary', collect([$purchase->fresh()]));
            $this->assertCount(1, $this->requests);
            $this->assertSame('/v1/payment_intents/pi_primary', $this->requests[0]['path']);
            $this->assertEmpty(data_get($purchase->fresh()->metadata, 'stripe_primary_schedule.cancel_requested'));
        }
    }

    public function test_another_payment_holding_the_user_lock_blocks_both_entry_points_before_stripe_calls(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        Compras::create(['id_user' => $user->id, 'source' => app(\App\Services\BancaOnlineCatalog::class)->source(),
            'monto' => 100, 'pagado' => 0, 'metadata' => ['checkout_token' => 'locked_checkout']]);
        $lock = Cache::lock('stripe-primary-user-'.$user->id, 1800);
        $this->assertTrue($lock->get());
        try {
            $request = \Illuminate\Http\Request::create('/procesar-pago-stripe', 'POST');
            $this->assertSame(409, app(ClienteController::class)->procesarPagoStripe($request)->getStatusCode());
            $this->assertSame(409, app(BancaOnlineController::class)->processPayment($request, 'locked_checkout')->getStatusCode());
            $this->assertSame([], $this->requests);
            $this->assertDatabaseCount('facturas', 0);
        } finally {
            $lock->release();
        }
        $checkoutLock = Cache::lock('stripe-primary-banca-'.hash('sha256', 'locked_checkout'), 1800);
        $this->assertTrue($checkoutLock->get(), 'The checkout lock must be released when the user lock is busy');
        $checkoutLock->release();
    }

    public function test_a_busy_checkout_lock_blocks_payment_and_validation_failure_releases_both_locks(): void
    {
        $user = $this->user();
        Compras::create(['id_user' => $user->id, 'source' => app(\App\Services\BancaOnlineCatalog::class)->source(),
            'monto' => 100, 'pagado' => 0, 'metadata' => ['checkout_token' => 'locked_checkout']]);
        $controller = app(BancaOnlineController::class);
        $request = \Illuminate\Http\Request::create('/payment', 'POST');
        $key = 'stripe-primary-banca-'.hash('sha256', 'locked_checkout');
        $lock = Cache::lock($key, 1800);
        $this->assertTrue($lock->get());
        try {
            $this->assertSame(409, $controller->processPayment($request, 'locked_checkout')->getStatusCode());
            $this->assertSame([], $this->requests);
        } finally {
            $lock->release();
        }
        try {
            $controller->processPayment($request, 'locked_checkout');
            $this->fail('The empty payment request must fail validation');
        } catch (\Illuminate\Validation\ValidationException) {
            foreach ([$key, 'stripe-primary-user-'.$user->id] as $releasedKey) {
                $releasedLock = Cache::lock($releasedKey, 1800);
                $this->assertTrue($releasedLock->get(), 'Payment locks must be released after validation fails');
                $releasedLock->release();
            }
        }
        $this->assertSame([], $this->requests);
    }
}
