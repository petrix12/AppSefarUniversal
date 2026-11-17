<?php

namespace Tests\Feature;

use App\Exceptions\CosIdentityConflictException;
use App\Models\User;
use App\Services\ClientRestrictionService;
use App\Services\CosHelperService;
use App\Services\CosMondayLookup;
use App\Services\HubspotDealLocalSyncService;
use App\Services\HubspotService;
use App\Services\TeamleaderDealEnrichmentService;
use App\Services\TeamleaderService;
use App\Services\UserSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientCosIdentityConflictTest extends TestCase
{
    private string $auditPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2014_10_12_000000_create_users_table.php',
            'database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php',
            'database/migrations/2021_03_08_173429_create_permission_tables.php',
            'database/migrations/2025_07_03_200540_add_arraycos_to_users_table.php',
            'database/migrations/2025_09_15_210626_add_cosready_to_users_table.php',
            'database/migrations/2026_08_20_120000_add_expires_at_to_personal_access_tokens_table_if_missing.php',
        ]])->assertExitCode(0);
        Schema::table('users', function (Blueprint $table) {
            $table->string('tl_id')->nullable();
            $table->string('monday_id')->nullable();
        });
        Schema::create('negocios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
        });
        Schema::create('monday_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('data')->nullable();
            $table->timestamps();
        });
        Role::findOrCreate('Cliente', 'web');
        Role::findOrCreate('Administrador', 'web');
        $this->auditPath = tempnam(sys_get_temp_dir(), 'cos-identity-audit-');
        config(['cache.default' => 'array', 'crm_sync.local_reads_enabled' => false, 'mcp.audit_log' => $this->auditPath]);
        Cache::flush();
        Http::preventStrayRequests();
        $this->mock(HubspotService::class);
        $this->mock(TeamleaderService::class);
    }

    protected function tearDown(): void
    {
        @unlink($this->auditPath);
        parent::tearDown();
    }

    #[DataProvider('conflictingFields')]
    public function test_conflicts_stop_before_importing_profile_files_deals_or_renewing_cos(string $field): void
    {
        $client = $this->client();
        $other = User::factory()->create([
            'email' => 'other@example.test', 'passport' => 'OTHER-PASSPORT',
            'hs_id' => $field === 'hs_id' ? $client->hs_id : 'other-hs',
        ]);
        $properties = match ($field) {
            'email' => ['email' => ' OTHER@EXAMPLE.TEST '],
            'passport' => ['numero_de_pasaporte' => ' other-passport '],
            'merged_alias' => ['hs_merged_object_ids' => 'old-unassigned;other-hs'],
            default => [],
        };
        $before = $client->fresh()->getRawOriginal();
        $otherBefore = $other->fresh()->getRawOriginal();
        $this->externalContact($client, $properties, false);
        $this->mock(HubspotDealLocalSyncService::class)->shouldNotReceive('sync');
        $this->mock(ClientRestrictionService::class)->shouldNotReceive('detectHubspotRetirement');
        $this->mock(CosHelperService::class)->shouldNotReceive('get');

        $response = $this->withToken($this->token())->postJson('/api/v1/clientes/cos/refresh', ['email' => $client->email])
            ->assertStatus(409)
            ->assertJsonPath('error', 'cos_refresh_failed')
            ->assertJsonPath('reason', 'identity_conflict')
            ->assertJsonPath('source', 'hubspot')
            ->assertJsonPath('retryable', false)
            ->assertJsonPath('action.type', 'review_client_identity')
            ->assertJsonPath('conflicting_fields', [$field === 'merged_alias' ? 'hs_id' : $field])
            ->assertJsonPath('data.client_id', $client->id)
            ->assertJsonPath('meta.found', true)
            ->assertJsonPath('meta.refreshed', false);

        $this->assertSame($response->json('reference'), $response->headers->get('X-Error-Reference'));
        $this->assertStringNotContainsString($other->email, $response->getContent());
        $this->assertStringNotContainsString($other->passport, $response->getContent());
        $this->assertSame($before, $client->fresh()->getRawOriginal());
        $this->assertSame($otherBefore, $other->fresh()->getRawOriginal());
        $this->assertDatabaseCount('negocios', 0);
        $this->assertDatabaseCount('monday_data', 0);
        Http::assertNothingSent();
    }

    public static function conflictingFields(): array
    {
        return [
            'shared HubSpot ID' => ['hs_id'],
            'normalized email belongs to another account' => ['email'],
            'normalized passport belongs to another account' => ['passport'],
            'merged alias belongs to another account' => ['merged_alias'],
        ];
    }

    public function test_an_ambiguous_lookup_does_not_link_a_previously_unlinked_client(): void
    {
        $client = $this->client(['hs_id' => null]);
        User::factory()->create(['hs_id' => 'ambiguous-hs']);
        $this->mock(HubspotService::class)->shouldReceive('searchContactByEmail')->once()->with($client->email)
            ->andReturn(['id' => 'ambiguous-hs', 'properties' => ['email' => $client->email]]);
        $before = $client->fresh()->getRawOriginal();

        $this->withToken($this->token())->postJson("/api/v1/clientes/{$client->id}/cos/refresh")
            ->assertStatus(409)->assertJsonPath('reason', 'identity_conflict')->assertJsonPath('conflicting_fields', ['hs_id']);

        $this->assertSame($before, $client->fresh()->getRawOriginal());
        Http::assertNothingSent();
    }

    public function test_cached_contacts_are_also_rejected_when_the_identity_becomes_ambiguous(): void
    {
        $client = $this->client();
        $other = User::factory()->create(['passport' => 'OTHER-PASSPORT']);
        Cache::put('hubspot_data_'.$client->hs_id, [
            'contact' => ['id' => $client->hs_id, 'properties' => ['numero_de_pasaporte' => $other->passport]],
            'deals' => [], 'files' => [],
        ], 300);
        $service = new UserSyncService(app(HubspotService::class), app(TeamleaderService::class));

        $this->expectException(CosIdentityConflictException::class);
        $service->syncWithHubspot($client);
    }

    public function test_a_merge_without_identity_collisions_can_still_refresh_and_update_the_profile(): void
    {
        $client = $this->client();
        $this->externalContact($client, ['hs_merged_object_ids' => $client->hs_id, 'phone' => '+100000001'], true);
        $this->allowCalculation($client);

        $this->withToken($this->token())->postJson('/api/v1/clientes/cos/refresh', ['email' => $client->email])
            ->assertOk()->assertJsonPath('meta.refreshed', true)->assertJsonPath('data.client_id', $client->id);

        $this->assertSame('+100000001', $client->fresh()->phone);
        $this->assertSame('client@example.test', $client->fresh()->email);
        $this->assertSame('OWN-PASSPORT', $client->fresh()->passport);
        $this->assertSame('old-hs', $client->fresh()->hs_id);
        $this->assertTrue($client->fresh()->arraycos_expire->isFuture());
        Http::assertNothingSent();
    }

    public function test_a_concurrent_identity_claim_is_translated_without_partial_profile_or_cos_changes(): void
    {
        $client = $this->client();
        $before = $client->fresh()->getRawOriginal();
        $this->externalContact($client, ['email' => 'race@example.test', 'phone' => '+100000001'], true);
        User::updating(function (User $saving) use ($client): void {
            if ($saving->id === $client->id && $saving->isDirty('email')) {
                User::withoutEvents(fn () => User::factory()->create(['email' => 'race@example.test']));
            }
        });
        $this->mock(HubspotDealLocalSyncService::class)->shouldNotReceive('sync');

        $this->withToken($this->token())->postJson('/api/v1/clientes/cos/refresh', ['email' => $client->email])
            ->assertStatus(409)->assertJsonPath('reason', 'identity_conflict')->assertJsonPath('conflicting_fields', ['email']);

        $this->assertSame($before, $client->fresh()->getRawOriginal());
        $this->assertDatabaseHas('users', ['email' => 'race@example.test']);
        $this->assertDatabaseCount('negocios', 0);
    }

    private function client(array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'email' => 'client@example.test', 'passport' => 'OWN-PASSPORT', 'hs_id' => 'old-hs',
            'tl_id' => 'tl-client', 'updated_at' => now()->subYear(),
            'arraycos' => [['servicio' => 'Anterior']], 'arraycos_expire' => now()->addDays(20), 'cosready' => true,
        ]);
    }

    private function token(): string
    {
        $actor = User::factory()->create()->syncRoles(['Administrador']);

        return $actor->createToken('COS test', ['cos:refresh'])->plainTextToken;
    }

    private function externalContact(User $client, array $properties, bool $valid): void
    {
        $this->mock(HubspotService::class, function ($mock) use ($client, $properties, $valid) {
            $mock->shouldReceive('executeConcurrent')->once()->andReturnUsing(
                fn (array $callbacks) => array_map(fn ($callback) => $callback(), $callbacks)
            );
            $mock->shouldReceive('getContactById')->once()->with($client->hs_id)->andReturn([
                'id' => 'canonical-hs', 'properties' => $properties + [
                    'email' => $client->email, 'numero_de_pasaporte' => $client->passport,
                    'lastmodifieddate' => now()->addDay()->toIso8601String(),
                ],
            ]);
            if ($valid) {
                $mock->shouldReceive('getContactFileFields')->once()->with($client->hs_id)->andReturn([]);
                $mock->shouldReceive('getDealsByContactId')->once()->with($client->hs_id)->andReturn([]);
            } else {
                $mock->shouldNotReceive('getContactFileFields');
                $mock->shouldNotReceive('getDealsByContactId');
            }
        });
        $this->mock(TeamleaderService::class, function ($mock) use ($client, $valid) {
            if ($valid) {
                $mock->shouldReceive('getContactById')->once()->with($client->tl_id)->andReturn(['id' => $client->tl_id]);
                $mock->shouldReceive('getProjectsWithDetailsByCustomerId')->once()->with($client->tl_id, true)->andReturn([]);
            } else {
                $mock->shouldNotReceive('getContactById');
                $mock->shouldNotReceive('getProjectsWithDetailsByCustomerId');
            }
        });
    }

    private function allowCalculation(User $client): void
    {
        $this->mock(HubspotDealLocalSyncService::class)->shouldReceive('sync')->once()->andReturn([]);
        $this->mock(TeamleaderDealEnrichmentService::class)->shouldReceive('refreshLinkedDeals')->once();
        $this->mock(CosHelperService::class)->shouldReceive('get')->with(false)->once()->andReturn([]);
        $this->mock(CosMondayLookup::class)->shouldReceive('localFor')->withArgs(fn ($user) => $user->id === $client->id)
            ->once()->andReturn(['item' => null, 'synced_at' => null]);
    }
}
