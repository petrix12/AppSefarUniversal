<?php

namespace Tests\Feature;

use App\Http\Middleware\AuditRequests;
use App\Models\Compras;
use App\Models\Factura;
use App\Models\RegistrationMeetingWebhook;
use App\Models\Servicio;
use App\Models\User;
use App\Services\HubspotService;
use App\Services\MondayRegistrationService;
use App\Services\Payments\RegistrationPaymentReceiptService;
use App\Services\RegistrationMeetingService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationMeetingWebhookTest extends TestCase
{
    use \Tests\Concerns\RegistrationSchema;
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-19 12:00:00', 'UTC'));
        config(['cache.default' => 'array', 'kalendia.token' => null, 'registration_meetings.notify_n8n' => true, 'registration_meetings.enabled' => true,
            'registration_meetings.url' => 'https://n8n.example.test/webhook/registration']);
        Http::preventStrayRequests();
        Mail::fake();
        $this->withoutMiddleware(AuditRequests::class);
        $this->createRegistrationSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[DataProvider('meetingServices')]
    public function test_registration_has_no_calendar_and_keeps_one_unpaid_purchase(string $name): void
    {
        Servicio::create(['nombre' => $name, 'id_hubspot' => $name]);
        $this->mock(HubspotService::class)->shouldReceive('searchContactByEmail')->once()->andReturn(['id' => '123']);
        $this->mock(MondayRegistrationService::class)->shouldNotReceive('syncAfterPayment');
        $this->get('/registerv2?servicio='.urlencode($name))->assertOk()->assertDontSee('name="meeting_at"', false);
        $this->post('/registerv2', $this->input($name) + ['pay' => '1', 'meeting_at' => '2020-01-01T00:00',
            'registration_contact' => ['service' => 'forged']])->assertOk();
        $this->assertSame(1, Compras::count());
        $this->assertSame(0, (int) User::first()->pay);
        $this->assertSame(0, Compras::first()->pagado);
        $this->assertSame($name, data_get(Compras::first()->metadata, 'registration_contact.service'));
        $this->assertSame(0, RegistrationMeetingWebhook::count());
        Http::assertNothingSent();
    }

    public static function meetingServices(): array
    {
        return array_map(fn ($name) => [$name], ['Nacionalidad portuguesa por cónyuge', 'Nacionalidad portuguesa para familiares',
            'Nacionalidad española por cónyuge', 'Nacionalidad española para familiares']);
    }

    #[DataProvider('confirmedPaymentMethods')]
    public function test_payment_queues_only_contact_and_receipt_information_once(string $method): void
    {
        [$receipt, $invoice] = $this->paidRegistration();
        $invoice->forceFill(['met' => $method])->save();
        $service = app(RegistrationPaymentReceiptService::class);
        $service->record($invoice, [$receipt], 'gateway-reference');
        $service->record($invoice, [$receipt->fresh()], 'gateway-reference');
        $this->assertSame(1, RegistrationMeetingWebhook::count());
        $this->assertSame(1, Compras::count());
        Http::assertNothingSent();
        Http::fake(['*' => Http::response(['accepted' => true], 202)]);
        $this->artisan('registration:deliver-meeting-webhooks')->assertExitCode(0);
        $this->artisan('registration:deliver-meeting-webhooks')->assertExitCode(0);
        Http::assertSentCount(1);
        $event = RegistrationMeetingWebhook::firstOrFail();
        $this->assertSame('sent', $event->status);
        Http::assertSent(fn ($request) => $request['event_id'] === $event->event_id && $request['formulario'] === '000'
            && $request['pasaporte'] === 'TEST-PASSPORT' && $request['correo_electronico'] === 'fake@example.test'
            && $request['factura_id'] === $invoice->id && !isset($request['reunion_fecha_hora'])
            && $request->hasHeader('Idempotency-Key', $event->event_id));
    }

    public static function confirmedPaymentMethods(): array { return [['stripe'], ['paypal'], ['cupon']]; }

    public function test_a_failed_notification_retries_without_a_second_payment(): void
    {
        [$receipt, $invoice] = $this->paidRegistration();
        app(RegistrationPaymentReceiptService::class)->record($invoice, [$receipt]);
        $event = RegistrationMeetingWebhook::firstOrFail();
        Http::fake(['*' => Http::sequence()->push('', 503)->push('', 200)]);
        $this->artisan('registration:deliver-meeting-webhooks')->assertExitCode(0);
        $this->assertSame('pending', $event->fresh()->status);
        $this->artisan('registration:deliver-meeting-webhooks')->assertExitCode(0);
        Http::assertSentCount(1);
        $this->travel(2)->minutes();
        $this->artisan('registration:deliver-meeting-webhooks')->assertExitCode(0);
        Http::assertSentCount(2);
        $this->assertSame($event->event_id, $event->fresh()->event_id);
        $this->assertSame('sent', $event->fresh()->status);
        $this->assertSame(1, Compras::count());
    }

    public function test_public_payment_claims_are_not_queued(): void
    {
        [$receipt, $invoice] = $this->paidRegistration();
        $receipt->pagado = 0;
        app(RegistrationMeetingService::class)->queueConfirmed($receipt, $invoice);
        $receipt->pagado = 1;
        $invoice->met = 'formulario';
        app(RegistrationMeetingService::class)->queueConfirmed($receipt, $invoice);
        $this->assertSame(0, RegistrationMeetingWebhook::count());
    }

    public function test_customer_is_synced_before_booking_and_payment_is_audited_without_creating_a_cita(): void
    {
        config(['kalendia.token' => 'fake-test-token', 'registration_meetings.notify_n8n' => false]);
        [$receipt, $invoice] = $this->paidRegistration();
        app(RegistrationPaymentReceiptService::class)->record($invoice, [$receipt]);
        Http::fake(['*/customers' => Http::response(['data' => ['id' => 'cli_fake']]),
            '*/customers/cli_fake/notes' => Http::response(['data' => ['id' => 'nt_fake']])]);
        $this->artisan('registration:deliver-meeting-webhooks')->assertExitCode(0);
        $this->artisan('registration:deliver-meeting-webhooks')->assertExitCode(0);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/customers')
            && data_get($request->data(), 'properties.cf_nopasaporte') === 'TEST-PASSPORT');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/notes') && str_contains($request['text'], '99.00 EUR'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/appointments') || str_contains($request->url(), '/payments'));
        $this->assertSame('cli_fake', data_get(RegistrationMeetingWebhook::first()->payload, '_kalendia.customer_id'));
        $this->assertSame(1, Compras::count());
    }

    public function test_an_unconfigured_destination_keeps_the_event_without_attempting_or_dropping_it(): void
    {
        config(['kalendia.token' => null, 'registration_meetings.notify_n8n' => false]);
        [$receipt, $invoice] = $this->paidRegistration();
        app(RegistrationPaymentReceiptService::class)->record($invoice, [$receipt]);
        $this->artisan('registration:deliver-meeting-webhooks')->assertExitCode(1);
        $this->assertSame('pending', RegistrationMeetingWebhook::first()->status);
        $this->assertSame(0, RegistrationMeetingWebhook::first()->attempts);
        Http::assertNothingSent();
    }

    public function test_payment_note_retry_keeps_the_confirmed_customer_and_the_same_operation(): void
    {
        config(['kalendia.token' => 'fake-test-token', 'registration_meetings.notify_n8n' => false]);
        [$receipt, $invoice] = $this->paidRegistration();
        app(RegistrationPaymentReceiptService::class)->record($invoice, [$receipt]);
        $notes = [];
        $customers = 0;
        Http::fake(function ($request) use (&$notes, &$customers) {
            if (str_ends_with($request->url(), '/customers')) {
                $customers++;
                return Http::response(['data' => ['id' => 'cli_fake']]);
            }
            $notes[] = ['body' => $request->data(), 'key' => $request->header('Idempotency-Key')];
            if (count($notes) === 1) throw new \Illuminate\Http\Client\ConnectionException('Simulated timeout');
            return Http::response(['data' => ['id' => 'nt_fake']]);
        });
        $this->artisan('registration:deliver-meeting-webhooks')->assertExitCode(0);
        $event = RegistrationMeetingWebhook::firstOrFail();
        $this->assertSame('pending', $event->status);
        $this->assertSame('cli_fake', data_get($event->payload, '_kalendia.customer_id'));
        $this->travel(2)->minutes();
        $this->artisan('registration:deliver-meeting-webhooks')->assertExitCode(0);
        // The HTTP fake records returned responses, excluding the simulated timeout.
        Http::assertSentCount(2);
        $this->assertSame(1, $customers);
        $this->assertCount(2, $notes);
        $this->assertSame($notes[0], $notes[1]);
        $this->assertSame('sent', $event->fresh()->status);
        $this->assertDatabaseCount('compras', 1);
        $this->assertDatabaseCount('facturas', 1);
    }

    public function test_real_events_cannot_use_the_test_webhook_and_tests_send_only_synthetic_data(): void
    {
        config(['registration_meetings.url' => 'https://n8n.example.test/webhook-test/registration',
            'registration_meetings.test_url' => 'https://n8n.example.test/webhook-test/registration']);
        $this->artisan('registration:deliver-meeting-webhooks')->assertExitCode(1);
        Http::assertNothingSent();
        Http::fake(['*' => Http::response([], 200)]);
        $this->artisan('registration:deliver-meeting-webhooks', ['--test' => true])->assertExitCode(0);
        Http::assertSent(fn ($request) => $request['prueba'] === true && $request['cliente_id'] === null);
        $this->assertSame(0, Compras::count());
    }

    private function paidRegistration(): array
    {
        $user = User::factory()->create(['name' => 'Persona Ficticia', 'nombres' => 'Persona', 'apellidos' => 'Ficticia',
            'email' => 'fake@example.test', 'phone' => '000000000', 'passport' => 'TEST-PASSPORT']);
        $name = 'Nacionalidad Portuguesa por Conyuge';
        $service = Servicio::create(['nombre' => $name, 'id_hubspot' => $name]);
        $context = app(RegistrationMeetingService::class)->contextForService($service);
        $receipt = Compras::create(['id_user' => $user->id, 'servicio_hs_id' => $name, 'servicio_id' => $service->id,
            'pagado' => 1, 'monto' => 99, 'hash_factura' => 'confirmed-registration', 'metadata' => ['registration_contact' => $context]]);
        $invoice = Factura::forceCreate(['id_cliente' => $user->id, 'hash_factura' => 'confirmed-registration', 'met' => 'stripe']);
        return [$receipt, $invoice];
    }

    private function input(string $service): array
    {
        return ['nombres' => 'Persona', 'apellidos' => 'Ficticia', 'email' => 'fake@example.test', 'passport' => 'TEST-PASSPORT',
            'phone' => '000000000', 'pais_de_nacimiento' => 'Pais de prueba', 'servicio' => $service, 'rol' => 'cliente'];
    }
}
