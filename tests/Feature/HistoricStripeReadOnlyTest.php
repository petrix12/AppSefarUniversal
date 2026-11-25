<?php

namespace Tests\Feature;

use App\Http\Middleware\AuditRequests;
use App\Models\{PlatformOperation, PlatformRecord, User};
use App\Services\{PlatformApiException, PlatformMirror, PlatformOperationService, StripeAdminApi};
use Illuminate\Support\Facades\{DB, Http, Mail, Schema};
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HistoricStripeReadOnlyTest extends TestCase
{
    use \Tests\Concerns\RegistrationSchema;

    private const BASE = '/admin/plataformas/stripe/spain_legacy';

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default'=>'array','session.driver'=>'array','services.stripe.secret'=>'sk_test_main',
            'services.stripe.history_es_secret'=>'sk_test_history','app.key'=>'base64:'.base64_encode(str_repeat('s',32))]);
        $this->withoutMiddleware(AuditRequests::class);
        Http::preventStrayRequests(); Mail::fake();
        $this->createRegistrationSchema();
        Schema::table('users', fn($t)=>$t->boolean('is_superadmin')->default(false));
        $this->artisan('migrate',['--path'=>[
            'database/migrations/2026_11_20_180000_create_kalendia_bookings_table.php',
            'database/migrations/2026_11_20_190000_create_kalendia_consultation_management_tables.php',
            'database/migrations/2026_11_22_120000_create_kalendia_webhook_events_table.php',
            'database/migrations/2026_11_22_180000_create_platform_management_tables.php',
        ]])->assertExitCode(0);
        Role::findOrCreate('Administrador');
    }

    private function admin(): User
    {
        $user=User::factory()->create(['is_superadmin'=>true]);
        $user->syncRoles(['Administrador']);
        return $user;
    }

    private function record(string $resource='charges', string $account='spain_legacy'): PlatformRecord
    {
        $data=match($resource) {
            'customers'=>['id'=>'cus_history','name'=>'Contacto histórico','email'=>'history@example.invalid'],
            'payment_intents'=>['id'=>'pi_history','amount'=>5136,'currency'=>'eur','status'=>'requires_capture'],
            default=>['id'=>'ch_same','description'=>'Histórico España','amount'=>5136,'currency'=>'eur','status'=>'succeeded','paid'=>true,'captured'=>true],
        };
        return app(PlatformMirror::class)->save('stripe',$account,$resource,$data+['livemode'=>false]);
    }

    public function test_history_is_identified_and_has_no_mutation_controls_even_for_a_superadmin(): void
    {
        $this->actingAs($this->admin());
        $this->get(self::BASE)->assertRedirect(route('admin.stripe.workspace', ['account' => 'spain_legacy']));
        $charge = ['id'=>'ch_same', 'object'=>'charge', 'status'=>'succeeded', 'amount'=>5136,
            'amount_refunded'=>0, 'currency'=>'eur', 'paid'=>true, 'captured'=>true];
        Http::fake([
            'api.stripe.com/v1/charges?*' => Http::response(['data'=>[$charge], 'has_more'=>false]),
            'api.stripe.com/v1/charges/ch_same' => Http::response($charge),
        ]);
        $this->get('/admin/stripe?account=spain_legacy&date_to=2026-11-24')->assertOk()
            ->assertSee('España · solo consulta')->assertSee('ch_same')->assertDontSee('Webhooks y actividad');
        $this->getJson('/admin/stripe/spain_legacy/ch_same')->assertOk()->assertDontSee('Gestionar reembolso');
        $this->assertDatabaseCount('platform_records', 0);
        Http::assertSentCount(2);
        foreach (Http::recorded() as [$request]) {
            $this->assertSame('GET', $request->method());
            $this->assertSame(['Bearer sk_test_history'], $request->header('Authorization'));
        }
        foreach(['charges','customers','payment_intents'] as $resource) {
            $row=$this->record($resource);
            $this->getJson(self::BASE.'/registros/'.$row->id)->assertOk()
                ->assertDontSee('Confirmar reembolso')->assertDontSee('Confirmar cancelación')->assertDontSee('Guardar contacto');
        }
        $this->get(self::BASE.'/datos/customers')->assertOk()->assertDontSee('Crear contacto');
        Http::assertSentCount(2);
    }

    public function test_direct_write_routes_cannot_bypass_the_historical_restriction(): void
    {
        $actor=$this->admin(); $this->actingAs($actor);
        foreach(['charges'=>'refund','customers'=>'customer_update','payment_intents'=>'cancel_payment'] as $resource=>$action) {
            $row=$this->record($resource);
            $this->postJson(self::BASE.'/registros/'.$row->id.'/operar',['operation_id'=>(string)Str::uuid(),'action'=>$action,'confirmed'=>'1'])->assertForbidden();
        }
        $this->postJson(self::BASE.'/clientes',[])->assertForbidden();
        $this->assertDatabaseCount('platform_operations',0);
        $row=$this->record();
        $operation=PlatformOperation::create(['record_id'=>$row->id,'actor_id'=>$actor->id,'uuid'=>(string)Str::uuid(),
            'action'=>'refund','status'=>'uncertain','payload'=>['input'=>[]]]);
        $this->postJson(self::BASE.'/operaciones/'.$operation->id.'/reintentar',['confirmed'=>'1'])->assertForbidden();
        $this->assertSame('uncertain',$operation->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_the_operation_service_also_rejects_historical_writes_before_creating_an_operation(): void
    {
        $actor=$this->admin(); $row=$this->record();
        try {
            app(PlatformOperationService::class)->run($row,$actor,(string)Str::uuid(),'refund',['amount'=>'51.36']);
            $this->fail('The historical account must reject direct service writes');
        } catch(HttpException $e) { $this->assertSame(403,$e->getStatusCode()); }
        $this->assertDatabaseCount('platform_operations',0);
        Http::assertNothingSent();
    }

    public function test_the_stripe_transport_rejects_all_non_get_methods_for_history(): void
    {
        foreach(['POST','DELETE','PATCH'] as $method) {
            try {
                app(StripeAdminApi::class)->request('spain_legacy',$method,'refunds',['charge'=>'ch_same']);
                $this->fail('Historical writes cannot reach Stripe');
            } catch(PlatformApiException $e) { $this->assertSame('read_only_account',$e->errorCode); }
        }
        Http::assertNothingSent();
    }

    public function test_historical_imports_use_the_separate_key_and_never_create_app_receipts(): void
    {
        $this->actingAs($this->admin());
        $this->post(self::BASE.'/datos/charges/sincronizar')->assertRedirect();
        Http::fake(['api.stripe.com/*'=>Http::response(['data'=>[['id'=>'ch_import','amount'=>5136,'currency'=>'eur','livemode'=>false]],'has_more'=>false])]);
        $this->assertSame(1,app(PlatformMirror::class)->sync()['records']);
        Http::assertSent(fn($r)=>$r->method()==='GET' && $r->header('Authorization')===['Bearer sk_test_history']);
        $this->assertSame('spain_legacy',PlatformRecord::sole()->account);
        $this->assertDatabaseCount('compras',0); $this->assertDatabaseCount('facturas',0);
    }

    public function test_missing_historical_credentials_never_fall_back_to_main_or_retired_keys(): void
    {
        config(['services.stripe.history_es_secret'=>null,'services.stripe.secret_port'=>'sk_test_wrong_account']);
        $this->actingAs($this->admin())->get('/admin/stripe?account=spain_legacy')->assertOk()
            ->assertSee('Falta configurar la clave');
        try {
            app(StripeAdminApi::class)->request('spain_legacy','GET','charges');
            $this->fail('A missing historical key must not fall back');
        } catch(PlatformApiException $e) { $this->assertSame('not_configured',$e->errorCode); }
        Http::assertNothingSent();
    }

    public function test_identical_remote_ids_never_cross_main_and_historical_accounts(): void
    {
        $this->actingAs($this->admin());
        $main=$this->record('charges','main'); $history=$this->record();
        $this->assertNotSame($main->id,$history->id);
        $this->getJson(self::BASE.'/registros/'.$main->id)->assertNotFound();
        $this->getJson('/admin/plataformas/stripe/main/registros/'.$history->id)->assertNotFound();
        $this->get('/admin/plataformas/kalendia/spain_legacy')->assertNotFound();
        $this->postJson('/api/webhooks/stripe/admin/spain_legacy',[])->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_clients_cannot_read_history_even_with_a_manually_granted_permission(): void
    {
        $user=User::factory()->create(); $user->assignRole('Cliente');
        \Spatie\Permission\Models\Permission::findOrCreate('stripe.manage'); $user->givePermissionTo('stripe.manage');
        $this->actingAs($user)->get(self::BASE)->assertForbidden();
        $this->get('/admin/stripe?account=spain_legacy')->assertForbidden();
        $this->getJson('/admin/stripe/spain_legacy/ch_same')->assertForbidden();
        Http::assertNothingSent();
    }
}
