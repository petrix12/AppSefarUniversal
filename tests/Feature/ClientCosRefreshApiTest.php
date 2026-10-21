<?php

namespace Tests\Feature;

use App\Exceptions\TeamleaderAuthenticationException;
use App\Exceptions\TeamleaderServiceUnavailableException;
use App\Models\MondayData;
use App\Models\Negocio;
use App\Models\User;
use App\Services\AutomaticTeamleaderDealAssociationService;
use App\Services\ClientCosSnapshotService;
use App\Services\CosHelperService;
use App\Services\HubspotService;
use App\Services\TeamleaderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientCosRefreshApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array', 'services.openrouter.key' => null]);
        Cache::flush();
        Http::preventStrayRequests();
        Role::firstOrCreate(['name' => 'Cliente', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        if (! Schema::hasColumn('users', 'tl_id')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('tl_id')->nullable());
        }
        if (! Schema::hasColumn('negocios', 'dealname')) {
            Schema::table('negocios', fn (Blueprint $table) => $table->string('dealname')->nullable());
        }
        $this->mock(HubspotService::class);
        $this->mock(TeamleaderService::class);
    }

    public function test_it_rejects_missing_and_invalid_tokens(): void
    {
        $client = User::factory()->create();
        $this->postJson($this->url($client))->assertUnauthorized();
        $this->withToken('invalid')->postJson($this->url($client))->assertUnauthorized();
        $this->withToken('invalid')->postJson('/api/v1/clientes/cos/refresh')->assertUnauthorized();
    }

    public function test_a_web_session_cannot_replace_the_api_token(): void
    {
        $client = User::factory()->create();
        $this->actingAs($this->internalUser());

        $this->postJson($this->url($client))->assertUnauthorized();
        $this->withToken('invalid')->postJson($this->url($client))->assertUnauthorized();
        $this->withToken('invalid')->postJson('/api/v1/clientes/cos/refresh')->assertUnauthorized();
    }

    public function test_read_and_mcp_tokens_do_not_grant_refresh_permission(): void
    {
        $client = User::factory()->create();
        $token = $this->internalUser()->createToken('Read only', ['read', 'mcp:read'])->plainTextToken;

        $this->withToken($token)->postJson($this->url($client))
            ->assertForbidden()
            ->assertJsonPath('message', 'Se requiere un token de usuario interno con permiso cos:refresh.');
        $this->withToken($token)->postJson('/api/v1/clientes/cos/refresh')->assertForbidden();
    }

    public function test_a_client_cannot_refresh_even_with_the_ability(): void
    {
        $client = User::factory()->create();
        $token = $client->createToken('Client', ['cos:refresh'])->plainTextToken;

        $this->withToken($token)->postJson($this->url($client))->assertForbidden();
        $this->withToken($token)->postJson('/api/v1/clientes/cos/refresh')->assertForbidden();
    }

    public function test_revoked_and_expired_tokens_are_rejected(): void
    {
        $client = User::factory()->create();
        $user = $this->internalUser();
        $revoked = $user->createToken('Revoked', ['cos:refresh']);
        $revoked->accessToken->delete();
        $expired = $user->createToken('Expired', ['cos:refresh'], now()->subMinute());

        $this->withToken($revoked->plainTextToken)->postJson($this->url($client))->assertUnauthorized();
        $this->withToken($expired->plainTextToken)->postJson($this->url($client))->assertUnauthorized();
    }

    public function test_the_target_must_be_an_existing_client(): void
    {
        $internal = $this->internalUser();
        $token = $internal->createToken('Refresh', ['cos:refresh'])->plainTextToken;

        $this->withToken($token)->postJson($this->url($internal))->assertNotFound();
        $this->withToken($token)->postJson('/api/v1/clientes/999999/cos/refresh')->assertNotFound();
    }

    #[DataProvider('lookupInputs')]
    public function test_lookup_refreshes_the_client_by_email_or_passport(array $input, string $matchedBy): void
    {
        $client = User::factory()->create(['email' => 'client@example.test', 'passport' => 'AB-123']);
        $token = $this->internalUser()->createToken('Refresh', ['cos:refresh'])->plainTextToken;
        $this->expectForcedRefresh($client);

        $this->withToken($token)->postJson('/api/v1/clientes/cos/refresh', $input)
            ->assertOk()
            ->assertJsonPath('data.client.id', $client->id)
            ->assertJsonPath('data.client_id', $client->id)
            ->assertJsonPath('meta.found', true)
            ->assertJsonPath('meta.refreshed', true)
            ->assertJsonPath('meta.matched_by', $matchedBy)
            ->assertJsonPath('meta.forced_refresh', true);
    }

    public static function lookupInputs(): array
    {
        return [
            'email only' => [['email' => 'client@example.test'], 'email'],
            'passport only' => [['passport' => 'AB-123'], 'passport'],
            'email absent, passport matches' => [['email' => 'absent@example.test', 'passport' => 'AB-123'], 'passport'],
            'normalized email matches, passport absent' => [['email' => ' CLIENT@EXAMPLE.TEST ', 'passport' => 'absent'], 'email'],
            'normalized passport with null email' => [['email' => null, 'passport' => ' ab-123 '], 'passport'],
        ];
    }

    public function test_lookup_prioritizes_the_email_match_and_refreshes_only_one_client(): void
    {
        $emailClient = User::factory()->create(['email' => 'client@example.test', 'passport' => 'EMAIL-PASSPORT']);
        $passportClient = User::factory()->create(['passport' => 'OTHER-PASSPORT', 'arraycos' => [['unchanged' => true]]]);
        $token = $this->internalUser()->createToken('Refresh', ['cos:refresh'])->plainTextToken;
        $this->expectForcedRefresh($emailClient);

        $this->withToken($token)->postJson('/api/v1/clientes/cos/refresh', [
            'email' => $emailClient->email, 'passport' => $passportClient->passport,
        ])
            ->assertOk()
            ->assertJsonPath('data.client.id', $emailClient->id)
            ->assertJsonPath('meta.matched_by', 'email');

        $this->assertSame([['unchanged' => true]], $passportClient->fresh()->arraycos);
    }

    public function test_lookup_returns_an_alert_without_mutations_or_external_calls_when_no_client_matches(): void
    {
        $client = $this->linkedClient();
        $previous = $client->fresh()->getRawOriginal();
        Negocio::create(['user_id' => $client->id, 'hubspot_id' => '10']);
        $token = $this->internalUser()->createToken('Refresh', ['cos:refresh'])->plainTextToken;
        $count = User::count();
        $this->app->bind(ClientCosSnapshotService::class, function () {
            throw new \LogicException('No refresh service may be resolved without a client match.');
        });

        $response = $this->withToken($token)->postJson('/api/v1/clientes/cos/refresh', [
            'email' => 'absent@example.test', 'passport' => 'COS-API',
        ])
            ->assertOk()
            ->assertJsonPath('alert.code', 'client_not_found')
            ->assertJsonPath('alert.type', 'warning')
            ->assertJsonPath('data', null)
            ->assertJsonPath('meta.found', false)
            ->assertJsonPath('meta.refreshed', false)
            ->assertJsonPath('meta.forced_refresh', false);

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame($count, User::count());
        $this->assertSame($previous, $client->fresh()->getRawOriginal());
        $this->assertDatabaseHas('negocios', ['user_id' => $client->id, 'hubspot_id' => '10']);
        $this->assertDatabaseCount('monday_data', 0);
        Http::assertNothingSent();
    }

    public function test_lookup_does_not_select_an_internal_user(): void
    {
        $internal = $this->internalUser();
        $token = $internal->createToken('Refresh', ['cos:refresh'])->plainTextToken;
        $this->mock(ClientCosSnapshotService::class, fn ($mock) => $mock->shouldNotReceive('get'));

        $this->withToken($token)->postJson('/api/v1/clientes/cos/refresh', ['email' => $internal->email])
            ->assertOk()
            ->assertJsonPath('alert.code', 'client_not_found')
            ->assertJsonPath('meta.refreshed', false);
    }

    public function test_lookup_requires_at_least_one_valid_identifier(): void
    {
        $token = $this->internalUser()->createToken('Refresh', ['cos:refresh'])->plainTextToken;
        $this->mock(ClientCosSnapshotService::class, fn ($mock) => $mock->shouldNotReceive('get'));

        $this->withToken($token)->postJson('/api/v1/clientes/cos/refresh')
            ->assertUnprocessable()->assertJsonValidationErrors(['email', 'passport']);
        $this->withToken($token)->postJson('/api/v1/clientes/cos/refresh', ['email' => ' ', 'passport' => ' '])
            ->assertUnprocessable()->assertJsonValidationErrors(['email', 'passport']);
        $this->withToken($token)->postJson('/api/v1/clientes/cos/refresh', ['email' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->withToken($token)->postJson('/api/v1/clientes/cos/refresh', ['passport' => ['AB-123']])
            ->assertUnprocessable()->assertJsonValidationErrors('passport');
    }

    public function test_an_administrator_can_issue_a_token_with_the_refresh_ability(): void
    {
        config(['mcp.audit_log' => storage_path('framework/testing/cos-refresh-token-audit.jsonl')]);
        $admin = $this->internalUser();
        $owner = $this->internalUser();

        $this->actingAs($admin)->from('/admin/integrations/api-tokens')
            ->post(route('admin.integrations.api-tokens.store'), [
                'user_id' => $owner->id, 'name' => 'Refresh integration',
                'abilities' => ['cos:refresh', 'client-notes:create'],
            ])
            ->assertRedirect('/admin/integrations/api-tokens')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('created_token');

        $this->assertSame(['cos:refresh', 'client-notes:create'], $owner->tokens()->firstOrFail()->abilities);
    }

    public function test_the_cos_client_id_can_be_used_to_create_a_note_with_the_same_token(): void
    {
        $client = User::factory()->create(['passport' => 'COS-NOTE-FLOW']);
        $actor = $this->internalUser();
        $token = $actor->createToken('n8n', ['cos:refresh', 'client-notes:create'])->plainTextToken;
        $this->expectForcedRefresh($client);

        $response = $this->withToken($token)->postJson('/api/v1/clientes/cos/refresh', ['passport' => $client->passport])
            ->assertOk()->assertJsonPath('data.client_id', $client->id);
        $clientId = $response->json('data.client_id');

        $this->withToken($token)->postJson("/api/v1/clientes/{$clientId}/notas", ['note' => 'COS revisado por n8n.'])
            ->assertCreated()->assertJsonPath('data.client_id', $client->id);

        $this->assertDatabaseHas('client_notes', [
            'client_id' => $client->id, 'user_id' => $actor->id, 'note' => 'COS revisado por n8n.',
        ]);
    }

    public function test_a_note_can_be_saved_using_the_client_id_returned_after_a_cos_refresh_failure(): void
    {
        $client = $this->linkedClient();
        $previousCos = $client->arraycos;
        $previousExpiry = $client->arraycos_expire;
        $actor = $this->internalUser();
        $token = $actor->createToken('n8n', ['cos:refresh', 'client-notes:create'])->plainTextToken;
        $this->mock(ClientCosSnapshotService::class, fn ($mock) => $mock
            ->shouldReceive('get')->once()->andThrow(new \RuntimeException('Upstream unavailable')));

        $response = $this->withToken($token)->postJson('/api/v1/clientes/cos/refresh', ['email' => $client->email])
            ->assertStatus(502)
            ->assertJsonPath('error', 'cos_refresh_failed')
            ->assertJsonPath('data.client_id', $client->id);
        $clientId = $response->json('data.client_id');

        $this->withToken($token)->postJson("/api/v1/clientes/{$clientId}/notas", ['note' => 'Seguimiento pendiente de sincronización.'])
            ->assertCreated()->assertJsonPath('data.client_id', $client->id);

        $this->assertSame($previousCos, $client->fresh()->arraycos);
        $this->assertTrue($previousExpiry->equalTo($client->fresh()->arraycos_expire));
        $this->assertDatabaseHas('client_notes', [
            'client_id' => $client->id, 'user_id' => $actor->id, 'note' => 'Seguimiento pendiente de sincronización.',
        ]);
    }

    public function test_every_call_refreshes_external_data_local_deals_and_cos_despite_fresh_caches(): void
    {
        $client = $this->linkedClient();
        $token = $this->internalUser()->createToken('Refresh', ['cos:refresh'])->plainTextToken;
        $other = User::factory()->create();
        Negocio::create(['user_id' => $client->id, 'hubspot_id' => '10', 'dealname' => 'Old']);
        Negocio::create(['user_id' => $client->id, 'hubspot_id' => '99', 'dealname' => 'Removed']);
        Negocio::create(['user_id' => $other->id, 'hubspot_id' => '77', 'dealname' => 'Other client']);
        $this->mock(CosHelperService::class, fn ($mock) => $mock->shouldReceive('get')
            ->with(false)->twice()->andReturn(array_cos()));
        $this->mock(AutomaticTeamleaderDealAssociationService::class, fn ($mock) => $mock
            ->shouldReceive('associate')->twice()->andReturn(['linked' => 0]));

        $this->mock(HubspotService::class, function ($mock) use ($client) {
            $mock->shouldReceive('executeConcurrent')->twice()->andReturnUsing(
                fn (array $callbacks) => array_map(fn ($callback) => $callback(), $callbacks)
            );
            $mock->shouldReceive('getContactById')->with($client->hs_id)->twice()->andReturn([
                'id' => $client->hs_id,
                'properties' => ['lastmodifieddate' => now()->subYear()->toIso8601String()],
            ]);
            $mock->shouldReceive('getContactFileFields')->with($client->hs_id)->twice()->andReturn([]);
            $deal = ['id' => '10', 'properties' => [
                'dealname' => 'Updated', 'servicio_solicitado' => 'Española Sefardi',
                'n3__informe_cargado' => now()->toDateString(),
            ]];
            $certifiedDeal = $deal;
            $certifiedDeal['properties']['n4__certificado_descargado'] = now()->toDateString();
            $mock->shouldReceive('getDealsByContactId')->with($client->hs_id)->twice()
                ->andReturn([$deal], [$certifiedDeal]);
        });
        $this->mock(TeamleaderService::class, function ($mock) use ($client) {
            $mock->shouldReceive('getContactById')->with($client->tl_id)->twice()
                ->andReturn(['id' => $client->tl_id]);
            $mock->shouldReceive('getProjectsWithDetailsByCustomerId')->with($client->tl_id, true)->twice()
                ->andReturn([]);
        });
        $item = $this->mondayItem($client);
        \Monday::shouldReceive('customQuery')->with(Mockery::on(fn ($query) => str_contains($query, 'items(ids:')))
            ->twice()->andReturn(['items' => [$item]]);
        \Monday::shouldReceive('customQuery')->with(Mockery::on(fn ($query) => str_contains($query, 'boards(ids:')))
            ->twice()->andReturn(['boards' => [['columns' => []]]]);
        Cache::put('cos.monday.columns.11', true, 3600);

        $this->withToken($token)->postJson('/api/v1/clientes/cos/refresh', [
            'email' => $client->email, 'passport' => 'absent', 'sync' => false, 'force_refresh' => false,
        ])
            ->assertOk()
            ->assertJsonPath('meta.matched_by', 'email')
            ->assertJsonPath('meta.forced_refresh', true)
            ->assertJsonPath('meta.sync.cache.hit', false)
            ->assertJsonPath('meta.sync.external', true)
            ->assertJsonPath('meta.sync.local_deals.updated', 1)
            ->assertJsonPath('data.cos.0.certificadoDescargado', 0);
        $response = $this->withToken($token)->postJson('/api/v1/clientes/cos/refresh', [
            'email' => 'absent@example.test', 'passport' => $client->passport,
        ])
            ->assertOk()
            ->assertJsonPath('meta.matched_by', 'passport')
            ->assertJsonPath('data.cos.0.certificadoDescargado', 1)
            ->assertJsonPath('data.client_id', $client->id)
            ->assertJsonPath('data.negocios_count', 1)
            ->assertJsonPath('data.monday.board', 'ANALISIS');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame($response->json('data.cos'), $client->fresh()->arraycos);
        $this->assertTrue($client->fresh()->arraycos_expire->isFuture());
        $this->assertDatabaseHas('negocios', ['user_id' => $client->id, 'hubspot_id' => '10', 'dealname' => 'Updated']);
        $this->assertDatabaseMissing('negocios', ['user_id' => $client->id, 'hubspot_id' => '99']);
        $this->assertDatabaseHas('negocios', ['user_id' => $other->id, 'hubspot_id' => '77']);
        $this->assertSame($item, json_decode(MondayData::where('user_id', $client->id)->value('data'), true));
        Http::assertNothingSent();
    }

    public function test_monday_errors_do_not_renew_or_replace_the_previous_cos(): void
    {
        $client = $this->linkedClient();
        $previousCos = $client->arraycos;
        $previousExpiry = $client->arraycos_expire;
        $token = $this->internalUser()->createToken('Refresh', ['cos:refresh'])->plainTextToken;
        $this->fakeCompleteExternalResults($client);
        $this->mock(CosHelperService::class, fn ($mock) => $mock->shouldReceive('get')->with(false)->andReturn(array_cos()));
        \Monday::shouldReceive('customQuery')->once()->andReturn(['errors' => [['message' => 'Rate limit']]]);

        $this->withToken($token)->postJson($this->url($client))
            ->assertStatus(502)
            ->assertJsonPath('error', 'cos_refresh_failed')
            ->assertJsonPath('source', 'monday')
            ->assertJsonPath('reason', 'upstream_error')
            ->assertJsonPath('data.client_id', $client->id)
            ->assertJsonMissing(['meta' => ['forced_refresh' => true]]);

        $this->assertSame($previousCos, $client->fresh()->arraycos);
        $this->assertTrue($previousExpiry->equalTo($client->fresh()->arraycos_expire));
    }

    public function test_it_searches_monday_even_when_a_recent_lookup_found_no_client(): void
    {
        $client = $this->linkedClient();
        $client->update(['monday_id' => null]);
        $token = $this->internalUser()->createToken('Refresh', ['cos:refresh'])->plainTextToken;
        config(['cos_snapshot.monday_search_boards' => [11 => 'ANALISIS'], 'services.monday.token' => 'test-token']);
        $key = 'cos.monday.lookup.' . hash('sha256', json_encode([$client->id, $client->passport, [11]]));
        Cache::put($key, ['item' => null], 60);
        $item = $this->mondayItem($client);
        $item['id'] = '456';
        Http::fake(['api.monday.com/*' => Http::response(['data' => ['b0' => ['items' => [$item]]]])]);
        $this->fakeCompleteExternalResults($client);
        $this->mock(CosHelperService::class, fn ($mock) => $mock->shouldReceive('get')->with(false)->andReturn(array_cos()));
        \Monday::shouldReceive('customQuery')->once()->andReturn(['boards' => [['columns' => []]]]);

        $this->withToken($token)->postJson($this->url($client))
            ->assertOk()
            ->assertJsonPath('data.client.monday_id', '456')
            ->assertJsonPath('data.monday.has_data', true);
        Http::assertSentCount(1);
    }

    public function test_failed_hubspot_contact_search_does_not_pass_as_an_absent_contact(): void
    {
        $client = $this->linkedClient();
        $client->update(['hs_id' => null]);
        $previousExpiry = $client->arraycos_expire;
        $token = $this->internalUser()->createToken('Refresh', ['cos:refresh'])->plainTextToken;
        $this->mock(HubspotService::class, function ($mock) {
            $mock->shouldReceive('searchContactByEmail')->once()->andThrow(new \RuntimeException('API unavailable'));
            $mock->shouldReceive('searchContactByPassport')->once()->andReturn(null);
        });

        $this->withToken($token)->postJson($this->url($client))->assertStatus(502);
        $this->assertTrue($previousExpiry->equalTo($client->fresh()->arraycos_expire));
    }

    public function test_incomplete_hubspot_data_does_not_delete_local_deals_or_renew_cos(): void
    {
        $client = $this->linkedClient();
        $previousExpiry = $client->arraycos_expire;
        Negocio::create(['user_id' => $client->id, 'hubspot_id' => '10']);
        $token = $this->internalUser()->createToken('Refresh', ['cos:refresh'])->plainTextToken;
        $this->mock(HubspotService::class, fn ($mock) => $mock->shouldReceive('executeConcurrent')->once()
            ->andReturn(['hubspot' => ['contact' => null, 'deals' => []]]));

        $this->withToken($token)->postJson($this->url($client))->assertStatus(502);

        $this->assertDatabaseHas('negocios', ['user_id' => $client->id, 'hubspot_id' => '10']);
        $this->assertTrue($previousExpiry->equalTo($client->fresh()->arraycos_expire));
    }

    public function test_incomplete_teamleader_data_does_not_renew_cos(): void
    {
        $client = $this->linkedClient();
        $previousExpiry = $client->arraycos_expire;
        $token = $this->internalUser()->createToken('Refresh', ['cos:refresh'])->plainTextToken;
        $this->mock(HubspotService::class, fn ($mock) => $mock->shouldReceive('executeConcurrent')->once()
            ->andReturn([
                'hubspot' => ['contact' => ['id' => $client->hs_id], 'deals' => []],
                'teamleader' => ['contact' => null, 'deals' => []],
            ]));

        $this->withToken($token)->postJson($this->url($client))->assertStatus(502);
        $this->assertTrue($previousExpiry->equalTo($client->fresh()->arraycos_expire));
    }

    #[DataProvider('teamleaderFailures')]
    public function test_teamleader_failures_identify_the_source_and_preserve_the_client_id_and_previous_cos(string $failure, string $reason, bool $retryable, string $action): void
    {
        $client = $this->linkedClient();
        $previousCos = $client->arraycos;
        $previousExpiry = $client->arraycos_expire;
        Negocio::create(['user_id' => $client->id, 'hubspot_id' => '10', 'dealname' => 'Previous deal']);
        $token = $this->internalUser()->createToken('Refresh', ['cos:refresh'])->plainTextToken;
        $exception = match ($failure) {
            'unavailable' => new TeamleaderServiceUnavailableException(504),
            'invalid_response' => new \UnexpectedValueException('Invalid response received from Authorization Server. Expected JSON.'),
            'connection' => new \Illuminate\Http\Client\ConnectionException('Connection timeout'),
            'authentication' => new TeamleaderAuthenticationException('Invalid refresh token'),
        };
        $this->mock(TeamleaderService::class, fn ($mock) => $mock
            ->shouldReceive('getContactById')->with($client->tl_id)->once()->andThrow($exception));
        $this->mock(HubspotService::class, function ($mock) use ($client) {
            $mock->shouldReceive('executeConcurrent')->once()->andReturnUsing(
                fn (array $callbacks) => array_map(fn ($callback) => $callback(), $callbacks)
            );
            $mock->shouldReceive('getContactById')->with($client->hs_id)->once()
                ->andReturn(['id' => $client->hs_id, 'properties' => []]);
            $mock->shouldReceive('getContactFileFields')->with($client->hs_id)->once()->andReturn([]);
            $mock->shouldReceive('getDealsByContactId')->with($client->hs_id)->once()->andReturn([]);
        });

        $response = $this->withToken($token)->postJson('/api/v1/clientes/cos/refresh', ['email' => $client->email])
            ->assertStatus(502)
            ->assertJsonPath('error', 'cos_refresh_failed')
            ->assertJsonPath('source', 'teamleader')
            ->assertJsonPath('reason', $reason)
            ->assertJsonPath('retryable', $retryable)
            ->assertJsonPath('action.type', $action)
            ->assertJsonPath('data.client_id', $client->id)
            ->assertJsonPath('meta.found', true)
            ->assertJsonPath('meta.refreshed', false)
            ->assertJsonPath('meta.matched_by', 'email');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString($exception->getMessage(), $response->getContent());
        $this->assertSame($previousCos, $client->fresh()->arraycos);
        $this->assertTrue($previousExpiry->equalTo($client->fresh()->arraycos_expire));
        $this->assertDatabaseHas('negocios', ['user_id' => $client->id, 'hubspot_id' => '10', 'dealname' => 'Previous deal']);
    }

    public static function teamleaderFailures(): array
    {
        return [
            'temporary outage' => ['unavailable', 'service_unavailable', true, 'retry_later'],
            'invalid OAuth response' => ['invalid_response', 'invalid_authorization_response', true, 'retry_later'],
            'network timeout' => ['connection', 'service_unavailable', true, 'retry_later'],
            'rejected refresh token' => ['authentication', 'authentication_required', false, 'reconnect_teamleader'],
        ];
    }

    private function fakeCompleteExternalResults(User $client): void
    {
        $this->mock(HubspotService::class, fn ($mock) => $mock->shouldReceive('executeConcurrent')->once()
            ->andReturn([
                'hubspot' => ['contact' => ['id' => $client->hs_id, 'properties' => [
                    'lastmodifieddate' => now()->subYear()->toIso8601String(),
                ]], 'deals' => []],
                'teamleader' => ['contact' => ['id' => $client->tl_id], 'deals' => []],
            ]));
        $this->mock(AutomaticTeamleaderDealAssociationService::class, fn ($mock) => $mock
            ->shouldReceive('associate')->andReturn(['linked' => 0]));
    }

    private function expectForcedRefresh(User $client): void
    {
        $this->mock(ClientCosSnapshotService::class, fn ($mock) => $mock
            ->shouldReceive('get')->once()
            ->with(Mockery::on(fn ($value) => $value instanceof User && $value->id === $client->id), true, true)
            ->andReturn([
                'client' => $client, 'cos' => [], 'cosready' => false,
                'arraycos_expire' => now()->addDays(30)->toIso8601String(), 'negocios_count' => 0,
                'monday' => ['id' => null, 'board' => null, 'has_data' => false],
                'sync' => ['external' => true, 'cache' => ['hit' => false]],
                'generated_at' => now()->toIso8601String(), 'duration_ms' => 1,
            ]));
    }

    private function linkedClient(): User
    {
        $client = User::factory()->create([
            'hs_id' => '123', 'tl_id' => 'tl-contact', 'monday_id' => '456',
            'passport' => 'COS-API-TEST',
            'arraycos' => [['servicio' => 'Española Sefardi', 'currentStepName' => 'Old snapshot']],
            'arraycos_expire' => now()->addDays(20),
        ]);
        Cache::put('hubspot_data_' . $client->hs_id, ['contact' => null, 'deals' => []], 300);
        Cache::put('teamleader_data_' . $client->id, ['contact' => null, 'deals' => []], 300);
        Cache::put("monday_data_{$client->id}_{$client->monday_id}", ['mondayUserDetails' => null], 600);

        return $client;
    }

    private function mondayItem(User $client): array
    {
        return [
            'id' => $client->monday_id, 'name' => 'Updated item',
            'board' => ['id' => '11', 'name' => 'ANALISIS'],
            'column_values' => [['id' => 'men__desplegable', 'text' => 'CARTA DE NATURALEZA SEFARDI P']],
        ];
    }

    private function internalUser(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['Administrador']);

        return $user;
    }

    private function url(User $client): string
    {
        return "/api/v1/clientes/{$client->id}/cos/refresh";
    }
}
