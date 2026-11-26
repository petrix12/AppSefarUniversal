<?php

namespace Tests\Feature;

use App\Exceptions\HubspotApiException;
use App\Models\Negocio;
use App\Models\User;
use App\Services\CosHelperService;
use App\Services\CosPortfolioBackfill;
use App\Services\HubspotCosFieldSyncService;
use App\Services\HubspotService;
use App\Services\TeamleaderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LocalCosBackfillTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2014_10_12_000000_create_users_table.php',
            'database/migrations/2021_03_08_173429_create_permission_tables.php',
            'database/migrations/2025_07_03_200540_add_arraycos_to_users_table.php',
            'database/migrations/2025_09_15_210626_add_cosready_to_users_table.php',
        ]])->assertSuccessful();
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('contrato')->default(false);
            $table->string('email_2')->nullable();
        });
        Schema::create('negocios', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('hubspot_id')->nullable();
            $table->string('teamleader_id')->nullable();
            $table->string('servicio_solicitado')->nullable();
            $table->timestamps();
        });
    }

    public function test_local_batches_cover_representados_without_payment_and_unlock_only_hubspot_when_done(): void
    {
        Role::findOrCreate('Cliente', 'web');
        Role::findOrCreate('Administrador', 'web');
        config(['crm_sync.processing_enabled' => false, 'crm_sync.hubspot.cos_fields_only' => true]);
        Http::preventStrayRequests();
        $this->mock(HubspotService::class);
        $this->mock(TeamleaderService::class);
        $this->mock(CosHelperService::class)->shouldReceive('get')->andReturn(['Española Sefardi' => []]);

        $first = User::factory()->create(['pay' => 0, 'contrato' => 0, 'arraycos' => null]);
        $second = User::factory()->create(['pay' => 0, 'contrato' => 0, 'arraycos' => null]);
        $staff = User::factory()->create(['arraycos' => null]);
        $staff->syncRoles(['Administrador']);
        Negocio::create(['user_id' => $first->id, 'servicio_solicitado' => 'Española Sefardi']);

        $this->artisan('cos:backfill-local --start --limit=1')->assertSuccessful();
        $this->assertFalse(CosPortfolioBackfill::allowsCrmProcessing('hubspot'));
        $this->assertSame('Española Sefardi', $first->fresh()->arraycos[0]['servicio']);
        $this->assertEquals($first->updated_at, $first->fresh()->updated_at);
        $this->assertNull($second->fresh()->arraycos);

        $this->artisan('cos:backfill-local --limit=1')->assertSuccessful();
        $this->assertSame('completed', CosPortfolioBackfill::state()['status']);
        $this->assertSame(2, CosPortfolioBackfill::state()['calculated']);
        $this->assertSame(1, CosPortfolioBackfill::state()['without_status']);
        $this->assertSame(2, CosPortfolioBackfill::state()['without_hubspot_link']);
        $this->assertTrue(CosPortfolioBackfill::allowsCrmProcessing('hubspot'));
        $this->assertFalse(CosPortfolioBackfill::allowsCrmProcessing('teamleader'));
        $this->assertSame([], $second->fresh()->arraycos);
        $this->assertNull($staff->fresh()->arraycos);
        Http::assertNothingSent();
    }

    public function test_backfill_reads_and_projects_missing_hubspot_deals_before_calculating(): void
    {
        Role::findOrCreate('Cliente', 'web');
        $user = User::factory()->create(['hs_id' => '123']);
        $hubspot = $this->mock(HubspotService::class);
        $hubspot->shouldReceive('webhookObject')->once()->with('contacts', '123', \Mockery::type('array'))
            ->andReturn(['id' => '123', 'properties' => ['phone' => '+582125550100']]);
        $hubspot->shouldReceive('webhookAssociations')->once()->with('contacts', '123', 'deals')->andReturn(['321']);
        $hubspot->shouldReceive('getDealById')->once()->with('321')->andReturn([
            'id' => '321', 'properties' => ['servicio_solicitado' => 'Española Sefardi'],
        ]);
        $this->mock(TeamleaderService::class);
        $this->mock(CosHelperService::class)->shouldReceive('get')->andReturn(['Española Sefardi' => []]);

        $this->artisan('cos:backfill-local --start')->assertSuccessful();
        $this->artisan('cos:backfill-local')->assertSuccessful();
        $this->assertDatabaseHas('negocios', ['user_id' => $user->id, 'hubspot_id' => '321']);
        $this->assertSame('+582125550100', $user->fresh()->phone);
        $this->assertSame('Española Sefardi', $user->fresh()->arraycos[0]['servicio']);
        $this->assertSame(1, CosPortfolioBackfill::state()['hubspot_synced']);
    }

    public function test_backfill_waits_for_hubspot_retry_after_and_preserves_the_existing_cos_in_the_meantime(): void
    {
        Role::findOrCreate('Cliente', 'web');
        config(['crm_sync.hubspot.cos_fields_only' => true]);
        $oldCos = [['currentStepName' => 'Formalizado']];
        $user = User::factory()->create(['hs_id' => '123', 'arraycos' => $oldCos]);
        $hubspot = $this->mock(HubspotService::class);
        $hubspot->shouldReceive('webhookObject')->once()->andThrow(new HubspotApiException(429, 600));
        $hubspot->shouldReceive('webhookObject')->once()->andReturn(['id' => '123', 'properties' => []]);
        $hubspot->shouldReceive('webhookAssociations')->once()->andReturn([]);
        $this->mock(TeamleaderService::class);
        $this->mock(CosHelperService::class)->shouldReceive('get')->andReturn([]);

        $this->artisan('cos:backfill-local --start')->assertSuccessful();
        $this->artisan('cos:backfill-local')->expectsOutputToContain('El lote está pausado')->assertSuccessful();
        $this->assertSame(0, CosPortfolioBackfill::state()['last_id']);
        $this->assertFalse(CosPortfolioBackfill::allowsCrmProcessing('hubspot'));
        $this->assertSame($oldCos, $user->fresh()->arraycos);

        $this->travel(11)->minutes();
        $this->artisan('cos:backfill-local')->assertSuccessful();
        $this->assertSame(1, CosPortfolioBackfill::state()['hubspot_synced']);
        $this->assertTrue(CosPortfolioBackfill::allowsCrmProcessing('hubspot'));
    }

    public function test_filter_matches_any_process_in_the_latest_snapshot_even_if_it_expired(): void
    {
        Role::findOrCreate('Cliente', 'web');
        $user = User::factory()->create(['arraycos' => [
            ['currentStepName' => 'Registro'],
            ['servicio' => 'Española Sefardi', 'currentStepName' => 'Recurso de alzada'],
        ], 'arraycos_expire' => now()->subDay()]);
        User::factory()->create(['arraycos' => [['currentStepName' => 'Recurso de alzada presentado']]]);
        $unavailable = User::factory()->create(['arraycos' => []]);

        $this->assertSame([$user->id], User::query()->withCosStatus('Recurso de alzada')->pluck('id')->all());
        $this->assertSame([$unavailable->id], User::query()->withCosStatus('__without_status')->pluck('id')->all());
    }

    public function test_contact_webhook_updates_only_scoped_profile_fields_using_the_existing_hubspot_id(): void
    {
        Role::findOrCreate('Cliente', 'web');
        $user = User::factory()->create(['hs_id' => '123', 'phone' => null]);
        $password = $user->password;
        $this->mock(HubspotService::class)->shouldReceive('webhookObject')->once()
            ->with('contacts', '123', \Mockery::type('array'))->andReturn([
                'id' => '123',
                'properties' => ['phone' => '+582125550100', 'password' => 'remote-value', 'contrato' => true],
            ]);
        $this->mock(TeamleaderService::class);
        $this->mock(CosHelperService::class)->shouldReceive('get')->andReturn([]);

        $service = app(HubspotCosFieldSyncService::class);
        $this->assertSame(1, $service->ingestEvent(['subscriptionType' => 'contact.propertyChange', 'objectId' => 123, 'propertyName' => 'phone'])['users']);
        $user->refresh();
        $this->assertSame('+582125550100', $user->phone);
        $this->assertSame($password, $user->password);
        $this->assertFalse((bool) $user->contrato);
        $this->assertSame([], $user->arraycos);
    }

    public function test_contact_with_an_email_claimed_as_secondary_is_held_for_review(): void
    {
        Role::findOrCreate('Cliente', 'web');
        $user = User::factory()->create(['hs_id' => '123']);
        User::factory()->create(['email_2' => 'same@example.com']);
        $this->mock(HubspotService::class)->shouldReceive('webhookObject')->once()->andReturn([
            'id' => '123', 'properties' => ['email' => 'SAME@example.com'],
        ]);

        $result = app(HubspotCosFieldSyncService::class)->ingestEvent([
            'subscriptionType' => 'contact.propertyChange', 'objectId' => 123, 'propertyName' => 'email',
        ]);

        $this->assertSame('review', $result['status']);
        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertNull($user->fresh()->arraycos);
    }
}
