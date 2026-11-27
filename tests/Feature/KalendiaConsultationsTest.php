<?php
namespace Tests\Feature;

use App\Http\Middleware\AuditRequests;
use App\Models\{Compras, Factura, KalendiaAppointment, KalendiaAppointmentAction, KalendiaConsultationOffer, KalendiaConsultationOrder, User};
use App\Services\{KalendiaAppointmentService, KalendiaConsultationCheckout, KalendiaStripeGateway};
use Carbon\Carbon;
use Illuminate\Support\Facades\{DB, Gate, Http, Mail, Schema};
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\{Permission, Role};
use Tests\TestCase;

class KalendiaConsultationsTest extends TestCase
{
    use \Tests\Concerns\RegistrationSchema;
    private FakeKalendiaStripeGateway $stripe;
    private array $mutations = [];
    private bool $failChange = false;
    private bool $failRead = false;
    private array $remoteAppointments = [];
    private array $remoteServices = [['id'=>'svc_consultation','name'=>'Consulta jurídica','price'=>0,'duration_minutes'=>45]];
    private bool $failServices = false;
    private bool $malformedServices = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default'=>'array','session.driver'=>'array','kalendia.token'=>'fake-token',
            'services.stripe.key'=>'pk_test_fake', 'services.stripe.secret'=>'sk_test_fake', 'kalendia.stripe_webhook_secret'=>'whsec_fake',
            'app.key'=>'base64:'.base64_encode(str_repeat('s',32))]);
        Carbon::setTestNow(Carbon::parse('2026-11-20T15:00:00Z'));
        $this->withoutMiddleware(AuditRequests::class); Mail::fake(); Http::preventStrayRequests();
        $this->createRegistrationSchema();
        Schema::table('facturas', function ($t) { $t->string('idcharge')->nullable(); $t->string('idcus')->nullable(); });
        $this->artisan('migrate', ['--path'=>['database/migrations/2026_11_20_180000_create_kalendia_bookings_table.php',
            'database/migrations/2026_11_20_190000_create_kalendia_consultation_management_tables.php']])->assertExitCode(0);
        $this->artisan('migrate', ['--path'=>'database/migrations/2026_11_23_190000_create_kalendia_booking_requests_table.php'])->assertExitCode(0);
        $this->artisan('migrate', ['--path'=>'database/migrations/2026_11_27_130000_add_kalendia_catalog_to_consultation_offers.php'])->assertExitCode(0);
        Role::findOrCreate('Administrador'); Permission::findOrCreate('cliente'); Role::findByName('Cliente')->givePermissionTo('cliente');
        $this->stripe = new FakeKalendiaStripeGateway;
        $this->app->instance(KalendiaStripeGateway::class, $this->stripe);
        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if (str_ends_with($path,'/services')) {
                if ($this->failServices) return Http::response([], 503);
                return Http::response(['data'=>$this->malformedServices ? ['unexpected'=>true] : ['items'=>$this->remoteServices]]);
            }
            if (str_ends_with($path,'/availability')) return Http::response(['data'=>['date'=>$request['date'],'timezone'=>'America/Bogota','slots'=>[['time'=>'10:00','staff_id'=>'stf_alex']]]]);
            if ($request->method()==='GET' && str_ends_with($path,'/appointments')) return $this->failRead ? Http::response([],503) : Http::response(['data'=>['items'=>$this->remoteAppointments]]);
            $this->mutations[] = ['method'=>$request->method(),'path'=>$path,'body'=>$request->data(),'key'=>$request->header('Idempotency-Key')];
            if ($request->method()==='PATCH') {
                if ($this->failChange) throw new \Illuminate\Http\Client\ConnectionException('Simulated timeout');
                return Http::response(['data'=>['status'=>$request['status'] ?? 'confirmed']]);
            }
            if (str_ends_with($path,'/customers')) return Http::response(['data'=>['id'=>'cli_fake']]);
            if (str_ends_with($path,'/appointments')) return Http::response(['data'=>['id'=>'apt_fake','status'=>'confirmed','virtual_meeting_url'=>'https://meet.example.test/own']]);
            throw new \RuntimeException('Unexpected API request');
        });
    }
    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }
    private function client(): User
    {
        return User::factory()->create(['name'=>'Cliente de consulta', 'email'=>'consultation@example.test','phone'=>'000000000',
            'passport'=>'TEST-NOT-VALID','pay'=>2,'contrato'=>1]);
    }
    private function admin(): User
    {
        $user=User::factory()->create(['email'=>'admin@example.test']); $user->syncRoles(['Administrador']); return $user;
    }
    private function offer(?float $price=125.50): KalendiaConsultationOffer
    {
        return KalendiaConsultationOffer::create(['name'=>'Consulta jurídica estándar','type'=>'Jurídica','price'=>$price,
            'remote_service_id'=>'svc_consultation','active'=>true,'currency'=>'EUR']);
    }
    private function order(User $user): KalendiaConsultationOrder
    {
        return app(KalendiaConsultationCheckout::class)->order($user,$this->offer(),(string)Str::uuid());
    }
    private function pay(KalendiaConsultationOrder $order): void
    {
        app(KalendiaConsultationCheckout::class)->begin($order);
        $id=$order->fresh()->payment_intent_id;
        $this->stripe->intents[$id]['status']='succeeded'; $this->stripe->intents[$id]['amount_received']=12550;
        app(KalendiaConsultationCheckout::class)->verify($order->fresh());
    }
    private function appointment(User $user): KalendiaAppointment
    {
        return app(KalendiaAppointmentService::class)->record(['id'=>'apt_fake','client_id'=>'cli_fake', 'customer_email'=>$user->email,
            'customer_name'=>$user->name,'service_id'=>'svc_consultation','service_name'=>'Consulta jurídica','staff_id'=>'stf_alex','staff_name'=>'Dr. Alex',
            'starts_at'=>'2027-03-19T09:00:00-05:00','ends_at'=>'2027-03-19T09:45:00-05:00','status'=>'confirmed','virtual_meeting_url'=>'https://meet.example.test/own']);
    }

    #[DataProvider('disabledPrices')]
    public function test_a_missing_or_zero_price_disables_the_button_and_rejects_direct_purchase(?float $price): void
    {
        $user=$this->client(); $offer=$this->offer($price);
        $this->actingAs($user)->get('/consultas')->assertOk()->assertSee('Próximamente');
        $this->post(route('clientes.consultations.purchase',$offer),['checkout_key'=>(string)Str::uuid(),'price'=>1])->assertSessionHasErrors('consultation');
        $this->assertDatabaseCount('kalendia_consultation_orders',0); $this->assertSame([], $this->stripe->creates);
    }
    public static function disabledPrices(): array { return [[null],[0.0]]; }

    public function test_prices_are_snapshotted_and_a_double_click_reuses_the_same_order(): void
    {
        $user=$this->client();$offer=$this->offer();$key=(string)Str::uuid();
        $this->actingAs($user)->post(route('clientes.consultations.purchase',$offer),['checkout_key'=>$key,'price'=>1])->assertRedirect();
        $this->post(route('clientes.consultations.purchase',$offer),['checkout_key'=>$key])->assertRedirect();
        $order=KalendiaConsultationOrder::firstOrFail();$offer->update(['price'=>300]);
        $this->postJson(route('clientes.consultations.intent',$order))->assertOk()->assertJsonPath('client_secret','pi_fake_secret_test');
        $this->postJson(route('clientes.consultations.intent',$order))->assertOk();
        $this->assertDatabaseCount('kalendia_consultation_orders',1);$this->assertCount(1,$this->stripe->creates);
        $this->assertSame(12550,$this->stripe->creates[0]['payload']['amount']);
        $this->assertDatabaseCount('compras',0);$this->assertSame(2,(int)$user->fresh()->pay);$this->assertSame(1,(int)$user->fresh()->contrato);
    }
    public function test_a_lost_intent_response_replays_the_same_body_and_key_instead_of_another_payment(): void
    {
        $order=$this->order($this->client());$this->stripe->loseFirst=true;
        try { app(KalendiaConsultationCheckout::class)->begin($order); } catch (\Illuminate\Validation\ValidationException) {}
        app(KalendiaConsultationCheckout::class)->begin($order->fresh());
        $this->assertCount(2,$this->stripe->creates);$this->assertSame($this->stripe->creates[0],$this->stripe->creates[1]);
        $this->assertCount(1,$this->stripe->intents);
    }
    public function test_an_old_unknown_payment_is_not_created_again_after_the_idempotency_window(): void
    {
        $order=$this->order($this->client());$order->update(['status'=>'uncertain','payment_started_at'=>now()->subHours(24)]);
        try { app(KalendiaConsultationCheckout::class)->begin($order->fresh());$this->fail(); }
        catch (\Illuminate\Validation\ValidationException) { $this->assertSame([],$this->stripe->creates); }
    }
    public function test_a_webhook_can_confirm_before_the_creation_response_is_saved(): void
    {
        $order=$this->order($this->client());
        $this->stripe->onCreate=function (array $intent) use ($order) {
            $intent['status']='succeeded';$intent['amount_received']=12550;
            app(KalendiaConsultationCheckout::class)->confirm($order->fresh(),$intent);
        };
        $result=app(KalendiaConsultationCheckout::class)->begin($order);
        $this->assertTrue($result['paid']);$this->assertSame('paid',$order->fresh()->status);
        $this->assertDatabaseCount('compras',1);$this->assertDatabaseCount('facturas',1);
    }
    public function test_background_reconciliation_recovers_a_payment_when_the_browser_is_closed(): void
    {
        $order=$this->order($this->client());app(KalendiaConsultationCheckout::class)->begin($order);
        $this->stripe->intents['pi_fake']['status']='succeeded';$this->stripe->intents['pi_fake']['amount_received']=12550;
        $this->artisan('kalendia:reconcile-payments')->assertExitCode(0);
        $this->artisan('kalendia:reconcile-payments')->assertExitCode(0);
        $this->assertSame('paid',$order->fresh()->status);$this->assertDatabaseCount('compras',1);
    }
    public function test_confirmed_payment_creates_one_receipt_and_invoice_without_restarting_onboarding(): void
    {
        $user=$this->client();$order=$this->order($user);$this->pay($order);
        app(KalendiaConsultationCheckout::class)->verify($order->fresh());
        $this->assertDatabaseCount('compras',1);$this->assertDatabaseCount('facturas',1);
        $this->assertSame(125.5,Compras::first()->monto);$this->assertSame(KalendiaConsultationOrder::SOURCE,Compras::first()->source);
        $this->assertSame(2,(int)$user->fresh()->pay);$this->assertSame(1,(int)$user->fresh()->contrato);
        $this->assertDatabaseCount('registration_meeting_webhooks',0);
        $purchase=$order->fresh()->compra_id;
        $this->actingAs($user)->get(route('clientes.consultations.reserve',$purchase))->assertOk();
        $slots=app(\App\Services\KalendiaRegistrationBookingService::class)->availableSlots(Compras::first(),'2027-03-19');
        $this->post(route('clientes.meeting.book',$purchase),['date'=>'2027-03-19','slot'=>$slots[0]['key']])->assertRedirect(route('clientes.meetings'));
        $this->artisan('kalendia:process-bookings')->assertExitCode(0);
        $this->get('/mis-reuniones')->assertOk()->assertSee('https://meet.example.test/own');
        $this->assertSame('Reunión programada',app(KalendiaAppointmentService::class)->summary($user)['status']);
        $this->assertDatabaseCount('compras',1);
    }
    public function test_another_user_cannot_open_or_confirm_someone_elses_payment_or_see_their_meeting(): void
    {
        $user=$this->client();$order=$this->order($user);$this->appointment($user);
        $other=User::factory()->create(['pay'=>2,'contrato'=>1]);
        $this->actingAs($other)->get(route('clientes.consultations.checkout',$order))->assertNotFound();
        $this->postJson(route('clientes.consultations.intent',$order))->assertNotFound();
        $this->get(route('clientes.consultations.complete',$order))->assertNotFound();
        $this->get('/mis-reuniones')->assertOk()->assertDontSee('https://meet.example.test/own');
        $this->assertSame([],$this->stripe->creates);
    }
    public function test_stripe_webhook_rejects_unsigned_events_and_reuses_a_signed_confirmation(): void
    {
        $order=$this->order($this->client());app(KalendiaConsultationCheckout::class)->begin($order);
        $intent=$this->stripe->intents['pi_fake'];$intent['status']='succeeded';$intent['amount_received']=12550;
        $body=json_encode(['id'=>'evt_test','object'=>'event','livemode'=>false,'type'=>'payment_intent.succeeded','data'=>['object'=>$intent]]);
        $this->postJson('/api/webhooks/kalendia/stripe',json_decode($body,true))->assertStatus(400);
        $signature='t='.time().',v1='.hash_hmac('sha256',time().'.'.$body,'whsec_fake');
        for($i=0;$i<2;$i++) $this->call('POST','/api/webhooks/kalendia/stripe',[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_STRIPE_SIGNATURE'=>$signature],$body)->assertOk();
        $this->assertDatabaseCount('compras',1);$this->assertDatabaseCount('facturas',1);
    }
    public function test_payment_with_a_wrong_amount_is_not_credited(): void
    {
        $order=$this->order($this->client());app(KalendiaConsultationCheckout::class)->begin($order);
        $intent=$this->stripe->intents['pi_fake'];$intent['status']='succeeded';$intent['amount_received']=1;
        try { app(KalendiaConsultationCheckout::class)->confirm($order->fresh(),$intent);$this->fail(); }
        catch(\Illuminate\Validation\ValidationException) { $this->assertDatabaseCount('compras',0); }
    }
    public function test_global_stripe_events_from_other_services_modes_accounts_and_payments_do_not_credit_a_consultation(): void
    {
        $order=$this->order($this->client()); app(KalendiaConsultationCheckout::class)->begin($order);
        $intent=$this->stripe->intents['pi_fake']; $intent['status']='succeeded'; $intent['amount_received']=12550;
        $before=Http::recorded()->count();
        $cases = [
            ['intent'=>array_replace($intent,['metadata'=>['checkout_token'=>'another-service']]),'reason'=>'unrelated_payment'],
            ['intent'=>array_replace($intent,['metadata'=>['kalendia_order'=>['invalid']]]),'reason'=>'unrelated_payment'],
            ['intent'=>array_replace($intent,['object'=>'charge']),'reason'=>'unrelated_payment'],
            ['intent'=>array_replace($intent,['metadata'=>['kalendia_order'=>(string)Str::uuid()]]),'reason'=>'order_not_found'],
            ['intent'=>array_replace($intent,['id'=>'pi_another']),'reason'=>'payment_reference'],
            ['intent'=>array_replace($intent,['metadata'=>array_replace($intent['metadata'],['sefar_flow'=>'banca_online'])]),'reason'=>'other_flow'],
            ['intent'=>$intent,'event'=>['livemode'=>true],'reason'=>'mode'],
            ['intent'=>$intent,'event'=>['account'=>'acct_other'],'reason'=>'connected_account'],
            ['intent'=>$intent,'event'=>['type'=>'charge.succeeded'],'reason'=>'event_type'],
        ];
        foreach ($cases as $case) {
            $body=json_encode(array_replace(['id'=>'evt_foreign','object'=>'event','livemode'=>false,
                'type'=>'payment_intent.succeeded','data'=>['object'=>$case['intent']]],$case['event']??[]));
            $time=time();$signature='t='.$time.',v1='.hash_hmac('sha256',$time.'.'.$body,'whsec_fake');
            $this->call('POST','/api/webhooks/kalendia/stripe',[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_STRIPE_SIGNATURE'=>$signature],$body)
                ->assertOk()->assertJsonPath('ignored',$case['reason']);
        }
        $this->assertDatabaseCount('compras',0); $this->assertDatabaseCount('facturas',0);
        $this->assertSame('pending',$order->fresh()->status); $this->assertSame(2,(int)$order->user->pay);
        $this->assertCount(1,$this->stripe->creates); $this->assertSame($before,Http::recorded()->count());
    }
    public function test_admin_can_configure_several_prices_without_enabling_an_unpriced_offer(): void
    {
        $this->actingAs($this->admin())->get('/admin/kalendia/consultas')->assertOk()->assertSee('Nueva consulta o modalidad');
        foreach(['Estándar'=>null,'Extendida'=>'175.25'] as $name=>$price) $this->post('/admin/kalendia/consultas',[
            'name'=>$name,'type'=>'Jurídica','price'=>$price,'active'=>1,'remote_service_id'=>'svc_consultation'])->assertRedirect();
        $this->assertDatabaseCount('kalendia_consultation_offers',2);
        $this->assertFalse(KalendiaConsultationOffer::where('name','Estándar')->first()->hasPrice());
        $this->assertSame('175.25',KalendiaConsultationOffer::where('name','Extendida')->first()->price);
    }

    public function test_admin_imports_the_priced_kalendia_services_without_creating_included_meetings_as_products(): void
    {
        $this->remoteServices = [
            ['id'=>'svc_genealogy','name'=>'Consulta Genealógica','price'=>'375.00','currency'=>'EUR'],
            ['id'=>'svc_legal','name'=>'Consulta Jurídica','price'=>375,'currency'=>'EUR'],
            ['id'=>'svc_law','name'=>'Consulta de Derecho Genealogista','price'=>475,'currency'=>'EUR'],
            ['id'=>'svc_strategy','name'=>'Análisis Estratégico','price'=>375,'currency'=>'EUR'],
            ['id'=>'svc_initial','name'=>'Reunión inicial','price'=>0,'currency'=>'EUR'],
        ];
        $this->actingAs($this->admin())->get('/admin/kalendia/consultas')->assertOk()
            ->assertSee('Actualizar servicios y precios de Kalendia')->assertSee('Consulta Genealógica');
        $this->post(route('admin.kalendia.offers.sync'))->assertRedirect();
        $this->assertDatabaseCount('kalendia_consultation_offers', 4);
        $this->assertSame('475.00', KalendiaConsultationOffer::where('catalog_service_id','svc_law')->sole()->price);
        $this->assertSame([], $this->stripe->creates);
        $this->assertSame([], $this->mutations);
    }

    public function test_catalog_refresh_updates_prices_preserves_manual_variants_and_respects_hidden_services(): void
    {
        $manual = $this->offer(99);
        $this->remoteServices[0]['price'] = 375;
        $catalog = app(\App\Services\KalendiaConsultationCatalog::class);
        $catalog->sync(true);
        $imported = KalendiaConsultationOffer::whereNotNull('catalog_service_id')->sole();
        $this->actingAs($this->admin())->put(route('admin.kalendia.offers.update',$imported), [
            'type'=>'Genealógica','active'=>0,'price'=>1,'remote_service_id'=>'svc_forged','name'=>'Falso',
        ])->assertRedirect();
        $this->remoteServices[0]['price'] = '425.50';
        $catalog->sync(true);
        $imported->refresh();
        $this->assertSame('425.50',$imported->price);
        $this->assertSame('svc_consultation',$imported->remote_service_id);
        $this->assertSame('Consulta jurídica',$imported->name);
        $this->assertSame('Genealógica',$imported->type);
        $this->assertFalse($imported->active);
        $this->assertSame('99.00',$manual->fresh()->price);
        $this->assertDatabaseCount('kalendia_consultation_offers',2);
    }

    public function test_a_missing_service_is_disabled_but_an_api_failure_does_not_erase_the_last_catalog(): void
    {
        $this->remoteServices[0]['price'] = 375;
        $catalog = app(\App\Services\KalendiaConsultationCatalog::class);
        $catalog->sync(true);
        $imported = KalendiaConsultationOffer::whereNotNull('catalog_service_id')->sole();
        foreach (['failServices','malformedServices'] as $failure) {
            $this->{$failure} = true;
            try { $catalog->sync(true); $this->fail('An incomplete catalog must fail.'); }
            catch (\App\Services\KalendiaApiException) {}
            $this->assertTrue($imported->fresh()->catalog_available);
            $this->assertSame('375.00',$imported->fresh()->price);
            $this->{$failure} = false;
        }
        $this->remoteServices = [];
        $catalog->sync(true);
        $this->assertFalse($imported->fresh()->hasPrice());
        $this->assertDatabaseCount('kalendia_consultation_offers',1);
    }

    public function test_invalid_prices_and_other_currencies_are_not_published_as_euro_consultations(): void
    {
        $this->remoteServices = [
            ['id'=>'svc_missing','name'=>'Sin precio','price'=>null],
            ['id'=>'svc_decimal','name'=>'Decimales incorrectos','price'=>'375.123'],
            ['id'=>'svc_usd','name'=>'Consulta USD','price'=>375,'currency'=>'USD'],
            ['id'=>'svc_negative','name'=>'Precio negativo','price'=>-10],
        ];
        app(\App\Services\KalendiaConsultationCatalog::class)->sync(true);
        $this->assertDatabaseCount('kalendia_consultation_offers',0);
    }

    public function test_a_failure_on_the_second_catalog_page_cannot_replace_the_complete_saved_prices(): void
    {
        $this->remoteServices[0]['price'] = 375;
        app(\App\Services\KalendiaConsultationCatalog::class)->sync(true);
        $api = \Mockery::mock(\App\Services\KalendiaApi::class)->makePartial();
        $api->shouldReceive('request')->once()->with('GET','services',['page'=>1,'per_page'=>100])->andReturn([
            'items'=>[['id'=>'svc_consultation','name'=>'Consulta jurídica','price'=>500]], 'pagination'=>['pages'=>2],
        ]);
        $api->shouldReceive('request')->once()->with('GET','services',['page'=>2,'per_page'=>100])->andThrow(new \App\Services\KalendiaApiException(503));
        try { (new \App\Services\KalendiaConsultationCatalog($api))->sync(true); $this->fail('A partial catalog must not be saved.'); }
        catch (\App\Services\KalendiaApiException) {}
        $offer = KalendiaConsultationOffer::whereNotNull('catalog_service_id')->sole();
        $this->assertSame('375.00',$offer->price);
        $this->assertTrue($offer->catalog_available);
    }

    public function test_imported_consultation_uses_the_latest_price_charges_once_in_the_app_and_omits_payment_method_when_booking(): void
    {
        $this->remoteServices[0]['price'] = 375;
        $user = $this->client();
        $this->actingAs($user)->get('/consultas')->assertOk()->assertSee('Contratar consulta');
        $offer = KalendiaConsultationOffer::whereNotNull('catalog_service_id')->sole();
        $this->remoteServices[0]['price'] = '425.50';
        $key = (string) Str::uuid();
        $this->post(route('clientes.consultations.purchase',$offer),['checkout_key'=>$key,'price'=>1])->assertRedirect();
        $order = KalendiaConsultationOrder::sole();
        $this->assertSame('425.50',$order->amount);
        $this->remoteServices[0]['price'] = 500;
        $this->post(route('clientes.consultations.purchase',$offer),['checkout_key'=>$key])->assertRedirect();
        $this->postJson(route('clientes.consultations.intent',$order))->assertOk();
        $this->postJson(route('clientes.consultations.intent',$order))->assertOk();
        $this->assertCount(1,$this->stripe->creates);
        $this->assertSame(42550,$this->stripe->creates[0]['payload']['amount']);
        $this->stripe->intents['pi_fake']['status']='succeeded';
        $this->stripe->intents['pi_fake']['amount_received']=42550;
        app(KalendiaConsultationCheckout::class)->verify($order->fresh());
        $purchase = $order->fresh()->purchase;
        $this->get(route('clientes.consultations.reserve',$purchase->id))->assertOk();
        $slots = app(\App\Services\KalendiaRegistrationBookingService::class)->availableSlots($purchase,'2027-03-19');
        $this->post(route('clientes.meeting.book',$purchase->id),['date'=>'2027-03-19','slot'=>$slots[0]['key']])->assertRedirect();
        $this->artisan('kalendia:process-bookings')->assertSuccessful();
        $appointment = collect($this->mutations)->first(fn ($mutation) => str_ends_with($mutation['path'],'/appointments'));
        $this->assertNotNull($appointment);
        $this->assertArrayNotHasKey('payment_method',$appointment['body']);
        $this->assertStringContainsString('425.50 EUR',$appointment['body']['notes']);
        $this->assertStringContainsString('Factura App #',$appointment['body']['notes']);
        $this->assertDatabaseCount('compras',1);
        $this->assertDatabaseCount('facturas',1);
        $this->assertDatabaseCount('kalendia_bookings',1);
        $this->assertSame(2,(int)$user->fresh()->pay);
    }
    public function test_client_cannot_enter_admin_kalendia_even_with_extra_permissions(): void
    {
        $user=$this->client();$user->assignRole('Administrador');
        $this->actingAs($user)->get('/admin/kalendia/consultas')->assertForbidden();
        $this->post('/admin/kalendia/sincronizar')->assertForbidden();
        $this->post(route('admin.kalendia.offers.sync'))->assertForbidden();Http::assertNothingSent();
    }
    public function test_sync_and_cancel_update_the_cos_and_client_history_without_refunding_or_reopening_initial_booking(): void
    {
        $user=$this->client();$appointment=$this->appointment($user);$actor=$this->admin();
        $this->actingAs($actor)->get(route('admin.kalendia.appointments',['client'=>$user->id]))->assertOk()->assertSee($user->name);
        $this->get(route('admin.kalendia.appointment',$appointment))->assertRedirect(route('admin.kalendia.appointments',
            ['appointment'=>$appointment->id,'date'=>'2027-03-19']));
        $uuid=(string)Str::uuid();$body=['operation_id'=>$uuid,'action'=>'cancel'];
        $this->post(route('admin.kalendia.change',$appointment),$body)->assertRedirect();
        $this->post(route('admin.kalendia.change',$appointment),$body)->assertRedirect();
        $this->assertCount(1,$this->mutations);$this->assertSame('cancelled',$appointment->fresh()->status);
        $this->assertSame('Sin próximas reuniones',app(KalendiaAppointmentService::class)->summary($user)['status']);
        $this->assertDatabaseCount('compras',0);
    }
    public function test_calendar_reads_all_visible_appointments_without_remote_requests_or_private_customer_data(): void
    {
        $user = $this->client();
        $appointment = $this->appointment($user);
        for ($i = 1; $i <= 34; $i++) {
            $copy = $appointment->replicate();
            $copy->remote_id = 'apt_calendar_'.$i;
            $copy->save();
        }
        $this->actingAs($this->admin())->get(route('admin.kalendia.appointments'))
            ->assertOk()->assertSee('Calendario de reuniones')->assertSee('data-calendar-view-button="timeGridDay"', false);
        $response = $this->getJson(route('admin.kalendia.events', [
            'start'=>'2027-03-01T00:00:00-04:00', 'end'=>'2027-04-01T00:00:00-04:00', 'timeZone'=>'America/Caracas',
        ]))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonCount(35)
            ->assertJsonPath('0.start', '2027-03-19T14:00:00+00:00')
            ->assertJsonPath('0.extendedProps.statusLabel', 'Confirmada')
            ->assertJsonPath('0.url', route('admin.kalendia.appointment', $appointment));
        $this->assertStringNotContainsString($user->email, $response->getContent());
        $this->assertStringNotContainsString('https://meet.example.test/own', $response->getContent());
        Http::assertNothingSent();
    }
    public function test_calendar_filters_by_client_status_and_overlapping_utc_range(): void
    {
        $user = $this->client();
        $appointment = $this->appointment($user);
        $appointment->update(['status'=>'cancelled','starts_at'=>'2027-03-18 23:30:00','ends_at'=>'2027-03-19 00:30:00']);
        $other = $appointment->replicate();
        $actor = $this->admin();
        $other->remote_id = 'apt_other'; $other->user_id = $actor->id; $other->save();
        $this->actingAs($actor);
        $query = ['start'=>'2027-03-19T01:00:00+01:00','end'=>'2027-03-20T01:00:00+01:00',
            'timeZone'=>'Europe/Madrid','client'=>$user->id,'status'=>'cancelled'];
        $this->getJson(route('admin.kalendia.events', $query))->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.id', (string) $appointment->id);
        $this->getJson(route('admin.kalendia.events', array_replace($query, ['status'=>'confirmed'])))->assertOk()->assertExactJson([]);
        $this->getJson(route('admin.kalendia.events', array_replace($query, ['start'=>'2027-03-19T01:30:00+01:00'])))->assertOk()->assertExactJson([]);
        Http::assertNothingSent();
    }
    public function test_calendar_rejects_invalid_ranges_and_is_restricted_to_internal_admins(): void
    {
        $this->actingAs($this->client())->getJson(route('admin.kalendia.events', [
            'start'=>'2027-03-01','end'=>'2027-04-01',
        ]))->assertForbidden();
        $this->actingAs($this->admin());
        foreach ([['start'=>'2027-03-01','end'=>'2027-02-01'], ['start'=>'2027-03-01','end'=>'2028-03-01'],
            ['start'=>'invalid','end'=>'2027-03-01'], ['start'=>'2027-03-01','end'=>'2027-04-01','timeZone'=>'Fake/Zone']] as $query) {
            $this->getJson(route('admin.kalendia.events', $query))->assertUnprocessable();
        }
        Http::assertNothingSent();
    }
    public function test_appointment_modal_loads_an_escaped_fragment_without_navigating_or_calling_kalendia(): void
    {
        $appointment = $this->appointment($this->client());
        $appointment->update(['service_name'=>'<script>alert("unsafe")</script>']);
        $this->actingAs($this->admin());
        $response = $this->getJson(route('admin.kalendia.appointment', $appointment))
            ->assertOk()->assertHeader('Cache-Control','no-store, private');
        $html = $response->json('html');
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<html', $html);
        $this->assertStringContainsString('Copiar enlace', $html);
        $this->assertStringContainsString('Reagendar en Kalendia', $html);
        Http::assertNothingSent();
    }
    public function test_modal_cancellation_returns_the_updated_fragment_and_reuses_the_same_operation(): void
    {
        $appointment = $this->appointment($this->client());
        $this->actingAs($this->admin());
        $body = ['operation_id'=>(string)Str::uuid(),'action'=>'cancel'];
        $response = $this->postJson(route('admin.kalendia.change', $appointment),$body)->assertOk();
        $this->assertStringContainsString('Cancelada',$response->json('html'));
        $this->assertStringNotContainsString('Reagendar en Kalendia',$response->json('html'));
        $this->postJson(route('admin.kalendia.change', $appointment),$body)->assertOk();
        $this->assertCount(1,$this->mutations);
    }
    public function test_uncertain_modal_changes_return_the_original_operation_for_a_safe_retry(): void
    {
        $appointment = $this->appointment($this->client());
        $this->actingAs($this->admin()); $this->failChange = true;
        $body = ['operation_id'=>(string)Str::uuid(),'action'=>'reschedule','date'=>'2027-03-22','time'=>'10:00'];
        $response = $this->postJson(route('admin.kalendia.change', $appointment),$body)->assertUnprocessable()->assertJsonValidationErrors('appointment');
        $this->assertStringContainsString('Comprobar cambio pendiente',$response->json('html'));
        $this->assertStringContainsString($body['operation_id'],$response->json('html'));
        $this->assertStringNotContainsString('data-kalendia-timezone-input',$response->json('html'));
        $this->failChange = false;
        $this->postJson(route('admin.kalendia.change', $appointment),$body)->assertOk();
        $this->assertCount(2,$this->mutations); $this->assertSame($this->mutations[0],$this->mutations[1]);
    }
    public function test_reschedule_uses_availability_and_retries_an_uncertain_patch_with_the_same_key(): void
    {
        $appointment=$this->appointment($this->client());$this->actingAs($this->admin());$this->failChange=true;
        $body=['operation_id'=>(string)Str::uuid(),'action'=>'reschedule','date'=>'2027-03-22','time'=>'10:00'];
        $this->post(route('admin.kalendia.change',$appointment),$body)->assertSessionHasErrors('appointment');
        $this->assertSame('uncertain',KalendiaAppointmentAction::first()->status);
        $this->failChange=false;$this->post(route('admin.kalendia.change',$appointment),$body)->assertRedirect();
        $this->assertCount(2,$this->mutations);$this->assertSame($this->mutations[0],$this->mutations[1]);
        $this->assertSame('2027-03-22 15:00:00',$appointment->fresh()->starts_at->format('Y-m-d H:i:s'));
    }
    public function test_sync_advances_only_on_success_and_cos_reads_make_no_external_requests(): void
    {
        $user=$this->client();$this->remoteAppointments=[['id'=>'apt_sync','client_id'=>'cli_sync','customer_email'=>$user->email,
            'service_id'=>'svc_consultation','starts_at'=>'2027-03-19T09:00:00-05:00','ends_at'=>'2027-03-19T09:45:00-05:00','status'=>'confirmed']];
        $this->artisan('kalendia:sync-appointments')->assertExitCode(0);
        $since=DB::table('kalendia_sync_states')->value('since');$this->assertNotNull($since);
        $this->failRead=true;$this->artisan('kalendia:sync-appointments')->assertExitCode(1);
        $this->assertSame($since,DB::table('kalendia_sync_states')->value('since'));
        $before=Http::recorded()->count();$this->actingAs($user);
        $html=view('kalendia.partials.cos',['user'=>$user])->render();$this->assertStringContainsString('Reunión programada',$html);
        $this->assertSame($before,Http::recorded()->count());
    }
    public function test_stored_utc_times_render_correctly_when_the_application_timezone_is_colombia(): void
    {
        $previous=date_default_timezone_get();
        try {
            date_default_timezone_set('America/Bogota');
            $user=$this->client();$appointment=$this->appointment($user)->fresh();
            $this->assertSame('2027-03-19 14:00:00',$appointment->getRawOriginal('starts_at'));
            $this->assertSame('2027-03-19 09:00',$appointment->starts_at->timezone('America/Bogota')->format('Y-m-d H:i'));
            $this->actingAs($user)->get('/mis-reuniones')->assertOk()->assertSee('19/03/2027 09:00');
        } finally { date_default_timezone_set($previous); }
    }
    public function test_timezone_preference_is_session_scoped_and_used_by_the_history(): void
    {
        $user=$this->client();$this->appointment($user);
        $this->actingAs($user)->postJson(route('kalendia.timezone'),['timezone'=>'Europe/Madrid'])->assertNoContent();
        $this->get('/mis-reuniones')->assertOk()->assertSee('19/03/2027 15:00');
        $this->postJson(route('kalendia.timezone'),['timezone'=>'invalid/timezone'])->assertUnprocessable();
        $this->assertSame('Europe/Madrid',session('kalendia_timezone'));$this->assertDatabaseCount('kalendia_appointments',1);
    }
    public function test_admin_rescheduling_converts_the_viewers_time_to_the_provider_time(): void
    {
        $appointment=$this->appointment($this->client());$this->actingAs($this->admin());
        $this->post(route('admin.kalendia.change',$appointment),['operation_id'=>(string)Str::uuid(),'action'=>'reschedule',
            'date'=>'2027-03-22','time'=>'16:00','timezone'=>'Europe/Madrid'])->assertRedirect();
        $this->assertSame(['date'=>'2027-03-22','time'=>'10:00'],$this->mutations[0]['body']);
    }
    public function test_a_sync_can_recover_an_uncertain_change_without_another_patch(): void
    {
        $user=$this->client();$appointment=$this->appointment($user);$this->actingAs($this->admin());$this->failChange=true;
        $this->post(route('admin.kalendia.change',$appointment),['operation_id'=>(string)Str::uuid(),'action'=>'reschedule',
            'date'=>'2027-03-22','time'=>'10:00'])->assertSessionHasErrors('appointment');
        $this->remoteAppointments=[['id'=>'apt_fake','client_id'=>'cli_fake','customer_email'=>$user->email,'service_id'=>'svc_consultation',
            'starts_at'=>'2027-03-22T10:00:00-05:00','ends_at'=>'2027-03-22T10:45:00-05:00','status'=>'confirmed']];
        $this->artisan('kalendia:sync-appointments')->assertExitCode(0);
        $this->assertSame('succeeded',KalendiaAppointmentAction::first()->status);$this->assertCount(1,$this->mutations);
    }
    public function test_the_checkout_view_keeps_credentials_private_and_does_not_create_a_charge(): void
    {
        $user=$this->client();$order=$this->order($user);
        $this->actingAs($user)->get(route('clientes.consultations.checkout',$order))->assertOk()->assertSee($order->name)
            ->assertDontSee('sk_test_fake')->assertDontSee('fake-token')->assertDontSee('whsec_fake');
        $this->assertSame([],$this->stripe->creates);
    }
    public function test_catalog_opens_an_escaped_payment_fragment_and_reopening_reuses_the_order(): void
    {
        $user = $this->client(); $offer = $this->offer(); $key = (string) Str::uuid();
        $offer->update(['name' => 'Consulta <script>alert(1)</script>']);
        $this->actingAs($user)->get(route('clientes.consultations'))->assertOk()
            ->assertSee('consultation-payment-modal')->assertSee('2. Elegir fecha y hora');
        $response = $this->postJson(route('clientes.consultations.purchase', $offer), ['checkout_key' => $key])
            ->assertOk()->assertJsonPath('paid', false)->assertJsonPath('next_url', null);
        $html = $response->json('html');
        $this->assertStringContainsString('data-consultation-payment', $html);
        $this->assertStringContainsString('Después de confirmar el pago elegirás la fecha y la hora', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        foreach (['<!DOCTYPE', '<script>alert(1)</script>', 'name="date"', 'sk_test_fake', 'fake-token', 'whsec_fake'] as $private) {
            $this->assertStringNotContainsString($private, $html);
        }
        $this->postJson(route('clientes.consultations.purchase', $offer), ['checkout_key' => $key])->assertOk()
            ->assertJsonPath('checkout_url', $response->json('checkout_url'));
        $this->getJson($response->json('checkout_url'))->assertOk()->assertJsonPath('paid', false);
        $this->assertDatabaseCount('kalendia_consultation_orders', 1);
        $this->assertDatabaseCount('compras', 0); $this->assertSame([], $this->stripe->creates);
    }
    public function test_modal_does_not_offer_dates_based_on_a_browser_payment_claim(): void
    {
        $user = $this->client(); $order = $this->order($user);
        $this->actingAs($user)->getJson(route('clientes.consultations.complete', $order).'?paid=true&redirect_status=succeeded')
            ->assertOk()->assertJsonPath('paid', false)->assertJsonPath('next_url', null);
        $this->postJson(route('clientes.consultations.intent', $order))->assertOk();
        $this->getJson(route('clientes.consultations.complete', $order))->assertOk()
            ->assertJsonPath('paid', false)->assertJsonPath('next_url', null);
        $this->getJson(route('clientes.consultations.reserve', 999))->assertNotFound();
        $this->assertDatabaseCount('compras', 0); $this->assertDatabaseCount('facturas', 0);
        $this->assertCount(1, $this->stripe->creates);
    }
    public function test_modal_payment_then_date_and_time_creates_one_receipt_and_one_booking_request(): void
    {
        $user = $this->client(); $offer = $this->offer(); $key = (string) Str::uuid();
        $this->actingAs($user)->postJson(route('clientes.consultations.purchase', $offer), ['checkout_key' => $key])->assertOk();
        $order = KalendiaConsultationOrder::firstOrFail();
        $this->postJson(route('clientes.consultations.intent', $order))->assertOk();
        $this->stripe->intents['pi_fake']['status'] = 'succeeded';
        $this->stripe->intents['pi_fake']['amount_received'] = 12550;
        $complete = $this->getJson(route('clientes.consultations.complete', $order))->assertOk()->assertJsonPath('paid', true);
        $purchase = Compras::firstOrFail(); $invoice = Factura::firstOrFail();
        $agenda = $this->getJson($complete->json('next_url'))->assertOk()->assertJsonPath('stage', 'schedule');
        foreach (['name="date"', 'name="slot"', 'name="timezone"', 'Pago confirmado', route('viewcomprobantecliente', $invoice)] as $text) {
            $this->assertStringContainsString($text, $agenda->json('html'));
        }
        $this->assertStringNotContainsString('data-consultation-payment', $agenda->json('html'));
        $this->get(route('clientes.consultations.complete', $order))->assertRedirect($complete->json('next_url'));
        $this->get($complete->json('next_url'))->assertOk()->assertSee('Tu consulta ya está pagada')
            ->assertDontSee('Esta reunión está incluida');
        $this->postJson(route('clientes.consultations.purchase', $offer), ['checkout_key' => $key])->assertOk()
            ->assertJsonPath('paid', true)->assertJsonPath('html', null)->assertJsonPath('next_url', $complete->json('next_url'));
        $availability = $this->getJson(route('clientes.meeting.availability', [
            'purchase' => $purchase->id, 'date' => '2027-03-19', 'timezone' => 'America/Bogota',
        ]))->assertOk()->assertJsonPath('slots.0.display_time', '10:00');
        $choice = ['date' => '2027-03-19', 'slot' => $availability->json('slots.0.key'), 'timezone' => 'America/Bogota'];
        for ($i = 0; $i < 2; $i++) {
            $this->postJson(route('clientes.meeting.book', $purchase), $choice)->assertStatus(202)
                ->assertJsonPath('next_url', route('clientes.meetings'));
        }
        $this->getJson($complete->json('next_url'))->assertOk()->assertJsonPath('stage', 'complete')
            ->assertJsonPath('message', 'Tu solicitud está registrada. Puedes seguir su estado en Mi agenda.');
        $this->assertDatabaseCount('kalendia_booking_requests', 1);
        $this->assertDatabaseCount('kalendia_bookings', 0);
        $this->assertDatabaseCount('compras', 1); $this->assertDatabaseCount('facturas', 1);
        $this->assertCount(1, $this->stripe->creates); $this->assertSame([], $this->mutations);
    }
    public function test_modal_fragments_and_booking_remain_private_to_the_paying_client(): void
    {
        $order = $this->order($this->client()); $this->pay($order);
        $other = User::factory()->create(['pay' => 2, 'contrato' => 1]);
        $this->actingAs($other)->getJson(route('clientes.consultations.checkout', $order))->assertNotFound();
        $this->getJson(route('clientes.consultations.complete', $order))->assertNotFound();
        $this->getJson(route('clientes.consultations.reserve', $order->fresh()->compra_id))->assertNotFound();
        $this->postJson(route('clientes.meeting.book', $order->fresh()->compra_id), [
            'date' => '2027-03-19', 'slot' => str_repeat('a', 64),
        ])->assertNotFound();
        $this->assertDatabaseCount('kalendia_booking_requests', 0);
        $this->assertDatabaseCount('compras', 1); $this->assertCount(1, $this->stripe->creates);
    }
    public function test_unavailable_agenda_after_payment_keeps_the_receipt_without_requesting_another_charge(): void
    {
        $user = $this->client(); $order = $this->order($user); $this->pay($order);
        $this->failServices = true; cache()->flush();
        $response = $this->actingAs($user)->getJson(route('clientes.consultations.reserve', $order->fresh()->compra_id))
            ->assertOk()->assertJsonPath('stage', 'schedule');
        $this->assertStringContainsString('Tu pago está confirmado. La agenda no está disponible ahora', $response->json('html'));
        $this->assertStringContainsString(route('viewcomprobantecliente', Factura::first()), $response->json('html'));
        $this->assertStringNotContainsString('data-consultation-payment', $response->json('html'));
        $this->assertDatabaseCount('compras', 1); $this->assertDatabaseCount('facturas', 1);
        $this->assertCount(1, $this->stripe->creates);
    }
    public function test_modal_cannot_advance_to_dates_when_stripe_reports_a_different_amount(): void
    {
        $user = $this->client(); $order = $this->order($user);
        $this->actingAs($user)->postJson(route('clientes.consultations.intent', $order))->assertOk();
        $this->stripe->intents['pi_fake']['status'] = 'succeeded';
        $this->stripe->intents['pi_fake']['amount_received'] = 1;
        $this->getJson(route('clientes.consultations.complete', $order))->assertStatus(503)->assertJsonPath('paid', false);
        $this->assertDatabaseCount('compras', 0); $this->assertDatabaseCount('facturas', 0);
        $this->assertCount(1, $this->stripe->creates);
    }
    public function test_legacy_consultation_links_use_the_new_catalog_and_other_products_remain_available(): void
    {
        Schema::table('servicios', function($t) {
            $t->string('tipo')->nullable();$t->string('categoria')->nullable();$t->integer('orden')->default(0);
            $t->boolean('activo')->default(true);$t->boolean('visible_cliente')->default(true);$t->boolean('requiere_agenda')->default(false);
        });
        $consultation=\App\Models\Servicio::create(['nombre'=>'Consulta antigua','id_hubspot'=>'legacy','precio'=>0,'tipo'=>'consulta','activo'=>true,'visible_cliente'=>true]);
        $product=\App\Models\Servicio::create(['nombre'=>'Servicio documental','id_hubspot'=>'documental','precio'=>100,'tipo'=>'servicio','activo'=>true,'visible_cliente'=>true]);
        $this->actingAs($this->client())->get(route('service-store.show',$consultation))->assertRedirect(route('clientes.consultations'));
        $this->post(route('service-store.purchase',$consultation))->assertRedirect(route('clientes.consultations'));
        $this->get(route('service-store.index'))->assertOk()->assertSee('Servicio documental')->assertDontSee('Consulta antigua');
        $this->get(route('service-store.show',$product))->assertOk()->assertSee('Servicio documental');
        $this->assertDatabaseCount('compras',0);$this->assertSame([],$this->stripe->creates);
    }
}

class FakeKalendiaStripeGateway extends KalendiaStripeGateway
{
    public array $creates=[]; public array $intents=[]; public bool $loseFirst=false;
    public ?\Closure $onCreate=null;
    public function create(array $payload,string $key): array
    {
        $this->creates[]=compact('payload','key');
        $this->intents['pi_fake'] ??= $payload+['id'=>'pi_fake','object'=>'payment_intent','client_secret'=>'pi_fake_secret_test','status'=>'requires_payment_method','amount_received'=>0];
        if($this->onCreate) ($this->onCreate)($this->intents['pi_fake']);
        if($this->loseFirst && count($this->creates)===1) throw new \RuntimeException('Simulated network failure');
        return $this->intents['pi_fake'];
    }
    public function retrieve(string $id): array { return $this->intents[$id]; }
}
