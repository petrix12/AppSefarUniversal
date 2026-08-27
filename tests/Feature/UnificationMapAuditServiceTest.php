<?php

namespace Tests\Feature;

use App\Http\Controllers\UnificationMapController;
use App\Models\UnificationAuditLink;
use App\Models\UnificationAuditRelation;
use App\Services\UnificationAiSuggestionService;
use App\Services\UnificationMapAuditService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UnificationMapAuditServiceTest extends TestCase
{
    private object $foundationMigration;

    private object $auditMigration;

    private object $relationMigration;

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
        $this->relationMigration = require database_path('migrations/2026_08_27_120000_create_unification_audit_relations_table.php');
        $this->relationMigration->up();
    }

    protected function tearDown(): void
    {
        $this->relationMigration->down();
        $this->auditMigration->down();
        $this->foundationMigration->down();
        Schema::dropIfExists('monday_field_mappings');
        Schema::dropIfExists('monday_form_builder');
        Schema::dropIfExists('tl_projects');
        Schema::dropIfExists('negocios');
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
        UnificationAuditRelation::create([
            'left_provider' => 'app',
            'left_entity_type' => 'client',
            'left_scope_key' => '*',
            'left_field_key' => 'estado_documental',
            'left_field_label' => 'Estado documental',
            'right_provider' => 'monday',
            'right_entity_type' => 'item',
            'right_scope_key' => 'ventas',
            'right_field_key' => 'status',
            'right_field_label' => 'Estado documental',
            'status' => 'approved',
        ]);

        $inventory = app(UnificationMapAuditService::class)->inventory();

        $this->assertSame(3, $inventory['summary']['legacy_associations']);
        $this->assertSame(6, $inventory['summary']['hubspot_fields']);
        $this->assertSame(3, $inventory['summary']['teamleader_fields']);
        $this->assertSame(1, $inventory['summary']['app_legacy_columns']);
        $this->assertSame(2, $inventory['summary']['monday_fields']);
        $this->assertTrue($inventory['summary']['audit_storage_ready']);
        $this->assertTrue($inventory['summary']['relation_storage_ready']);
        $this->assertGreaterThanOrEqual(1, $inventory['summary']['automatic_relations']);

        $legacyRow = collect($inventory['map_rows'])->firstWhere('identity', 'legacy:estado_documental');
        $this->assertCount(2, $legacyRow['teamleader']);
        $this->assertSame([], $legacyRow['monday_matches']);
        $mondayRow = collect($inventory['map_rows'])->firstWhere('identity', 'monday:ventas:status');
        $this->assertSame('Estado documental', $mondayRow['monday_matches'][0]['label']);
        $this->assertFalse(collect($inventory['automatic_relations'])->contains(
            fn (array $relation) => $relation['left']['provider'] === 'monday'
                || $relation['right']['provider'] === 'monday'
        ));

        $manualRow = collect($inventory['map_rows'])->firstWhere('identity', 'audit:fecha_de_ingreso');
        $this->assertSame('Propuesta de auditoría', $manualRow['app']['source']);
        $this->assertSame('date_1', $manualRow['monday_matches'][0]['key']);
        $this->assertSame('proposed', $manualRow['audit_links'][0]['status']);

        $this->assertCount(1, $inventory['audited_relations']);
        $this->assertGreaterThanOrEqual(3, $inventory['summary']['derived_relations']);
        $this->assertTrue(collect($inventory['derived_relations'])->contains(
            fn (array $relation) => $relation['left']['provider'] === 'monday'
                || $relation['right']['provider'] === 'monday'
        ));

        $this->assertDatabaseCount('integration_field_mappings', 0);
        $this->assertDatabaseCount('custom_field_definitions', 0);
    }

    public function test_fast_inventory_skips_automatic_comparisons_until_requested(): void
    {
        $inventory = app(UnificationMapAuditService::class)->inventory(false);

        $this->assertFalse($inventory['summary']['automatic_relations_loaded']);
        $this->assertNull($inventory['summary']['automatic_relations']);
        $this->assertSame([], $inventory['automatic_relations']);
    }

    public function test_paginated_map_page_only_passes_the_current_page_and_not_the_full_field_catalog(): void
    {
        $response = app(UnificationMapController::class)->map(
            Request::create('/admin/unification-map', 'GET', ['per_page' => 25]),
            app(UnificationMapAuditService::class),
        );
        $data = $response->getData();

        $this->assertLessThanOrEqual(25, count($data['map_rows']));
        $this->assertArrayNotHasKey('field_options', $data);
        $this->assertSame([], $data['automatic_relations']);
    }

    public function test_it_records_a_monday_relation_between_two_different_boards(): void
    {
        $user = new \App\Models\User;
        $user->id = 999;
        $request = Request::create('/admin/unification-map/relations', 'POST', [
            'left_provider' => 'monday',
            'left_entity_type' => 'item',
            'left_scope_key' => '10',
            'left_field_key' => 'status',
            'left_field_label' => 'Estado',
            'right_provider' => 'monday',
            'right_entity_type' => 'item',
            'right_scope_key' => '20',
            'right_field_key' => 'status',
            'right_field_label' => 'Estado',
        ]);
        $request->setUserResolver(fn () => $user);

        $response = app(UnificationMapController::class)->storeRelation($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertDatabaseHas('unification_audit_relations', [
            'left_provider' => 'monday',
            'left_scope_key' => '10',
            'left_field_key' => 'status',
            'right_provider' => 'monday',
            'right_scope_key' => '20',
            'right_field_key' => 'status',
            'status' => 'proposed',
        ]);
    }

    public function test_monday_field_endpoint_loads_columns_for_the_selected_board(): void
    {
        Cache::flush();
        config()->set('services.monday.token', 'monday-test-token');
        Http::fake(function (ClientRequest $request) {
            $this->assertStringContainsString('columns {', $request->data()['query']);

            return Http::response([
                'data' => ['boards' => [[
                    'columns' => [
                        ['id' => 'status', 'title' => 'Estado', 'type' => 'status'],
                        ['id' => 'text', 'title' => 'Nombre', 'type' => 'text'],
                    ],
                ]]],
            ]);
        });

        $response = app(UnificationMapController::class)->mondayFields(
            Request::create('/admin/unification-map/monday/fields', 'GET', [
                'board_id' => '10',
                'search' => 'estado',
            ]),
            app(\App\Services\MondayCatalogService::class),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'identity' => '10:status',
            'provider' => 'monday',
            'entity_type' => 'item',
            'scope_key' => '10',
            'key' => 'status',
            'label' => 'Estado',
            'type' => 'status',
            'source' => 'Catálogo de Monday',
        ], $response->getData(true)['data'][0]);
    }

    public function test_er_diagram_includes_approved_cross_board_monday_relations(): void
    {
        UnificationAuditRelation::create([
            'left_provider' => 'monday',
            'left_entity_type' => 'item',
            'left_scope_key' => '10',
            'left_field_key' => 'status',
            'left_field_label' => 'Estado',
            'right_provider' => 'monday',
            'right_entity_type' => 'item',
            'right_scope_key' => '20',
            'right_field_key' => 'status',
            'right_field_label' => 'Estado',
            'status' => 'approved',
        ]);

        $service = app(UnificationMapAuditService::class);
        $diagram = $service->erDiagram();

        $this->assertSame(1, $diagram['approved_relations']);
        $this->assertSame(1, $diagram['cross_board_monday_relations']);
        $this->assertStringContainsString('<svg', $service->renderErDiagramSvg());
    }

    public function test_er_diagram_can_be_downloaded_as_svg(): void
    {
        $response = app(UnificationMapController::class)->diagram(
            Request::create('/admin/unification-map/diagram', 'GET', [
                'format' => 'svg',
                'download' => '1',
            ]),
            app(UnificationMapAuditService::class),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/svg+xml; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="diagrama-er-unificacion.svg"', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('<svg', $response->getContent());
    }

    public function test_it_separates_contact_fields_from_deal_and_project_fields(): void
    {
        Schema::create('negocios', function (Blueprint $table) {
            $table->id();
            $table->string('estado_operativo')->nullable();
            $table->string('hubspot_id')->nullable();
            $table->string('teamleader_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
        Schema::create('tl_projects', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('estado_operativo')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });

        $inventory = app(UnificationMapAuditService::class)->inventory();

        $this->assertSame(1, $inventory['summary']['business_app_fields']);
        $this->assertSame(1, $inventory['summary']['hubspot_deal_fields']);
        $this->assertSame(1, $inventory['summary']['teamleader_project_fields']);

        $businessRow = collect($inventory['map_rows'])->firstWhere('identity', 'business:estado_operativo');
        $this->assertSame('business', $businessRow['app']['entity_type']);
        $this->assertSame('deal', $businessRow['hubspot'][0]['entity_type']);
        $this->assertSame('project', $businessRow['teamleader'][0]['entity_type']);

        $this->assertFalse(collect($inventory['automatic_relations'])->contains(
            fn (array $relation) => in_array($relation['left']['entity_type'], ['client', 'contact'], true)
                && in_array($relation['right']['entity_type'], ['business', 'deal', 'project'], true)
        ));
    }

    public function test_ai_evaluates_the_two_explicitly_selected_fields_even_when_they_are_not_an_automatic_match(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('aacs_madre')->nullable();
        });
        DB::table('assoc_tl_hs')->insert([
            'tl_id' => 'tl-enviada',
            'hs_id' => 'estado_documental',
            'modulo' => null,
        ]);
        DB::table('tl_custom_field_definitions')->insert([
            'id' => 'tl-enviada',
            'label' => 'Enviada al cliente',
            'type' => 'single_select',
            'context' => 'contact',
        ]);
        config()->set('services.openrouter.key', 'test-key');
        config()->set('services.openrouter.url', 'https://openrouter.test/chat');
        config()->set('services.openrouter.unification_model', 'test-model');
        Http::fake([
            'https://openrouter.test/chat' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode(['suggestions' => [[
                            'candidate_index' => 0,
                            'confidence' => 12,
                            'reason' => 'Los campos no parecen representar el mismo dato.',
                        ]]]),
                    ],
                ]],
            ]),
        ]);

        $response = app(UnificationMapController::class)->suggest(
            Request::create('/admin/unification-map/suggest', 'POST', [
                'left_provider' => 'teamleader',
                'left_entity_type' => 'contact',
                'left_scope_key' => '*',
                'left_field_key' => 'tl-enviada',
                'right_provider' => 'hubspot',
                'right_entity_type' => 'contact',
                'right_scope_key' => '*',
                'right_field_key' => 'aacs_madre',
            ]),
            app(UnificationMapAuditService::class),
            app(UnificationAiSuggestionService::class),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['suggestion']['used_ai']);
        Http::assertSent(function (ClientRequest $request): bool {
            $prompt = json_decode((string) data_get($request->data(), 'messages.1.content'), true);
            $candidate = data_get($prompt, 'candidate_pairs.0');

            return count(data_get($prompt, 'candidate_pairs', [])) === 1
                && data_get($candidate, 'left.key') === 'tl-enviada'
                && data_get($candidate, 'right.key') === 'aacs_madre';
        });
        $this->assertDatabaseCount('unification_audit_relations', 0);
        $this->assertDatabaseCount('integration_field_mappings', 0);
    }

    public function test_it_saves_multiple_ai_recommendations_only_as_audit_proposals(): void
    {
        DB::table('assoc_tl_hs')->insert([
            [
                'tl_id' => 'tl-estado-1',
                'hs_id' => 'estado_documental',
                'modulo' => null,
            ],
            [
                'tl_id' => 'tl-origen',
                'hs_id' => 'name',
                'modulo' => null,
            ],
        ]);
        DB::table('tl_custom_field_definitions')->insert([
            [
                'id' => 'tl-estado-1',
                'label' => 'Estado documental',
                'type' => 'single_select',
                'context' => 'contact',
            ],
            [
                'id' => 'tl-origen',
                'label' => 'Origen',
                'type' => 'text',
                'context' => 'contact',
            ],
        ]);
        $user = new \App\Models\User;
        $user->id = 999;
        $request = Request::create('/admin/unification-map/relations/bulk', 'POST', [
            'relations' => [[
                'left_provider' => 'teamleader',
                'left_entity_type' => 'contact',
                'left_scope_key' => '*',
                'left_field_key' => 'tl-estado-1',
                'right_provider' => 'hubspot',
                'right_entity_type' => 'contact',
                'right_scope_key' => '*',
                'right_field_key' => 'estado_documental',
                'confidence' => 91,
                'reason' => 'Las etiquetas coinciden.',
            ], [
                'left_provider' => 'teamleader',
                'left_entity_type' => 'contact',
                'left_scope_key' => '*',
                'left_field_key' => 'tl-origen',
                'right_provider' => 'hubspot',
                'right_entity_type' => 'contact',
                'right_scope_key' => '*',
                'right_field_key' => 'name',
                'confidence' => 75,
                'reason' => 'Propuesta independiente para auditoría.',
            ]],
        ]);
        $request->setUserResolver(fn () => $user);

        $response = app(UnificationMapController::class)->storeRelationsBulk(
            $request,
            app(UnificationMapAuditService::class),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(2, $response->getData(true)['created']);
        $this->assertDatabaseHas('unification_audit_relations', [
            'left_provider' => 'teamleader',
            'left_field_key' => 'tl-estado-1',
            'right_provider' => 'hubspot',
            'right_field_key' => 'estado_documental',
            'match_method' => 'ai_batch',
            'status' => 'proposed',
        ]);
        $this->assertDatabaseHas('unification_audit_relations', [
            'left_provider' => 'teamleader',
            'left_field_key' => 'tl-origen',
            'right_provider' => 'hubspot',
            'right_field_key' => 'name',
            'match_method' => 'ai_batch',
            'status' => 'proposed',
        ]);
        $this->assertDatabaseCount('integration_field_mappings', 0);
    }

    public function test_ai_accepts_multiple_explicit_pairs_in_one_batch_without_creating_mappings(): void
    {
        DB::table('assoc_tl_hs')->insert([
            [
                'tl_id' => 'tl-estado-1',
                'hs_id' => 'estado_documental',
                'modulo' => null,
            ],
            [
                'tl_id' => 'tl-origen',
                'hs_id' => 'name',
                'modulo' => null,
            ],
        ]);
        DB::table('tl_custom_field_definitions')->insert([
            [
                'id' => 'tl-estado-1',
                'label' => 'Estado documental',
                'type' => 'single_select',
                'context' => 'contact',
            ],
            [
                'id' => 'tl-origen',
                'label' => 'Origen',
                'type' => 'text',
                'context' => 'contact',
            ],
        ]);
        config()->set('services.openrouter.key', 'test-key');
        config()->set('services.openrouter.url', 'https://openrouter.test/chat');
        Http::fake([
            'https://openrouter.test/chat' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode(['suggestions' => [[
                            'candidate_index' => 0,
                            'confidence' => 89,
                            'reason' => 'Propuesta de auditoría.',
                        ]]]),
                    ],
                ]],
            ]),
        ]);

        $response = app(UnificationMapController::class)->suggest(
            Request::create('/admin/unification-map/suggest', 'POST', [
                'left_provider' => 'teamleader',
                'right_provider' => 'hubspot',
                'batch_pairs' => [
                    [
                        'left' => ['entity_type' => 'contact', 'scope_key' => '*', 'field_key' => 'tl-estado-1'],
                        'right' => ['entity_type' => 'contact', 'scope_key' => '*', 'field_key' => 'estado_documental'],
                    ],
                    [
                        'left' => ['entity_type' => 'contact', 'scope_key' => '*', 'field_key' => 'tl-origen'],
                        'right' => ['entity_type' => 'contact', 'scope_key' => '*', 'field_key' => 'name'],
                    ],
                ],
            ]),
            app(UnificationMapAuditService::class),
            app(UnificationAiSuggestionService::class),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(2, $response->getData(true)['suggestion']['candidate_count']);
        $this->assertSame(1, $response->getData(true)['suggestion']['batch_count']);
        $this->assertDatabaseCount('unification_audit_relations', 0);
        $this->assertDatabaseCount('integration_field_mappings', 0);
    }
}
