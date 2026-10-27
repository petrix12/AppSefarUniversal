<?php

namespace Tests\Feature;

use App\Jobs\Monday\SyncMondayBoardJob;
use App\Models\MondayBoard;
use App\Models\MondayGroup;
use App\Models\MondayItem;
use App\Models\MondayItemValue;
use App\Models\MondaySyncRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResetMondayMirrorCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_only_clears_the_local_mirror_tables(): void
    {
        config()->set('services.monday.mirror_enabled', false);
        DB::table('monday_field_mappings')->insert([
            'local_field_key' => 'estado',
            'board_id' => '10',
            'monday_column_id' => 'status',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $board = MondayBoard::create(['id' => '10', 'name' => 'Ventas', 'state' => 'active']);
        MondayGroup::create(['board_id' => $board->id, 'monday_id' => 'new', 'title' => 'Nuevos']);
        $item = MondayItem::create(['id' => '20', 'board_id' => $board->id, 'name' => 'Cliente']);
        MondayItemValue::create([
            'item_id' => $item->id,
            'board_id' => $board->id,
            'monday_column_id' => 'status',
            'text' => 'Nuevo',
        ]);
        MondaySyncRun::start('account');
        SyncMondayBoardJob::dispatch('10')->onConnection('monday')->onQueue('monday-sync');
        DB::table('failed_jobs')->insert([
            'uuid' => 'a51d6dd0-229f-4e7c-8b52-e617d14157e0',
            'connection' => 'monday',
            'queue' => 'monday-sync',
            'payload' => json_encode(['displayName' => SyncMondayBoardJob::class], JSON_THROW_ON_ERROR),
            'exception' => 'test',
            'failed_at' => now(),
        ]);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('failed_jobs', 1);

        $this->artisan('monday:reset', ['--force' => true])->assertSuccessful();

        foreach ([
            'monday_boards',
            'monday_groups',
            'monday_items',
            'monday_item_values',
            'monday_sync_runs',
            'monday_webhook_events',
        ] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseCount('monday_field_mappings', 1);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);

        SyncMondayBoardJob::dispatch('10')->onConnection('monday')->onQueue('monday-sync');
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_it_refuses_to_reset_while_the_mirror_worker_is_enabled(): void
    {
        config()->set('services.monday.mirror_enabled', true);
        MondayBoard::create(['id' => '10', 'name' => 'Ventas', 'state' => 'active']);

        $this->artisan('monday:reset', ['--force' => true])->assertFailed();

        $this->assertDatabaseHas('monday_boards', ['id' => '10']);
    }
}
