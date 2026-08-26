<?php

namespace Tests\Feature;

use App\Models\MondayServiceRegistration;
use App\Models\Servicio;
use App\Models\User;
use App\Services\MondayRegistrationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MondayRegistrationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.monday_registration_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('database.default', 'monday_registration_test');
        config()->set('services.monday.token', 'monday-test-token');
        config()->set('app.url', 'https://app.test');

        DB::purge('monday_registration_test');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('nombres')->nullable();
            $table->string('apellidos')->nullable();
            $table->string('email')->nullable();
            $table->string('passport')->nullable();
            $table->string('hs_id')->nullable();
            $table->string('monday_id')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('nombre_de_familiar_realizando_procesos')->nullable();
            $table->timestamps();
        });

        Schema::create('servicios', function (Blueprint $table) {
            $table->id();
            $table->string('id_hubspot')->unique();
            $table->string('nombre');
            $table->integer('precio')->default(0);
            $table->boolean('monday_sync_enabled')->default(false);
            $table->string('monday_board_id')->nullable();
            $table->string('monday_group_id')->nullable();
            $table->timestamps();
        });

        Schema::create('monday_service_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('servicio_id');
            $table->string('board_id');
            $table->string('group_id');
            $table->string('monday_item_id')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('monday_service_registrations');
        Schema::dropIfExists('servicios');
        Schema::dropIfExists('users');
        DB::disconnect('monday_registration_test');

        parent::tearDown();
    }

    public function test_it_sends_a_non_audit_registration_to_the_configured_destination_once(): void
    {
        Http::fake([
            'api.monday.com/v2' => Http::response([
                'data' => ['create_item' => ['id' => '987654', 'name' => 'Perez Ana']],
            ]),
        ]);

        $user = User::withoutEvents(fn () => User::create([
            'name' => 'Ana Perez',
            'nombres' => 'Ana',
            'apellidos' => 'Perez',
            'email' => 'ana@example.test',
            'passport' => 'P123456',
            'hs_id' => 'hubspot-123',
        ]));
        $servicio = Servicio::create([
            'id_hubspot' => 'Gestion Documental',
            'nombre' => 'Gestión Documental',
            'precio' => 100,
            'monday_sync_enabled' => true,
            'monday_board_id' => '878831315',
            'monday_group_id' => 'duplicate_of_en_proceso',
        ]);

        $service = app(MondayRegistrationService::class);

        $this->assertTrue($service->sync($user, $servicio));
        $this->assertTrue($service->sync($user->fresh(), $servicio));

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $variables = $request->data()['variables'];
            $columns = json_decode($variables['columnValues'], true);

            return $request->url() === 'https://api.monday.com/v2'
                && $request->hasHeader('Authorization', 'monday-test-token')
                && $variables['boardId'] === '878831315'
                && $variables['groupId'] === 'duplicate_of_en_proceso'
                && $variables['itemName'] === 'Perez Ana'
                && $columns['texto'] === 'P123456'
                && $columns['texto1'] === 'Gestion Documental';
        });

        $this->assertDatabaseHas('monday_service_registrations', [
            'user_id' => $user->id,
            'servicio_id' => $servicio->id,
            'board_id' => '878831315',
            'group_id' => 'duplicate_of_en_proceso',
            'monday_item_id' => '987654',
            'status' => 'synced',
            'attempts' => 1,
        ]);
        $this->assertSame('987654', $user->fresh()->monday_id);
    }

    public function test_it_never_uses_the_generic_flow_for_auditoria_de_procedimientos(): void
    {
        Http::fake();

        $user = User::withoutEvents(fn () => User::create([
            'name' => 'Cliente Auditoria',
            'passport' => 'AUD12345',
        ]));
        $servicio = Servicio::create([
            'id_hubspot' => 'Auditoria de Procedimientos',
            'nombre' => 'Auditoría de Procedimientos',
            'precio' => 100,
            'monday_sync_enabled' => true,
            'monday_board_id' => '878831315',
            'monday_group_id' => 'duplicate_of_en_proceso',
        ]);

        $this->assertFalse(app(MondayRegistrationService::class)->sync($user, $servicio));

        Http::assertNothingSent();
        $this->assertSame(0, MondayServiceRegistration::count());
    }
}
