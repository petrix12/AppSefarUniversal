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
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-19 12:00:00', 'UTC'));
        config(['cache.default' => 'array', 'registration_meetings.enabled' => true,
            'registration_meetings.url' => 'https://n8n.example.test/webhook/registration']);
        Http::preventStrayRequests();
        Mail::fake();
        $this->withoutMiddleware(AuditRequests::class);
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2014_10_12_000000_create_users_table.php',
            'database/migrations/2021_03_08_173429_create_permission_tables.php',
        ]])->assertExitCode(0);
        Role::findOrCreate('Cliente');
        Permission::findOrCreate('pay.services');
        Permission::findOrCreate('finish.register');
        Schema::table('users', function (Blueprint $t) {
            foreach (['cantidad_alzada', 'antepasados', 'vinculo_antepasados', 'contrato', 'cosready'] as $field) $t->integer($field)->nullable();
            $t->text('estado_de_datos_y_documentos_de_los_antepasados')->nullable();
        });
        Schema::create('servicios', function (Blueprint $t) {
            $t->id(); $t->string('nombre'); $t->string('id_hubspot'); $t->integer('precio')->default(99); $t->integer('tipov')->default(1); $t->timestamps();
        });
        Schema::create('compras', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('id_user'); $t->unsignedBigInteger('servicio_id')->nullable(); $t->string('servicio_hs_id')->nullable();
            $t->string('source')->nullable(); $t->text('descripcion')->nullable(); $t->integer('pagado')->default(0); $t->decimal('monto', 12, 2);
            $t->string('hash_factura')->nullable(); $t->unsignedBigInteger('deal_id')->nullable(); $t->json('metadata')->nullable(); $t->timestamp('paid_at')->nullable();
            $t->decimal('montooriginal', 12, 2)->nullable(); $t->integer('porcentajedescuento')->nullable(); $t->timestamps();
        });
        Schema::create('facturas', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('id_cliente'); $t->string('hash_factura'); $t->string('met'); $t->timestamps();
        });
        Schema::create('agclientes', function (Blueprint $t) {
            $t->id(); $t->integer('IDPersona');
            foreach (['IDCliente', 'Nombres', 'Apellidos', 'NPasaporte', 'PNacimiento', 'PaisNac', 'referido', 'FRegistro', 'FUpdate', 'Usuario'] as $field) $t->string($field)->nullable();
            $t->timestamps();
        });
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_19_120100_create_registration_meeting_webhooks_table.php'])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[DataProvider('meetingServices')]
    public function test_each_selected_service_requires_a_valid_meeting_before_creating_any_account(string $service): void
    {
        Servicio::create(['nombre' => $service, 'id_hubspot' => $service]);
        $this->from('/registerv2?servicio='.urlencode($service))->post('/registerv2', $this->input($service) + ['pay' => '1'])
            ->assertRedirect()->assertSessionHasErrors('meeting_at');
        $this->assertSame(0, User::count());
        $this->assertSame(0, RegistrationMeetingWebhook::count());
        Http::assertNothingSent();
    }

    public static function meetingServices(): array
    {
        return array_map(fn ($name) => [$name], [
            'Nacionalidad portuguesa por cónyuge', 'Nacionalidad portuguesa para familiares',
            'Nacionalidad española por cónyuge', 'Nacionalidad española para familiares',
        ]);
    }

    public function test_public_registration_saves_the_meeting_on_one_unpaid_purchase_without_trusting_pay(): void
    {
        $name = 'Nacionalidad Portuguesa por Conyuge';
        Servicio::create(['nombre' => $name, 'id_hubspot' => $name]);
        $this->mock(HubspotService::class)->shouldReceive('searchContactByEmail')->once()->andReturn(['id' => '123']);
        $this->mock(MondayRegistrationService::class)->shouldNotReceive('syncAfterPayment');
        $this->post('/registerv2', $this->input($name) + ['meeting_at' => '2027-03-05T14:30', 'meeting_timezone' => 'America/Caracas', 'pay' => '1', 'is_superadmin' => '1'])
            ->assertOk();
        $this->assertSame(1, Compras::count());
        $receipt = Compras::firstOrFail();
        $this->assertSame(0, $receipt->pagado);
        $this->assertSame(0, (int) User::first()->pay);
        $this->assertSame('2027-03-05T18:30:00+00:00', data_get($receipt->metadata, 'registration_meeting.requested_at'));
        $this->assertSame(0, RegistrationMeetingWebhook::count());
        Http::assertNothingSent();
    }

    #[DataProvider('confirmedPaymentMethods')]
    public function test_a_confirmed_registration_is_queued_once_and_is_sent_with_the_requested_time(string $method): void
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
            && $request['reunion_fecha_hora'] === '2027-03-05T14:30:00-04:00'
            && $request->hasHeader('Idempotency-Key', $event->event_id));
    }

    public static function confirmedPaymentMethods(): array
    {
        return [['stripe'], ['paypal'], ['cupon']];
    }

    public function test_a_failed_delivery_retries_with_the_same_reference_and_without_a_second_payment(): void
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

    public function test_a_pending_or_manually_claimed_payment_cannot_generate_the_webhook(): void
    {
        [$receipt, $invoice] = $this->paidRegistration();
        $receipt->pagado = 0;
        app(RegistrationMeetingService::class)->queueConfirmed($receipt, $invoice);
        $receipt->pagado = 1;
        $invoice->met = 'formulario';
        app(RegistrationMeetingService::class)->queueConfirmed($receipt, $invoice);
        $this->assertSame(0, RegistrationMeetingWebhook::count());
        Http::assertNothingSent();
    }

    public function test_invalid_timezone_or_past_meeting_is_rejected_and_other_services_need_no_meeting(): void
    {
        $service = new Servicio(['nombre' => 'Nacionalidad Española para Familiares', 'id_hubspot' => 'Nacionalidad Española para Familiares']);
        try {
            app(RegistrationMeetingService::class)->validateMeeting(['meeting_at' => '2020-01-01T12:00', 'meeting_timezone' => 'UTC', 'phone' => '000'], $service);
            $this->fail('A past meeting must fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('meeting_at', $exception->errors());
        }
        try {
            app(RegistrationMeetingService::class)->validateMeeting(['meeting_at' => '2027-03-05T14:30', 'meeting_timezone' => 'Not/A_Zone', 'phone' => '000'], $service);
            $this->fail('An invalid timezone must fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('meeting_timezone', $exception->errors());
        }
        $this->assertNull(app(RegistrationMeetingService::class)->validateMeeting([], new Servicio(['nombre' => 'Italiana', 'id_hubspot' => 'Italiana'])));
    }

    public function test_real_events_cannot_be_sent_to_the_test_destination_and_synthetic_tests_read_no_payments(): void
    {
        config(['registration_meetings.url' => 'https://n8n.example.test/webhook-test/registration',
            'registration_meetings.test_url' => 'https://n8n.example.test/webhook-test/registration']);
        $this->artisan('registration:deliver-meeting-webhooks')->assertExitCode(1);
        Http::assertNothingSent();
        Http::fake(['*' => Http::response([], 200)]);
        $this->artisan('registration:deliver-meeting-webhooks', ['--test' => true])->assertExitCode(0);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['prueba'] === true && $request['cliente_id'] === null
            && $request['correo_electronico'] === 'prueba@example.invalid');
        $this->assertSame(0, Compras::count());
        $this->assertSame(0, RegistrationMeetingWebhook::count());
    }

    private function paidRegistration(): array
    {
        $user = User::factory()->create(['name' => 'Persona Ficticia', 'nombres' => 'Persona', 'apellidos' => 'Ficticia',
            'email' => 'fake@example.test', 'phone' => '000000000', 'passport' => 'TEST-PASSPORT']);
        $name = 'Nacionalidad Portuguesa por Conyuge';
        $service = Servicio::create(['nombre' => $name, 'id_hubspot' => $name]);
        $meeting = app(RegistrationMeetingService::class)->validateMeeting([
            'meeting_at' => '2027-03-05T14:30', 'meeting_timezone' => 'America/Caracas', 'phone' => $user->phone,
        ], $service);
        $receipt = Compras::create(['id_user' => $user->id, 'servicio_hs_id' => $name, 'servicio_id' => $service->id,
            'pagado' => 1, 'monto' => 99, 'hash_factura' => 'confirmed-registration', 'metadata' => ['registration_meeting' => $meeting]]);
        $invoice = Factura::forceCreate(['id_cliente' => $user->id, 'hash_factura' => 'confirmed-registration', 'met' => 'stripe']);
        return [$receipt, $invoice];
    }

    private function input(string $service): array
    {
        return ['nombres' => 'Persona', 'apellidos' => 'Ficticia', 'email' => 'fake@example.test', 'passport' => 'TEST-PASSPORT',
            'phone' => '000000000', 'pais_de_nacimiento' => 'Pais de prueba', 'servicio' => $service, 'rol' => 'cliente'];
    }
}
