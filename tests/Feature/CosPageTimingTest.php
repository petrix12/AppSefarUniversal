<?php

namespace Tests\Feature;

use App\Http\Controllers\UserController;
use App\Http\Middleware\AuditRequests;
use App\Models\User;
use App\Support\CosPageTiming;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CosPageTimingTest extends TestCase
{
    private User $admin;
    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cos_diagnostics.viewer_email' => 'owner@example.test', 'cos_diagnostics.viewer_user_id' => null]);
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2014_10_12_000000_create_users_table.php',
            'database/migrations/2021_03_08_173429_create_permission_tables.php',
        ]])->assertExitCode(0);
        foreach (['Administrador', 'Cliente', 'Ventas'] as $role) Role::findOrCreate($role, 'web');
        $this->admin = User::factory()->create(['email' => 'owner@example.test'])->syncRoles(['Administrador']);
        $this->client = User::factory()->create();
        $this->withoutMiddleware(AuditRequests::class);
        Gate::define('crud.users.index', fn () => true);
        Log::spy();
        $this->mock(UserController::class)->makePartial()->shouldReceive('edit')->andReturnUsing(function (User $user) {
            DB::select('select 1 as diagnostic_query');
            CosPageTiming::current()?->checkpoint('fixture', 'Carga de datos');
            $html = view('crud.users.partials.load-timing')->render();
            CosPageTiming::current()?->checkpoint('blade', 'Renderizado de Blade');
            return $html;
        });
    }

    public function test_opt_in_admin_measurement_captures_sql_blade_and_a_unique_report_without_query_contents(): void
    {
        $url = route('crud.users.edit', $this->client).'?cos_timing=1';
        $response = $this->actingAs($this->admin)->get($url)->assertOk();
        $response->assertHeader('X-COS-Timing-ID')->assertHeader('Server-Timing');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $report = $this->report($response->getContent());
        $this->assertGreaterThan(0, $report['queries']);
        $this->assertGreaterThanOrEqual(0, $report['server_ms']);
        $this->assertContains('blade', array_column($report['stages'], 'name'));
        $this->assertSame($report['queries'], array_sum(array_column($report['stages'], 'queries')));
        $this->assertStringNotContainsString('diagnostic_query', json_encode($report));
        $this->assertStringNotContainsString($this->client->email, json_encode($report));
        $this->assertNull(CosPageTiming::current());
        Log::shouldHaveReceived('info')->with('COS carga cronometrada', \Mockery::on(fn ($data) => $data['trace_id'] === $report['trace_id']))->once();
        $second = $this->get($url)->assertOk();
        $this->assertNotSame($report['trace_id'], $this->report($second->getContent())['trace_id']);
        $this->assertNull(CosPageTiming::current());
    }

    public function test_normal_requests_show_only_the_admin_start_control_and_do_not_emit_a_report(): void
    {
        $response = $this->actingAs($this->admin)->get(route('crud.users.edit', $this->client))->assertOk();
        $response->assertSee('Cronometrar carga')->assertDontSee('cos-timing-data');
        $response->assertHeaderMissing('Server-Timing')->assertHeaderMissing('X-COS-Timing-ID');
        Log::shouldNotHaveReceived('info');
    }

    public function test_staff_clients_and_guests_cannot_obtain_diagnostics_with_a_query_flag(): void
    {
        $url = route('crud.users.edit', $this->client).'?cos_timing=1';
        $this->get($url)->assertRedirect(route('login'))->assertHeaderMissing('Server-Timing');
        $staff = User::factory()->create()->syncRoles(['Ventas']);
        $this->actingAs($staff)->get($url)->assertOk()->assertDontSee('cos-timing-data')->assertHeaderMissing('Server-Timing');
        $otherAdmin = User::factory()->create()->syncRoles(['Administrador']);
        $this->actingAs($otherAdmin)->get($url)->assertOk()->assertDontSee('Cronometrar carga')->assertDontSee('cos-timing-data')->assertHeaderMissing('Server-Timing');
        $this->actingAs($this->client)->get($url)->assertForbidden()->assertHeaderMissing('Server-Timing');
        Log::shouldNotHaveReceived('info');
        $this->assertNull(CosPageTiming::current());
    }

    public function test_an_exception_is_logged_without_its_sensitive_message_and_does_not_leak_the_timer(): void
    {
        $this->mock(UserController::class)->makePartial()->shouldReceive('edit')->andThrow(new \RuntimeException('secret CRM payload'));
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->admin)->get(route('crud.users.edit', $this->client).'?cos_timing=1');
            $this->fail('Expected an exception.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('secret CRM payload', $exception->getMessage());
        }
        Log::shouldHaveReceived('warning')->with('COS carga cronometrada fallida', \Mockery::on(fn ($data) =>
            $data['exception_class'] === \RuntimeException::class && ! str_contains(json_encode($data), 'secret CRM payload')))->once();
        $this->assertNull(CosPageTiming::current());
    }

    public function test_monotonic_stage_measurements_and_external_failures_are_aggregated_without_double_counting(): void
    {
        $now = 100.0;
        $timing = new CosPageTiming(function () use (&$now): float { return $now; });
        request()->attributes->set(CosPageTiming::ATTRIBUTE, $timing);
        $timing->query(12.5);
        $now += 25;
        $timing->checkpoint('local', 'Datos locales');
        $result = CosPageTiming::external('crm', 'CRM', function () use (&$now) { $now += 100; return 'result'; });
        $this->assertSame('result', $result);
        try {
            CosPageTiming::external('crm', 'CRM', function () use (&$now) { $now += 75; throw new \RuntimeException('failed'); });
        } catch (\RuntimeException) {}
        $timing->query(2.5);
        $now += 5;
        $timing->checkpoint('remote', 'Carga remota');
        $report = $timing->finish();
        $this->assertSame(205.0, $report['server_ms']);
        $this->assertSame(15.0, $report['query_ms']);
        $this->assertSame(180.0, $report['stages'][1]['duration_ms']);
        $this->assertSame(25.0, $report['stages'][1]['started_ms']);
        $this->assertSame(205.0, $report['stages'][1]['ended_ms']);
        $this->assertSame(['name' => 'crm', 'label' => 'CRM', 'duration_ms' => 175.0, 'calls' => 2, 'failures' => 1], $report['external'][0]);
        $this->assertSame(25.0, $report['external_calls'][0]['started_ms']);
        $this->assertSame(125.0, $report['external_calls'][1]['started_ms']);
        $this->assertSame($report, $timing->finish());
        $this->assertNull(CosPageTiming::current());
        request()->attributes->remove(CosPageTiming::ATTRIBUTE);
    }

    public function test_diagnostic_access_can_be_pinned_to_a_user_id_and_an_empty_allowlist_denies_everyone(): void
    {
        $url = route('crud.users.edit', $this->client).'?cos_timing=1';
        config(['cos_diagnostics.viewer_user_id' => $this->client->id]);
        $this->actingAs($this->admin)->get($url)->assertOk()->assertHeaderMissing('Server-Timing')->assertDontSee('Cronometrar carga');
        config(['cos_diagnostics.viewer_user_id' => $this->admin->id]);
        $this->get($url)->assertOk()->assertHeader('Server-Timing');
        config(['cos_diagnostics.viewer_email' => '']);
        $this->get($url)->assertOk()->assertHeaderMissing('Server-Timing')->assertDontSee('Cronometrar carga');
    }

    public function test_http_breakdown_records_timing_and_status_without_urls_headers_or_response_data(): void
    {
        $timing = new CosPageTiming();
        request()->attributes->set(CosPageTiming::ATTRIBUTE, $timing);
        $request = new \GuzzleHttp\Psr7\Request('GET', 'https://api.example.test/contacts/private-id?token=secret', ['Authorization' => 'Bearer secret']);
        $stats = new \GuzzleHttp\TransferStats($request, new \GuzzleHttp\Psr7\Response(200, [], 'confidential body'), 1.25, null, [
            'namelookup_time' => .01, 'connect_time' => .03, 'appconnect_time' => .08,
            'pretransfer_time' => .09, 'starttransfer_time' => 1.2,
        ]);
        CosPageTiming::httpTransfer('HubSpot', $stats);
        $report = $timing->finish();
        $call = $report['http_transfers'][0];
        $this->assertSame(1250.0, $call['duration_ms']);
        $this->assertSame(10.0, $call['dns_ms']);
        $this->assertSame(20.0, $call['tcp_ms']);
        $this->assertSame(50.0, $call['tls_ms']);
        $this->assertSame(1110.0, $call['waiting_ms']);
        $this->assertSame(50.0, $call['download_ms']);
        $this->assertSame(200, $call['status']);
        foreach (['secret', 'private-id', 'Authorization', 'confidential body'] as $private) $this->assertStringNotContainsString($private, json_encode($report));
        request()->attributes->remove(CosPageTiming::ATTRIBUTE);
    }

    private function report(string $html): array
    {
        $this->assertMatchesRegularExpression('/<script type="application\/json" id="cos-timing-data">(.*?)<\/script>/s', $html);
        preg_match('/<script type="application\/json" id="cos-timing-data">(.*?)<\/script>/s', $html, $matches);
        return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    }
}
