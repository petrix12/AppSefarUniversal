<?php

namespace Tests\Feature;

use App\Models\CustomFieldDefinition;
use App\Models\IntegrationFieldMapping;
use App\Models\IntegrationOutbox;
use App\Models\User;
use App\Models\WorkflowBoard;
use App\Models\WorkflowMembership;
use App\Models\WorkflowStage;
use App\Services\UnifiedClientProfileService;
use App\Services\WorkflowTransferService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UnifiedDataFoundationTest extends TestCase
{
    private object $migration;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.unified_foundation_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('database.default', 'unified_foundation_test');
        DB::purge('unified_foundation_test');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $this->migration = require database_path('migrations/2026_08_27_090000_create_unified_data_foundation_tables.php');
        $this->migration->up();
    }

    protected function tearDown(): void
    {
        $this->migration->down();
        Schema::dropIfExists('users');
        DB::disconnect('unified_foundation_test');

        parent::tearDown();
    }

    public function test_custom_fields_queue_only_configured_outbound_updates(): void
    {
        $client = User::withoutEvents(fn () => User::create(['name' => 'Ana Pérez']));
        $profiles = app(UnifiedClientProfileService::class);
        $field = $profiles->defineField([
            'key' => 'estado_documental',
            'label' => 'Estado documental',
            'data_type' => 'select',
        ]);

        IntegrationFieldMapping::create([
            'provider' => 'hubspot',
            'external_entity_type' => 'contact',
            'scope_key' => '*',
            'external_field_key' => 'estado_documental',
            'entity_type' => CustomFieldDefinition::ENTITY_CLIENT,
            'custom_field_definition_id' => $field->id,
            'direction' => 'bidirectional',
            'conflict_policy' => 'manual',
            'is_active' => true,
        ]);

        $profiles->setValue($client, $field, 'Pendiente');

        $this->assertDatabaseHas('custom_field_values', [
            'custom_field_definition_id' => $field->id,
            'entity_type' => CustomFieldDefinition::ENTITY_CLIENT,
            'entity_id' => $client->id,
            'value_text' => 'Pendiente',
            'source' => 'app',
        ]);
        $this->assertDatabaseHas('integration_outbox', [
            'provider' => 'hubspot',
            'entity_id' => $client->id,
            'operation' => 'update_fields',
            'status' => 'pending',
        ]);
        $this->assertSame('Pendiente', IntegrationOutbox::firstOrFail()->payload['fields']['estado_documental']);
    }

    public function test_new_integration_mappings_are_inactive_until_explicitly_enabled(): void
    {
        $field = app(UnifiedClientProfileService::class)->defineField([
            'key' => 'campo_auditable',
            'label' => 'Campo auditable',
        ]);

        $mapping = IntegrationFieldMapping::create([
            'provider' => 'hubspot',
            'external_entity_type' => 'contact',
            'scope_key' => '*',
            'external_field_key' => 'campo_auditable',
            'entity_type' => 'client',
            'custom_field_definition_id' => $field->id,
            'direction' => 'pull',
            'conflict_policy' => 'manual',
        ]);

        $this->assertFalse($mapping->fresh()->is_active);
    }

    public function test_transfer_keeps_history_and_queues_the_monday_operation(): void
    {
        $client = User::withoutEvents(fn () => User::create(['name' => 'Ana Pérez']));
        $sourceBoard = WorkflowBoard::create([
            'provider' => 'monday',
            'external_board_id' => 'ventas',
            'name' => 'Ventas',
        ]);
        $sourceStage = WorkflowStage::create([
            'workflow_board_id' => $sourceBoard->id,
            'external_stage_id' => 'por_revisar',
            'name' => 'Por revisar',
        ]);
        $targetBoard = WorkflowBoard::create([
            'provider' => 'monday',
            'external_board_id' => 'produccion',
            'name' => 'Producción',
        ]);
        $targetStage = WorkflowStage::create([
            'workflow_board_id' => $targetBoard->id,
            'external_stage_id' => 'nuevos',
            'name' => 'Nuevos',
        ]);
        $sourceMembership = WorkflowMembership::create([
            'entity_type' => CustomFieldDefinition::ENTITY_CLIENT,
            'entity_id' => $client->id,
            'workflow_board_id' => $sourceBoard->id,
            'workflow_stage_id' => $sourceStage->id,
            'external_item_id' => 'item-1',
            'entered_at' => now(),
        ]);

        $targetMembership = app(WorkflowTransferService::class)->transfer(
            $client,
            $sourceMembership,
            $targetBoard,
            $targetStage,
            null,
            'Cliente formalizado',
        );

        $this->assertSame('moved', $sourceMembership->fresh()->status);
        $this->assertSame('active', $targetMembership->fresh()->status);
        $this->assertSame($targetStage->id, $targetMembership->workflow_stage_id);
        $this->assertDatabaseHas('workflow_transitions', [
            'entity_id' => $client->id,
            'from_workflow_board_id' => $sourceBoard->id,
            'to_workflow_board_id' => $targetBoard->id,
            'reason' => 'Cliente formalizado',
        ]);
        $this->assertDatabaseHas('integration_outbox', [
            'provider' => 'monday',
            'entity_id' => $client->id,
            'operation' => 'transfer_workflow',
            'status' => 'pending',
        ]);
    }
}
