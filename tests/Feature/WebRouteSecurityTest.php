<?php

namespace Tests\Feature;

use App\Http\Controllers\BancaOnlineController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\MarketingTrackingController;
use App\Http\Controllers\RegisterV2Controller;
use App\Http\Middleware\AuditRequests;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebRouteSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Deliberately avoid RefreshDatabase: legacy migrations use external databases.
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
        $this->withoutMiddleware(AuditRequests::class);

        foreach (['administrador', 'crud.users.index', 'crud.stripeverify.index',
            'crud.mondayreportes.index', 'administrar.documentos', 'genealogista',
            'crud.files.index', 'crud.files.create', 'crud.files.edit',
            'crud.coupons.edit', 'crud.solicitudcupones.index', 'crud.comprobantes.index'] as $ability) {
            Gate::define($ability, fn (User $user): bool => $user->email === 'admin@example.test');
        }
        Gate::define('notCliente', fn (User $user): bool => $user->email === 'admin@example.test');
    }

    #[DataProvider('internalRequests')]
    public function test_guests_are_rejected_before_internal_actions_run(string $method, string $uri): void
    {
        Artisan::shouldReceive('call')->never();

        $this->json($method, $uri)->assertUnauthorized();
    }

    #[DataProvider('privilegedRequests')]
    public function test_regular_users_cannot_run_privileged_actions(string $method, string $uri): void
    {
        Artisan::shouldReceive('call')->never();
        $this->actingAs($this->actor());

        $this->json($method, $uri)->assertForbidden();
    }

    public static function internalRequests(): array
    {
        return array_merge(self::privilegedRequests(), [
            ['POST', '/changepassword'],
            ['POST', '/client/requests/1/upload'],
            ['GET', '/viewcomprobantecliente/1'],
            ['POST', '/procesar-pago-stripe'],
        ]);
    }

    public static function privilegedRequests(): array
    {
        return [
            ['POST', '/deploy'],
            ['POST', '/storage-link'],
            ['POST', '/config-cache'],
            ['POST', '/cache-clear'],
            ['POST', '/route-clear'],
            ['POST', '/config-clear'],
            ['POST', '/view-clear'],
            ['POST', '/admin/process-queue'],
            ['POST', '/hubspot/sync-client-owners'],
            ['POST', '/adminchangepassword'],
            ['GET', '/request-audits'],
            ['GET', '/admin/procesos'],
            ['GET', '/whatsapp/url'],
            ['POST', '/whatsapp/url'],
            ['GET', '/whatsapp/numbers'],
            ['POST', '/admin/requests/1/approve'],
            ['POST', '/admin/requests/1/reject'],
            ['DELETE', '/admin/requests/1'],
            ['POST', '/openfile'],
            ['POST', '/storefile'],
            ['POST', '/getfileupdate'],
            ['POST', '/exonerarfase1'],
            ['POST', '/stripeupdatedata'],
            ['POST', '/registrarMD'],
            ['POST', '/updatetreena'],
            ['POST', '/testcorreos'],
            ['GET', '/viewcomprobante/1'],
            ['POST', '/cuponaceptar/1'],
            ['POST', '/cuponrechazar/1'],
            ['POST', '/fixCouponHubspot'],
            ['POST', '/fixPayDataHubspot'],
        ];
    }

    public function test_browser_guests_are_redirected_to_login(): void
    {
        $this->get('/request-audits')->assertRedirect(route('login'));
    }

    public function test_maintenance_get_urls_cannot_execute_commands_even_for_an_admin(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->actingAs($this->actor(true));

        foreach (['deploy', 'storage-link', 'config-cache', 'cache-clear', 'route-clear',
            'config-clear', 'view-clear', 'hubspot/sync-client-owners', 'cuponaceptar/1',
            'cuponrechazar/1', 'fixCouponHubspot', 'fixPayDataHubspot'] as $uri) {
            $this->getJson('/'.$uri)->assertStatus(405);
        }

        $this->getJson('/key-generate')->assertNotFound();
        $this->postJson('/key-generate')->assertNotFound();
    }

    public function test_admin_can_run_an_authorized_maintenance_post(): void
    {
        Artisan::shouldReceive('call')->once()->with('view:clear')->andReturn(0);

        $this->actingAs($this->actor(true))->post('/view-clear')->assertOk();
    }

    public function test_deploy_requires_an_admin_and_a_valid_csrf_token(): void
    {
        // Laravel normally skips CSRF verification while testing; enable it for this case.
        $this->app->instance(VerifyCsrfToken::class, new class($this->app, $this->app['encrypter']) extends VerifyCsrfToken {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->partialMock(DeployController::class)
            ->shouldReceive('deploy')->once()->andReturn(response()->json(['ok' => true]));

        $this->actingAs($this->actor(true));
        $this->postJson('/deploy')->assertStatus(419);
        $this->withSession(['_token' => 'test-csrf-token'])
            ->postJson('/deploy', ['_token' => 'test-csrf-token'])->assertOk();
    }

    #[DataProvider('maintenanceCronUrls')]
    public function test_cron_requires_a_platform_token_even_for_admin_sessions(string $uri): void
    {
        Artisan::shouldReceive('call')->never();
        $this->getJson($uri)->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer old-static-token')->getJson($uri)->assertUnauthorized();
        $this->actingAs($this->actor(true))->flushHeaders()->getJson($uri)->assertUnauthorized();
    }

    public static function maintenanceCronUrls(): array
    {
        return [
            ['/cron/scheduler-run'], ['/cron/queue-worker'], ['/cron/followups-registration-payment'],
            ['/makereport'], ['/cron/teamleader/sync'], ['/cron/teamleader/work'],
            ['/cron/tasks/work'], ['/cron/jotform/coupons'], ['/internal/tasks/daily-workflow'],
        ];
    }

    public function test_registration_checkout_and_marketing_links_remain_public(): void
    {
        $registration = $this->partialMock(RegisterV2Controller::class);
        $registration->shouldReceive('index')->once()->andReturn(response('registration'));
        $registration->shouldReceive('store')->once()->andReturn(response('registered'));
        $checkout = $this->partialMock(BancaOnlineController::class);
        $checkout->shouldReceive('landing')->once()->andReturn(response('checkout'));
        $checkout->shouldReceive('processPayment')->once()->andReturn(response('payment'));
        $this->partialMock(MarketingTrackingController::class)->shouldReceive('open')->once()->andReturn(response('tracking'));

        $this->get('/registerv2')->assertOk()->assertSee('registration');
        $this->post('/registerv2')->assertOk()->assertSee('registered');
        $this->get('/banca-online-2026')->assertOk()->assertSee('checkout');
        $this->post('/banca-online-2026/pago/test-payment-token/stripe')->assertOk()->assertSee('payment');
        $this->get('/email/t/test-recipient/open')->assertOk()->assertSee('tracking');
    }

    public function test_a_client_cannot_replace_another_clients_requested_document(): void
    {
        $document = $this->requestedDocument(202);
        Storage::fake('s3');
        Storage::disk('s3')->put('existing.pdf', 'original document');

        $this->actingAs($this->actor())->postJson('/client/requests/'.$document->id.'/upload', [
            'file' => UploadedFile::fake()->create('replacement.pdf', 10, 'application/pdf'),
        ])->assertForbidden();

        $this->assertSame('original document', Storage::disk('s3')->get('existing.pdf'));
        $this->assertSame('existing.pdf', $document->fresh()->file_path);
    }

    public function test_a_client_can_upload_their_own_requested_document(): void
    {
        $document = $this->requestedDocument(101);
        Storage::fake('s3');

        $this->actingAs($this->actor())->postJson('/client/requests/'.$document->id.'/upload', [
            'file' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
        ])->assertOk();

        $this->assertSame('resuelto', $document->fresh()->status);
        Storage::disk('s3')->assertExists($document->fresh()->file_path);
    }

    public function test_the_no_document_action_checks_ownership_and_is_routable(): void
    {
        $document = $this->requestedDocument(202);
        $this->actingAs($this->actor());
        $this->postJson('/client/requests/'.$document->id.'/no-doc')->assertForbidden();

        $document->update(['user_id' => 101]);
        $this->postJson('/client/requests/'.$document->id.'/no-doc')->assertOk();
        $this->assertSame('no_documento', $document->fresh()->status);
    }

    private function actor(bool $admin = false): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 101;
        $user->email = $admin ? 'admin@example.test' : 'client@example.test';
        $user->passport = 'TEST-101';
        $user->password = 'test-password-hash';
        $user->shouldReceive('hasRole')->andReturnFalse();
        $user->shouldReceive('checkPermissionTo')->andReturnFalse();

        return $user;
    }

    private function requestedDocument(int $userId): DocumentRequest
    {
        Schema::create('document_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('document_name');
            $table->string('status');
            $table->string('file_path')->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamp('no_document_button_at')->nullable();
            $table->timestamps();
        });

        return DocumentRequest::create([
            'user_id' => $userId,
            'document_name' => 'Nacimiento',
            'status' => 'en_espera_cliente',
            'file_path' => 'existing.pdf',
            'no_document_button_at' => now()->subDay(),
        ]);
    }
}
