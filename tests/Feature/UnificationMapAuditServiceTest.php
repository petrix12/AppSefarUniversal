<?php

namespace Tests\Feature;

use App\Models\UnificationAuditLink;
use App\Services\UnificationMapAuditService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UnificationMapAuditServiceTest extends TestCase
{
    private object $foundationMigration;

    private object $auditMigration;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.unification_map_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('database.default', 'unification_map_test');
        DB::purge('unification_map_test');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('estado_documental')->nullable();
            $table->timestamps();
        });
        Schema::create('assoc_tl_hs', function (Blueprint $table) {
            $table->string('tl_id')->nullable();
            $table->string('hs_id')->nullable();
            $table->string('modulo')->nullable();
            $table->timestamps();
        });
        Schema::create('tl_custom_field_definitions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('label');
            $table->string('type');
            $table->string('context');
        });
        Schema::create('monday_form_builder', function (Blueprint $table) {
            $table->id();
            $table->string('board_id');
            $table->string('column_id');
            $table->string('title');
            $table->string('type');
        });
        Schema::create('monday_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('local_field_key');
            $table->string('board_id');
            $table->string('monday_column_id');
        });

        $this->foundationMigration = require database_path('migrations/2026_08_27_090000_create_unified_data_foundation_tables.php');
        $this->foundationMigration->up();
        $this->auditMigration = require database_path('migrations/2026_08_27_110000_create_unification_audit_links_table.php');
        $this->auditMigration->up();
    }

    protected function tearDown(): void
    {
        $this->auditMigration->down();
        $this->foundationMigration->down();
        Schema::dropIfExists('monday_field_mappings');
        Schema::dropIfExists('monday_form_builder');
        Schema::dropIfExists('tl_custom_field_definitions');
        Schema::dropIfExists('assoc_tl_hs');
        Schema::dropIfExists('users');
        DB::disconnect('unification_map_test');

        parent::tearDown();
    }

    public function test_it_builds_a_read_only_cross_platform_inventory_and_keeps_manual_links_as_audit_records(): void
    {
        DB::table('assoc_tl_hs')->insert([
            ['tl_id' => 'tl-estado-1', 'hs_id' => 'estado_documental', 'modulo' => null],
            ['tl_id' => 'tl-estado-2', 'hs_id' => 'estado_documental', 'modulo' => null],
            ['tl_id' => 'tl-origen', 'hs_id' => 'origen_cliente', 'modulo' => null],
        ]);
        DB::table('tl_custom_field_definitions')->insert([
            ['id' => 'tl-estado-1', 'label' => 'Estado documental', 'type' => 'single_select', 'context' => 'contact'],
            ['id' => 'tl-estado-2', 'label' => 'Estado de documentos', 'type' => 'single_select', 'context' => 'contact'],
            ['id' => 'tl-origen', 'label' => 'Origen del cliente', 'type' => 'text', 'context' => 'contact'],
        ]);
        DB::table('monday_form_builder')->insert([
            ['board_id' => 'ventas', 'column_id' => 'status', 'title' => 'Estado documental', 'type' => 'status'],
        ]);
        DB::table('monday_field_mappings')->insert([
            ['board_id' => 'ventas', 'monday_column_id' => 'status', 'local_field_key' => 'estado_documental'],
            ['board_id' => 'produccion', 'monday_column_id' => 'text_1', 'local_field_key' => 'texto_largo_1'],
        ]);

        UnificationAuditLink::create([
            'app_field_key' => 'fecha_de_ingreso',
            'app_field_label' => 'Fecha de ingreso',
            'provider' => 'monday',
            'external_entity_type' => 'item',
            'scope_key' => 'produccion',
            'external_field_key' => 'date_1',
            'external_field_label' => 'Fecha de ingreso',
            'match_method' => 'manual',
            'status' => 'proposed',
        ]);

        $inventory = app(UnificationMapAuditService::class)->inventory();

        $this->assertSame(3, $inventory['summary']['legacy_associations']);
        $this->assertSame(2, $inventory['summary']['hubspot_fields']);
        $this->assertSame(3, $inventory['summary']['teamleader_fields']);
        $this->assertSame(1, $inventory['summary']['app_legacy_columns']);
        $this->assertSame(2, $inventory['summary']['monday_fields']);
        $this->assertTrue($inventory['summary']['audit_storage_ready']);

        $legacyRow = collect($inventory['map_rows'])->firstWhere('identity', 'legacy:estado_documental');
        $this->assertCount(2, $legacyRow['teamleader']);
        $this->assertSame('Estado documental', $legacyRow['monday_matches'][0]['label']);
        $this->assertSame(100, $legacyRow['monday_matches'][0]['confidence']);

        $manualRow = collect($inventory['map_rows'])->firstWhere('identity', 'audit:fecha_de_ingreso');
        $this->assertSame('Propuesta de auditoría', $manualRow['app']['source']);
        $this->assertSame('date_1', $manualRow['monday_matches'][0]['key']);
        $this->assertSame('proposed', $manualRow['audit_links'][0]['status']);

        $this->assertDatabaseCount('integration_field_mappings', 0);
        $this->assertDatabaseCount('custom_field_definitions', 0);
    }
}
