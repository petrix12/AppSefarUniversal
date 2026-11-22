<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PaypalHistoryApi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaypalHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-11-22T12:00:00Z');
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2014_10_12_000000_create_users_table.php',
            'database/migrations/2021_03_08_173429_create_permission_tables.php',
        ]])->assertExitCode(0);
        foreach (['Administrador', 'Cliente', 'Ventas'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        Permission::findOrCreate('paypal.history', 'web');
        config(['services.paypal.history_es_client_id' => 'legacy-id', 'services.paypal.history_es_secret' => 'legacy-secret']);
        Http::preventStrayRequests();
    }

    public function test_access_requires_internal_admin_role_and_permission(): void
    {
        $this->get(route('admin.paypal.history'))->assertRedirect(route('login'));
        $client = User::factory()->create()->syncRoles(['Cliente'])->givePermissionTo('paypal.history');
        $this->actingAs($client)->get(route('admin.paypal.history'))->assertForbidden();
        $staff = User::factory()->create()->syncRoles(['Ventas'])->givePermissionTo('paypal.history');
        $this->actingAs($staff)->get(route('admin.paypal.history'))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_missing_credentials_shows_pending_state_without_using_primary_paypal_credentials(): void
    {
        config(['services.paypal.history_es_client_id' => null, 'services.paypal.history_es_secret' => null,
            'services.paypal.client_id' => 'current-id']);
        $this->actingAs($this->admin())->get(route('admin.paypal.history', [
            'start_date' => '2026-11-01', 'end_date' => '2026-11-02',
        ]))->assertOk()->assertSee('faltan las credenciales dedicadas');
        Http::assertNothingSent();
    }

    public function test_search_uses_oauth_and_only_the_read_only_reporting_get_with_page_size_100(): void
    {
        Http::fake([
            'https://api-m.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token'], 200),
            'https://api-m.paypal.com/v1/reporting/transactions*' => Http::response([
                'page' => 2, 'total_pages' => 3, 'transaction_details' => [[
                    'transaction_info' => ['transaction_id' => 'ABCDEFGHIJKLMNOPQ', 'transaction_status' => 'S',
                        'transaction_amount' => ['value' => '10.00', 'currency_code' => 'EUR'],
                        'transaction_subject' => 'Test'],
                    'payer_info' => ['email_address' => 'buyer@example.test'],
                ]],
            ], 200),
        ]);

        $this->actingAs($this->admin())->get(route('admin.paypal.history', [
            'start_date' => '2026-11-01', 'end_date' => '2026-11-03',
            'transaction_id' => 'ABCDEFGHIJKLMNOPQ', 'page' => 2,
        ]))->assertOk()->assertSee('ABCDEFGHIJKLMNOPQ')->assertSee('buyer@example.test')
            ->assertSee('Test')->assertSee('Página 2 de 3');

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://api-m.paypal.com/v1/oauth2/token'
            && $request['grant_type'] === 'client_credentials');
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && parse_url($request->url(), PHP_URL_HOST) === 'api-m.paypal.com'
            && parse_url($request->url(), PHP_URL_PATH) === '/v1/reporting/transactions'
            && $request['fields'] === 'transaction_info,payer_info'
            && $request['page_size'] === 100 && $request['page'] === 2
            && $request['transaction_id'] === 'ABCDEFGHIJKLMNOPQ');
    }

    public function test_invalid_range_bounds_and_pagination_are_rejected_before_oauth(): void
    {
        $this->actingAs($this->admin())->get(route('admin.paypal.history', [
            'start_date' => '2026-10-01', 'end_date' => '2026-11-01',
        ]))->assertSessionHasErrors('end_date');
        $this->get(route('admin.paypal.history', [
            'start_date' => '2023-11-21', 'end_date' => '2023-11-22',
        ]))->assertSessionHasErrors('start_date');
        $this->get(route('admin.paypal.history', [
            'start_date' => '2026-11-01', 'end_date' => '2026-11-02', 'page' => 0,
        ]))->assertSessionHasErrors('page');
        Http::assertNothingSent();
    }

    public function test_service_and_own_route_do_not_expose_write_actions(): void
    {
        $methods = app('router')->getRoutes()->getByName('admin.paypal.history')->methods();
        $this->assertContains('GET', $methods);
        $this->assertNotContains('POST', $methods);
        $this->actingAs($this->admin())->postJson(route('admin.paypal.history'), ['action'=>'refund'])->assertStatus(405);
        Http::assertNothingSent();
    }

    public function test_provider_credentials_and_errors_never_appear_in_the_history_page(): void
    {
        Http::fake(['api-m.paypal.com/*'=>Http::response(['message'=>'SECRET-DO-NOT-EXPOSE','access_token'=>'PRIVATE-TOKEN'],401)]);
        $this->actingAs($this->admin())->get(route('admin.paypal.history',[
            'start_date'=>'2026-11-01','end_date'=>'2026-11-02',
        ]))->assertOk()->assertSee('PayPal no autorizó la consulta histórica.')
            ->assertDontSee('SECRET-DO-NOT-EXPOSE')->assertDontSee('PRIVATE-TOKEN')->assertDontSee('legacy-secret');
        Http::assertSentCount(1);
    }

    public function test_the_read_projection_drops_unneeded_provider_data_and_today_never_queries_the_future(): void
    {
        Http::fake([
            'api-m.paypal.com/v1/oauth2/token'=>Http::response(['access_token'=>'fake-token']),
            'api-m.paypal.com/v1/reporting/transactions*'=>Http::response(['transaction_details'=>[[
                'transaction_info'=>['transaction_id'=>'ABCDEFGHIJKLMNOPQ','private_token'=>'do-not-retain',
                    'transaction_amount'=>['value'=>'51.36','currency_code'=>'EUR','private'=>'do-not-retain']],
                'payer_info'=>['email_address'=>'buyer@example.invalid','address'=>['street'=>'do-not-retain']],
                'payment_source'=>['card'=>'do-not-retain'],
            ]]]),
        ]);
        $this->actingAs($this->admin())->get(route('admin.paypal.history',[
            'start_date'=>'2026-11-22','end_date'=>'2026-11-22',
        ]))->assertOk()->assertSee('51.36')->assertHeader('Cache-Control','no-store, private');
        Http::assertSent(fn($r)=>$r->method()==='GET' && $r['end_date']==='2026-11-22T12:00:00Z');
        $result=app(PaypalHistoryApi::class)->transactions('2026-11-22T00:00:00Z','2026-11-22T12:00:00Z');
        $this->assertStringNotContainsString('do-not-retain',json_encode($result));
        $this->assertSame('buyer@example.invalid',data_get($result,'transactions.0.payer_info.email_address'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create()->syncRoles(['Administrador'])->givePermissionTo('paypal.history');
    }
}
