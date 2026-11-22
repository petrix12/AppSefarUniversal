<?php

namespace Tests\Feature;

use App\Models\{KalendiaAppointment, User};
use App\Services\KalendiaWebhookProcessor;
use Illuminate\Support\Facades\{DB, Http, Mail};
use Tests\TestCase;

class KalendiaWebhooksTest extends TestCase
{
    use \Tests\Concerns\RegistrationSchema;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default'=>'array','session.driver'=>'array','kalendia.token'=>'fake',
            'kalendia.webhook_secret'=>'test-kalendia-secret','kalendia.webhook_business_id'=>'site_test',
            'app.key'=>'base64:'.base64_encode(str_repeat('s',32))]);
        Mail::fake(); Http::preventStrayRequests();
        $this->createRegistrationSchema();
        $this->artisan('migrate', ['--path'=>[
            'database/migrations/2026_11_20_180000_create_kalendia_bookings_table.php',
            'database/migrations/2026_11_20_190000_create_kalendia_consultation_management_tables.php',
            'database/migrations/2026_11_22_120000_create_kalendia_webhook_events_table.php',
            'database/migrations/2026_11_22_180000_create_platform_management_tables.php',
        ]])->assertExitCode(0);
    }

    private function event(array $changes=[]): array
    {
        return array_replace(['event_id'=>'evt_one','event_type'=>'appointment.created','event_version'=>1,
            'occurred_at'=>'2026-11-22T01:00:00Z','business_id'=>'site_test','resource_id'=>'apt_one',
            'data'=>['appointment_id'=>'apt_one','status'=>'pending','customer_email'=>'private@example.invalid']],$changes);
    }
    private function signed(array $event, array $changes=[], ?string $raw=null)
    {
        $body=$raw ?? json_encode($event);
        $headers=array_replace(['CONTENT_TYPE'=>'application/json','HTTP_X_KALENDIA_TIMESTAMP'=>(string)time(),
            'HTTP_X_KALENDIA_DELIVERY'=>'delivery_one','HTTP_X_KALENDIA_EVENT'=>$event['event_type']],$changes);
        $headers['HTTP_X_KALENDIA_SIGNATURE']='v1='.hash_hmac('sha256',
            $headers['HTTP_X_KALENDIA_TIMESTAMP'].'.'.$headers['HTTP_X_KALENDIA_DELIVERY'].'.'.$headers['HTTP_X_KALENDIA_EVENT'].'.'.$body,'test-kalendia-secret');
        return $this->call('POST','/api/webhooks/kalendia',[],[],[],$headers,$body);
    }
    private function providerRecord(User $user, array $changes=[]): array
    {
        return array_replace(['id'=>'apt_one','client_id'=>'cli_one','customer_email'=>$user->email,'customer_name'=>$user->name,
            'service_id'=>'svc_one','starts_at'=>'2026-12-01T10:00:00-05:00','ends_at'=>'2026-12-01T11:00:00-05:00',
            'status'=>'confirmed','virtual_meeting_url'=>'https://meet.example.invalid/test'],$changes);
    }

    public function test_signed_notifications_are_persisted_quickly_once_without_personal_data_or_remote_requests(): void
    {
        $this->signed($this->event())->assertStatus(202);
        $this->signed($this->event(),['HTTP_X_KALENDIA_DELIVERY'=>'delivery_two'])->assertOk()->assertJsonPath('duplicate',true);
        $this->assertDatabaseCount('kalendia_webhook_events',1);
        $row=DB::table('kalendia_webhook_events')->first();
        $this->assertSame('pending',$row->status);
        $this->assertStringNotContainsString('private@example.invalid',json_encode($row));
        $this->assertDatabaseCount('kalendia_appointments',0); Http::assertNothingSent();
    }
    public function test_signatures_use_raw_bytes_and_reject_missing_headers_wrong_secrets_and_expired_deliveries(): void
    {
        $this->postJson('/api/webhooks/kalendia',$this->event())->assertUnauthorized();
        $this->signed($this->event(),['HTTP_X_KALENDIA_TIMESTAMP'=>(string)(time()-301)])->assertUnauthorized();
        $this->signed($this->event(),['HTTP_X_KALENDIA_TIMESTAMP'=>(string)(time()+301)])->assertUnauthorized();
        config(['kalendia.webhook_secret'=>'a-different-secret']);
        $this->signed($this->event())->assertUnauthorized();
        config(['kalendia.webhook_secret'=>'test-kalendia-secret']);
        $this->signed($this->event(),[],json_encode($this->event(),JSON_PRETTY_PRINT))->assertStatus(202);
        $this->assertDatabaseCount('kalendia_webhook_events',1); Http::assertNothingSent();
    }
    public function test_the_receiver_rejects_another_business_invalid_json_conflicting_ids_and_header_payload_mismatches(): void
    {
        $this->signed($this->event(['business_id'=>'site_other']))->assertForbidden();
        $this->signed($this->event(),[],'{broken')->assertStatus(400);
        $this->signed($this->event(),['HTTP_X_KALENDIA_EVENT'=>'appointment.cancelled'])->assertUnprocessable();
        $this->signed($this->event(['resource_id'=>'apt_other']))->assertUnprocessable();
        $this->signed($this->event(['event_version'=>2]))->assertUnprocessable();
        $this->assertDatabaseCount('kalendia_webhook_events',0);
        $this->signed($this->event())->assertStatus(202);
        $this->signed($this->event(['event_type'=>'appointment.cancelled']))->assertStatus(409);
        $this->assertDatabaseCount('kalendia_webhook_events',1);
    }
    public function test_payment_and_customer_events_are_acknowledged_without_creating_invoices_contacts_or_appointments(): void
    {
        foreach(['payment.confirmed'=>'pay_one','payment.refunded'=>'pay_one','appointment.payment_updated'=>'apt_one','customer.created'=>'cli_one'] as $type=>$id) {
            $this->signed($this->event(['event_id'=>'evt_'.$type,'event_type'=>$type,'resource_id'=>$id]))->assertStatus(202);
        }
        $this->assertDatabaseCount('platform_events',4);
        foreach(['kalendia_webhook_events','users','compras','facturas','kalendia_appointments'] as $table) $this->assertDatabaseCount($table,0);
        Http::assertNothingSent();
    }
    public function test_processing_reads_the_exact_appointment_and_uses_its_current_status_instead_of_old_notification_data(): void
    {
        $user=User::factory()->create(); $user->syncRoles(['Cliente']);
        $this->signed($this->event())->assertStatus(202);
        $this->signed($this->event(['event_id'=>'evt_older','event_type'=>'appointment.confirmed','occurred_at'=>'2026-11-21T01:00:00Z']))->assertStatus(202);
        Http::fake(['*'=>Http::response(['data'=>['items'=>[$this->providerRecord($user,['status'=>'cancelled'])]]])]);
        $result=app(KalendiaWebhookProcessor::class)->process();
        $this->assertSame(2,$result['synced']);
        $this->assertDatabaseCount('kalendia_appointments',1);
        $this->assertSame('cancelled',KalendiaAppointment::first()->status);
        $this->assertSame($user->id,KalendiaAppointment::first()->user_id);
        Http::assertSent(fn($r)=>$r->method()==='GET' && $r['id']==='apt_one');
        $this->assertTrue(Http::recorded()->every(fn($pair)=>$pair[0]->method()==='GET'));
        $this->assertDatabaseCount('compras',0); $this->assertDatabaseCount('facturas',0);
        $this->assertSame(0,app(KalendiaWebhookProcessor::class)->process()['synced']);
    }
    public function test_api_failures_and_wrong_resource_filters_retry_without_importing_other_appointments(): void
    {
        $user=User::factory()->create();
        $this->signed($this->event())->assertStatus(202);
        Http::fakeSequence()->push([],503)->push(['data'=>['items'=>[$this->providerRecord($user,['id'=>'apt_other'])]]]);
        $this->assertSame(1,app(KalendiaWebhookProcessor::class)->process()['retry']);
        $this->assertDatabaseCount('kalendia_appointments',0);
        DB::table('kalendia_webhook_events')->update(['next_attempt_at'=>now()->subMinute()]);
        $this->assertSame(1,app(KalendiaWebhookProcessor::class)->process()['retry']);
        $this->assertDatabaseCount('kalendia_appointments',0);
        $this->assertSame('resource_mismatch',DB::table('kalendia_webhook_events')->value('last_error_code'));
    }
    public function test_processing_reclaims_expired_leases_but_never_runs_a_current_attempt_twice(): void
    {
        $user=User::factory()->create();
        $this->signed($this->event())->assertStatus(202);
        DB::table('kalendia_webhook_events')->update(['status'=>'processing','leased_until'=>now()->addMinute()]);
        $this->assertSame(0,app(KalendiaWebhookProcessor::class)->process()['synced']); Http::assertNothingSent();
        DB::table('kalendia_webhook_events')->update(['leased_until'=>now()->subMinute()]);
        Http::fake(['*'=>Http::response(['data'=>['items'=>[$this->providerRecord($user)]]])]);
        $this->artisan('kalendia:process-webhooks')->assertExitCode(0);
        $this->assertSame('synced',DB::table('kalendia_webhook_events')->value('status'));
    }
    public function test_missing_configuration_does_not_accept_an_event_that_cannot_be_authenticated(): void
    {
        config(['kalendia.webhook_secret'=>null]);
        $this->signed($this->event())->assertStatus(503);
        $this->assertDatabaseCount('kalendia_webhook_events',0);
    }
    public function test_an_optional_business_pin_is_not_required_for_a_webhook_with_its_own_valid_signature(): void
    {
        config(['kalendia.webhook_business_id'=>null]);
        $this->signed($this->event())->assertStatus(202);
        $this->assertSame('site_test',DB::table('kalendia_webhook_events')->value('business_id'));
    }
    public function test_kalendia_and_stripe_do_not_accept_each_others_signatures(): void
    {
        config(['kalendia.stripe_webhook_secret'=>'whsec_fake']);
        $body=json_encode($this->event()); $time=(string)time();
        $this->call('POST','/api/webhooks/kalendia',[],[],[],[
            'CONTENT_TYPE'=>'application/json','HTTP_STRIPE_SIGNATURE'=>'t='.$time.',v1='.hash_hmac('sha256',$time.'.'.$body,'whsec_fake'),
        ],$body)->assertUnauthorized();
        $this->call('POST','/api/webhooks/kalendia/stripe',[],[],[],[
            'CONTENT_TYPE'=>'application/json','HTTP_X_KALENDIA_TIMESTAMP'=>$time,
            'HTTP_X_KALENDIA_DELIVERY'=>'delivery_one','HTTP_X_KALENDIA_EVENT'=>'appointment.created',
            'HTTP_X_KALENDIA_SIGNATURE'=>'v1='.hash_hmac('sha256',$time.'.delivery_one.appointment.created.'.$body,'test-kalendia-secret'),
        ],$body)->assertStatus(400);
        $this->assertDatabaseCount('kalendia_webhook_events',0); Http::assertNothingSent();
    }
    public function test_repeated_api_failures_stop_for_review_without_losing_the_event_reference(): void
    {
        $this->signed($this->event())->assertStatus(202);
        DB::table('kalendia_webhook_events')->update(['attempts'=>7]);
        Http::fake(['*'=>Http::response([],503)]);
        $this->assertSame(1,app(KalendiaWebhookProcessor::class)->process()['review']);
        $this->assertSame('review',DB::table('kalendia_webhook_events')->value('status'));
        $this->assertSame('evt_one',DB::table('kalendia_webhook_events')->value('event_id'));
        $this->assertSame(0,app(KalendiaWebhookProcessor::class)->process()['retry']);
        $this->assertDatabaseCount('kalendia_appointments',0);
    }
}
