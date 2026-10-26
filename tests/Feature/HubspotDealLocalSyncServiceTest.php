<?php

namespace Tests\Feature;

use App\Jobs\SyncUserDealsJob;
use App\Models\Negocio;
use App\Models\User;
use App\Services\AutomaticTeamleaderDealAssociationService;
use App\Services\ClientCosSnapshotService;
use App\Services\CosHelperService;
use App\Services\HubspotDealLocalSyncService;
use App\Services\HubspotService;
use App\Services\TeamleaderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HubspotDealLocalSyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('hs_id')->nullable();
            $table->string('tl_id')->nullable();
            $table->string('monday_id')->nullable();
            $table->json('arraycos')->nullable();
            $table->timestamp('arraycos_expire')->nullable();
            $table->boolean('cosready')->default(false);
            $table->timestamps();
        });

        Schema::create('negocios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('hubspot_id')->nullable();
            $table->string('teamleader_id')->nullable();
            $table->unsignedTinyInteger('teamleader_match_confidence')->nullable();
            $table->string('teamleader_match_reason')->nullable();
            $table->json('teamleader_comparisons')->nullable();
            $table->timestamp('teamleader_matched_at')->nullable();
            $table->string('dealname')->nullable();
            $table->string('codigo_de_proceso')->nullable();
            $table->text('documentos')->nullable();
            $table->timestamps();
        });

        Schema::create('monday_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('data')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('monday_data');
        Schema::dropIfExists('negocios');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_empty_response_preserves_existing_deals_and_associations(): void
    {
        $user = User::withoutEvents(fn () => User::create([
            'name' => 'Solicitante',
            'email' => 'solicitante@example.com',
            'password' => 'password',
        ]));
        $linkedDeal = Negocio::create([
            'user_id' => $user->id,
            'hubspot_id' => 'hs-linked',
            'teamleader_id' => 'tl-project',
            'dealname' => 'Trato asociado',
        ]);
        $unlinkedDeal = Negocio::create([
            'user_id' => $user->id,
            'hubspot_id' => 'hs-unlinked',
            'dealname' => 'Trato sin asociación',
        ]);

        $result = app(HubspotDealLocalSyncService::class)->sync($user, []);

        $this->assertDatabaseHas('negocios', [
            'id' => $linkedDeal->id,
            'hubspot_id' => 'hs-linked',
            'teamleader_id' => 'tl-project',
        ]);
        $this->assertDatabaseHas('negocios', ['id' => $unlinkedDeal->id]);
        $this->assertSame(1, $result['preserved_associations']);
        $this->assertSame(0, $result['deleted']);
        $this->assertSame(0, $result['inserted']);
    }

    public function test_sync_updates_a_returned_deal_without_losing_its_teamleader_link(): void
    {
        $user = User::withoutEvents(fn () => User::create([
            'name' => 'Solicitante',
            'email' => 'solicitante@example.com',
            'password' => 'password',
        ]));
        $linkedDeal = Negocio::create([
            'user_id' => $user->id,
            'hubspot_id' => 'hs-linked',
            'teamleader_id' => 'tl-project',
            'teamleader_match_confidence' => 99,
            'teamleader_match_reason' => 'Asociación verificada',
            'teamleader_comparisons' => [['field' => 'documentos', 'hubspot' => 'A', 'teamleader' => 'B']],
            'teamleader_matched_at' => '2026-10-01 12:00:00',
            'dealname' => 'Nombre anterior',
        ]);
        $association = $linkedDeal->getAttributes();

        app(HubspotDealLocalSyncService::class)->sync($user, [[
            'id' => 'hs-linked',
            'properties' => [
                'dealname' => 'Nombre actualizado',
                'teamleader_id' => null,
                'teamleader_match_confidence' => null,
                'teamleader_match_reason' => null,
                'teamleader_comparisons' => null,
                'teamleader_matched_at' => null,
                'user_id' => 999,
                'hubspot_id' => 'another-deal',
            ],
        ]]);

        $this->assertDatabaseHas('negocios', [
            'id' => $linkedDeal->id,
            'hubspot_id' => 'hs-linked',
            'teamleader_id' => 'tl-project',
            'teamleader_match_confidence' => 99,
            'teamleader_match_reason' => 'Asociación verificada',
            'dealname' => 'Nombre actualizado',
        ]);
        $linkedDeal->refresh();
        foreach (['teamleader_id', 'teamleader_match_confidence', 'teamleader_match_reason', 'teamleader_comparisons', 'teamleader_matched_at', 'user_id'] as $field) {
            $this->assertSame($association[$field], $linkedDeal->getRawOriginal($field));
        }
    }

    public function test_repeated_syncs_and_duplicate_response_ids_reuse_the_same_record(): void
    {
        $user = $this->client();
        $service = app(HubspotDealLocalSyncService::class);
        $deal = ['id' => '123', 'properties' => ['dealname' => 'Trato original']];
        $duplicate = array_merge($deal, ['id' => 123]);

        $first = $service->sync($user, [$deal, $duplicate]);
        $record = Negocio::query()->sole();
        $record->update(['teamleader_id' => 'tl-project']);
        $deal['properties']['dealname'] = 'Trato actualizado';

        $second = $service->sync($user, [$deal, $deal]);
        $third = $service->sync($user, [$deal]);

        $this->assertSame(1, $first['inserted']);
        $this->assertSame(0, $second['inserted']);
        $this->assertSame(1, $second['updated']);
        $this->assertSame(0, $third['inserted']);
        $this->assertSame(0, $third['updated']);
        $this->assertDatabaseCount('negocios', 1);
        $this->assertDatabaseHas('negocios', [
            'id' => $record->id, 'hubspot_id' => '123',
            'teamleader_id' => 'tl-project', 'dealname' => 'Trato actualizado',
        ]);
    }

    public function test_a_missing_deal_returns_to_the_same_record_and_link(): void
    {
        $user = $this->client();
        $deal = Negocio::create([
            'user_id' => $user->id, 'hubspot_id' => 'hs-linked',
            'teamleader_id' => 'tl-project', 'dealname' => 'Trato anterior',
        ]);
        $service = app(HubspotDealLocalSyncService::class);
        $service->sync($user, []);
        $result = $service->sync($user, [[
            'id' => 'hs-linked', 'properties' => ['dealname' => 'Trato actualizado'],
        ]]);

        $this->assertSame(0, $result['inserted']);
        $this->assertDatabaseCount('negocios', 1);
        $this->assertDatabaseHas('negocios', [
            'id' => $deal->id, 'teamleader_id' => 'tl-project', 'dealname' => 'Trato actualizado',
        ]);
    }

    public function test_partial_response_preserves_omitted_data_and_other_clients(): void
    {
        $user = $this->client();
        $deal = Negocio::create([
            'user_id' => $user->id, 'hubspot_id' => 'hs-linked',
            'teamleader_id' => 'tl-project', 'dealname' => 'Nombre conservado',
            'documentos' => '["Anterior"]',
        ]);
        $other = Negocio::create([
            'user_id' => 999, 'hubspot_id' => 'hs-other',
            'teamleader_id' => 'tl-other', 'dealname' => 'Otro solicitante',
        ]);
        $otherAttributes = $other->fresh()->getAttributes();

        app(HubspotDealLocalSyncService::class)->sync($user, [
            ['id' => 'hs-linked', 'properties' => ['documentos' => null]],
            ['properties' => ['dealname' => 'Sin identificador']],
        ]);

        $this->assertDatabaseCount('negocios', 2);
        $this->assertDatabaseHas('negocios', [
            'id' => $deal->id, 'dealname' => 'Nombre conservado',
            'documentos' => null, 'teamleader_id' => 'tl-project',
        ]);
        $this->assertSame($otherAttributes, $other->fresh()->getAttributes());
    }

    public function test_legacy_job_and_cos_sync_reuse_the_same_deal_and_link(): void
    {
        $user = $this->client();
        $deal = ['id' => 'hs-linked', 'properties' => ['dealname' => 'Trato']];
        $hubspot = $this->mock(HubspotService::class, function ($mock) use ($deal) {
            $mock->shouldReceive('getDealsByContactId')->with('hs-contact')->twice()->andReturn([$deal, $deal]);
        });
        (new SyncUserDealsJob($user))->handle($hubspot);
        $record = Negocio::query()->sole();
        $record->update(['teamleader_id' => 'tl-project']);

        app(HubspotDealLocalSyncService::class)->sync($user, [$deal]);
        (new SyncUserDealsJob($user))->handle($hubspot);

        $this->assertDatabaseCount('negocios', 1);
        $this->assertDatabaseHas('negocios', ['id' => $record->id, 'teamleader_id' => 'tl-project']);
        Http::assertNothingSent();
    }

    public function test_an_existing_deal_is_not_copied_or_reassigned_from_another_client(): void
    {
        $user = $this->client();
        $other = Negocio::create([
            'user_id' => 999, 'hubspot_id' => 'hs-shared',
            'teamleader_id' => 'tl-project', 'dealname' => 'Trato asociado existente',
        ]);
        $original = $other->fresh()->getAttributes();

        $result = app(HubspotDealLocalSyncService::class)->sync($user, [[
            'id' => 'hs-shared', 'properties' => ['dealname' => 'Trato recibido'],
        ]]);

        $this->assertSame(0, $result['inserted']);
        $this->assertDatabaseCount('negocios', 1);
        $this->assertSame($original, $other->fresh()->getAttributes());
    }

    public function test_forced_cos_refresh_reads_external_data_without_relinking_or_remote_writes(): void
    {
        $user = $this->client();
        $linked = Negocio::create([
            'user_id' => $user->id, 'hubspot_id' => 'hs-linked',
            'teamleader_id' => 'tl-project', 'dealname' => 'Trato anterior',
        ]);
        $this->mock(HubspotService::class, function ($mock) {
            $mock->shouldReceive('executeConcurrent')->twice()->andReturnUsing(
                fn (array $callbacks) => array_map(fn ($callback) => $callback(), $callbacks)
            );
            $mock->shouldReceive('getContactById')->with('hs-contact')->twice()->andReturn([
                'id' => 'hs-contact', 'properties' => ['lastmodifieddate' => '2020-01-01T00:00:00Z'],
            ]);
            $mock->shouldReceive('getContactFileFields')->with('hs-contact')->twice()->andReturn([]);
            $deal = ['id' => 'hs-linked', 'properties' => ['dealname' => 'Trato actualizado']];
            $mock->shouldReceive('getDealsByContactId')->with('hs-contact')->twice()->andReturn([
                $deal, $deal, ['id' => 'hs-unlinked', 'properties' => ['dealname' => 'Otro trato existente']],
            ]);
        });
        $this->mock(TeamleaderService::class, function ($mock) {
            $mock->shouldReceive('getContactById')->with('tl-contact')->twice()->andReturn(['id' => 'tl-contact']);
            $mock->shouldReceive('getProjectsWithDetailsByCustomerId')->with('tl-contact', true)->twice()
                ->andReturn([
                    ['id' => 'tl-project', 'custom_fields' => [[
                        'definition' => ['id' => 'a42f63f5-d527-0544-ab50-9c03857707f2'],
                        'value' => 'Proceso actualizado desde Teamleader',
                    ]]],
                    ['id' => 'tl-unlinked'],
                ]);
        });
        $this->mock(AutomaticTeamleaderDealAssociationService::class, fn ($mock) => $mock->shouldNotReceive('associate'));
        $this->mock(CosHelperService::class, fn ($mock) => $mock->shouldReceive('get')->with(false)->twice()->andReturn([]));
        \Monday::shouldReceive('customQuery')->twice()->andReturn(['items' => []]);

        $snapshots = app(ClientCosSnapshotService::class);
        $first = $snapshots->get($user, true);
        $second = $snapshots->get($user, true);

        $this->assertSame(1, $first['sync']['local_deals']['inserted']);
        $this->assertSame(0, $second['sync']['local_deals']['inserted']);
        $this->assertDatabaseCount('negocios', 2);
        $this->assertDatabaseHas('negocios', [
            'id' => $linked->id, 'hubspot_id' => 'hs-linked',
            'teamleader_id' => 'tl-project', 'dealname' => 'Trato actualizado',
            'codigo_de_proceso' => 'Proceso actualizado desde Teamleader',
        ]);
        $this->assertDatabaseHas('negocios', ['hubspot_id' => 'hs-unlinked', 'teamleader_id' => null]);
        Http::assertNothingSent();
    }

    private function client(): User
    {
        return User::withoutEvents(fn () => User::create([
            'name' => 'Solicitante', 'email' => 'solicitante@example.com', 'password' => 'password',
            'hs_id' => 'hs-contact', 'tl_id' => 'tl-contact', 'monday_id' => '123',
        ]));
    }
}
