<?php
namespace Tests\Feature;

use App\Http\Middleware\AuditRequests;
use App\Models\{PlatformOperation, PlatformRecord, User};
use App\Services\{PlatformEventProcessor, PlatformMirror, PlatformOperationService, StripeMoney};
use Illuminate\Support\Facades\{DB, Http, Mail, Schema};
use Illuminate\Support\Str;
use Spatie\Permission\Models\{Permission, Role};
use Tests\TestCase;

class PlatformManagementTest extends TestCase
{
    use \Tests\Concerns\RegistrationSchema;
    private const BASE='/admin/plataformas/stripe/main';
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default'=>'array','session.driver'=>'array','services.stripe.secret'=>'sk_test_fake',
            'services.stripe.admin_webhook_secret'=>'whsec_admin','kalendia.token'=>'fake','kalendia.webhook_secret'=>'kalendia_secret',
            'kalendia.webhook_business_id'=>'site_test','app.key'=>'base64:'.base64_encode(str_repeat('s',32))]);
        $this->withoutMiddleware(AuditRequests::class); Mail::fake(); Http::preventStrayRequests();
        $this->createRegistrationSchema();
        Schema::table('users',fn($t)=>$t->boolean('is_superadmin')->default(false));
        $this->artisan('migrate',['--path'=>[
            'database/migrations/2026_11_20_180000_create_kalendia_bookings_table.php',
            'database/migrations/2026_11_20_190000_create_kalendia_consultation_management_tables.php',
            'database/migrations/2026_11_22_120000_create_kalendia_webhook_events_table.php',
            'database/migrations/2026_11_22_180000_create_platform_management_tables.php',
        ]])->assertExitCode(0);
        Role::findOrCreate('Administrador');
    }
    private function admin(bool $super=false): User
    {
        $user=User::factory()->create(); $user->syncRoles(['Administrador']);
        foreach(['crud.stripeverify.index','admin.payments'] as $permission) { Permission::findOrCreate($permission); $user->givePermissionTo($permission); }
        $user->forceFill(['is_superadmin'=>$super])->save(); return $user;
    }
    private function charge(array $extra=[]): array
    {
        return array_replace(['id'=>'ch_one','object'=>'charge','amount'=>10000,'amount_refunded'=>0,'captured'=>true,'paid'=>true,
            'currency'=>'eur','status'=>'succeeded','livemode'=>false,'description'=>'Pago de prueba','client_secret'=>'do-not-store',
            'billing_details'=>['address'=>['line1'=>'private']],'payment_method_details'=>['card'=>['last4'=>'1234']]],$extra);
    }
    private function record(string $provider='stripe',string $resource='charges',array $data=[]): PlatformRecord
    {
        return app(PlatformMirror::class)->save($provider,'main',$resource,$data?:$this->charge());
    }
    private function refundInput(array $extra=[]): array
    {
        return array_replace(['operation_id'=>(string)Str::uuid(),'action'=>'refund','amount'=>'25.50','reason'=>'requested_by_customer',
            'comment'=>'Solicitud revisada con el cliente','confirmed'=>'1'],$extra);
    }
    private function stripeEvent(string $id='evt_one',string $type='charge.succeeded',array $object=[])
    {
        $body=json_encode(['id'=>$id,'object'=>'event','type'=>$type,'livemode'=>false,'data'=>['object'=>$object?:$this->charge()]]);
        $signature='t='.time().',v1='.hash_hmac('sha256',time().'.'.$body,'whsec_admin');
        return $this->call('POST','/api/webhooks/stripe/admin/main',[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_STRIPE_SIGNATURE'=>$signature],$body);
    }
    public function test_legacy_records_and_webhooks_keep_using_local_data(): void
    {
        $this->actingAs($this->admin()); $row=$this->record();
        $this->assertTrue(auth()->user()->hasRole('Administrador'));
        $this->assertFalse(auth()->user()->hasRole('Cliente'));
        $this->assertTrue(auth()->user()->can('admin.payments'));
        $this->assertTrue(auth()->user()->can('crud.stripeverify.index'));
        $this->assertTrue(auth()->user()->can('stripe.manage'));
        $this->get(self::BASE)->assertRedirect(route('admin.stripe.workspace', ['account' => 'main']));
        $this->get(self::BASE.'/datos/charges')->assertOk()->assertSee('Pago de prueba');
        $this->getJson(self::BASE.'/registros/'.$row->id)->assertOk()->assertJsonMissing(['client_secret'=>'do-not-store']);
        $this->get(self::BASE.'/webhooks')->assertOk(); Http::assertNothingSent();
        $stored=DB::table('platform_records')->first();
        $this->assertStringNotContainsString('Pago de prueba',$stored->data);
        $this->assertArrayNotHasKey('client_secret',$row->data); $this->assertArrayNotHasKey('billing_details',$row->data);
    }

    public function test_live_workspace_keeps_the_decline_diagnosis_and_filters_on_stripe_without_importing(): void
    {
        $this->actingAs($this->admin());
        $event = [
            'id' => 'evt_declined', 'type' => 'payment_intent.payment_failed', 'created' => 1795482000,
            'data' => ['object' => [
                'id' => 'pi_declined', 'object' => 'payment_intent', 'status' => 'requires_payment_method',
                'amount' => 2500, 'currency' => 'eur', 'client_secret' => 'sensitive-test-value',
                'last_payment_error' => ['code' => 'card_declined', 'decline_code' => 'insufficient_funds', 'message' => 'Fondos insuficientes'],
            ]],
        ];
        Http::fake([
            'api.stripe.com/v1/events?*' => Http::response(['data' => [$event], 'has_more' => true]),
            'api.stripe.com/v1/events/evt_declined' => Http::response($event),
        ]);
        $this->get(self::BASE.'/auditoria-pagos')->assertRedirect(route('admin.stripe.workspace', [
            'account'=>'main', 'dataset'=>'events', 'category'=>'failures',
        ]));
        $this->get('/admin/stripe?dataset=events&date_from=2026-11-23')->assertOk()
            ->assertSee('Fondos insuficientes')->assertSee('25,00 EUR')->assertSee('insufficient_funds')
            ->assertSee('starting_after=evt_declined')->assertDontSee('sensitive-test-value');
        $this->getJson('/admin/stripe/main/evt_declined')->assertOk()
            ->assertSee('card_declined')->assertSee('insufficient_funds')->assertDontSee('sensitive-test-value');
        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);
            return $request->method() === 'GET' && str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/events')
                && in_array('payment_intent.payment_failed', $query['types'] ?? [], true)
                && ($query['created']['gte'] ?? null) === (string) strtotime('2026-11-23T00:00:00Z');
        });
        Http::assertSentCount(2);
        $this->assertDatabaseCount('platform_records', 0);
        $this->assertDatabaseCount('platform_operations', 0);
    }

    public function test_live_charge_detail_only_offers_refund_to_superadmins_for_eligible_payments(): void
    {
        Http::fake([
            'api.stripe.com/v1/charges/ch_one' => Http::response($this->charge()),
            'api.stripe.com/v1/charges/ch_failed' => Http::response($this->charge([
                'id'=>'ch_failed', 'status'=>'failed', 'paid'=>false, 'captured'=>false, 'failure_code'=>'card_declined',
            ])),
        ]);
        $this->actingAs($this->admin())->getJson('/admin/stripe/main/ch_one')->assertOk()->assertDontSee('Gestionar reembolso');
        $this->actingAs($this->admin(true))->getJson('/admin/stripe/main/ch_one')->assertOk()->assertSee('Gestionar reembolso');
        $this->getJson('/admin/stripe/main/ch_failed')->assertOk()->assertSee('card_declined')->assertDontSee('Gestionar reembolso');
        $this->assertDatabaseCount('platform_records', 0);
        $this->assertDatabaseCount('platform_operations', 0);
    }
    public function test_an_ordinary_admin_cannot_refund_or_cancel_even_with_the_transactions_permission(): void
    {
        $admin=$this->admin(); Permission::findOrCreate('stripe.transactions'); $admin->givePermissionTo('stripe.transactions');
        $row=$this->record(); $this->actingAs($admin);
        $this->getJson(self::BASE.'/registros/'.$row->id)->assertOk()->assertDontSee('Confirmar reembolso');
        $this->postJson(self::BASE.'/registros/'.$row->id.'/operar',$this->refundInput())->assertForbidden();
        $this->postJson(self::BASE.'/registros/'.$row->id.'/operar',$this->refundInput(['action'=>'cancel_payment']))->assertForbidden();
        $this->assertDatabaseCount('platform_operations',0); Http::assertNothingSent();
    }
    public function test_clients_and_nonadministrators_cannot_access_platforms_despite_extra_permissions(): void
    {
        $user=User::factory()->create(); $user->assignRole('Cliente');
        foreach(['stripe.manage','platforms.manage','kalendia.manage'] as $p) { Permission::findOrCreate($p); $user->givePermissionTo($p); }
        $this->actingAs($user)->get(self::BASE)->assertForbidden();
        $this->get('/admin/plataformas/kalendia/main')->assertForbidden();
        Http::assertNothingSent();
    }
    public function test_records_and_events_cannot_cross_account_or_provider_boundaries(): void
    {
        $row=$this->record(); $this->actingAs($this->admin());
        $this->getJson('/admin/plataformas/stripe/retired/registros/'.$row->id)->assertNotFound();
        $this->getJson('/admin/plataformas/kalendia/main/registros/'.$row->id)->assertNotFound();
        $this->get('/admin/plataformas/kalendia/retired')->assertNotFound(); Http::assertNothingSent();
    }
    public function test_retired_account_jobs_never_reuse_primary_credentials(): void
    {
        $row=$this->record(); $mirror=app(PlatformMirror::class);
        $mirror->queue('stripe','main','charges');
        PlatformEventProcessor::receive('stripe','main','evt_retired','charge.succeeded','charges',$row->remote_id);
        // Simulate preserved work and records from an account which is no longer enabled.
        foreach (['platform_records','platform_events','platform_sync_states'] as $table) {
            DB::table($table)->update(['account'=>'retired']);
        }
        $this->assertSame(1,app(PlatformEventProcessor::class)->process()['review']);
        $this->assertSame('account_disabled',DB::table('platform_events')->value('error_code'));
        $this->assertSame(['records'=>0,'pages'=>0,'errors'=>1],$mirror->sync());
        $this->assertFalse((bool)DB::table('platform_sync_states')->value('requested'));
        $this->assertSame('account_disabled',DB::table('platform_sync_states')->value('error_code'));
        $this->assertSame('retired',$row->fresh()->account);
        $this->assertDatabaseCount('platform_records',1);
        $this->postJson('/api/webhooks/stripe/admin/retired',[])->assertNotFound();
        Http::assertNothingSent();
    }
    public function test_signed_stripe_webhooks_are_fast_deduplicated_and_do_not_create_purchases(): void
    {
        $this->postJson('/api/webhooks/stripe/admin/main',[])->assertStatus(400);
        $this->stripeEvent()->assertStatus(202); $this->stripeEvent()->assertOk()->assertJsonPath('duplicate',true);
        $this->assertDatabaseCount('platform_events',1); $this->assertDatabaseCount('platform_records',0);
        $this->assertDatabaseCount('compras',0); $this->assertDatabaseCount('facturas',0);
        $this->assertStringNotContainsString('billing_details',json_encode(DB::table('platform_events')->first())); Http::assertNothingSent();
    }
    public function test_event_processing_reads_current_state_and_never_sends_updates_back(): void
    {
        $this->stripeEvent()->assertStatus(202);
        Http::fake(['api.stripe.com/*'=>Http::response($this->charge(['refunded'=>true,'amount_refunded'=>10000]))]);
        $this->assertSame(1,app(PlatformEventProcessor::class)->process()['synced']);
        $this->assertTrue(PlatformRecord::first()->data['refunded']);
        $this->assertTrue(Http::recorded()->every(fn($pair)=>$pair[0]->method()==='GET'));
        $this->assertSame(0,app(PlatformEventProcessor::class)->process()['synced']);
    }
    public function test_wrong_resource_or_provider_failure_retries_without_importing_other_records(): void
    {
        $this->stripeEvent()->assertStatus(202);
        Http::fake(['*'=>Http::response($this->charge(['id'=>'ch_other']))]);
        $this->assertSame(1,app(PlatformEventProcessor::class)->process()['retry']);
        $this->assertDatabaseCount('platform_records',0);
        $this->assertSame('resource_mismatch',DB::table('platform_events')->value('error_code'));
    }
    public function test_a_superadmin_can_refund_with_exact_minor_units_and_replay_without_another_refund(): void
    {
        $this->actingAs($this->admin(true)); $row=$this->record(); $input=$this->refundInput();
        Http::fake(function($request) {
            if ($request->method()==='GET') return Http::response($this->charge());
            return Http::response(['id'=>'re_one','object'=>'refund','charge'=>'ch_one','amount'=>2550,'currency'=>'eur','status'=>'succeeded','livemode'=>false]);
        });
        $url=self::BASE.'/registros/'.$row->id.'/operar';
        $this->postJson($url,$input)->assertOk()->assertJsonPath('status','succeeded');
        $this->postJson($url,$input)->assertOk();
        $this->assertSame(1,Http::recorded()->filter(fn($pair)=>$pair[0]->method()==='POST')->count());
        Http::assertSent(fn($r)=>$r->method()==='POST' && $r['amount']===2550 && $r['charge']==='ch_one'
            && $r->header('Idempotency-Key')===['sefar-admin-'.$input['operation_id']]);
        $this->assertDatabaseCount('platform_operations',1); $this->assertDatabaseCount('compras',0); $this->assertDatabaseCount('facturas',0);
        $this->postJson($url,array_replace($input,['amount'=>'30.00']))->assertConflict();
    }
    public function test_refunds_validate_live_balance_and_require_confirmation_before_a_write(): void
    {
        $this->actingAs($this->admin(true)); $row=$this->record();
        $this->postJson(self::BASE.'/registros/'.$row->id.'/operar',$this->refundInput(['confirmed'=>'0']))->assertUnprocessable();
        Http::fake(['*'=>Http::response($this->charge(['amount_refunded'=>9999]))]);
        $this->postJson(self::BASE.'/registros/'.$row->id.'/operar',$this->refundInput())->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->assertSame(0,Http::recorded()->filter(fn($pair)=>$pair[0]->method()==='POST')->count());
    }
    public function test_an_uncertain_refund_reuses_its_request_and_blocks_another_operation(): void
    {
        $super=$this->admin(true); $this->actingAs($super); $row=$this->record(); $input=$this->refundInput();
        $fail=true;
        Http::fake(function($request) use (&$fail) {
            if ($request->method()==='GET') return Http::response($this->charge());
            if ($fail) throw new \Illuminate\Http\Client\ConnectionException('secret should never reach logs');
            return Http::response(['id'=>'re_one','object'=>'refund','charge'=>'ch_one','amount'=>2550,'currency'=>'eur','status'=>'succeeded']);
        });
        $this->postJson(self::BASE.'/registros/'.$row->id.'/operar',$input)->assertUnprocessable();
        $this->assertSame('uncertain',PlatformOperation::first()->status);
        $this->postJson(self::BASE.'/registros/'.$row->id.'/operar',$this->refundInput())->assertUnprocessable();
        $fail=false;
        $this->postJson(self::BASE.'/operaciones/'.PlatformOperation::first()->id.'/reintentar',['confirmed'=>'1'])->assertOk();
        $this->assertSame('succeeded',PlatformOperation::first()->status);
        $this->assertDatabaseCount('platform_operations',1);
    }
    public function test_old_uncertain_refunds_are_never_replayed_after_stripe_key_retention(): void
    {
        $super=$this->admin(true); $row=$this->record(); $uuid=(string)Str::uuid();
        PlatformOperation::create(['record_id'=>$row->id,'actor_id'=>$super->id,'uuid'=>$uuid,'action'=>'refund','status'=>'uncertain',
            'payload'=>['input'=>['amount'=>'25.50']],'created_at'=>now()->subDay()]);
        $this->actingAs($super)->postJson(self::BASE.'/operaciones/'.PlatformOperation::first()->id.'/reintentar',['confirmed'=>'1'])->assertUnprocessable();
        Http::assertNothingSent();
    }
    public function test_pagination_is_bounded_and_imports_only_explicitly_requested_resources(): void
    {
        $this->actingAs($this->admin())->post(self::BASE.'/datos/charges/sincronizar')->assertRedirect();
        Http::fake(['*'=>Http::response(['data'=>[$this->charge()],'has_more'=>false])]);
        $this->assertSame(1,app(PlatformMirror::class)->sync()['records']);
        $this->assertDatabaseCount('platform_records',1); $this->assertFalse((bool)DB::table('platform_sync_states')->value('requested'));
        $this->assertSame(0,app(PlatformMirror::class)->sync()['pages']);
    }
    public function test_kalendia_customer_updates_preserve_identity_and_use_idempotency(): void
    {
        $this->actingAs($this->admin());
        $customer=['id'=>'cli_one','name'=>'Nombre anterior','email'=>'same@example.invalid','phone'=>'+0000','timezone'=>'America/Bogota'];
        $row=$this->record('kalendia','customers',$customer);
        Http::fake(fn($r)=>Http::response(['data'=>$r->method()==='GET' ? ['items'=>[$customer]] : ['customer'=>array_replace($customer,['name'=>'Nombre actualizado'])]]));
        $this->postJson('/admin/plataformas/kalendia/main/registros/'.$row->id.'/operar',[
            'operation_id'=>(string)Str::uuid(),'action'=>'customer_update','name'=>'Nombre actualizado','email'=>'other@example.invalid','confirmed'=>'1'
        ])->assertOk();
        Http::assertSent(fn($r)=>$r->method()==='POST' && $r['email']==='same@example.invalid' && $r['phone']==='+0000' && count($r->header('Idempotency-Key'))===1);
        $this->assertSame('Nombre actualizado',$row->fresh()->name);
    }
    public function test_kalendia_payment_events_refresh_the_mirror_without_creating_a_second_receipt(): void
    {
        $body=json_encode(['event_id'=>'evt_payment','business_id'=>'site_test','event_version'=>1,'event_type'=>'payment.confirmed','resource_id'=>'pay_one']);
        $time=(string)time(); $signature='v1='.hash_hmac('sha256',$time.'.delivery.payment.confirmed.'.$body,'kalendia_secret');
        $this->call('POST','/api/webhooks/kalendia',[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_X_KALENDIA_TIMESTAMP'=>$time,
            'HTTP_X_KALENDIA_DELIVERY'=>'delivery','HTTP_X_KALENDIA_EVENT'=>'payment.confirmed','HTTP_X_KALENDIA_SIGNATURE'=>$signature],$body)->assertStatus(202);
        Http::fake(['*'=>Http::response(['data'=>['items'=>[['id'=>'pay_one','status'=>'paid','amount'=>99,'currency'=>'eur']]]])]);
        $this->assertSame(1,app(PlatformEventProcessor::class)->process()['synced']);
        $this->assertDatabaseCount('compras',0); $this->assertDatabaseCount('facturas',0);
    }
    public function test_monetary_conversion_is_exact_for_euros_pesos_and_three_decimal_currencies(): void
    {
        $this->assertSame(5136,StripeMoney::minor('51.36','eur')); $this->assertSame(5000,StripeMoney::minor('5000','clp'));
        $this->assertSame(1234,StripeMoney::minor('1.234','kwd')); $this->assertSame('5.000 CLP',StripeMoney::display(5000,'clp'));
    }
    public function test_only_a_superadmin_can_cancel_an_eligible_payment_intent_and_retries_are_idempotent(): void
    {
        $this->actingAs($this->admin(true));
        $intent=['id'=>'pi_one','object'=>'payment_intent','amount'=>9900,'currency'=>'eur','status'=>'requires_capture','livemode'=>false];
        $row=$this->record('stripe','payment_intents',$intent);
        Http::fake(fn($r)=>Http::response(array_replace($intent,['status'=>$r->method()==='GET'?'requires_capture':'canceled'])));
        $input=$this->refundInput(['action'=>'cancel_payment','reason'=>'requested_by_customer']);
        $this->postJson(self::BASE.'/registros/'.$row->id.'/operar',$input)->assertOk();
        $this->postJson(self::BASE.'/registros/'.$row->id.'/operar',$input)->assertOk();
        $this->assertSame('canceled',$row->fresh()->status);
        $this->assertSame(1,Http::recorded()->filter(fn($pair)=>$pair[0]->method()==='POST')->count());
    }
    public function test_creating_a_customer_uses_the_same_operation_on_replay_without_creating_an_app_account(): void
    {
        $this->actingAs($this->admin());
        Http::fake(['*'=>Http::response(['id'=>'cus_created','object'=>'customer','name'=>'Contacto','email'=>'contact@example.invalid','livemode'=>false])]);
        $input=['operation_id'=>(string)Str::uuid(),'name'=>'Contacto','email'=>'contact@example.invalid','confirmed'=>'1'];
        $this->postJson(self::BASE.'/clientes',$input)->assertOk();
        $this->postJson(self::BASE.'/clientes',$input)->assertOk();
        $this->assertDatabaseCount('users',1); $this->assertDatabaseCount('platform_operations',1);
        $this->assertSame(1,PlatformRecord::where('remote_id','cus_created')->count()); Http::assertSentCount(1);
    }
    public function test_kalendia_notes_use_text_and_a_stable_idempotency_key_without_creating_an_app_note(): void
    {
        $this->actingAs($this->admin()); $customer=['id'=>'cli_one','name'=>'Contacto','email'=>'contact@example.invalid'];
        $row=$this->record('kalendia','customers',$customer);
        Http::fake(fn($r)=>Http::response(['data'=>$r->method()==='GET' ? ['items'=>[$customer]] : ['note'=>['id'=>'nt_one','text'=>'Seguimiento']]]));
        $input=['operation_id'=>(string)Str::uuid(),'action'=>'note_create','text'=>'Seguimiento','is_pinned'=>'0','confirmed'=>'1'];
        $url='/admin/plataformas/kalendia/main/registros/'.$row->id.'/operar';
        $this->postJson($url,$input)->assertOk(); $this->postJson($url,$input)->assertOk();
        Http::assertSent(fn($r)=>$r->method()==='POST' && str_ends_with(parse_url($r->url(),PHP_URL_PATH),'/customers/cli_one/notes') && $r['text']==='Seguimiento' && $r['is_pinned']===false);
        $this->assertSame(1,Http::recorded()->filter(fn($pair)=>$pair[0]->method()==='POST')->count());
    }
    public function test_admin_booking_uses_available_local_times_and_records_the_meeting_once(): void
    {
        $this->actingAs($this->admin()); $customer=['id'=>'cli_one','name'=>'Contacto','email'=>'contact@example.invalid'];
        $row=$this->record('kalendia','customers',$customer);
        Http::fake(function($r) use ($customer) {
            $path=parse_url($r->url(),PHP_URL_PATH);
            if (str_ends_with($path,'/customers')) return Http::response(['data'=>['items'=>[$customer]]]);
            if (str_ends_with($path,'/services')) return Http::response(['data'=>['items'=>[['id'=>'svc_one','name'=>'Consulta','price'=>0,'duration_minutes'=>60]]]]);
            if (str_ends_with($path,'/availability')) return Http::response(['data'=>['date'=>$r['date'],'timezone'=>'America/Bogota','slots'=>[['time'=>'10:00','staff_id'=>'stf_one']]]]);
            return Http::response(['data'=>['appointment'=>['id'=>'apt_one','client_id'=>'cli_one','service_id'=>'svc_one',
                'starts_at'=>'2026-12-01T10:00:00-05:00','ends_at'=>'2026-12-01T11:00:00-05:00','status'=>'confirmed']]]);
        });
        $base='/admin/plataformas/kalendia/main/registros/'.$row->id;
        $slots=$this->getJson($base.'/horarios?service_id=svc_one&date=2026-12-01&timezone=Europe%2FMadrid')->assertOk()->json('slots');
        $this->assertSame('16:00',$slots[0]['display_time']);
        $input=['operation_id'=>(string)Str::uuid(),'action'=>'create_appointment','service_id'=>'svc_one','date'=>'2026-12-01','timezone'=>'Europe/Madrid','slot'=>$slots[0]['key'],'confirmed'=>'1'];
        $this->postJson($base.'/operar',$input)->assertOk(); $this->postJson($base.'/operar',$input)->assertOk();
        $this->assertDatabaseCount('kalendia_appointments',1); $this->assertDatabaseCount('compras',0); $this->assertDatabaseCount('facturas',0);
        Http::assertSent(fn($r)=>$r->method()==='POST' && $r['time']==='10:00' && $r['timezone']==='America/Bogota');
    }
}
