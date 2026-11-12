<?php

namespace Tests\Feature;

use App\Http\Middleware\AuditRequests;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualMondayRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => [
            'database/migrations/2014_10_12_000000_create_users_table.php',
            'database/migrations/2021_03_08_173429_create_permission_tables.php',
        ]])->assertExitCode(0);
        Schema::create('facturas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('id_cliente');
            $table->string('hash_factura');
            $table->timestamps();
        });
        Schema::create('compras', function (Blueprint $table): void {
            $table->id();
            $table->string('hash_factura');
            $table->string('servicio_hs_id')->nullable();
        });
        Schema::create('agclientes', function (Blueprint $table): void {
            $table->id();
            $table->string('IDCliente');
            $table->integer('IDPersona');
            $table->string('Nombres')->nullable();
            $table->string('Apellidos')->nullable();
            $table->integer('AnhoNac')->nullable();
            $table->integer('MesNac')->nullable();
            $table->integer('DiaNac')->nullable();
        });
        Role::findOrCreate('Cliente', 'web');
        Role::findOrCreate('Administrador', 'web');
        Gate::define('crud.mondayreportes.index', fn (User $user): bool => $user->hasRole('Administrador'));
        $this->withoutMiddleware(AuditRequests::class);
        config(['services.monday.token' => 'test-token', 'app.url' => 'https://app.test']);
        Http::preventStrayRequests();
        Mail::fake();
        $this->actingAs(User::factory()->create()->syncRoles(['Administrador']));
    }

    #[DataProvider('missingPassports')]
    public function test_unknown_passports_and_email_input_are_handled_without_accessing_a_missing_user(string $input): void
    {
        $this->client();
        $this->post(route('registrarMD'), ['passport' => $input])
            ->assertRedirect(route('mondayregistrar'))->assertSessionHas('status', 'error');
        Http::assertNothingSent();
    }

    public static function missingPassports(): array
    {
        return [['missing'], ['lgav25@gmail.com'], ['%'], ["' OR 1=1 --"]];
    }

    #[DataProvider('incompleteStates')]
    public function test_only_completed_clients_can_be_sent_to_monday(?int $pay): void
    {
        $this->client(['pay' => $pay]);
        $this->post(route('registrarMD'), ['passport' => 'P-123'])
            ->assertRedirect(route('mondayregistrar'))->assertSessionHas('status', 'error2');
        Http::assertNothingSent();
    }

    public static function incompleteStates(): array
    {
        return [[null], [0], [1]];
    }

    public function test_invalid_input_is_validated_before_lookup(): void
    {
        foreach ([null, ['P-123'], str_repeat('x', 21)] as $input) {
            $this->postJson(route('registrarMD'), ['passport' => $input])
                ->assertUnprocessable()->assertJsonValidationErrors('passport');
        }
        Http::assertNothingSent();
    }

    public function test_a_client_without_an_invoice_or_tree_can_register_without_an_invented_birth_date(): void
    {
        $this->client();
        $this->mondayAccepts();

        $this->post(route('registrarMD'), ['passport' => ' P-123 '])
            ->assertRedirect(route('mondayregistrar'))->assertSessionHas('status', 'ok');

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $columns = json_decode($request['variables']['columnVals'], true);

            return $request->hasHeader('Authorization', 'test-token')
                && $request['variables']['myItemName'] === 'Apellido Nombre'
                && $columns['texto'] === 'P-123'
                && $columns['texto1'] === 'Servicio local'
                && $columns['texto_largo8'] === ''
                && $columns['texto_largo75'] === ''
                && $columns['enlace']['url'] === 'https://app.test/tree/P-123'
                && ! array_key_exists('fecha75', $columns);
        });
    }

    public function test_registration_uses_the_latest_invoice_and_parent_ids_even_with_sparse_out_of_order_people(): void
    {
        $client = $this->client();
        DB::table('facturas')->insert([
            ['id_cliente' => $client->id, 'hash_factura' => 'old'],
            ['id_cliente' => $client->id, 'hash_factura' => 'latest'],
        ]);
        DB::table('compras')->insert([
            ['hash_factura' => 'old', 'servicio_hs_id' => 'No corresponde'],
            ['hash_factura' => 'latest', 'servicio_hs_id' => 'Servicio A'],
            ['hash_factura' => 'latest', 'servicio_hs_id' => 'Servicio B'],
        ]);
        DB::table('agclientes')->insert([
            ['IDCliente' => 'P-123', 'IDPersona' => 3, 'Nombres' => 'Madre', 'Apellidos' => 'Apellido'],
            ['IDCliente' => 'P-123', 'IDPersona' => 1, 'Nombres' => 'Nombre', 'Apellidos' => 'Apellido'],
        ]);
        $this->mondayAccepts();

        $this->post(route('registrarMD'), ['passport' => 'P-123'])->assertSessionHas('status', 'ok');

        Http::assertSent(function ($request): bool {
            $columns = json_decode($request['variables']['columnVals'], true);

            return $columns['texto1'] === 'Servicio A, Servicio B'
                && $columns['texto_largo8'] === '' && $columns['texto_largo75'] === 'Madre Apellido';
        });
    }

    #[DataProvider('birthDates')]
    public function test_only_complete_valid_birth_dates_are_sent(array $treeDate, ?string $localDate, ?string $expected): void
    {
        $this->client(['date_of_birth' => $localDate]);
        DB::table('agclientes')->insert($treeDate + ['IDCliente' => 'P-123', 'IDPersona' => 1]);
        $this->mondayAccepts();

        $this->post(route('registrarMD'), ['passport' => 'P-123'])->assertSessionHas('status', 'ok');

        Http::assertSent(function ($request) use ($expected): bool {
            $columns = json_decode($request['variables']['columnVals'], true);

            return $expected === null
                ? ! array_key_exists('fecha75', $columns)
                : $columns['fecha75']['date'] === $expected;
        });
    }

    public static function birthDates(): array
    {
        return [
            'complete tree date' => [['AnhoNac' => 1990, 'MesNac' => 3, 'DiaNac' => 4], null, '1990-03-04'],
            'partial tree date' => [['AnhoNac' => 1990], null, null],
            'impossible date' => [['AnhoNac' => 1990, 'MesNac' => 2, 'DiaNac' => 31], null, null],
            'local fallback' => [['AnhoNac' => 1990], '1990-03-04 00:00:00', '1990-03-04'],
        ];
    }

    #[DataProvider('mondayFailures')]
    public function test_monday_failures_never_report_a_successful_registration(int $status, array|string $body): void
    {
        $this->client();
        Http::fake(['api.monday.com/v2' => Http::response($body, $status)]);

        $this->post(route('registrarMD'), ['passport' => 'P-123'])
            ->assertRedirect(route('mondayregistrar'))->assertSessionHas('status', 'error3');
        Http::assertSentCount(1);
    }

    public static function mondayFailures(): array
    {
        return [
            [200, ['errors' => [['message' => 'Invalid column']]]],
            [200, ['data' => ['create_item' => null]]],
            [200, ['data' => ['create_item' => ['id' => '123']], 'errors' => [['message' => 'Partial failure']]]],
            [500, 'Internal error'],
        ];
    }

    public function test_a_connection_failure_is_reported_without_a_500_or_an_automatic_creation_retry(): void
    {
        $this->client();
        Http::fake(['api.monday.com/v2' => Http::failedConnection()]);

        $this->post(route('registrarMD'), ['passport' => 'P-123'])
            ->assertRedirect(route('mondayregistrar'))->assertSessionHas('status', 'error3');
    }

    public function test_missing_credentials_do_not_report_success_or_make_a_request(): void
    {
        $this->client();
        config(['services.monday.token' => null]);

        $this->post(route('registrarMD'), ['passport' => 'P-123'])->assertSessionHas('status', 'error3');
        Http::assertNothingSent();
    }

    private function client(array $attributes = []): User
    {
        return User::factory()->create(array_replace([
            'passport' => 'P-123', 'nombres' => 'Nombre', 'apellidos' => 'Apellido',
            'servicio' => 'Servicio local', 'pay' => 2, 'date_of_birth' => null,
        ], $attributes));
    }

    private function mondayAccepts(): void
    {
        Http::fake(['api.monday.com/v2' => Http::response(['data' => ['create_item' => ['id' => '123']]])]);
    }
}
