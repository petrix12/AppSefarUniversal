<?php

namespace Tests\Feature;

use App\Http\Middleware\AuditRequests;
use App\Models\Compras;
use App\Models\Factura;
use App\Models\KalendiaBooking;
use App\Models\Servicio;
use App\Models\User;
use App\Services\KalendiaApi;
use App\Services\KalendiaRegistrationBookingService;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KalendiaBookingTest extends TestCase
{
    use \Tests\Concerns\RegistrationSchema;
    private const NAME = 'Nacionalidad española para familiares';
    private array $writes = [];
    private bool $failFirstAppointment = false;
    private int $appointmentAttempts = 0;
    private bool $availabilityFailure = false;
    private string $remoteTimezone = 'America/Bogota';
    private bool $rejectFirstAppointment = false;
    private mixed $remotePrice = null;
    private bool $liveSlotShape = false;
    private bool $ambiguousStaff = false;
    private bool $postOmitsMeetingLink = false;
    private bool $meetingLinkReadFails = false;
    private bool $meetingLinkWrongCustomer = false;
    private bool $boundarySlot = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'session.driver' => 'array', 'kalendia.token' => 'fake-test-token',
            'kalendia.prepaid_payment_method' => 'provider-confirmed-test-method',
            'app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
        Carbon::setTestNow(Carbon::parse('2026-11-20T15:30:45Z'));
        $this->withoutMiddleware(AuditRequests::class);
        Mail::fake();
        Http::preventStrayRequests();
        $this->createRegistrationSchema();
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_20_180000_create_kalendia_bookings_table.php'])->assertExitCode(0);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_20_190000_create_kalendia_consultation_management_tables.php'])->assertExitCode(0);
        Permission::findOrCreate('cliente');
        Role::findByName('Cliente')->givePermissionTo('cliente');
        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if (str_ends_with($path, '/services')) return Http::response(['data' => ['items' => [
                ['id' => 'svc_family', 'name' => self::NAME, 'duration_minutes' => 60, 'price' => $this->remotePrice]], 'pagination' => ['pages' => 1]]]);
            if (str_ends_with($path, '/staff')) return Http::response(['data' => ['items' => $this->ambiguousStaff
                ? [['id' => 'stf_lawyer', 'name' => 'Dr. Alex Goiticoa'], ['id' => 'stf_other', 'name' => 'Dr. Alex Goiticoa']]
                : [['id' => 'stf_lawyer', 'name' => 'Dr. Alex Goiticoa']]]]);
            if (str_ends_with($path, '/availability') && $this->availabilityFailure) return Http::response(['message' => 'private-provider-information'], 503);
            if (str_ends_with($path, '/availability') && $this->boundarySlot) return Http::response(['data'=>['date'=>$request['date'],
                'timezone'=>'America/Bogota','slots'=>[['time'=>'19:00','staff_id'=>'stf_lawyer']]]]);
            if (str_ends_with($path, '/availability')) return Http::response(['data' => ['date' => $request['date'],
                'timezone' => $this->remoteTimezone, 'slots' => $this->liveSlotShape
                    ? [['time' => '09:00', 'label' => '9:00 am', 'staffId' => 7, 'staff' => 'Dr. Alex Goiticoa']]
                    : [['time' => '09:00', 'staff_id' => 'stf_lawyer'],
                    ['time' => '10:30', 'staff_id' => 'stf_lawyer'], ['time' => '10:31', 'staff_id' => 'stf_lawyer']]]]);
            if ($request->method() === 'GET' && str_ends_with($path, '/appointments')) {
                if ($this->meetingLinkReadFails) return Http::response([], 503);
                return Http::response(['data' => ['items' => [['id' => 'apt_fake', 'client_id' => $this->meetingLinkWrongCustomer ? 'cli_other' : 'cli_fake',
                    'service_id' => 'svc_family', 'virtual_meeting_url' => 'https://meet.example.test/recovered-link']]]]);
            }
            if ($request->method() === 'POST') {
                $this->writes[] = ['path' => $path, 'body' => $request->data(), 'key' => $request->header('Idempotency-Key')[0] ?? null];
                if (str_ends_with($path, '/customers')) return Http::response(['data' => ['id' => 'cli_fake']]);
                if (str_ends_with($path, '/appointments')) {
                    $this->appointmentAttempts++;
                    if ($this->rejectFirstAppointment && $this->appointmentAttempts === 1) return Http::response(['error' => ['code' => 'slot_unavailable', 'message' => 'El horario ya no está disponible.']], 409);
                    if ($this->failFirstAppointment && $this->appointmentAttempts === 1) throw new ConnectionException('Simulated timeout');
                    return Http::response(['data' => array_filter(['id' => 'apt_fake', 'status' => 'confirmed',
                        'virtual_meeting_url' => $this->postOmitsMeetingLink ? null : 'https://meet.example.test/meeting'])]);
                }
            }
            throw new \RuntimeException('Unexpected API operation in test');
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function customer(string $serviceName = self::NAME): array
    {
        $user = User::factory()->create(['name' => 'Persona de prueba', 'email' => 'persona@example.test', 'phone' => '000000000',
            'passport' => 'TEST-PASSPORT', 'pay' => 2, 'contrato' => 1, 'servicio' => $serviceName]);
        $service = Servicio::create(['nombre' => $serviceName, 'id_hubspot' => $serviceName]);
        $invoice = Factura::create(['id_cliente' => $user->id, 'hash_factura' => 'paid-'.$user->id, 'met' => 'stripe']);
        $purchase = Compras::create(['id_user' => $user->id, 'servicio_id' => $service->id, 'servicio_hs_id' => $serviceName,
            'pagado' => 1, 'monto' => 299.35, 'hash_factura' => $invoice->hash_factura, 'metadata' => ['gateway_reference' => 'payment-real-reference']]);
        return [$user, $purchase, $invoice];
    }

    private function choice(Compras $purchase): array
    {
        $slots = app(KalendiaRegistrationBookingService::class)->availableSlots($purchase, '2026-11-23');
        return ['date' => '2026-11-23', 'slot' => $slots[0]['key']];
    }

    public function test_a_guest_cannot_access_the_agenda(): void
    {
        $this->get('/mi-reunion')->assertRedirect(route('login'));
        $this->getJson('/mi-reunion/disponibilidad?purchase=1&date=2026-11-23')->assertUnauthorized();
        Http::assertNothingSent();
    }

    #[DataProvider('unfinishedOnboarding')]
    public function test_payment_and_form001_must_be_complete(int $pay, string $destination): void
    {
        [$user, $purchase] = $this->customer();
        $user->forceFill(['pay' => $pay, 'contrato' => 0])->save();
        $this->actingAs($user)->get('/mi-reunion')->assertRedirect(route($destination));
        $this->post(route('clientes.meeting.book', $purchase->id), ['date' => '2026-11-23', 'slot' => str_repeat('a', 64)])->assertForbidden();
        $this->get(route('checkContrato'))->assertRedirect(route($destination));
        $this->assertSame(0, (int) $user->fresh()->contrato);
        Http::assertNothingSent();
    }

    public static function unfinishedOnboarding(): array { return [[0, 'clientes.pay'], [1, 'clientes.getinfo'], [3, 'clientes.getinfo']]; }

    public function test_a_paid_client_sees_the_agenda_without_the_api_credential(): void
    {
        [$user] = $this->customer();
        $this->actingAs($user)->get('/mi-reunion')->assertOk()->assertSee('Zona horaria de los horarios mostrados')
            ->assertSee('Pago verificado en la App')->assertDontSee('fake-test-token');
    }

    public function test_another_clients_purchase_is_never_used(): void
    {
        [$user, $purchase] = $this->customer();
        $other = User::factory()->create(['pay' => 2, 'contrato' => 1]);
        $this->actingAs($other)->get('/mi-reunion?purchase='.$purchase->id)->assertNotFound();
        $this->getJson('/mi-reunion/disponibilidad?purchase='.$purchase->id.'&date=2026-11-23')->assertNotFound();
        $this->post(route('clientes.meeting.book', $purchase->id))->assertNotFound();
        Http::assertNothingSent();
    }

    #[DataProvider('invalidDays')]
    public function test_weekends_and_past_days_are_rejected_without_querying_kalendia(string $day): void
    {
        [$user, $purchase] = $this->customer();
        $this->actingAs($user)->getJson('/mi-reunion/disponibilidad?purchase='.$purchase->id.'&date='.$day)->assertUnprocessable();
        Http::assertNothingSent();
    }

    public static function invalidDays(): array { return [['2026-11-19'], ['2026-11-21'], ['2026-11-22']]; }

    public function test_today_only_future_times_are_offered_in_colombia(): void
    {
        [$user, $purchase] = $this->customer();
        $this->actingAs($user)->getJson('/mi-reunion/disponibilidad?purchase='.$purchase->id.'&date=2026-11-20')
            ->assertOk()->assertJsonCount(1, 'slots')->assertJsonPath('slots.0.time', '10:31')->assertJsonPath('timezone', 'America/Bogota');
    }

    #[DataProvider('viewerTimezones')]
    public function test_availability_is_converted_for_the_viewer_with_daylight_saving_rules(string $timezone, string $date, string $expected): void
    {
        [$user,$purchase]=$this->customer();
        $this->actingAs($user)->getJson('/mi-reunion/disponibilidad?'.http_build_query(['purchase'=>$purchase->id,'date'=>$date,'timezone'=>$timezone]))
            ->assertOk()->assertJsonPath('timezone',$timezone)->assertJsonPath('slots.0.time','09:00')->assertJsonPath('slots.0.display_time',$expected);
    }
    public static function viewerTimezones(): array
    {
        return [['Europe/Madrid','2027-03-19','15:00'],['Europe/Madrid','2027-07-19','16:00'],
            ['Atlantic/Canary','2027-03-19','14:00'],['Atlantic/Canary','2027-07-19','15:00'],
            ['America/Santiago','2027-03-19','11:00'],['America/Santiago','2027-07-19','10:00'],
            ['America/Caracas','2027-03-19','10:00'],['America/Bogota','2027-03-19','09:00']];
    }
    public function test_a_viewer_date_crossing_midnight_books_the_correct_business_date(): void
    {
        [$user,$purchase]=$this->customer();$this->boundarySlot=true;
        $slots=app(KalendiaRegistrationBookingService::class)->availableSlots($purchase,'2027-03-19','Europe/Madrid');
        $this->assertCount(1,$slots);$this->assertSame('01:00',$slots[0]['display_time']);$this->assertSame('2027-03-18',$slots[0]['date']);
        $this->actingAs($user)->post(route('clientes.meeting.book',$purchase->id),['date'=>'2027-03-19','timezone'=>'Europe/Madrid','slot'=>$slots[0]['key']])->assertRedirect();
        $this->assertSame('2027-03-18',$this->writes[1]['body']['date']);$this->assertSame('19:00',$this->writes[1]['body']['time']);
        $this->assertSame('America/Bogota',$this->writes[1]['body']['timezone']);$this->assertSame('Europe/Madrid',$this->writes[0]['body']['timezone']);
    }

    public function test_booking_creates_customer_then_appointment_once_and_uses_the_existing_payment(): void
    {
        [$user, $purchase, $invoice] = $this->customer();
        $input = $this->choice($purchase) + ['monto' => 1, 'customer' => ['email' => 'forged@example.test'], 'staff_id' => 'stf_wrong'];
        $this->actingAs($user)->post(route('clientes.meeting.book', $purchase->id), $input)->assertRedirect(route('clientes.meetings'));
        $this->post(route('clientes.meeting.book', $purchase->id), $input)->assertRedirect();
        $this->assertCount(2, $this->writes);
        $this->assertStringEndsWith('/customers', $this->writes[0]['path']);
        $this->assertSame('TEST-PASSPORT', $this->writes[0]['body']['properties']['cf_nopasaporte']);
        $body = $this->writes[1]['body'];
        $this->assertSame('persona@example.test', $body['customer']['email']);
        $this->assertSame('stf_lawyer', $body['staff_id']);
        $this->assertSame('provider-confirmed-test-method', $body['payment_method']);
        $this->assertStringContainsString('299.35 EUR', $body['notes']);
        $this->assertStringContainsString('Factura App #'.$invoice->id, $body['notes']);
        $this->assertDatabaseCount('compras', 1);
        $this->assertDatabaseCount('facturas', 1);
        $this->assertDatabaseCount('kalendia_bookings', 1);
        $this->assertSame('booked', KalendiaBooking::first()->status);
        $this->assertStringNotContainsString('persona@example.test', KalendiaBooking::first()->getRawOriginal('request_payload'));
    }

    public function test_a_timeout_replays_the_exact_request_even_if_the_client_posts_another_date(): void
    {
        [$user, $purchase] = $this->customer();
        $this->failFirstAppointment = true;
        $this->actingAs($user)->post(route('clientes.meeting.book', $purchase->id), $this->choice($purchase))->assertSessionHasErrors('booking');
        $this->assertSame('uncertain', KalendiaBooking::first()->status);
        $this->post(route('clientes.meeting.book', $purchase->id), ['date' => '2026-11-24', 'slot' => str_repeat('b', 64)])->assertRedirect();
        $appointments = array_values(array_filter($this->writes, fn ($write) => str_ends_with($write['path'], '/appointments')));
        $this->assertCount(2, $appointments);
        $this->assertSame($appointments[0]['key'], $appointments[1]['key']);
        $this->assertSame($appointments[0]['body'], $appointments[1]['body']);
        $this->assertCount(3, $this->writes);
        $this->assertSame('apt_fake', KalendiaBooking::first()->remote_id);
    }

    public function test_unavailable_or_forged_slots_cannot_create_a_customer_or_appointment(): void
    {
        [$user, $purchase] = $this->customer();
        $this->actingAs($user)->post(route('clientes.meeting.book', $purchase->id), ['date' => '2026-11-23', 'slot' => str_repeat('a', 64)])
            ->assertSessionHasErrors('slot');
        $this->assertSame([], $this->writes);
        $this->assertDatabaseCount('kalendia_bookings', 0);
    }

    #[DataProvider('nonFreePrices')]
    public function test_payment_method_must_be_confirmed_for_a_nonzero_or_unknown_remote_price(mixed $price): void
    {
        [$user, $purchase] = $this->customer();
        $this->remotePrice = $price;
        config(['kalendia.prepaid_payment_method' => null]);
        $this->actingAs($user)->post(route('clientes.meeting.book', $purchase->id), ['date' => '2026-11-23', 'slot' => str_repeat('a', 64)])
            ->assertSessionHasErrors('booking');
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
        $this->assertSame([], $this->writes);
    }

    public static function nonFreePrices(): array { return [[null], [''], ['not-a-price'], [-1], [375]]; }

    #[DataProvider('freePrices')]
    public function test_included_meetings_omit_remote_payment_and_use_the_staff_id_from_the_real_catalog(mixed $price): void
    {
        [$user, $purchase] = $this->customer();
        $this->remotePrice = $price;
        $this->liveSlotShape = true;
        config(['kalendia.prepaid_payment_method' => null]);
        $this->actingAs($user)->get('/mi-reunion')->assertOk()->assertSee('data-booking-ready="1"', false);
        $this->post(route('clientes.meeting.book', $purchase->id), $this->choice($purchase))->assertRedirect();
        $this->assertCount(2, $this->writes);
        $body = $this->writes[1]['body'];
        $this->assertArrayNotHasKey('payment_method', $body);
        $this->assertSame('stf_lawyer', $body['staff_id']);
        $this->assertStringContainsString('299.35 EUR', $body['notes']);
        $this->assertSame('booked', KalendiaBooking::first()->status);
        $this->assertDatabaseCount('compras', 1);
        $this->assertDatabaseCount('facturas', 1);
    }

    public static function freePrices(): array { return [[0], ['0.00']]; }

    public function test_ambiguous_staff_names_are_not_guessed_from_numeric_identifiers(): void
    {
        [$user, $purchase] = $this->customer();
        $this->liveSlotShape = true;
        $this->ambiguousStaff = true;
        $this->assertSame([], app(KalendiaRegistrationBookingService::class)->availableSlots($purchase, '2026-11-23'));
        $this->assertSame([], $this->writes);
    }

    public function test_creation_without_a_link_reads_the_existing_appointment_and_checks_its_owner(): void
    {
        [$user, $purchase] = $this->customer();
        $this->postOmitsMeetingLink = true;
        $this->actingAs($user)->post(route('clientes.meeting.book', $purchase->id), $this->choice($purchase))->assertRedirect();
        $this->assertSame('https://meet.example.test/recovered-link', KalendiaBooking::first()->meeting_url);
        $this->assertCount(2, $this->writes);
        $this->get('/mi-reunion')->assertRedirect(route('clientes.meetings'));
        $this->get('/mis-reuniones')->assertOk()->assertSee('https://meet.example.test/recovered-link');
    }

    public function test_repeated_booking_submissions_return_to_the_already_registered_meeting(): void
    {
        [$user, $purchase] = $this->customer();
        $url = route('clientes.meeting.book', $purchase->id);
        $this->actingAs($user)->post($url, $this->choice($purchase))->assertRedirect(route('clientes.meetings'));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post($url)->assertRedirect(route('clientes.meetings'));
        }

        $this->post($url)->assertStatus(303)->assertRedirect(route('clientes.meetings'))
            ->assertSessionHas('success', 'Tu reserva ya está registrada. Aquí puedes consultar su estado.');
        $this->assertDatabaseCount('kalendia_bookings', 1);
        $this->assertCount(2, $this->writes);
    }

    public function test_exceeding_the_limit_before_booking_shows_a_warning_in_my_meetings(): void
    {
        [$user, $purchase] = $this->customer();
        $url = route('clientes.meeting.book', $purchase->id);
        $this->actingAs($user);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post($url)->assertSessionHasErrors('date');
        }

        $this->post($url)->assertStatus(303)->assertRedirect(route('clientes.meetings'));
        $this->get(route('clientes.meetings'))->assertOk()
            ->assertSee('Espera unos segundos antes de volver a agendar tu reunión.');
        $this->postJson($url)->assertTooManyRequests()->assertHeader('Retry-After');
        $this->assertDatabaseCount('kalendia_bookings', 0);
        $this->assertSame([], $this->writes);
    }

    #[DataProvider('failedLinkReads')]
    public function test_failed_or_foreign_link_reads_do_not_repeat_or_invalidate_the_confirmed_creation(bool $failure, bool $wrongOwner): void
    {
        [$user, $purchase] = $this->customer();
        $this->postOmitsMeetingLink = true;
        $this->meetingLinkReadFails = $failure;
        $this->meetingLinkWrongCustomer = $wrongOwner;
        $this->actingAs($user)->post(route('clientes.meeting.book', $purchase->id), $this->choice($purchase))->assertRedirect();
        $this->post(route('clientes.meeting.book', $purchase->id))->assertRedirect();
        $booking = KalendiaBooking::first();
        $this->assertSame('booked', $booking->status);
        $this->assertNull($booking->meeting_url);
        $this->assertCount(2, $this->writes);
    }

    public static function failedLinkReads(): array { return [[true, false], [false, true]]; }

    public function test_an_uncertain_included_booking_keeps_its_request_when_the_remote_price_changes(): void
    {
        [$user, $purchase] = $this->customer();
        $this->remotePrice = 0;
        config(['kalendia.prepaid_payment_method' => null]);
        $this->failFirstAppointment = true;
        $this->actingAs($user)->post(route('clientes.meeting.book', $purchase->id), $this->choice($purchase))->assertSessionHasErrors('booking');
        $this->remotePrice = 375;
        \Illuminate\Support\Facades\Cache::flush();
        $this->get('/mi-reunion')->assertOk()->assertSee('Comprobar reserva')->assertViewHas('bookingReady', true);
        $this->post(route('clientes.meeting.book', $purchase->id))->assertRedirect();
        $appointments = array_values(array_filter($this->writes, fn ($write) => str_ends_with($write['path'], '/appointments')));
        $this->assertCount(2, $appointments);
        $this->assertSame($appointments[0], $appointments[1]);
        $this->assertArrayNotHasKey('payment_method', $appointments[1]['body']);
        $this->assertSame('booked', KalendiaBooking::first()->status);
    }

    public function test_foreign_payments_and_public_payment_claims_do_not_grant_a_meeting(): void
    {
        [$user, $purchase, $invoice] = $this->customer();
        $invoice->forceFill(['met' => 'formulario'])->save();
        $this->actingAs($user)->get('/mi-reunion')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_provider_errors_do_not_expose_private_response_bodies(): void
    {
        [$user, $purchase] = $this->customer();
        $this->availabilityFailure = true;
        $this->actingAs($user)->getJson('/mi-reunion/disponibilidad?purchase='.$purchase->id.'&date=2026-11-23')
            ->assertStatus(503)->assertDontSee('private-provider-information')->assertDontSee('fake-test-token');
    }

    public function test_slots_in_an_unexpected_timezone_are_not_offered(): void
    {
        [$user, $purchase] = $this->customer();
        $this->remoteTimezone = 'Europe/Madrid';
        $this->actingAs($user)->getJson('/mi-reunion/disponibilidad?purchase='.$purchase->id.'&date=2026-11-23')->assertStatus(503);
    }

    public function test_missing_services_are_not_mapped_to_a_different_nationality(): void
    {
        [$user, $purchase] = $this->customer();
        $purchase->servicio->update(['nombre' => 'Nacionalidad portuguesa por cónyuge']);
        $this->actingAs($user)->get('/mi-reunion')->assertOk()->assertSee('La agenda de este servicio todavía no está disponible');
        $this->post(route('clientes.meeting.book', $purchase->id), ['date' => '2026-11-23', 'slot' => str_repeat('a', 64)])->assertSessionHasErrors('slot');
        $this->assertSame([], $this->writes);
    }

    public function test_an_active_reservation_attempt_cannot_be_sent_again_concurrently(): void
    {
        [$user, $purchase] = $this->customer();
        $this->failFirstAppointment = true;
        $this->actingAs($user)->post(route('clientes.meeting.book', $purchase->id), $this->choice($purchase))->assertSessionHasErrors('booking');
        KalendiaBooking::first()->forceFill(['status' => 'processing', 'leased_until' => now()->addMinute()])->save();
        $this->post(route('clientes.meeting.book', $purchase->id))->assertSessionHasErrors('booking');
        $this->assertCount(2, $this->writes);
    }

    public function test_a_definitive_slot_rejection_allows_choosing_another_time_without_reusing_the_operation_key(): void
    {
        [$user, $purchase] = $this->customer();
        $this->rejectFirstAppointment = true;
        $this->actingAs($user)->post(route('clientes.meeting.book', $purchase->id), $this->choice($purchase))->assertSessionHasErrors('booking');
        $this->assertSame('rejected', KalendiaBooking::first()->status);
        $this->assertSame(409, KalendiaBooking::first()->last_http_status);
        $choices = app(KalendiaRegistrationBookingService::class)->availableSlots($purchase, '2026-11-24');
        $this->post(route('clientes.meeting.book', $purchase->id), ['date' => '2026-11-24', 'slot' => $choices[0]['key']])->assertRedirect();
        $appointments = array_values(array_filter($this->writes, fn ($write) => str_ends_with($write['path'], '/appointments')));
        $this->assertNotSame($appointments[0]['key'], $appointments[1]['key']);
        $this->assertSame('booked', KalendiaBooking::first()->status);
        $this->assertDatabaseCount('compras', 1);
    }

    #[DataProvider('formCompletionStates')]
    public function test_verified_form001_routes_to_the_contract_before_the_agenda(int $pay, int $contract, string $destination): void
    {
        [$user] = $this->customer();
        $user->forceFill(['pay' => $pay, 'contrato' => $contract, 'hs_id' => 'contact-test'])->save();
        if ($pay === 1) \Illuminate\Support\Facades\Schema::table('users', fn ($table) => $table->integer('cerocerouno')->nullable());
        $this->mock(\App\Services\TeamleaderService::class);
        $this->mock(\App\Services\MondayRegistrationService::class)->shouldReceive('syncPurchasedServices')->andReturn([]);
        $this->mock(\App\Services\HubspotService::class)->shouldReceive('check001')->once()->andReturn(['verified' => true]);
        $this->actingAs($user)->postJson(route('procesargetinfo'), ['data' => [['name' => 'email', 'value' => $user->email]]])
            ->assertOk()->assertJsonPath('redirect_url', route($destination));
        $this->assertSame(2, (int) $user->fresh()->pay);
        $this->assertSame($contract, (int) $user->fresh()->contrato);
        Http::assertNothingSent();
    }

    public static function formCompletionStates(): array
    {
        return [[1, 0, 'cliente.contrato'], [3, 0, 'cliente.contrato'], [1, 1, 'clientes.meeting'], [3, 1, 'clientes.meeting']];
    }

    public function test_a_client_without_a_contract_cannot_see_or_use_the_agenda_even_with_forged_fields(): void
    {
        [$user, $purchase] = $this->customer();
        $user->forceFill(['contrato' => 0])->save();
        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($user)->allows('registration.meeting'));
        $this->actingAs($user)->get('/')->assertRedirect(route('cliente.contrato'));
        $this->get('/mi-reunion')->assertRedirect(route('cliente.contrato'));
        $this->get('/contrato')->assertOk()->assertSee('Al completar el contrato')
            ->assertDontSee('href="'.route('clientes.meeting').'"', false);
        $this->getJson('/mi-reunion/disponibilidad?purchase='.$purchase->id.'&date=2026-11-23')->assertForbidden();
        $this->post(route('clientes.meeting.book', $purchase->id), ['date' => '2026-11-23', 'slot' => str_repeat('a', 64),
            'contrato' => 1, 'pay' => 2])->assertForbidden();
        $this->assertSame(0, (int) $user->fresh()->contrato);
        $this->assertDatabaseCount('kalendia_bookings', 0);
        Http::assertNothingSent();
    }

    #[DataProvider('meetingServices')]
    public function test_contract_confirmation_enables_and_opens_the_agenda_for_the_four_services(string $name): void
    {
        [$user] = $this->customer($name);
        $user->forceFill(['contrato' => 0])->save();
        $this->actingAs($user)->get(route('checkContrato'))->assertRedirect(route('clientes.meeting'));
        $this->assertSame(1, (int) $user->fresh()->contrato);
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($user->fresh())->allows('registration.meeting'));
        Http::assertNothingSent();
    }

    public static function meetingServices(): array
    {
        return array_map(fn ($name) => [$name], ['Nacionalidad portuguesa por cónyuge', 'Nacionalidad portuguesa para familiares',
            'Nacionalidad española por cónyuge', 'Nacionalidad española para familiares']);
    }

    public function test_other_services_keep_their_existing_destination_after_contract_confirmation(): void
    {
        [$user] = $this->customer('Investigación genealógica');
        $user->forceFill(['contrato' => 0])->save();
        $this->actingAs($user)->get(route('checkContrato'))->assertRedirect(route('clientes.tree'));
        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($user->fresh())->allows('registration.meeting'));
        Http::assertNothingSent();
    }

    public function test_the_four_services_open_the_agenda_after_the_contract_instead_of_a_genealogy_tree(): void
    {
        [$user] = $this->customer();
        $user->forceFill(['contrato' => 1])->save();
        $this->actingAs($user)->get('/')->assertRedirect(route('clientes.meeting'));
    }

    public function test_the_initial_agenda_does_not_reappear_after_booking_even_when_the_appointment_is_cancelled(): void
    {
        [$user, $purchase] = $this->customer();
        $this->actingAs($user)->post(route('clientes.meeting.book', $purchase->id), $this->choice($purchase))->assertRedirect(route('clientes.meetings'));
        $this->get('/')->assertRedirect(route('clientes.status'));
        $this->get('/mi-reunion')->assertRedirect(route('clientes.meetings'));
        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($user)->allows('registration.meeting'));
        $this->get('/mis-reuniones')->assertOk()->assertDontSee('Elegir fecha y hora');
        KalendiaBooking::first()->forceFill(['remote_status'=>'cancelled'])->save();
        $this->get('/mi-reunion')->assertRedirect(route('clientes.meetings'));
        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($user)->allows('registration.meeting'));
    }

    public function test_a_second_initial_purchase_does_not_allow_another_free_meeting(): void
    {
        [$user, $purchase] = $this->customer();
        $this->actingAs($user)->post(route('clientes.meeting.book', $purchase->id), $this->choice($purchase))->assertRedirect();
        $second = Compras::create($purchase->only(['id_user','servicio_id','servicio_hs_id','monto','pagado','hash_factura','metadata']));
        $this->post(route('clientes.meeting.book',$second->id), ['date'=>'2026-11-23','slot'=>str_repeat('a',64)])->assertForbidden();
        $this->assertCount(2,$this->writes);
        $this->assertDatabaseCount('kalendia_bookings',1);
    }

    public function test_an_uncertain_initial_booking_blocks_a_parallel_initial_purchase(): void
    {
        [$user, $purchase] = $this->customer(); $this->failFirstAppointment = true;
        $this->actingAs($user)->post(route('clientes.meeting.book', $purchase->id), $this->choice($purchase))->assertSessionHasErrors('booking');
        $second = Compras::create($purchase->only(['id_user','servicio_id','servicio_hs_id','monto','pagado','hash_factura','metadata']));
        $this->post(route('clientes.meeting.book',$second->id), $this->choice($second))->assertSessionHasErrors('booking');
        $this->assertCount(2,$this->writes);$this->assertDatabaseCount('kalendia_bookings',1);
    }

    public function test_the_agenda_degrades_without_a_migration_instead_of_crashing_the_client_home(): void
    {
        [$user] = $this->customer();
        \Illuminate\Support\Facades\Schema::drop('kalendia_bookings');
        $this->actingAs($user)->get('/mi-reunion')->assertOk()->assertSee('La agenda de este servicio todavía no está disponible');
        Http::assertNothingSent();
    }
}
