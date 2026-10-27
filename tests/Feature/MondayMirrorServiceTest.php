<?php

namespace Tests\Feature;

use App\Models\MondayBoard;
use App\Models\MondayColumn;
use App\Models\MondayGroup;
use App\Models\MondayItem;
use App\Models\MondayItemValue;
use App\Models\MondaySyncRun;
use App\Services\MondayCatalogService;
use App\Services\CosMondayLookup;
use App\Services\MondayMirrorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use Mockery;
use Tests\TestCase;

class MondayMirrorServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.monday.token', 'test-token');
        config()->set('services.monday.api_version', '2026-10');
        config()->set('services.monday.mirror_reads', true);
        config()->set('services.monday.mirror_page_size', 2);
    }

    public function test_it_mirrors_structure_items_values_subitems_and_cursor_pages(): void
    {
        Http::fake(function ($request) {
            $query = $request->data()['query'];

            if (str_contains($query, 'MirrorNextItems')) {
                return Http::response(['data' => [
                    'next_items_page' => [
                        'cursor' => null,
                        'items' => [[
                            'id' => 'item-3',
                            'name' => 'Tercero',
                            'state' => 'active',
                            'board' => ['id' => '10', 'name' => 'Ventas'],
                            'group' => ['id' => 'done', 'title' => 'Listos'],
                            'column_values' => [],
                            'subitems' => [[
                                'id' => 'sub-1',
                                'name' => 'Documento',
                                'state' => 'active',
                                'board' => ['id' => '999', 'name' => 'Subitems'],
                                'group' => ['id' => 'subitems', 'title' => 'Subitems'],
                                'column_values' => [[
                                    'id' => 'status',
                                    'type' => 'status',
                                    'text' => 'Hecho',
                                    'value' => '{"index":1}',
                                ]],
                            ]],
                        ]],
                    ],
                ]]);
            }

            return Http::response(['data' => [
                'boards' => [[
                    'id' => '10',
                    'name' => 'Ventas',
                    'state' => 'active',
                    'board_kind' => 'private',
                    'permissions' => 'owners',
                    'items_count' => 3,
                    'workspace' => ['id' => '1', 'name' => 'Principal'],
                    'groups' => [
                        ['id' => 'new', 'title' => 'Nuevos', 'color' => '#fff', 'position' => '1'],
                        ['id' => 'done', 'title' => 'Listos', 'color' => '#000', 'position' => '2'],
                    ],
                    'columns' => [[
                        'id' => 'status',
                        'title' => 'Estado',
                        'type' => 'status',
                        'description' => null,
                        'settings_str' => '{"labels":{"1":"Hecho"}}',
                    ]],
                    'items_page' => [
                        'cursor' => 'next-page',
                        'items' => [
                            $this->item('item-1', 'Primero', 'Nuevo'),
                            $this->item('item-2', 'Segundo', 'En curso'),
                        ],
                    ],
                ]],
            ]]);
        });

        $counts = app(MondayMirrorService::class)->syncBoard('10');

        $this->assertSame(['items' => 3, 'subitems' => 1, 'values' => 3], $counts);
        $this->assertDatabaseHas('monday_boards', ['id' => '10', 'name' => 'Ventas']);
        $this->assertDatabaseCount('monday_groups', 2);
        $this->assertDatabaseCount('monday_columns', 1);
        $this->assertDatabaseCount('monday_items', 4);
        $this->assertDatabaseCount('monday_item_values', 3);
        $this->assertDatabaseHas('monday_items', [
            'id' => 'sub-1',
            'board_id' => '10',
            'source_board_id' => '999',
            'parent_item_id' => 'item-3',
        ]);
        $this->assertSame(['index' => 1], MondayItemValue::where('item_id', 'sub-1')->firstOrFail()->value);
        $this->assertNotNull(MondayBoard::findOrFail('10')->last_synced_at);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->hasHeader('API-Version', '2026-10'));
    }

    public function test_account_inventory_marks_missing_boards_and_only_schedules_active_ones(): void
    {
        MondayBoard::create(['id' => '99', 'name' => 'Antiguo', 'state' => 'active']);
        Http::fake(Http::response(['data' => ['boards' => [
            ['id' => '10', 'name' => 'Activo', 'state' => 'active'],
            ['id' => '20', 'name' => 'Archivado', 'state' => 'archived'],
        ]]]));

        $ids = app(MondayMirrorService::class)->discoverBoards();

        $this->assertSame(['10'], $ids);
        $this->assertNotNull(MondayBoard::findOrFail('99')->remote_deleted_at);
        $this->assertSame('archived', MondayBoard::findOrFail('20')->state);
        $this->assertDatabaseHas('monday_sync_runs', [
            'scope' => 'account',
            'status' => 'completed',
            'boards' => 2,
        ]);
    }

    public function test_a_complete_resync_soft_deletes_items_no_longer_returned(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            return Http::response(['data' => ['boards' => [[
                'id' => '10',
                'name' => 'Ventas',
                'state' => 'active',
                'groups' => [],
                'columns' => [],
                'items_page' => [
                    'cursor' => null,
                    'items' => $calls === 1 ? [$this->item('item-1', 'Primero', 'Nuevo')] : [],
                ],
            ]]]]);
        });

        app(MondayMirrorService::class)->syncBoard('10');
        $this->travel(1)->seconds();
        app(MondayMirrorService::class)->syncBoard('10');

        $this->assertNull(MondayItem::find('item-1'));
        $this->assertNotNull(MondayItem::withTrashed()->findOrFail('item-1')->deleted_at);
    }

    public function test_an_out_of_order_source_event_keeps_an_item_in_its_actual_destination_board(): void
    {
        MondayBoard::create(['id' => '10', 'name' => 'Origen', 'state' => 'active']);
        MondayBoard::create(['id' => '20', 'name' => 'Destino', 'state' => 'active']);
        MondayItem::create(['id' => 'item-1', 'board_id' => '10', 'name' => 'Cliente', 'state' => 'active']);
        Http::fake(Http::response(['data' => ['items' => [[
            'id' => 'item-1',
            'name' => 'Cliente',
            'state' => 'active',
            'board' => ['id' => '20', 'name' => 'Destino'],
            'group' => ['id' => 'new', 'title' => 'Nuevos'],
            'column_values' => [],
            'subitems' => [],
        ]]]]));

        // The event says "removed from 10", but Monday already reports the
        // item as active in 20. The destination must win.
        app(MondayMirrorService::class)->syncItem('item-1', '10');

        $item = MondayItem::findOrFail('item-1');
        $this->assertSame('20', $item->board_id);
        $this->assertSame('new', $item->group_id);
        $this->assertNull($item->deleted_at);
        $this->assertDatabaseHas('monday_item_movements', [
            'item_id' => 'item-1',
            'from_board_id' => '10',
            'to_board_id' => '20',
            'source' => 'webhook',
        ]);
    }

    public function test_an_archived_item_is_retained_with_soft_deletion(): void
    {
        MondayBoard::create(['id' => '10', 'name' => 'Ventas', 'state' => 'active']);
        Http::fake(Http::response(['data' => ['items' => [[
            'id' => 'item-1',
            'name' => 'Cliente',
            'state' => 'archived',
            'board' => ['id' => '10', 'name' => 'Ventas'],
            'group' => ['id' => 'done', 'title' => 'Terminados'],
            'column_values' => [],
            'subitems' => [],
        ]]]]));

        app(MondayMirrorService::class)->syncItem('item-1', '10');

        $this->assertNull(MondayItem::find('item-1'));
        $this->assertSame('archived', MondayItem::withTrashed()->findOrFail('item-1')->state);
    }

    public function test_catalog_reads_the_completed_local_mirror_without_calling_monday(): void
    {
        Http::fake();
        MondayBoard::create([
            'id' => '10',
            'name' => 'Ventas',
            'state' => 'active',
            'last_seen_at' => now(),
            'last_synced_at' => now(),
        ]);
        MondaySyncRun::start('account')->complete(['boards' => 1]);
        MondayGroup::create(['board_id' => '10', 'monday_id' => 'new', 'title' => 'Nuevos']);
        MondayColumn::create(['board_id' => '10', 'monday_id' => 'status', 'title' => 'Estado', 'type' => 'status']);

        $catalog = app(MondayCatalogService::class);

        $this->assertSame([['id' => '10', 'name' => 'Ventas']], $catalog->boards());
        $this->assertSame([['id' => 'new', 'name' => 'Nuevos']], $catalog->groups('10'));
        $this->assertSame([['id' => 'status', 'name' => 'Estado', 'type' => 'status']], $catalog->columns('10'));
        Http::assertNothingSent();
    }

    public function test_cos_lookup_uses_the_completed_local_mirror(): void
    {
        config()->set('cos_snapshot.monday_search_boards', ['10' => 'Ventas']);
        Http::fake();
        MondayBoard::create([
            'id' => '10',
            'name' => 'Ventas',
            'state' => 'active',
            'last_seen_at' => now(),
            'last_synced_at' => now(),
        ]);
        MondayColumn::create([
            'board_id' => '10',
            'monday_id' => 'enlace',
            'title' => 'Enlace',
            'type' => 'link',
        ]);
        MondayItem::create([
            'id' => '20',
            'board_id' => '10',
            'name' => 'Cliente local',
            'state' => 'active',
        ]);
        MondayItemValue::create([
            'item_id' => '20',
            'board_id' => '10',
            'monday_column_id' => 'enlace',
            'type' => 'link',
            'text' => 'https://app.sefaruniversal.com/tree/V123',
        ]);
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 123;
        $user->shouldReceive('save')->once()->andReturnTrue();

        $result = app(CosMondayLookup::class)->find('V123', $user, true);

        $this->assertSame('20', $result['id']);
        $this->assertSame('Ventas', $result['board']['name']);
        $this->assertSame('20', $user->monday_id);
        Http::assertNothingSent();
    }

    private function item(string $id, string $name, string $status): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'state' => 'active',
            'creator_id' => '5',
            'created_at' => '2026-10-27T10:00:00Z',
            'updated_at' => '2026-10-27T11:00:00Z',
            'relative_link' => '/boards/10/pulses/'.$id,
            'board' => ['id' => '10', 'name' => 'Ventas'],
            'group' => ['id' => 'new', 'title' => 'Nuevos'],
            'column_values' => [[
                'id' => 'status',
                'type' => 'status',
                'text' => $status,
                'value' => '{"index":0}',
            ]],
            'subitems' => [],
        ];
    }
}
