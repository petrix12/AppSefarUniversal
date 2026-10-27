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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MondayMirrorUiTest extends TestCase
{
    use RefreshDatabase;

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
