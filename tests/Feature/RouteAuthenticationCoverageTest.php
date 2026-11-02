<?php

namespace Tests\Feature;

use App\Http\Controllers\ServicioController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RouteAuthenticationCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'session.driver' => 'array',
        ]);
        DB::purge('sqlite');
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('token', 64)->unique();
        });
        Cache::flush();
        Http::preventStrayRequests();
        Mail::fake();
    }

    public function test_every_registered_route_requires_authentication_or_an_explicit_exception(): void
    {
        $public = [
            'login', 'forgot-password', 'reset-password', 'reset-password/{token}',
            'two-factor-challenge', 'sanctum/csrf-cookie', 'terms-of-service', 'privacy-policy',
            'registerv2', 'registerv2/google', 'registerv2/google/callback', 'registro-coordinador',
            'api/user/check-email', 'gracias', 'noservices',
            'banca-online-2026', 'banca-online-2026/cliente', 'banca-online-2026/pago/{token}',
            'banca-online-2026/pago/{token}/stripe', 'banca-online-2026/gracias/{token}',
            'banca-online-2026/{country}', 'banca-online-2026/{country}/{plan}', 'banca-online-2026/{plan}',
            'email/t/{recipient}/open', 'email/t/{recipient}/click/{url}', 'email/unsubscribe/{recipient}',
            'livewire/livewire.js', 'livewire/livewire.min.js.map',
        ];
        // These handlers verify provider signatures or existing integration tokens.
        $providerAuthenticated = [
            'api/marketing/ses-events', 'api/stripe/banca-online/webhook', 'api/webhooks/monday',
        ];
        $unprotected = [];

        foreach (Route::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();
            $authenticated = collect($middleware)->contains(fn (string $entry): bool =>
                $entry === 'auth' || str_starts_with($entry, 'auth:') ||
                str_starts_with($entry, 'auth.internal:')
            );
            if ($authenticated || in_array($route->uri(), $public, true) ||
                in_array($route->uri(), $providerAuthenticated, true)) {
                continue;
            }

            if (str_starts_with($route->uri(), '_ignition/') &&
                in_array(\Spatie\LaravelIgnition\Http\Middleware\RunnableSolutionsEnabled::class, $middleware, true)) {
                continue;
            }

            $unprotected[] = implode('|', $route->methods()).' '.$route->uri();
        }

        $this->assertSame([], $unprotected, 'Rutas sin autenticación ni excepción revisada: '.implode(', ', $unprotected));
    }

    public function test_internal_route_files_enforce_auth_even_without_the_provider_group(): void
    {
        $original = Route::getRoutes();

        try {
            foreach (['web.php' => 'auth', 'api.php' => 'auth:sanctum'] as $file => $required) {
                Route::setRoutes(new RouteCollection());
                require base_path('routes/'.$file);
                $this->assertGreaterThan(0, count(Route::getRoutes()));

                foreach (Route::getRoutes() as $route) {
                    $this->assertContains($required, $route->gatherMiddleware(), $file.': '.$route->uri());
                }
            }
        } finally {
            if ($original instanceof RouteCollection) {
                Route::setRoutes($original);
            } else {
                // Router::setRoutes accepts only uncached collections.
                require $this->app->getCachedRoutesPath();
            }
        }
    }

    #[DataProvider('privateEndpoints')]
    public function test_anonymous_visitors_cannot_call_private_or_package_endpoints(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized();
        Http::assertNothingSent();
    }

    public static function privateEndpoints(): array
    {
        return [
            ['GET', '/api/getservicio'], ['GET', '/api/getactivealerts'],
            ['POST', '/api/assistant/chat'], ['POST', '/api/chat/iniciar'], ['POST', '/api/chat/enviar'],
            ['POST', '/livewire/update'], ['POST', '/livewire/upload-file'],
            ['GET', '/livewire/preview-file/test-file.pdf'], ['POST', '/adminlte/darkmode/toggle'],
        ];
    }

    public function test_authenticated_api_users_can_still_read_service_data(): void
    {
        Sanctum::actingAs($this->actor());
        $this->partialMock(ServicioController::class)->shouldReceive('getservicio')->once()
            ->andReturn(response()->json(['precio' => 100]));

        $this->getJson('/api/getservicio')->assertOk()->assertJson(['precio' => 100]);
    }

    public function test_client_tokens_cannot_call_the_internal_legacy_chat(): void
    {
        Sanctum::actingAs($this->actor(true));
        foreach (['/api/assistant/chat', '/api/chat/iniciar', '/api/chat/enviar'] as $uri) {
            $this->postJson($uri)->assertForbidden();
        }
        Http::assertNothingSent();
    }

    public function test_static_integration_credentials_no_longer_replace_platform_tokens(): void
    {
        $this->withHeader('Authorization', 'Bearer old-whatsapp-token')
            ->getJson('/api/whatsapp-requests/pending')->assertUnauthorized();
        $this->withHeader('X-Webhook-Token', 'old-n8n-token')
            ->postJson('/api/n8n/tasks')->assertUnauthorized();
    }

    public function test_stripe_never_accepts_unsigned_events_when_the_secret_is_missing(): void
    {
        config(['services.stripe.banca_online_webhook_secret' => null]);
        $this->postJson('/api/stripe/banca-online/webhook', ['type' => 'invoice.payment_succeeded'])
            ->assertStatus(503);

        config(['services.stripe.banca_online_webhook_secret' => 'test-stripe-secret']);
        $this->postJson('/api/stripe/banca-online/webhook', ['type' => 'invoice.payment_succeeded'])
            ->assertStatus(400);
    }

    public function test_stripe_still_accepts_a_correctly_signed_event_without_a_user_session(): void
    {
        config(['services.stripe.banca_online_webhook_secret' => 'test-stripe-secret']);
        $payload = json_encode(['id' => 'evt_test', 'type' => 'unhandled.event', 'data' => ['object' => ['id' => 'obj_test']]]);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'test-stripe-secret');

        $this->call('POST', '/api/stripe/banca-online/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ], $payload)->assertOk()->assertJson(['received' => true]);
    }

    public function test_other_provider_webhooks_reject_unsigned_or_unauthenticated_requests(): void
    {
        config(['services.monday.webhook_secret' => 'test-monday-secret']);
        $this->postJson('/api/webhooks/monday')->assertUnauthorized();
        $this->postJson('/api/marketing/ses-events')->assertForbidden();
    }

    private function actor(bool $client = false): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 101;
        $user->shouldReceive('hasRole')->andReturnUsing(fn ($role): bool => $client && $role === 'Cliente');
        $user->shouldReceive('checkPermissionTo')->andReturnFalse();

        return $user;
    }
}
