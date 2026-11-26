<?php

namespace Tests\Feature;

use App\Jobs\ProcessCrmWebhookEvent;
use App\Models\CrmContactSnapshot;
use App\Models\CrmWebhookEvent;
use App\Models\User;
use App\Services\ClientRestrictionService;
use App\Services\CosPortfolioBackfill;
use App\Services\CrmContactMirrorService;
use App\Services\CrmMirrorSource;
use App\Services\HubspotDealLocalSyncService;
use App\Services\HubspotCosFieldSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CrmWebhooksTest extends TestCase
{
    private $source;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'crm_sync.processing_enabled' => true, 'crm_sync.hubspot.enabled' => true,
            'cos_snapshot.backfill_progress_path' => sys_get_temp_dir().'/sefar-cos-'.bin2hex(random_bytes(8)).'/progress.json',
            'crm_sync.hubspot.portal_id' => '123', 'crm_sync.hubspot.client_secret' => 'example-secret',
            'crm_sync.hubspot.signature_version' => 'v3', 'crm_sync.teamleader.enabled' => true,
            'crm_sync.teamleader.account_id' => 'account-1', 'crm_sync.teamleader.secret' => str_repeat('s', 40)]);
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2014_10_12_000000_create_users_table.php',
            'database/migrations/2021_03_08_173429_create_permission_tables.php',
            'database/migrations/2026_11_16_120000_create_crm_webhook_mirrors.php',
        ]])->assertExitCode(0);
        Schema::table('users', function (Blueprint $table) {
            $table->string('tl_id')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->timestamp('arraycos_expire')->nullable();
        });
        Role::findOrCreate('Cliente', 'web');
        Role::findOrCreate('Administrador', 'web');
        $this->source = $this->mock(CrmMirrorSource::class);
        Http::preventStrayRequests();
        Bus::fake();
    }

    private function event(array $changes = []): array
    {
        return $changes + ['portalId' => 123, 'eventId' => 4, 'occurredAt' => 100,
            'subscriptionType' => 'contact.propertyChange', 'objectId' => 10, 'propertyName' => 'firstname', 'propertyValue' => 'Private name'];
    }

    private function send(array $events, ?string $signature = null, ?string $timestamp = null)
    {
        $url = route('api.crm.webhook', ['provider' => 'hubspot']);
        $body = json_encode($events);
        $timestamp ??= (string) now()->getTimestampMs();
        $signature ??= base64_encode(hash_hmac('sha256', 'POST'.$url.$body.$timestamp, 'example-secret', true));
        return $this->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUBSPOT_SIGNATURE_V3' => $signature, 'HTTP_X_HUBSPOT_REQUEST_TIMESTAMP' => $timestamp], $body);
    }

    public function test_signed_batch_is_durable_private_deduplicated_and_forced_to_database_queue(): void
    {
        $this->send([$this->event()])->assertStatus(202);
        $this->send([$this->event(['attemptNumber' => 2])])->assertStatus(202);
        $this->assertDatabaseCount('crm_webhook_events', 1);
        $stored = CrmWebhookEvent::first();
        $this->assertStringNotContainsString('Private name', json_encode($stored->payload));
        Bus::assertDispatched(ProcessCrmWebhookEvent::class, fn ($job) => $job->connection === 'crm' && $job->queue === 'crm-sync');
        $job = new ProcessCrmWebhookEvent($stored->id);
        $this->mock(CrmContactMirrorService::class)->shouldReceive('process')->once()->with('hubspot', $stored->payload);
        $job->handle(app(CrmContactMirrorService::class));
        $job->handle(app(CrmContactMirrorService::class));
        $this->assertSame('completed', $stored->fresh()->status);
        $this->assertSame(1, $stored->fresh()->attempts);
        Http::assertNothingSent();
    }

    public function test_bad_signature_expired_future_timestamp_or_other_portal_cannot_write(): void
    {
        $this->send([$this->event()], 'invalid')->assertUnauthorized();
        $this->send([$this->event()], timestamp: (string) now()->subMinutes(6)->getTimestampMs())->assertUnauthorized();
        $this->send([$this->event()], timestamp: (string) now()->addMinutes(6)->getTimestampMs())->assertUnauthorized();
        $this->send([$this->event(), $this->event(['portalId' => 999])])->assertUnauthorized();
        $this->assertDatabaseCount('crm_webhook_events', 0);
        Bus::assertNothingDispatched();
    }

    public function test_cos_fields_mode_filters_events_and_waits_for_local_backfill(): void
    {
        config(['crm_sync.processing_enabled' => false, 'crm_sync.hubspot.cos_fields_only' => true]);
        $this->send([
            $this->event(),
            $this->event(['eventId' => 5, 'subscriptionType' => 'quote.creation']),
        ])->assertStatus(202);
        $this->assertDatabaseCount('crm_webhook_events', 1);
        Bus::assertNothingDispatched();

        $this->travel(6)->minutes();
        Cache::forever(CosPortfolioBackfill::PROGRESS_KEY, ['status' => 'completed', 'completed_at' => now()->toIso8601String()]);
        $this->artisan('crm:retry-webhooks')->assertSuccessful();
        Bus::assertDispatched(ProcessCrmWebhookEvent::class);
        $event = CrmWebhookEvent::first();
        $this->mock(HubspotCosFieldSyncService::class)->shouldReceive('ingestEvent')->once()->with($event->payload)
            ->andReturn(['status' => 'processed', 'users' => 1]);
        $this->mock(CrmContactMirrorService::class)->shouldNotReceive('process');
        (new ProcessCrmWebhookEvent($event->id))->handle(app(CrmContactMirrorService::class));
        $this->assertSame('completed', $event->fresh()->status);
    }

    public function test_paused_processing_retains_authenticated_events_and_their_source_time_without_profile_writes(): void
    {
        config(['crm_sync.processing_enabled' => false]);
        $this->send([$this->event(['occurredAt' => 123456789, 'changeSource' => 'CRM', 'sourceId' => 'app-1'])])->assertStatus(202);
        $event = CrmWebhookEvent::first();
        $this->mock(CrmContactMirrorService::class)->shouldNotReceive('process');
        (new ProcessCrmWebhookEvent($event->id))->handle(app(CrmContactMirrorService::class));
        $this->assertSame('pending', $event->fresh()->status);
        $this->assertSame(0, $event->fresh()->attempts);
        $this->assertSame(123456789, $event->payload['occurredAt']);
        $this->assertSame('firstname', $event->payload['propertyName']);
        $this->assertStringNotContainsString('Private name', json_encode($event->payload));
        Bus::assertNothingDispatched();
        $this->travel(6)->minutes();
        $this->artisan('crm:retry-webhooks')->assertExitCode(0);
        Bus::assertNothingDispatched();
        config(['crm_sync.processing_enabled' => true]);
        $this->artisan('crm:retry-webhooks')->assertExitCode(0);
        Bus::assertDispatched(ProcessCrmWebhookEvent::class, fn ($job) => $job->eventId === $event->id);
    }

    public function test_explicit_legacy_signature_and_disabled_receiver_fail_closed(): void
    {
        $event = [$this->event()];
        config(['crm_sync.hubspot.signature_version' => 'v1']);
        $body = json_encode($event);
        $this->call('POST', route('api.crm.webhook', ['provider' => 'hubspot']), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_HUBSPOT_SIGNATURE' => hash('sha256', 'example-secret'.$body),
        ], $body)->assertStatus(202);
        config(['crm_sync.hubspot.enabled' => false]);
        $this->send($event)->assertStatus(503);
    }

    public function test_teamleader_validates_secret_account_and_subject_and_strips_extra_subject_data(): void
    {
        $url = route('api.crm.webhook', ['provider' => 'teamleader', 'token' => str_repeat('s', 40)]);
        $event = ['type' => 'contact.updated', 'account' => ['id' => 'account-1'],
            'subject' => ['id' => '76c9d03c-ec5c-4b21-9fc6-7ffee488b12d', 'type' => 'contact', 'name' => 'Private name']];
        $this->postJson($url, $event)->assertStatus(202);
        $this->assertStringNotContainsString('Private name', json_encode(CrmWebhookEvent::first()->payload));
        $this->postJson($url, array_replace_recursive($event, ['account' => ['id' => 'wrong']]))->assertUnauthorized();
        $this->postJson($url, array_replace_recursive($event, ['subject' => ['id' => 'invalid']]))->assertUnprocessable();
        $this->postJson(route('api.crm.webhook', ['provider' => 'teamleader']), $event)->assertUnauthorized();
        $this->assertDatabaseCount('crm_webhook_events', 1);
    }

    public function test_leads_never_import_profile_create_accounts_or_change_client_data(): void
    {
        $user = User::factory()->create(['hs_id' => '10', 'nombres' => 'Original'])->assignRole('Cliente');
        $this->source->shouldReceive('contactExists')->once()->with('hubspot', '10')->andReturnTrue();
        $this->source->shouldReceive('contactDeals')->once()->with('hubspot', '10')->andReturn([]);
        $this->source->shouldNotReceive('contact');
        $snapshot = app(CrmContactMirrorService::class)->syncContact('hubspot', '10');
        $this->assertSame('lead', $snapshot->classification);
        $this->assertNull($snapshot->contact);
        $this->assertSame('Original', $user->fresh()->nombres);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_qualified_client_sync_is_idempotent_and_cannot_change_access_or_payment_properties(): void
    {
        $user = User::factory()->create(['hs_id' => '10', 'pay' => 1, 'nombres' => 'Original'])->assignRole('Cliente');
        $deals = [['id' => '20', 'properties' => ['dealstage' => 'closedlost']]];
        $this->source->shouldReceive('contactExists')->twice()->andReturnTrue();
        $this->source->shouldReceive('contactDeals')->twice()->andReturn($deals);
        $this->source->shouldReceive('contact')->twice()->andReturn(
            ['id' => '10', 'properties' => ['firstname' => 'Updated', 'pay' => 2, 'password' => 'bad']],
            ['properties' => ['password' => 'bad', 'pay' => 2, 'firstname' => 'Updated'], 'id' => '10'],
        );
        $this->mock(HubspotDealLocalSyncService::class)->shouldReceive('sync')->once()->withArgs(fn ($client, $rows) => $client->id === $user->id && $rows === $deals)->andReturn([]);
        $this->mock(ClientRestrictionService::class)->shouldReceive('detectHubspotRetirement')->once();
        $service = app(CrmContactMirrorService::class);
        $this->assertSame('client', $service->syncContact('hubspot', '10')->classification);
        $updatedAt = $user->fresh()->updated_at;
        $this->travel(1)->minutes();
        $service->syncContact('hubspot', '10');
        $this->assertSame('Updated', $user->fresh()->nombres);
        $this->assertSame(1, (int) $user->fresh()->pay);
        $this->assertTrue($updatedAt->equalTo($user->fresh()->updated_at));
        $this->assertDatabaseCount('crm_contact_snapshots', 1);
    }

    public function test_api_failure_preserves_previous_classification_and_profile(): void
    {
        CrmContactSnapshot::create(['provider' => 'hubspot', 'contact_id' => '10', 'classification' => 'client',
            'deal_ids' => ['20'], 'contact' => ['id' => '10'], 'synced_at' => now(), 'content_hash' => 'old']);
        $this->source->shouldReceive('contactExists')->andReturnTrue();
        $this->source->shouldReceive('contactDeals')->andThrow(new \RuntimeException('outage'));
        try { app(CrmContactMirrorService::class)->syncContact('hubspot', '10'); $this->fail(); }
        catch (\RuntimeException $exception) { $this->assertSame('outage', $exception->getMessage()); }
        $this->assertSame('client', CrmContactSnapshot::first()->classification);
        $this->assertSame(['id' => '10'], CrmContactSnapshot::first()->contact);
    }

    public function test_removal_of_last_deal_stops_profile_sync_and_deleted_contact_preserves_local_account(): void
    {
        $user = User::factory()->create(['hs_id' => '10'])->assignRole('Cliente');
        CrmContactSnapshot::create(['provider' => 'hubspot', 'contact_id' => '10', 'classification' => 'client',
            'deal_ids' => ['20'], 'contact' => ['id' => '10'], 'synced_at' => now(), 'content_hash' => 'old']);
        $this->source->shouldReceive('contactExists')->once()->andReturnTrue();
        $this->source->shouldReceive('contactDeals')->once()->andReturn([]);
        app(CrmContactMirrorService::class)->process('hubspot', ['subscriptionType' => 'deal.deletion', 'objectId' => 20]);
        $this->assertSame('lead', CrmContactSnapshot::first()->classification);
        $this->assertNull(CrmContactSnapshot::first()->contact);
        $this->source->shouldReceive('contactExists')->once()->andReturnFalse();
        $this->assertSame('deleted', app(CrmContactMirrorService::class)->syncContact('hubspot', '10')->classification);
        $this->assertNotNull($user->fresh());
    }

    public function test_association_removal_rechecks_contact_instead_of_applying_the_old_payload(): void
    {
        $this->source->shouldReceive('contactExists')->once()->with('hubspot', '10')->andReturnTrue();
        $this->source->shouldReceive('contactDeals')->once()->with('hubspot', '10')->andReturn([]);
        app(CrmContactMirrorService::class)->process('hubspot', ['subscriptionType' => 'contact.associationChange',
            'associationType' => 'CONTACT_TO_DEAL', 'fromObjectId' => 10, 'toObjectId' => 20, 'associationRemoved' => true]);
        $this->assertSame('lead', CrmContactSnapshot::first()->classification);
    }

    public function test_admin_status_excludes_payloads_and_other_roles_cannot_view_it(): void
    {
        $url = route('admin.integrations.crm-sync');
        $this->get($url)->assertRedirect(route('login'));
        $client = User::factory()->create()->assignRole('Cliente');
        $this->actingAs($client)->get($url)->assertForbidden();
        $admin = User::factory()->create()->syncRoles(['Administrador']);
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('Sincronización CRM')
            ->assertDontSee('example-secret')->assertDontSee(str_repeat('s', 40));
    }

    public function test_cos_progress_panel_shows_retries_and_is_only_visible_to_internal_admins(): void
    {
        config(['crm_sync.hubspot.cos_fields_only' => true]);
        Cache::forever(CosPortfolioBackfill::PROGRESS_KEY, [
            'status' => 'running', 'calculated' => 17, 'hubspot_review' => 2, 'total' => 100,
            'last_scheduled_at' => now()->toIso8601String(), 'last_progress_at' => now()->subMinutes(5)->toIso8601String(),
            'error' => ['user_id' => 65, 'http_status' => 414, 'type' => 'private.details', 'attempts' => 4],
        ]);
        $url = route('admin.integrations.crm-sync.cos-progress');
        $this->get($url)->assertRedirect(route('login'));
        $mixedRole = User::factory()->create()->assignRole('Administrador');
        $this->actingAs($mixedRole)->get($url)->assertForbidden();

        $admin = User::factory()->create()->syncRoles(['Administrador']);
        $this->actingAs($admin)->get(route('admin.integrations.crm-sync'))
            ->assertOk()->assertSee('Carga inicial del COS')->assertSee($url);
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('aria-valuenow="19"', false)
            ->assertSee('Reintento pendiente')->assertSee('representado 65')->assertSee('HTTP 414')
            ->assertSee('Último lote programado')->assertSee('Último avance')->assertSee('Intentos con este error 4')
            ->assertSee('En espera de la carga')->assertDontSee('private.details');

        Cache::forever(CosPortfolioBackfill::PROGRESS_KEY, ['status' => 'completed', 'completed_at' => now()->toIso8601String()]);
        $this->get($url)->assertOk()->assertSee('Completado')->assertSee('Activos');
        Http::assertNothingSent();
    }

    public function test_paused_processing_disables_local_cos_reads_and_initial_reconciliation(): void
    {
        config(['crm_sync.processing_enabled' => false, 'crm_sync.local_reads_enabled' => true, 'services.monday.mirror_enabled' => true]);
        $this->assertFalse(CrmContactMirrorService::localReadsEnabled());
        $this->mock(CrmContactMirrorService::class)->shouldNotReceive('syncContact');
        $this->artisan('crm:reconcile-contacts', ['provider' => 'hubspot'])->assertExitCode(1);
        config(['crm_sync.processing_enabled' => true, 'crm_sync.hubspot.enabled' => false]);
        $this->artisan('crm:reconcile-contacts', ['provider' => 'hubspot'])->assertExitCode(1);
        Http::assertNothingSent();
    }
}
