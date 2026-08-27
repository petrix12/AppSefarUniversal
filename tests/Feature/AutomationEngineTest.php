<?php

namespace Tests\Feature;

use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Models\User;
use App\Services\AutomationEngine;
use App\Services\UnifiedClientProfileService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AutomationEngineTest extends TestCase
{
    private object $foundationMigration;

    private object $automationMigration;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.automation_engine_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('database.default', 'automation_engine_test');
        DB::purge('automation_engine_test');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->timestamps();
        });
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('contact_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date');
            $table->string('status');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->boolean('skip_hubspot_reassignment')->default(false);
            $table->timestamps();
        });

        $this->foundationMigration = require database_path('migrations/2026_08_27_090000_create_unified_data_foundation_tables.php');
        $this->foundationMigration->up();
        $this->automationMigration = require database_path('migrations/2026_08_27_100000_create_automation_engine_tables.php');
        $this->automationMigration->up();
    }

    protected function tearDown(): void
    {
        $this->automationMigration->down();
        $this->foundationMigration->down();
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('users');
        DB::disconnect('automation_engine_test');

        parent::tearDown();
    }

    public function test_a_field_change_creates_an_auditable_delayed_task(): void
    {
        $advisor = User::withoutEvents(fn () => User::create(['name' => 'Asesor']));
        $client = User::withoutEvents(fn () => User::create([
            'name' => 'Ana Pérez',
            'email' => 'ana@example.test',
            'owner_id' => $advisor->id,
        ]));
        $profiles = app(UnifiedClientProfileService::class);
        $profiles->defineField([
            'key' => 'estatus_documental',
            'label' => 'Estatus documental',
            'data_type' => 'select',
        ]);

        $rule = AutomationRule::create([
            'name' => 'Solicitar recaudos',
            'entity_type' => 'client',
            'trigger_type' => AutomationRule::TRIGGER_EVENT,
            'trigger_event' => AutomationEngine::EVENT_FIELD_CHANGED,
            'conditions' => ['all' => [
                ['path' => 'field.key', 'operator' => 'equals', 'value' => 'estatus_documental'],
                ['path' => 'field.new_value', 'operator' => 'equals', 'value' => 'Pendiente'],
            ]],
        ]);
        AutomationAction::create([
            'automation_rule_id' => $rule->id,
            'position' => 0,
            'action_type' => AutomationAction::CREATE_TASK,
            'delay_minutes' => 0,
            'config' => [
                'assignee' => 'owner',
                'title' => 'Solicitar documentos a {{client.name}}',
                'description' => 'Estatus actual: {{field.new_value}}',
            ],
        ]);

        $profiles->setValue($client, 'estatus_documental', 'Pendiente');

        $this->assertDatabaseHas('automation_runs', [
            'automation_rule_id' => $rule->id,
            'entity_id' => $client->id,
            'status' => AutomationRun::PENDING,
        ]);

        $result = app(AutomationEngine::class)->processDueRuns();

        $this->assertSame(1, $result['completed']);
        $this->assertDatabaseHas('tasks', [
            'user_id' => $advisor->id,
            'contact_id' => $client->id,
            'title' => 'Solicitar documentos a Ana Pérez',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('automation_runs', ['status' => AutomationRun::COMPLETED]);
    }

    public function test_a_date_field_rule_is_scheduled_once_for_the_client(): void
    {
        $advisor = User::withoutEvents(fn () => User::create(['name' => 'Asesor']));
        $client = User::withoutEvents(fn () => User::create(['name' => 'Ana Pérez', 'owner_id' => $advisor->id]));
        $profiles = app(UnifiedClientProfileService::class);
        $profiles->defineField([
            'key' => 'fecha_vencimiento',
            'label' => 'Fecha de vencimiento',
            'data_type' => 'date',
        ]);

        $rule = AutomationRule::create([
            'name' => 'Vencimiento de documento',
            'entity_type' => 'client',
            'trigger_type' => AutomationRule::TRIGGER_DATE_FIELD,
            'trigger_config' => [
                'field_key' => 'fecha_vencimiento',
                'offset_minutes' => 0,
                'catch_up' => true,
            ],
        ]);
        AutomationAction::create([
            'automation_rule_id' => $rule->id,
            'position' => 0,
            'action_type' => AutomationAction::CREATE_TASK,
            'config' => [
                'assignee' => 'owner',
                'title' => 'Documento vencido: {{client.name}}',
            ],
        ]);
        $profiles->setValue($client, 'fecha_vencimiento', today()->toDateString());

        $result = app(AutomationEngine::class)->runScheduler();

        $this->assertSame(1, $result['date_queued']);
        $this->assertSame(1, $result['processed']['completed']);
        $this->assertDatabaseHas('tasks', [
            'user_id' => $advisor->id,
            'contact_id' => $client->id,
            'title' => 'Documento vencido: Ana Pérez',
        ]);
    }

    public function test_a_cron_rule_can_schedule_a_client_action(): void
    {
        $advisor = User::withoutEvents(fn () => User::create(['name' => 'Asesor']));
        $client = User::withoutEvents(fn () => User::create(['name' => 'Ana Pérez', 'owner_id' => $advisor->id]));
        $rule = AutomationRule::create([
            'name' => 'Revisión periódica',
            'entity_type' => 'client',
            'trigger_type' => AutomationRule::TRIGGER_SCHEDULE,
            'cron_expression' => '* * * * *',
            'timezone' => config('app.timezone'),
            'trigger_config' => ['client_id' => $client->id],
        ]);
        AutomationAction::create([
            'automation_rule_id' => $rule->id,
            'position' => 0,
            'action_type' => AutomationAction::CREATE_TASK,
            'config' => [
                'assignee' => 'owner',
                'title' => 'Revisión periódica: {{client.name}}',
            ],
        ]);

        $result = app(AutomationEngine::class)->runScheduler();

        $this->assertSame(1, $result['cron_queued']);
        $this->assertSame(1, $result['processed']['completed']);
        $this->assertDatabaseHas('tasks', [
            'title' => 'Revisión periódica: Ana Pérez',
        ]);
    }
}
