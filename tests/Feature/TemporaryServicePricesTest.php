<?php

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\ServicioController;
use App\Http\Middleware\AuditRequests;
use App\Models\{Compras, Servicio, User};
use App\Services\{HubspotService, MondayRegistrationService, RegistrationPurchaseService};
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Http, Mail, Schema};
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\{Permission, Role};
use Tests\TestCase;

class TemporaryServicePricesTest extends TestCase
{
    use \Tests\Concerns\RegistrationSchema;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'session.driver' => 'array', 'app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
        Carbon::setTestNow(Carbon::parse('2026-11-27T12:00:00Z'));
        Mail::fake(); Http::preventStrayRequests(); $this->withoutMiddleware(AuditRequests::class);
        $this->createRegistrationSchema();
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2026_06_16_100000_add_sales_catalog_fields_to_servicios_table.php',
            'database/migrations/2026_08_26_100000_add_monday_destination_to_servicios_table.php',
            'database/migrations/2026_08_26_160000_add_monday_registration_timing_to_servicios_table.php',
            'database/migrations/2026_11_27_150000_add_temporary_prices_to_servicios_table.php',
        ]])->assertExitCode(0);
        Schema::table('servicios', fn (Blueprint $table) => $table->boolean('requires_getinfo')->default(false));
        Schema::table('users', fn (Blueprint $table) => $table->string('tiene_hermanos')->nullable());
        Schema::create('alerts', function (Blueprint $table) { $table->id(); $table->date('start_date'); $table->date('end_date'); });
        Role::findOrCreate('Administrador');
        foreach (['cliente', 'crud.servicios.index', 'crud.servicios.edit'] as $permission) Permission::findOrCreate($permission);
        Role::findByName('Cliente')->givePermissionTo('cliente');
    }

    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }

    private function service(string $code = 'Española LMD', array $extra = []): Servicio
    {
        return Servicio::create(array_replace(['nombre' => $code, 'id_hubspot' => $code, 'precio' => 299, 'tipo' => 'servicio',
            'activo' => true, 'visible_cliente' => true, 'moneda' => 'EUR', 'tipov' => 0], $extra));
    }

    private function promotion(array $extra = []): array
    {
        return array_replace(['precio_temporal' => 49, 'precio_temporal_desde' => '2026-11-27', 'precio_temporal_hasta' => '2026-12-05'], $extra);
    }

    private function admin(): User
    {
        $user = User::factory()->create(); $user->syncRoles(['Administrador']);
        $user->givePermissionTo(['crud.servicios.index', 'crud.servicios.edit']);
        return $user;
    }

    private function registrationInput(string $code, string $suffix = 'one'): array
    {
        return ['nombres' => 'Persona', 'apellidos' => 'Ficticia', 'email' => $suffix.'@example.test',
            'passport' => 'TEST-'.$suffix, 'phone' => '000000000', 'pais_de_nacimiento' => 'Pais de prueba',
            'servicio' => $code, 'rol' => 'cliente', 'pay' => '0', 'antepasados' => '0'];
    }

    #[DataProvider('promotionBoundaries')]
    public function test_price_uses_inclusive_dates_in_caracas_without_modifying_the_base_price(string $instant, int $expected): void
    {
        $service = $this->service('Española LMD', $this->promotion())->fresh();
        Carbon::setTestNow(Carbon::parse($instant));
        $this->assertSame($expected, $service->currentPrice());
        $this->assertSame(299, $service->precio);
        $this->assertSame(299, $service->fresh()->precio);
    }

    public static function promotionBoundaries(): array
    {
        return [['2026-11-27T03:59:59Z', 299], ['2026-11-27T04:00:00Z', 49],
            ['2026-12-06T03:59:59Z', 49], ['2026-12-06T04:00:00Z', 299]];
    }

    public function test_admin_can_schedule_several_services_without_repricing_any_purchase(): void
    {
        $first = $this->service(); $second = $this->service('Italiana'); $other = $this->service('Otro servicio');
        $buyer = User::factory()->create();
        $old = Compras::create(['id_user' => $buyer->id, 'servicio_id' => $first->id, 'monto' => 299, 'pagado' => 0]);
        $paid = Compras::create(['id_user' => $buyer->id, 'servicio_id' => $first->id, 'monto' => 299, 'pagado' => 1, 'hash_factura' => 'old-receipt']);
        $this->actingAs($this->admin())->get(route('crud.servicios.promotions.index'))->assertOk()->assertSee('Último día incluido');
        $this->post(route('crud.servicios.promotions.store'), $this->promotion() + ['servicios' => [$first->id, $second->id]])
            ->assertRedirect(route('crud.servicios.promotions.index'));
        $this->assertSame(49, $first->fresh()->currentPrice()); $this->assertSame(49, $second->fresh()->currentPrice());
        $this->assertSame(299, $other->fresh()->currentPrice()); $this->assertSame(299, $first->fresh()->precio);
        $this->assertSame(299.0, $old->fresh()->monto); $this->assertSame(299.0, $paid->fresh()->monto);
        $this->assertSame('old-receipt', $paid->fresh()->hash_factura); $this->assertSame(1, $paid->fresh()->pagado);
        Http::assertNothingSent();
    }

    public function test_mixed_selection_rolls_back_all_promotions_when_one_service_has_a_lower_regular_price(): void
    {
        $first = $this->service(); $cheaper = $this->service('Otro servicio', ['precio' => 35]);
        $this->actingAs($this->admin())->postJson(route('crud.servicios.promotions.store'),
            $this->promotion() + ['servicios' => [$first->id, $cheaper->id]])->assertUnprocessable()->assertJsonValidationErrors('precio_temporal');
        $this->assertNull($first->fresh()->precio_temporal); $this->assertNull($cheaper->fresh()->precio_temporal);
    }

    #[DataProvider('invalidSelections')]
    public function test_phases_consultations_other_currencies_and_inactive_services_are_not_included(array $attributes): void
    {
        $service = $this->service('Servicio excluido', $attributes);
        $this->actingAs($this->admin())->get(route('crud.servicios.promotions.index'))->assertOk()->assertDontSee('Servicio excluido');
        $this->postJson(route('crud.servicios.promotions.store'), $this->promotion() + ['servicios' => [$service->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('servicios');
        $this->assertNull($service->fresh()->precio_temporal);
    }

    public static function invalidSelections(): array
    {
        return [[['tipo' => 'cos_fase']], [['tipo' => 'consulta']], [['moneda' => 'USD']], [['activo' => false]], [['precio' => 0]]];
    }

    #[DataProvider('invalidPromotionInputs')]
    public function test_invalid_dates_and_prices_cannot_be_scheduled(array $input, string $field): void
    {
        $service = $this->service();
        $this->actingAs($this->admin())->postJson(route('crud.servicios.promotions.store'),
            $this->promotion($input) + ['servicios' => [$service->id]])->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertNull($service->fresh()->precio_temporal);
    }

    public static function invalidPromotionInputs(): array
    {
        return [[['precio_temporal' => -1], 'precio_temporal'], [['precio_temporal' => 49.5], 'precio_temporal'],
            [['precio_temporal_hasta' => '2026-11-26'], 'precio_temporal_hasta'], [['precio_temporal_desde' => null], 'precio_temporal_desde']];
    }

    public function test_clients_and_staff_without_edit_permission_cannot_schedule_prices(): void
    {
        $service = $this->service();
        $client = User::factory()->create(); $client->givePermissionTo(['crud.servicios.index', 'crud.servicios.edit']);
        $this->actingAs($client)->getJson(route('crud.servicios.promotions.index'))->assertForbidden();
        $this->postJson(route('crud.servicios.promotions.store'), $this->promotion() + ['servicios' => [$service->id]])->assertForbidden();
        $reader = User::factory()->create(); $reader->syncRoles(['Administrador']); $reader->givePermissionTo('crud.servicios.index');
        $this->actingAs($reader)->postJson(route('crud.servicios.promotions.store'), $this->promotion() + ['servicios' => [$service->id]])->assertForbidden();
        $this->assertNull($service->fresh()->precio_temporal);
    }

    public function test_normal_edit_can_remove_a_promotion_without_changing_the_regular_price(): void
    {
        $service = $this->service('Española LMD', $this->promotion());
        $payload = $service->only(['id_hubspot', 'nombre', 'precio', 'tipo', 'moneda', 'activo', 'visible_cliente']);
        $this->actingAs($this->admin())->put(route('crud.servicios.update', $service), $payload + [
            'monday_sync_enabled' => 0, 'precio_temporal' => '', 'precio_temporal_desde' => '', 'precio_temporal_hasta' => '',
        ])->assertOk();
        $this->assertNull($service->fresh()->precio_temporal); $this->assertSame(299, $service->fresh()->currentPrice());
    }

    public function test_a_partial_promotion_in_normal_edit_is_rejected(): void
    {
        $service = $this->service();
        $payload = $service->only(['id_hubspot', 'nombre', 'precio', 'tipo', 'moneda', 'activo', 'visible_cliente']);
        $this->actingAs($this->admin())->putJson(route('crud.servicios.update', $service), $payload + ['precio_temporal' => 49])
            ->assertUnprocessable()->assertJsonValidationErrors(['precio_temporal_desde', 'precio_temporal_hasta']);
        $this->assertNull($service->fresh()->precio_temporal);
    }

    public function test_the_service_form_saves_a_temporary_price_and_shows_the_regular_price_for_editing(): void
    {
        $this->actingAs($this->admin())->post(route('crud.servicios.store'), $this->promotion() + [
            'id_hubspot' => 'Italiana', 'nombre' => 'Nacionalidad Italiana', 'precio' => 299, 'tipo' => 'servicio',
            'moneda' => 'EUR', 'activo' => 1, 'visible_cliente' => 1, 'monday_sync_enabled' => 0,
        ])->assertOk();
        $service = Servicio::sole();
        $this->assertSame(49, $service->currentPrice()); $this->assertSame(299, $service->precio);
        $this->get(route('crud.servicios.edit', $service))->assertOk()
            ->assertSee('name="precio_temporal"', false)->assertSee('value="299"', false)->assertSee('value="49"', false);
    }

    public function test_legacy_clients_without_a_purchase_receive_the_current_registration_price_once(): void
    {
        $service = $this->service('Española LMD', $this->promotion());
        $user = User::factory()->create(['servicio' => $service->id_hubspot, 'pay' => 0, 'antepasados' => 0, 'contrato' => 0]);
        $this->actingAs($user)->get(route('clientes.pay'))->assertOk();
        $this->assertSame(49.0, Compras::sole()->monto);
        Carbon::setTestNow(Carbon::parse('2026-12-06T04:00:00Z'));
        $this->get(route('clientes.pay'))->assertOk();
        $this->assertSame(49.0, Compras::sole()->monto); $this->assertDatabaseCount('compras', 1);
    }

    public function test_a_registration_locks_the_promotional_price_even_if_paid_after_the_promotion(): void
    {
        $service = $this->service('Española LMD', $this->promotion());
        $this->mock(HubspotService::class)->shouldReceive('searchContactByEmail')->twice()->andReturn(['id' => '123']);
        $this->mock(MondayRegistrationService::class)->shouldNotReceive('syncAfterPayment');
        $this->get('/registerv2?servicio='.urlencode($service->id_hubspot))->assertOk()
            ->assertSee('Precio temporal hasta el 05/12/2026 inclusive.');
        $this->post('/registerv2', $this->registrationInput($service->id_hubspot) + ['monto' => 1,
            'precio_temporal' => 1, 'metadata' => ['pricing' => ['unit_price' => 1]]])->assertOk();
        $purchase = Compras::firstOrFail();
        $this->assertSame(49.0, $purchase->monto);
        $this->assertSame(299, data_get($purchase->metadata, 'pricing.base_price'));
        $this->assertSame(49, data_get($purchase->metadata, 'pricing.unit_price'));
        $this->assertSame('2026-12-05', data_get($purchase->metadata, 'pricing.promotion.ends_on'));
        Carbon::setTestNow(Carbon::parse('2026-12-06T04:00:00Z'));
        $this->actingAs(User::first())->get(route('clientes.pay'))->assertOk()->assertViewHas('compras',
            fn ($purchases) => $purchases->count() === 1 && $purchases->first()->monto === 49.0);
        $this->post('/registerv2', $this->registrationInput($service->id_hubspot, 'two'))->assertOk();
        $this->assertSame(299.0, Compras::latest('id')->first()->monto);
        $this->assertSame(49.0, $purchase->fresh()->monto);
        $this->assertDatabaseCount('compras', 2); $this->assertDatabaseCount('facturas', 0);
        Http::assertNothingSent();
    }

    public function test_fortify_registration_also_saves_the_price_before_the_first_visit_to_payment(): void
    {
        $service = $this->service('Italiana', $this->promotion());
        $input = $this->registrationInput($service->id_hubspot) + ['name' => 'Persona Ficticia', 'password' => 'Password123!',
            'password_confirmation' => 'Password123!', 'terms' => true, 'referido' => '', 'cantidad_alzada' => 0,
            'vinculo_antepasados' => 0, 'estado_de_datos_y_documentos_de_los_antepasados' => '', 'tiene_hermanos' => 0];
        $user = app(CreateNewUser::class)->create($input);
        $this->assertSame(49.0, Compras::where('id_user', $user->id)->sole()->monto);
        Carbon::setTestNow(Carbon::parse('2026-12-06T04:00:00Z'));
        $this->assertSame(49.0, Compras::where('id_user', $user->id)->sole()->monto);
        $this->assertSame(299, $service->fresh()->currentPrice());
    }

    public function test_catalog_and_price_endpoint_use_the_current_price_while_the_admin_keeps_the_base(): void
    {
        $service = $this->service('Italiana', $this->promotion())->fresh();
        $this->actingAs(User::factory()->create(['pay' => 2, 'contrato' => 1]))->get(route('service-store.show', $service))
            ->assertOk()->assertSee('Precio temporal hasta el 05/12/2026 inclusive.');
        $response = app(ServicioController::class)->getservicio(Request::create('/api/getservicio', 'GET', ['servicio' => $service->id_hubspot]));
        $this->assertSame(49, $response->getData(true)['precio']);
        $this->post(route('service-store.purchase', $service), ['precio' => 1, 'monto' => 1])->assertRedirect(route('clientes.pay'));
        $this->assertSame(49.0, Compras::sole()->monto);
        Carbon::setTestNow(Carbon::parse('2026-12-06T04:00:00Z'));
        $response = app(ServicioController::class)->getservicio(Request::create('/api/getservicio', 'GET', ['servicio' => $service->id_hubspot]));
        $this->assertSame(299, $response->getData(true)['precio']);
        $this->assertSame(299, $service->fresh()->precio);
    }

    public function test_sibling_prices_and_quantities_keep_their_service_rules_with_a_promotion(): void
    {
        $main = $this->service('Italiana');
        $sibling = $this->service('Italiana - Hermano', $this->promotion() + ['precio' => 149]);
        $buyer = User::factory()->create(['tiene_hermanos' => 1]);
        $data = app(RegistrationPurchaseService::class)->data($buyer, $main);
        $this->assertSame($sibling->id, $data['servicio_id']); $this->assertSame(49, $data['monto']);
        $alzada = $this->service('Recurso de Alzada', $this->promotion() + ['precio' => 1200]);
        $buyer->cantidad_alzada = 3; $buyer->tiene_hermanos = 0;
        $data = app(RegistrationPurchaseService::class)->data($buyer, $alzada);
        $this->assertSame(147, $data['monto']); $this->assertSame(3, data_get($data, 'metadata.pricing.quantity'));
    }
}
