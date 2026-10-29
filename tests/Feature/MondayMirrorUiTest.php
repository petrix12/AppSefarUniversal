<?php

namespace Tests\Feature;

use App\Models\MondayBoard;
use App\Models\MondayColumn;
use App\Models\MondayGroup;
use App\Models\MondayItem;
use App\Models\MondayItemMovement;
use App\Models\MondayItemValue;
use App\Models\MondaySyncRun;
use App\Models\MondayWebhookEvent;
use App\Models\User;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MondayMirrorUiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2014_10_12_000000_create_users_table.php',
            'database/migrations/2021_03_08_173429_create_permission_tables.php',
            'database/migrations/2026_10_27_180000_create_monday_mirror_tables.php',
            'database/migrations/2026_10_27_190000_add_type_to_monday_boards_table.php',
        ]])->assertExitCode(0);
        Http::preventStrayRequests();
    }

    public function test_admin_can_browse_local_boards_and_items(): void
    {
        $this->withoutMiddleware(Authorize::class);
        Role::create(['name' => 'Cliente']);
        $this->actingAs(User::factory()->create());
        [$board, $item] = $this->seedMirror();

        $this->get(route('admin.monday.index'))
            ->assertOk()
            ->assertSee('Réplica local de Monday')
            ->assertSee('Operaciones');

        $this->get(route('admin.monday.boards.show', $board))
            ->assertOk()
            ->assertSee('Solicitudes abiertas')
            ->assertSee('Cliente de prueba')
            ->assertSee('En proceso');

        $this->get(route('admin.monday.items.show', $item->id))
            ->assertOk()
            ->assertSee('Cliente de prueba')
            ->assertSee('Valores')
            ->assertSee('En proceso');
    }

    public function test_admin_can_review_webhooks_syncs_and_movements(): void
    {
        $this->withoutMiddleware(Authorize::class);
        Role::create(['name' => 'Cliente']);
        $this->actingAs(User::factory()->create());
        [$board, $item] = $this->seedMirror();

        MondaySyncRun::create([
            'token' => '3fc22ab6-f57a-4d13-bac3-a82dc930508d',
            'scope' => 'board',
            'board_id' => $board->id,
            'status' => 'completed',
            'items' => 1,
            'started_at' => now()->subSecond(),
            'finished_at' => now(),
        ]);
        MondayWebhookEvent::create([
            'event_key' => 'event-1',
            'event_type' => 'change_column_value',
            'board_id' => $board->id,
            'item_id' => $item->id,
            'payload' => ['event' => ['type' => 'change_column_value']],
            'status' => 'completed',
            'processed_at' => now(),
        ]);
        MondayItemMovement::create([
            'item_id' => $item->id,
            'from_board_id' => '999',
            'to_board_id' => $board->id,
            'source' => 'webhook',
            'moved_at' => now(),
        ]);

        $this->get(route('admin.monday.activity'))
            ->assertOk()
            ->assertSee('Actividad de Monday')
            ->assertSee('change_column_value')
            ->assertSee('Movimientos de elementos');
    }

    public function test_board_loads_at_most_100_parents_per_batch_without_raw_payloads(): void
    {
        $this->signIn();
        [$board, $item] = $this->seedMirror();
        $item->update(['raw_data' => ['large_payload' => str_repeat('x', 32000)]]);
        $item->values()->first()->update(['raw_data' => ['large_payload' => str_repeat('x', 32000)]]);
        $this->seedParents($board, 205);

        $response = $this->get(route('admin.monday.boards.show', $board))->assertOk();
        $items = $response->viewData('items');
        $this->assertSame(206, $items->total());
        $this->assertCount(100, $items);
        $this->assertArrayNotHasKey('raw_data', $items->first()->getAttributes());
        $this->assertArrayNotHasKey('raw_data', $items->first()->values->first()->getAttributes());
        $response->assertSee('monday-batch-spinner')->assertSee('monday-batch-retry');

        $next = $this->getJson($items->nextPageUrl())->assertOk()->assertJsonPath('loaded', 200)->assertJsonPath('total', 206);
        $this->assertSame(100, substr_count($next->json('groups.0.html'), 'class="monday-item-link"'));
        $this->assertSame('group-a', $next->json('groups.0.id'));
        $last = $this->getJson($next->json('next_page_url'))->assertOk()
            ->assertJsonPath('loaded', 206)->assertJsonPath('next_page_url', null);
        $this->assertSame(6, substr_count($last->json('groups.0.html'), 'class="monday-item-link"'));
        Http::assertNothingSent();
    }

    public function test_batch_urls_preserve_filters_and_search_the_whole_board(): void
    {
        $this->signIn();
        [$board] = $this->seedMirror();
        $this->seedParents($board, 105, ['state' => 'archived', 'deleted_at' => now()]);
        $response = $this->get(route('admin.monday.boards.show', [
            'board' => $board, 'q' => 'Elemento', 'group' => 'group-a', 'state' => 'archived',
        ]))->assertOk();
        $items = $response->viewData('items');
        $this->assertSame(105, $items->total());
        parse_str(parse_url($items->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertSame(['q' => 'Elemento', 'group' => 'group-a', 'state' => 'archived', 'page' => '2'], $query);
        $next = $this->getJson($items->nextPageUrl())->assertOk()->assertJsonPath('loaded', 105);
        $this->assertSame(5, substr_count($next->json('groups.0.html'), 'class="monday-item-link"'));

        $this->getJson(route('admin.monday.boards.show', ['board' => $board, 'q' => 'Elemento 104', 'state' => 'archived']))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('next_page_url', null);
    }

    public function test_subitem_previews_are_bounded_per_parent_but_search_includes_all_subitems(): void
    {
        $this->signIn();
        [$board, $first] = $this->seedMirror();
        $second = MondayItem::create(['id' => '789', 'board_id' => $board->id, 'name' => 'Segundo cliente', 'group_id' => 'group-a']);
        $this->seedSubitems($first, 7);
        $this->seedSubitems($second, 7);
        MondayItemValue::where('item_id', $first->id.'-sub-006')->update(['text' => 'Hallazgo fuera de la vista previa']);

        $response = $this->get(route('admin.monday.boards.show', $board))->assertOk()->assertSee('Ver los 7 subelementos');
        foreach ($response->viewData('items') as $parent) {
            $this->assertSame(7, $parent->subitems_count);
            $this->assertCount(5, $parent->subitems);
            $this->assertArrayNotHasKey('raw_data', $parent->subitems->first()->getAttributes());
        }
        $search = $this->getJson(route('admin.monday.boards.show', ['board' => $board, 'q' => 'Hallazgo fuera']))
            ->assertOk()->assertJsonPath('total', 1);
        $this->assertStringContainsString('Cliente de prueba', $search->json('groups.0.html'));
        $this->assertStringNotContainsString('Segundo cliente', $search->json('groups.0.html'));
    }

    public function test_later_batches_can_fill_groups_that_are_empty_in_the_first_batch(): void
    {
        $this->signIn();
        [$board] = $this->seedMirror();
        MondayGroup::create(['board_id' => $board->id, 'monday_id' => 'group-b', 'title' => 'Segundo grupo']);
        $this->seedParents($board, 105);
        MondayItem::where('board_id', $board->id)->where('id', '>=', 'parent-099')->update(['group_id' => 'group-b']);
        $response = $this->get(route('admin.monday.boards.show', $board))->assertOk();
        $this->assertSame(['group-a'], $response->viewData('items')->pluck('group_id')->unique()->all());
        $response->assertSee('data-monday-group-items="group-b"', false);
        $next = $this->getJson($response->viewData('items')->nextPageUrl())->assertOk()
            ->assertJsonPath('groups.0.id', 'group-b')->assertJsonPath('loaded', 106)->assertJsonPath('next_page_url', null);
        $this->assertSame(6, substr_count($next->json('groups.0.html'), 'class="monday-item-link"'));
    }

    public function test_all_subitems_remain_accessible_in_the_paginated_item_detail(): void
    {
        $this->signIn();
        [, $item] = $this->seedMirror();
        $this->seedSubitems($item, 60);
        $response = $this->get(route('admin.monday.items.show', $item->id))->assertOk();
        $subitems = $response->viewData('subitems');
        $this->assertSame(60, $subitems->total());
        $this->assertCount(50, $subitems);
        $this->assertFalse($subitems->first()->relationLoaded('values'));
        $this->assertArrayNotHasKey('raw_data', $subitems->first()->getAttributes());
        $this->assertSame(1, $subitems->first()->populated_values_count);
        $this->get($subitems->nextPageUrl())->assertOk()->assertViewHas('subitems', fn ($rows) => $rows->count() === 10);
    }

    public function test_items_remain_visible_when_their_group_is_no_longer_available(): void
    {
        $this->signIn();
        [$board, $item] = $this->seedMirror();
        $item->update(['group_id' => 'missing-group']);
        $this->get(route('admin.monday.boards.show', $board))->assertOk()->assertSee('Cliente de prueba');
        $this->getJson(route('admin.monday.boards.show', $board))->assertOk()
            ->assertJsonPath('groups.0.id', 'missing-group')->assertJsonPath('total', 1);
    }

    private function signIn(): void
    {
        $this->withoutMiddleware(Authorize::class);
        Role::create(['name' => 'Cliente']);
        $this->actingAs(User::factory()->create());
    }

    private function seedParents(MondayBoard $board, int $count, array $overrides = []): void
    {
        $rows = [];
        for ($index = 0; $index < $count; $index++) {
            $rows[] = array_merge([
                'id' => 'parent-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'board_id' => $board->id, 'group_id' => 'group-a', 'name' => 'Elemento '.$index, 'state' => 'active',
            ], $overrides);
        }
        MondayItem::insert($rows);
    }

    private function seedSubitems(MondayItem $parent, int $count): void
    {
        $rows = [];
        $values = [];
        for ($index = 0; $index < $count; $index++) {
            $id = $parent->id.'-sub-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT);
            $rows[] = ['id' => $id, 'parent_item_id' => $parent->id, 'board_id' => $parent->board_id, 'name' => 'Subelemento '.$index];
            $values[] = ['item_id' => $id, 'board_id' => $parent->board_id, 'monday_column_id' => 'status', 'text' => 'En proceso'];
        }
        MondayItem::insert($rows);
        MondayItemValue::insert($values);
    }

    private function seedMirror(): array
    {
        $board = MondayBoard::create([
            'id' => '123',
            'name' => 'Operaciones',
            'state' => 'active',
            'workspace_name' => 'Sefar',
            'last_synced_at' => now(),
        ]);
        MondayGroup::create([
            'board_id' => $board->id,
            'monday_id' => 'group-a',
            'title' => 'Solicitudes abiertas',
            'color' => '#579bfc',
        ]);
        MondayColumn::create([
            'board_id' => $board->id,
            'monday_id' => 'status',
            'title' => 'Estado',
            'type' => 'color',
        ]);
        $item = MondayItem::create([
            'id' => '456',
            'board_id' => $board->id,
            'group_id' => 'group-a',
            'name' => 'Cliente de prueba',
            'state' => 'active',
            'last_synced_at' => now(),
        ]);
        MondayItemValue::create([
            'item_id' => $item->id,
            'board_id' => $board->id,
            'monday_column_id' => 'status',
            'type' => 'color',
            'text' => 'En proceso',
            'value' => ['label' => ['style' => ['color' => '#fdab3d']]],
        ]);

        return [$board, $item];
    }
}
