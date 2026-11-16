<?php

namespace Tests\Feature;

use App\Models\CrmContactSnapshot;
use App\Models\TlProject;
use App\Models\User;
use App\Services\CosCrmSyncFields;
use App\Services\CrmContactMirrorService;
use App\Services\CrmMirrorSource;
use App\Services\CrmProjectMirrorService;
use App\Services\TeamleaderProjectPaymentAnalyzer;
use App\Services\TeamleaderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CrmCosFieldsTest extends TestCase
{
    private const PROJECT = '76c9d03c-ec5c-4b21-9fc6-7ffee488b12d';
    private const CONTACT = '76c9d03c-ec5c-4b21-9fc6-7ffee488b12e';

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Http::preventStrayRequests();
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2014_10_12_000000_create_users_table.php',
            'database/migrations/2021_03_08_173429_create_permission_tables.php',
            'database/migrations/2026_03_13_164351_create_tl_sync_tables.php',
        ]])->assertExitCode(0);
        Schema::table('users', function (Blueprint $table) {
            $table->string('tl_id')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->timestamp('arraycos_expire')->nullable();
        });
        Schema::create('negocios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('teamleader_id')->nullable();
        });
        Role::findOrCreate('Cliente', 'web');
    }

    private function project(array $changes = []): array
    {
        return $changes + [
            'id' => self::PROJECT, 'title' => 'Proceso', 'status' => 'active',
            'customer' => ['id' => self::CONTACT, 'type' => 'contact'],
            'custom_fields' => [
                ['definition' => ['id' => TeamleaderProjectPaymentAnalyzer::PHASE_FIELDS[1]['preestab']['id']], 'value' => '1000'],
                ['definition' => ['id' => TeamleaderProjectPaymentAnalyzer::PHASE_FIELDS[1]['paid']['id']], 'value' => '500'],
            ],
        ];
    }

    private function event(string $type = 'project.updated'): array
    {
        return ['type' => $type, 'subject' => ['id' => self::PROJECT, 'type' => 'project']];
    }

    private function qualification(string $classification = 'client', int $times = 1): void
    {
        $this->mock(CrmContactMirrorService::class)->shouldReceive('syncContact')->times($times)
            ->with('teamleader', self::CONTACT)->andReturn(new CrmContactSnapshot(['classification' => $classification]));
    }

    public function test_properties_unrelated_to_the_cos_do_not_trigger_remote_reads_or_profile_writes(): void
    {
        $this->mock(CrmMirrorSource::class)->shouldNotReceive('contactExists')->shouldNotReceive('contactDeals');
        app(CrmContactMirrorService::class)->process('hubspot', [
            'subscriptionType' => 'contact.propertyChange', 'objectId' => 10, 'propertyName' => 'utm_campaign',
        ]);
        app(CrmContactMirrorService::class)->process('hubspot', [
            'subscriptionType' => 'deal.propertyChange', 'objectId' => 20, 'propertyName' => 'password',
        ]);
        Http::assertNothingSent();
    }

    public function test_phase_update_recalculates_local_cos_once_without_creating_financial_transactions(): void
    {
        $user = User::factory()->create(['tl_id' => self::CONTACT, 'arraycos_expire' => now()->addDay()])->assignRole('Cliente');
        Cache::put('cos.portfolio.v2.all', 'cached');
        $this->qualification(times: 2);
        $project = $this->project();
        $this->mock(TeamleaderService::class)->shouldReceive('webhookObject')->twice()->with('project', self::PROJECT)->andReturn($project);
        $service = app(CrmProjectMirrorService::class);
        $service->process($this->event());
        $this->assertFalse(Cache::has('cos.portfolio.v2.all'));
        $this->assertTrue($user->fresh()->arraycos_expire < now());
        $mirror = TlProject::findOrFail(self::PROJECT);
        $analysis = app(TeamleaderProjectPaymentAnalyzer::class)->analyzeProject($mirror);
        $this->assertSame(500.0, $analysis['phases'][1]['balance_amount']);
        $updatedAt = $mirror->updated_at;
        $this->travel(1)->minutes();
        $service->process($this->event());
        $this->assertTrue($updatedAt->equalTo($mirror->fresh()->updated_at));
        $this->assertDatabaseCount('tl_invoices', 0);
        Http::assertNothingSent();
    }

    public function test_a_project_without_deals_does_not_import_the_lead_or_the_project(): void
    {
        $this->qualification('lead');
        $this->mock(TeamleaderService::class)->shouldReceive('webhookObject')->once()->andReturn($this->project());
        app(CrmProjectMirrorService::class)->process($this->event('project.created'));
        $this->assertDatabaseCount('tl_projects', 0);
        $this->assertDatabaseCount('tl_contacts', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_company_project_does_not_qualify_all_company_contacts(): void
    {
        $this->mock(CrmContactMirrorService::class)->shouldNotReceive('syncContact');
        $this->mock(TeamleaderService::class)->shouldReceive('webhookObject')->once()
            ->andReturn($this->project(['customer' => ['id' => self::CONTACT, 'type' => 'company']]));
        app(CrmProjectMirrorService::class)->process($this->event());
        $this->assertDatabaseCount('tl_projects', 0);
    }

    public function test_confirmed_removal_preserves_history_excludes_cos_and_delayed_event_can_restore_it(): void
    {
        $original = TlProject::fromTeamleader($this->project());
        $this->mock(TeamleaderService::class)->shouldReceive('webhookObject')->twice()
            ->andReturn(null, $this->project());
        $service = app(CrmProjectMirrorService::class);
        $service->process($this->event('project.deleted'));
        $this->assertDatabaseCount('tl_projects', 1);
        $this->assertSame($original->raw_data, $original->fresh()->raw_data);
        $this->assertSame(0, TlProject::forCos()->count());
        $this->assertSame([], app(TeamleaderProjectPaymentAnalyzer::class)->analyzeProjects(TlProject::all())['projects']);
        $this->qualification();
        $service->process($this->event('project.deleted'));
        $this->assertSame(1, TlProject::forCos()->count());
        $this->assertSame('active', $original->fresh()->status);
    }

    public function test_api_failure_does_not_hide_the_previous_certificate_or_payments(): void
    {
        $original = TlProject::fromTeamleader($this->project());
        $this->mock(TeamleaderService::class)->shouldReceive('webhookObject')->once()->andThrow(new \RuntimeException('outage'));
        try { app(CrmProjectMirrorService::class)->process($this->event()); $this->fail('Expected API failure.'); }
        catch (\RuntimeException $exception) { $this->assertSame('outage', $exception->getMessage()); }
        $this->assertSame('active', $original->fresh()->status);
        $this->assertSame($original->raw_data, $original->fresh()->raw_data);
    }

    public function test_changes_to_unrelated_project_fields_do_not_invalidate_cos_or_touch_the_mirror(): void
    {
        $original = TlProject::fromTeamleader($this->project());
        $this->qualification();
        $this->mock(TeamleaderService::class)->shouldReceive('webhookObject')->once()
            ->andReturn($this->project(['description' => 'Unrelated edit', 'updated_at' => now()->toIso8601String()]));
        Cache::put('cos.portfolio.v2.all', 'cached');
        $this->travel(1)->minutes();
        app(CrmProjectMirrorService::class)->process($this->event());
        $this->assertSame('cached', Cache::get('cos.portfolio.v2.all'));
        $this->assertTrue($original->updated_at->equalTo($original->fresh()->updated_at));
        $this->assertSame(13, count(CosCrmSyncFields::teamleaderProject()));
    }

    public function test_project_move_does_not_expose_another_clients_data_through_an_old_app_link(): void
    {
        $oldContact = '76c9d03c-ec5c-4b21-9fc6-7ffee488b12f';
        $user = User::factory()->create(['tl_id' => $oldContact, 'arraycos_expire' => now()->addDay()])->assignRole('Cliente');
        DB::table('negocios')->insert(['user_id' => $user->id, 'teamleader_id' => self::PROJECT]);
        $old = $this->project(['customer' => ['id' => $oldContact, 'type' => 'contact']]);
        TlProject::fromTeamleader($old);
        $this->qualification();
        $this->mock(TeamleaderService::class)->shouldReceive('webhookObject')->once()->andReturn($this->project());
        try { app(CrmProjectMirrorService::class)->process($this->event()); $this->fail('Expected association conflict.'); }
        catch (\App\Exceptions\CrmProjectAssociationConflictException) {}
        $this->assertSame(0, TlProject::forCos()->count());
        $this->assertSame($old, TlProject::findOrFail(self::PROJECT)->raw_data);
        $this->assertTrue($user->fresh()->arraycos_expire < now());
        $this->assertDatabaseHas('negocios', ['user_id' => $user->id, 'teamleader_id' => self::PROJECT]);
    }
}
