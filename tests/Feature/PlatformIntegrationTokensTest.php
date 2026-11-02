<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminIntegrationTokenController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\InternalTaskWorkflowController;
use App\Http\Controllers\JotformCouponCronController;
use App\Http\Controllers\N8nTaskWebhookController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TaskCronController;
use App\Http\Controllers\TeamleaderCronController;
use App\Http\Controllers\WhatsappController;
use App\Http\Middleware\AuditRequests;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlatformIntegrationTokensTest extends TestCase
{
    private string $auditPath;

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
        Cache::flush();
        Http::preventStrayRequests();
        Mail::fake();
        $this->withoutMiddleware(AuditRequests::class);
        $this->auditPath = tempnam(sys_get_temp_dir(), 'sefar-integration-audit-');
        config(['mcp.audit_log' => $this->auditPath, 'mcp.audit_secret' => 'test-audit-secret']);

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        require_once database_path('migrations/2021_03_08_173429_create_permission_tables.php');
        (new \CreatePermissionTables())->up();
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        if (isset($this->auditPath)) {
            @unlink($this->auditPath);
        }
        parent::tearDown();
    }

    #[DataProvider('integrationEndpoints')]
    public function test_each_operation_rejects_missing_wrong_revoked_expired_and_client_tokens(
        string $method, string $uri, string $ability, ?string $controller, ?string $action
    ): void {
        Artisan::shouldReceive('call')->never();
        $user = $this->internalUser();
        $this->request($method, $uri)->assertUnauthorized();
        $this->request($method, $uri, 'invalid')->assertUnauthorized();

        $wrong = $user->createToken('Unrelated token', ['read', 'mcp:read']);
        $this->request($method, $uri, $wrong->plainTextToken)->assertForbidden();

        $revoked = $user->createToken('Revoked', [$ability]);
        $revoked->accessToken->delete();
        $this->request($method, $uri, $revoked->plainTextToken)->assertUnauthorized();

        $expired = $user->createToken('Expired', [$ability], now()->subMinute());
        $this->request($method, $uri, $expired->plainTextToken)->assertUnauthorized();

        $client = $this->internalUser('client@example.test');
        $client->assignRole(Role::findOrCreate('Cliente', 'web'));
        $clientToken = $client->createToken('Client token', [$ability]);
        $this->request($method, $uri, $clientToken->plainTextToken)->assertForbidden();
        Http::assertNothingSent();
    }

    #[DataProvider('integrationEndpoints')]
    public function test_each_operation_accepts_a_real_platform_token_with_its_exact_permission(
        string $method, string $uri, string $ability, ?string $controller, ?string $action
    ): void {
        $token = $this->internalUser()->createToken('Integration worker', [$ability]);
        if ($ability === 'deploy:run') {
            $token->accessToken->tokenable->assignRole(Role::findOrCreate('Administrador', 'web'));
        }
        if ($controller !== null) {
            $this->partialMock($controller)->shouldReceive($action)->once()
                ->andReturn(response()->json(['ok' => true]));
            Artisan::shouldReceive('call')->never();
        } else {
            Artisan::shouldReceive('call')->once()->andReturn(0);
            Artisan::shouldReceive('output')->zeroOrMoreTimes()->andReturn('');
        }

        $this->request($method, $uri, $token->plainTextToken)->assertOk();
        $this->assertNotNull($token->accessToken->fresh()->last_used_at);
        $events = $this->events();
        $this->assertSame('integration_request_started', $events[0]['event']);
        $this->assertSame($token->accessToken->id, $events[0]['target']['token_id']);
        $this->assertSame($ability, $events[0]['required_ability']);
        $this->assertSame('ok', $events[1]['status']);
        $this->assertSame($events[0]['actor'], $events[1]['actor']);
        $this->assertSame($events[0]['path'], $events[1]['path']);
        $this->assertStringNotContainsString($token->plainTextToken, file_get_contents($this->auditPath));
    }

    public static function integrationEndpoints(): array
    {
        return [
            ['POST', '/api/deploy', 'deploy:run', DeployController::class, 'deploy'],
            ['GET', '/api/whatsapp-requests/pending', 'whatsapp:read', WhatsappController::class, 'pending'],
            ['POST', '/api/whatsapp-requests/1/success', 'whatsapp:update', WhatsappController::class, 'success'],
            ['POST', '/api/whatsapp-requests/1/fail', 'whatsapp:update', WhatsappController::class, 'fail'],
            ['POST', '/api/n8n/tasks', 'tasks:create', N8nTaskWebhookController::class, 'store'],
            ['GET', '/cron/scheduler-run', 'cron:scheduler:run', null, null],
            ['GET', '/cron/queue-worker', 'cron:queue:work', null, null],
            ['GET', '/cron/followups-registration-payment', 'cron:followups:run', null, null],
            ['GET', '/makereport', 'cron:reports:generate', ReportController::class, 'makereport'],
            ['GET', '/cron/teamleader/sync', 'cron:teamleader:sync', TeamleaderCronController::class, 'sync'],
            ['GET', '/cron/teamleader/work', 'cron:teamleader:work', TeamleaderCronController::class, 'work'],
            ['GET', '/cron/tasks/work', 'cron:tasks:work', TaskCronController::class, 'work'],
            ['GET', '/cron/jotform/coupons', 'cron:jotform:sync', JotformCouponCronController::class, '__invoke'],
            ['GET', '/internal/tasks/daily-workflow', 'tasks:workflow', InternalTaskWorkflowController::class, '__invoke'],
        ];
    }

    public function test_a_browser_session_and_legacy_credentials_cannot_replace_a_personal_access_token(): void
    {
        Artisan::shouldReceive('call')->never();
        $user = $this->internalUser();
        $this->actingAs($user, 'web')->getJson('/cron/scheduler-run')->assertUnauthorized();
        $this->withHeader('X-Webhook-Token', 'old-static-secret')->postJson('/api/n8n/tasks')->assertUnauthorized();
        $this->getJson('/cron/teamleader/sync?token=old-static-secret')->assertUnauthorized();
    }

    public function test_deploy_requires_an_admin_owner_and_an_exclusive_deployment_token(): void
    {
        $this->partialMock(DeployController::class)->shouldNotReceive('deploy');
        $owner = $this->internalUser();
        $token = $owner->createToken('Not an administrator', ['deploy:run']);
        $this->request('POST', '/api/deploy', $token->plainTextToken)->assertForbidden();

        $owner->assignRole(Role::findOrCreate('Administrador', 'web'));
        foreach ([['*'], ['deploy:run', 'read'], ['cron:scheduler:run']] as $abilities) {
            Cache::flush();
            $token = $owner->createToken('Not a dedicated deploy token', $abilities);
            $this->request('POST', '/api/deploy', $token->plainTextToken)->assertForbidden();
        }

        Cache::flush();
        $token = $owner->createToken('Administrator removed after issue', ['deploy:run']);
        $owner->removeRole('Administrador');
        $this->request('POST', '/api/deploy', $token->plainTextToken)->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_deploy_never_accepts_get_query_tokens_or_browser_sessions(): void
    {
        $this->partialMock(DeployController::class)->shouldNotReceive('deploy');
        $owner = $this->internalUser();
        $owner->assignRole(Role::findOrCreate('Administrador', 'web'));
        $token = $owner->createToken('Deployment', ['deploy:run']);
        $this->actingAs($owner)->postJson('/api/deploy')->assertUnauthorized();
        $this->request('POST', '/api/deploy?token='.urlencode($token->plainTextToken))->assertUnauthorized();
        $this->request('GET', '/api/deploy', $token->plainTextToken)->assertStatus(405);
        $this->request('GET', '/deploy', $token->plainTextToken)->assertStatus(405);
    }

    public function test_deploy_token_creation_enforces_admin_ownership_and_exclusive_scope(): void
    {
        $admin = $this->internalUser('admin@example.test');
        $admin->assignRole(Role::findOrCreate('Administrador', 'web'));
        $operator = $this->internalUser();
        $this->actingAs($admin);
        $this->postJson(route('admin.integrations.api-tokens.store'), [
            'user_id' => $operator->id, 'name' => 'Deploy', 'abilities' => ['deploy:run'],
        ])->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->postJson(route('admin.integrations.api-tokens.store'), [
            'user_id' => $admin->id, 'name' => 'Deploy', 'abilities' => ['read', 'deploy:run'],
        ])->assertUnprocessable()->assertJsonValidationErrors('abilities');
        $this->assertSame(0, PersonalAccessToken::count());

        $view = app(AdminIntegrationTokenController::class)->apiTokens();
        $this->assertArrayHasKey('deploy:run', $view->getData()['abilities']);
        $this->post(route('admin.integrations.api-tokens.store'), [
            'user_id' => $admin->id, 'name' => 'cron-job.org Deploy', 'abilities' => ['deploy:run'],
        ])->assertRedirect()->assertSessionHas('created_token');
        $token = PersonalAccessToken::findToken(session('created_token'));
        $this->assertSame(['deploy:run'], $token->abilities);
        $this->assertSame($admin->id, $token->tokenable_id);
    }

    public function test_deployment_requests_are_limited_to_one_per_minute_per_owner(): void
    {
        $owner = $this->internalUser();
        $owner->assignRole(Role::findOrCreate('Administrador', 'web'));
        $token = $owner->createToken('Deployment', ['deploy:run']);
        $this->partialMock(DeployController::class)->shouldReceive('deploy')->once()
            ->andReturn(response()->json(['ok' => true]));

        $this->request('POST', '/api/deploy', $token->plainTextToken)->assertOk();
        $this->request('POST', '/api/deploy', $token->plainTextToken)->assertStatus(429);
        $this->assertStringNotContainsString($token->plainTextToken, file_get_contents($this->auditPath));
    }

    public function test_read_and_update_permissions_do_not_grant_each_other(): void
    {
        $user = $this->internalUser();
        $read = $user->createToken('Read only', ['whatsapp:read']);
        $update = $user->createToken('Update only', ['whatsapp:update']);

        $this->request('POST', '/api/whatsapp-requests/1/success', $read->plainTextToken)->assertForbidden();
        $this->request('GET', '/api/whatsapp-requests/pending', $update->plainTextToken)->assertForbidden();
    }

    public function test_cron_calls_remain_rate_limited_with_platform_tokens(): void
    {
        $token = $this->internalUser()->createToken('Scheduler', ['cron:scheduler:run']);
        Artisan::shouldReceive('call')->times(6)->with('schedule:run')->andReturn(0);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->request('GET', '/cron/scheduler-run', $token->plainTextToken)->assertOk();
        }
        $this->request('GET', '/cron/scheduler-run', $token->plainTextToken)->assertStatus(429);
    }

    public function test_n8n_reaches_validation_and_audit_excludes_message_bodies(): void
    {
        $token = $this->internalUser()->createToken('n8n', ['tasks:create']);
        $this->request('POST', '/api/n8n/tasks', $token->plainTextToken, [
            'description' => 'private-body-must-not-be-logged',
        ])->assertUnprocessable();

        $audit = file_get_contents($this->auditPath);
        $this->assertStringNotContainsString('private-body-must-not-be-logged', $audit);
        $this->assertStringNotContainsString($token->plainTextToken, $audit);
        $this->assertSame(422, $this->events()[1]['status_code']);
    }

    public function test_the_existing_token_platform_lists_creates_and_revokes_integration_permissions(): void
    {
        $admin = $this->internalUser('admin@example.test');
        $admin->assignRole(Role::findOrCreate('Administrador', 'web'));
        $owner = $this->internalUser();
        $this->actingAs($admin, 'web');

        $view = app(AdminIntegrationTokenController::class)->apiTokens();
        $this->assertArrayHasKey('whatsapp:read', $view->getData()['abilities']);
        $this->assertArrayHasKey('cron:scheduler:run', $view->getData()['abilities']);

        $this->post(route('admin.integrations.api-tokens.store'), [
            'user_id' => $owner->id, 'name' => 'WhatsApp production',
            'abilities' => ['whatsapp:read', 'whatsapp:update'],
        ])->assertRedirect()->assertSessionHas('created_token');
        $plainText = session('created_token');
        $token = PersonalAccessToken::findToken($plainText);
        $this->assertSame(['whatsapp:read', 'whatsapp:update'], $token->abilities);
        $this->assertSame($owner->id, $token->tokenable_id);

        $this->delete(route('admin.integrations.tokens.destroy', $token))->assertRedirect();
        $this->assertNull(PersonalAccessToken::findToken($plainText));
        $this->assertStringNotContainsString($plainText, file_get_contents($this->auditPath));
    }

    public function test_admin_audit_page_can_filter_the_new_integration_events(): void
    {
        $token = $this->internalUser()->createToken('Audit check', ['whatsapp:read']);
        $this->partialMock(WhatsappController::class)->shouldReceive('pending')->once()
            ->andReturn(response()->json([]));
        $this->request('GET', '/api/whatsapp-requests/pending', $token->plainTextToken)->assertOk();

        $view = app(AdminIntegrationTokenController::class)->audit(
            \Illuminate\Http\Request::create('/admin/integrations/audit?source=integration_http', 'GET')
        );
        $data = $view->getData();
        $this->assertCount(2, $data['events']);
        $this->assertSame(2, $data['summary']['integrations']);
        $this->assertSame('integration_http', $data['events']->first()['source']);
    }

    private function internalUser(string $email = 'integration@example.test'): User
    {
        $id = DB::table('users')->insertGetId(['name' => 'Integration user', 'email' => $email]);

        return User::findOrFail($id);
    }

    private function request(string $method, string $uri, ?string $token = null, array $data = [])
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->setDefaultDriver('web');
        $this->flushHeaders();
        if ($token !== null) {
            $this->withToken($token);
        }

        return $this->json($method, $uri, $data);
    }

    private function events(): array
    {
        return array_map(fn (string $line): array => json_decode($line, true),
            file($this->auditPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    }
}
