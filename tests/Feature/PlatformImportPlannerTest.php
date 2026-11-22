<?php
namespace Tests\Feature;

use App\Services\PlatformImportPlanner;
use Illuminate\Support\Facades\{DB, Http, Schema};
use Tests\TestCase;

class PlatformImportPlannerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.secret'=>'', 'services.stripe.history_es_secret'=>'sk_test_legacy',
            'kalendia.token'=>'', 'services.paypal.history_es_client_id'=>'', 'services.paypal.history_es_secret'=>'']);
        Http::preventStrayRequests();
        $this->createRegistrationSchema();
        $this->artisan('migrate', ['--path'=>'database/migrations/2026_11_22_180000_create_platform_management_tables.php'])->assertExitCode(0);
    }

    use \Tests\Concerns\RegistrationSchema;

    public function test_plan_is_read_only_and_reports_supported_accounts_without_credential_fallback(): void
    {
        config(['services.stripe.secret'=>'', 'services.stripe.history_es_secret'=>'sk_test_history']);
        $result = app(PlatformImportPlanner::class)->plan();
        $legacy = collect($result['connections'])->firstWhere('account', 'spain_legacy');
        $main = collect($result['connections'])->first(fn ($row) => $row['provider']==='stripe' && $row['account']==='main');
        $this->assertTrue($legacy['configured']);
        $this->assertFalse($main['configured']);
        $this->assertTrue($legacy['read_only']);
        $this->assertSame(0, DB::table('platform_sync_states')->count());
        Http::assertNothingSent();
    }

    public function test_queue_skips_unconfigured_sources_and_repeated_requests_without_resetting_cursor(): void
    {
        config(['services.stripe.secret'=>'sk_test_main', 'kalendia.token'=>'']);
        $planner = app(PlatformImportPlanner::class);
        $first = $planner->queueAccount('stripe', 'main');
        $this->assertSame(9, $first['requested']);
        DB::table('platform_sync_states')->where('resource','charges')->update(['cursor'=>'ch_saved','page'=>7,'has_more'=>true]);
        $second = $planner->queueAccount('stripe', 'main');
        $this->assertSame(9, $second['already_running']);
        $state = DB::table('platform_sync_states')->where('resource','charges')->first();
        $this->assertSame('ch_saved', $state->cursor);
        $this->assertSame(7, $state->page);
        $this->assertTrue((bool)$state->requested);
        $this->assertSame(13, $planner->queueAccount('kalendia', 'main')['skipped']);
        Http::assertNothingSent();
    }

    public function test_invalid_filter_is_rejected_before_any_database_write(): void
    {
        try {
            app(PlatformImportPlanner::class)->queueAll('stripe', 'retired');
            $this->fail('Expected invalid account filter to be rejected.');
        } catch (\InvalidArgumentException) {
            $this->assertSame(0, DB::table('platform_sync_states')->count());
        }
        Http::assertNothingSent();
    }

    public function test_partial_filters_and_paypal_keep_their_supported_scope(): void
    {
        $planner = app(PlatformImportPlanner::class);
        $this->assertSame(['main','spain_legacy'], collect($planner->plan('stripe')['connections'])->pluck('account')->all());
        $this->assertSame(['stripe','kalendia'], collect($planner->plan(null, 'main')['connections'])->pluck('provider')->all());
        $paypal = $planner->plan('paypal', 'spain_legacy')['connections'][0];
        $this->assertSame('api', $paypal['strategy']);
        $this->assertSame([], $paypal['resources']);
        $this->assertSame(['requested'=>0,'already_running'=>0,'skipped'=>1,'configured'=>0,'reasons'=>['api_only'=>1]],
            $planner->queueAll('paypal', 'spain_legacy'));
        Http::assertNothingSent();
    }

    public function test_cli_reports_partial_queue_as_success_when_a_source_is_configured(): void
    {
        config(['services.stripe.secret'=>'sk_test_main']);
        $this->artisan('platforms:import', ['--queue'=>true])
            ->assertExitCode(0)->expectsOutputToContain('omitidas:');
    }
}
