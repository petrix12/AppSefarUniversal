<?php

namespace Tests\Feature;

use App\Http\Controllers\ServicioController;
use App\Models\Servicio;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ServicioPersistenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.servicio_persistence_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('database.default', 'servicio_persistence_test');
        config()->set('app.debug', true);

        DB::purge('servicio_persistence_test');

        Schema::create('servicios', function (Blueprint $table) {
            $table->id();
            $table->string('id_hubspot')->unique();
            $table->string('nombre');
            $table->integer('precio');
            $table->string('categoria')->default('general');
            $table->string('tipo')->default('servicio');
            $table->text('descripcion_publica')->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('visible_cliente')->default(false);
            $table->string('moneda', 3)->default('EUR');
            $table->unsignedSmallInteger('duracion_minutos')->nullable();
            $table->boolean('requiere_agenda')->default(false);
            $table->unsignedInteger('orden')->default(0);
            $table->string('hubspot_pipeline_id')->nullable();
            $table->string('hubspot_stage_id')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('monday_sync_enabled')->default(false);
            $table->string('monday_board_id')->nullable();
            $table->string('monday_group_id')->nullable();
            $table->integer('tipov')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('servicios');
        DB::disconnect('servicio_persistence_test');

        parent::tearDown();
    }

    public function test_service_model_persists_the_monday_configuration(): void
    {
        $servicio = Servicio::create($this->validPayload());

        $this->assertTrue($servicio->monday_sync_enabled);
        $this->assertDatabaseHas('servicios', [
            'id_hubspot' => 'SERVICIO-PRUEBA',
            'monday_board_id' => '878831315',
            'monday_group_id' => 'duplicate_of_en_proceso',
        ]);
    }

    public function test_validation_explains_which_field_is_invalid(): void
    {
        $payload = $this->validPayload();
        $payload['monday_board_id'] = 'tablero-no-numerico';
        $request = Request::create('/servicios', 'POST', $payload);

        try {
            app(ServicioController::class)->store($request);
            $this->fail('Se esperaba un error de validación.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertSame(
                'El Board ID de Monday debe contener solamente números.',
                $exception->errors()['monday_board_id'][0]
            );
        }
    }

    public function test_database_failures_include_technical_detail_and_a_reference(): void
    {
        DB::statement(<<<'SQL'
            CREATE TRIGGER fail_service_insert
            BEFORE INSERT ON servicios
            BEGIN
                SELECT RAISE(FAIL, 'forced service failure');
            END
            SQL);

        $request = Request::create('/servicios', 'POST', $this->validPayload(), [], [], [
            'HTTP_REFERER' => '/servicios/create',
        ]);
        $session = app('session')->driver();
        $session->start();
        $request->setLaravelSession($session);

        $response = app(ServicioController::class)->store($request);
        $message = $session->get('errors')->first('service_save');

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('Error de base de datos', $message);
        $this->assertStringContainsString('Detalle técnico:', $message);
        $this->assertMatchesRegularExpression('/Referencia: [A-Z0-9]{8}\./', $message);
    }

    private function validPayload(): array
    {
        return [
            'id_hubspot' => 'SERVICIO-PRUEBA',
            'nombre' => 'Servicio de prueba',
            'precio' => 100,
            'tipov' => 0,
            'categoria' => 'general',
            'tipo' => 'servicio',
            'activo' => 1,
            'visible_cliente' => 0,
            'moneda' => 'EUR',
            'requiere_agenda' => 0,
            'orden' => 0,
            'monday_sync_enabled' => 1,
            'monday_board_id' => '878831315',
            'monday_group_id' => 'duplicate_of_en_proceso',
        ];
    }
}
