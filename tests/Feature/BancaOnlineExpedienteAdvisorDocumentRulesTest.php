<?php

namespace Tests\Feature;

use App\Models\BancaOnlineDocumentRule;
use App\Models\DocumentRequest;
use App\Models\Servicio;
use App\Models\User;
use App\Services\BancaOnlineCatalog;
use App\Services\BancaOnlineCosContext;
use App\Services\BancaOnlineExpedienteAdvisor;
use App\Services\BancaOnlineFlow;
use App\Services\ClientStageResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BancaOnlineExpedienteAdvisorDocumentRulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.banca_online_advisor_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('database.default', 'banca_online_advisor_test');

        DB::purge('banca_online_advisor_test');

        Schema::connection('banca_online_advisor_test')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->integer('pay')->default(0);
            $table->integer('contrato')->default(0);
            $table->boolean('cosready')->default(false);
            $table->json('arraycos')->nullable();
            $table->timestamp('arraycos_expire')->nullable();
            $table->timestamps();
        });

        Schema::connection('banca_online_advisor_test')->create('servicios', function (Blueprint $table) {
            $table->id();
            $table->string('id_hubspot')->nullable()->unique();
            $table->string('nombre');
            $table->integer('precio')->default(0);
            $table->string('categoria')->default('general');
            $table->string('tipo')->default('servicio');
            $table->text('descripcion_publica')->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('visible_cliente')->default(false);
            $table->string('moneda', 3)->default('EUR');
            $table->unsignedInteger('orden')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::connection('banca_online_advisor_test')->create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('requested_by');
            $table->string('document_name');
            $table->enum('document_type', ['juridico', 'genealogico']);
            $table->enum('status', ['en_espera_cliente', 'resuelto', 'no_documento', 'aprobada', 'rechazada'])
                ->default('en_espera_cliente');
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamp('no_document_button_at')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();
        });

        Schema::connection('banca_online_advisor_test')->create('banca_online_document_rules', function (Blueprint $table) {
            $table->id();
            $table->string('country_slug', 40)->nullable();
            $table->string('plan_slug', 120)->nullable();
            $table->string('document_name');
            $table->enum('document_type', ['juridico', 'genealogico', 'otro'])->default('otro');
            $table->json('match_keywords')->nullable();
            $table->foreignId('recommended_service_id')->nullable();
            $table->string('recommended_plan_slug', 120)->nullable();
            $table->string('client_label')->nullable();
            $table->text('client_explanation')->nullable();
            $table->text('internal_notes')->nullable();
            $table->boolean('required')->default(true);
            $table->boolean('active')->default(true);
            $table->boolean('client_visible')->default(true);
            $table->unsignedSmallInteger('priority')->default(50);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::connection('banca_online_advisor_test')->dropIfExists('banca_online_document_rules');
        Schema::connection('banca_online_advisor_test')->dropIfExists('document_requests');
        Schema::connection('banca_online_advisor_test')->dropIfExists('servicios');
        Schema::connection('banca_online_advisor_test')->dropIfExists('users');
        DB::disconnect('banca_online_advisor_test');

        parent::tearDown();
    }

    public function test_missing_document_uses_admin_rule_to_recommend_exact_support(): void
    {
        $user = User::withoutEvents(fn () => User::create([
            'name' => 'Cliente Demo',
            'email' => 'cliente@example.test',
            'pay' => 2,
            'contrato' => 1,
            'cosready' => 0,
        ]));

        $service = Servicio::create([
            'id_hubspot' => 'DOC-SUPPORT-001',
            'nombre' => 'Busqueda documental italiana',
            'precio' => 450,
            'categoria' => 'banca_online_2026',
            'tipo' => 'servicio',
            'activo' => true,
            'moneda' => 'EUR',
            'orden' => 1,
        ]);

        DocumentRequest::create([
            'user_id' => $user->id,
            'requested_by' => $user->id,
            'document_name' => 'Acta de nacimiento italiana apostillada',
            'document_type' => 'genealogico',
            'status' => 'no_documento',
        ]);

        BancaOnlineDocumentRule::create([
            'country_slug' => 'italia',
            'plan_slug' => 'solicitud-estrategica',
            'document_name' => 'Acta de nacimiento',
            'document_type' => 'genealogico',
            'match_keywords' => ['nacimiento italiana', 'apostilla'],
            'recommended_service_id' => $service->id,
            'recommended_plan_slug' => 'administrativo',
            'client_label' => 'Resolver acta italiana',
            'client_explanation' => 'Podemos buscar o sustituir este documento con soporte especializado.',
            'active' => true,
            'client_visible' => true,
            'priority' => 10,
        ]);

        $advisor = new BancaOnlineExpedienteAdvisor(
            new BancaOnlineCatalog(),
            new BancaOnlineFlow(),
            new ClientStageResolver(),
            new BancaOnlineCosContext()
        );

        $context = $advisor->forUser($user, 'italia', null, [], 'cos');

        $this->assertTrue($context['document_support']['available']);
        $this->assertSame('administrativo', $context['document_support']['plan_slug']);
        $this->assertSame($service->id, $context['document_support']['recommended_service_id']);
        $this->assertSame('Busqueda documental italiana', $context['document_support']['recommended_service_name']);
        $this->assertSame('Resolver acta italiana', $context['next_action']['label']);
        $this->assertSame('document_support', $context['next_action']['type']);
    }
}
