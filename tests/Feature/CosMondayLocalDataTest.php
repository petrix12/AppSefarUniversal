<?php

namespace Tests\Feature;

use App\Exceptions\CosSourceRefreshException;
use App\Jobs\RefreshClientCosSnapshot;
use App\Models\MondayBoard;
use App\Models\MondayColumn;
use App\Models\MondayData;
use App\Models\MondayItem;
use App\Models\MondayItemValue;
use App\Models\Negocio;
use App\Models\User;
use App\Services\ClientCosSnapshotService;
use App\Services\CosHelperService;
use App\Services\CosMondayLookup;
use App\Services\HubspotService;
use App\Services\TeamleaderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CosMondayLocalDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'services.monday.mirror_reads' => true,
            'services.openrouter.key' => null, 'cos_snapshot.monday_search_boards' => [11 => 'ANALISIS']]);
        Cache::flush();
        Http::preventStrayRequests();
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2014_10_12_000000_create_users_table.php',
            'database/migrations/2021_03_08_173429_create_permission_tables.php',
            'database/migrations/2025_01_07_115948_create_monday_data_table.php',
            'database/migrations/2026_10_27_180000_create_monday_mirror_tables.php',
            'database/migrations/2026_10_27_190000_add_type_to_monday_boards_table.php',
        ]])->assertExitCode(0);
        Schema::table('users', function (Blueprint $table) {
            $table->string('monday_id')->nullable();
            $table->json('arraycos')->nullable();
            $table->timestamp('arraycos_expire')->nullable();
            $table->boolean('cosready')->default(false);
        });
        Schema::create('negocios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('servicio_solicitado')->nullable();
            $table->timestamps();
        });
        Role::findOrCreate('Cliente', 'web');
        $this->mock(HubspotService::class);
        $this->mock(TeamleaderService::class);
        $this->mock(CosHelperService::class, fn ($mock) => $mock->shouldReceive('get')->andReturn([
            'Española Sefardi' => ['genealogico' => [['paso' => 0, 'nombre_corto' => 'Inicio', 'nombre_largo' => 'Inicio']], 'juridico' => []],
        ]));
    }

    public function test_forced_cos_calculation_uses_the_local_item_without_calling_monday(): void
    {
        $user = $this->client();
        $this->mirror();
        \Monday::shouldReceive('customQuery')->never();
        $result = app(ClientCosSnapshotService::class)->refresh($user, false, true);
        $this->assertSame('local_mirror', $result['monday']['source']);
        $this->assertSame('ANALISIS', $result['monday']['board']);
        $this->assertFalse($result['monday']['fallback']);
        $this->assertNotNull($result['monday']['synced_at']);
        $this->assertSame('Española Sefardi', $result['cos'][0]['servicio']);
        $this->assertSame('Inicio de Investigación', $result['cos'][0]['description']);
        $this->assertEquals($result['cos'], $user->fresh()->arraycos);
        $details = json_decode(MondayData::where('user_id', $user->id)->value('data'), true);
        $this->assertSame('En investigación', collect($details['column_values'])->firstWhere('id', 'men__desplegable')['text']);
        Http::assertNothingSent();
    }

    public function test_client_without_a_monday_id_is_found_locally_using_the_exact_passport_link(): void
    {
        $user = $this->client(['monday_id' => null]);
        $this->mirror('111', '11', 'ABC1234');
        $this->mirror();
        \Monday::shouldReceive('customQuery')->never();
        $result = app(ClientCosSnapshotService::class)->refresh($user, false, true);
        $this->assertSame('456', $user->fresh()->monday_id);
        $this->assertSame('local_mirror', $result['monday']['source']);
        Http::assertNothingSent();
    }

    public function test_local_search_preserves_board_priority_and_does_not_trust_an_incomplete_search(): void
    {
        $user = $this->client(['monday_id' => null]);
        config(['cos_snapshot.monday_search_boards' => [22 => 'Primero', 11 => 'ANALISIS']]);
        $this->mirror();
        $lookup = app(CosMondayLookup::class);
        $this->assertNull($lookup->localFor($user));
        $this->mirror('789', '22');
        $this->assertSame('789', $lookup->localFor($user)['item']['id']);
        $this->assertNull($user->fresh()->monday_id);
    }

    public function test_local_reads_see_webhook_changes_despite_the_old_cos_data_cache(): void
    {
        $user = $this->client();
        $this->mirror();
        \Monday::shouldReceive('customQuery')->never();
        $service = app(ClientCosSnapshotService::class);
        $service->refresh($user, false, true);
        MondayItemValue::where('item_id', '456')->where('monday_column_id', 'men__desplegable')->update(['text' => 'Genealogía aprobada']);
        $service->refresh($user, false, true);
        $this->assertStringContainsString('Genealogía aprobada', json_decode(MondayData::where('user_id', $user->id)->value('data'), true)['column_values'][1]['text']);
        Http::assertNothingSent();
    }

    public function test_page_uses_the_replica_and_queues_recalculation_when_local_labels_change(): void
    {
        Queue::fake();
        $user = $this->client();
        $this->storeSnapshot($user);
        $this->mirror();
        Cache::put('cos.page_refreshed.'.$user->id, true, 300);
        \Monday::shouldReceive('customQuery')->never();
        $page = app(ClientCosSnapshotService::class)->forPage($user);
        $this->assertSame('En investigación', $page['monday_data']['mondaydataforAI']['etiquetas']);
        $this->assertSame('local_mirror', $page['monday_data']['source']);
        Queue::assertPushed(RefreshClientCosSnapshot::class);
        Http::assertNothingSent();
    }

    public function test_deleted_local_items_are_not_resurrected_from_an_old_snapshot(): void
    {
        $user = $this->client();
        $this->storeSnapshot($user);
        $this->mirror()->delete();
        \Monday::shouldReceive('customQuery')->never();
        $result = app(ClientCosSnapshotService::class)->refresh($user, false, true);
        $this->assertSame('local_mirror', $result['monday']['source']);
        $this->assertFalse($result['monday']['has_data']);
        $this->assertSame('null', MondayData::where('user_id', $user->id)->value('data'));
    }

    public function test_first_page_can_calculate_the_status_directly_from_the_replica(): void
    {
        Queue::fake();
        $user = $this->client(['arraycos' => null]);
        $this->mirror();
        \Monday::shouldReceive('customQuery')->never();
        $page = app(ClientCosSnapshotService::class)->forPage($user);
        $this->assertSame('Inicio de Investigación', $page['cos'][0]['description']);
        $this->assertNull($user->fresh()->arraycos);
        Http::assertNothingSent();
    }

    public function test_a_complete_local_search_can_confirm_absence_without_contacting_monday(): void
    {
        $user = $this->client(['monday_id' => null]);
        $this->storeSnapshot($user);
        $this->mirror('789', '11', 'another-passport');
        \Monday::shouldReceive('customQuery')->never();
        $result = app(ClientCosSnapshotService::class)->refresh($user, false, true);
        $this->assertSame('local_mirror', $result['monday']['source']);
        $this->assertFalse($result['monday']['has_data']);
    }

    public function test_a_missing_local_item_can_still_be_read_from_the_api(): void
    {
        $user = $this->client();
        \Monday::shouldReceive('customQuery')->once()->withArgs(fn ($query) => str_contains($query, 'items(ids:'))
            ->andReturn(['items' => [$this->snapshot()]]);
        \Monday::shouldReceive('customQuery')->once()->withArgs(fn ($query) => str_contains($query, 'boards(ids:'))
            ->andReturn(['boards' => [['columns' => []]]]);
        $result = app(ClientCosSnapshotService::class)->refresh($user, false, true);
        $this->assertSame('api', $result['monday']['source']);
        $this->assertFalse($result['monday']['fallback']);
    }

    public function test_an_api_failure_uses_the_last_valid_snapshot_and_retries_soon(): void
    {
        $user = $this->client();
        $stored = $this->storeSnapshot($user);
        \Monday::shouldReceive('customQuery')->once()->andThrow(new \RuntimeException('Monday unavailable'));
        $result = app(ClientCosSnapshotService::class)->refresh($user, false, true);
        $this->assertSame('stored_snapshot_fallback', $result['monday']['source']);
        $this->assertTrue($result['monday']['fallback']);
        $this->assertSame($stored->updated_at->toIso8601String(), $result['monday']['synced_at']);
        $this->assertTrue($user->fresh()->arraycos_expire->lessThanOrEqualTo(now()->addMinutes(5)));
        $this->assertTrue($stored->updated_at->equalTo($stored->fresh()->updated_at));
        Http::assertNothingSent();
    }

    #[DataProvider('unusableSnapshots')]
    public function test_an_api_failure_without_usable_local_data_preserves_the_previous_cos(?array $overrides): void
    {
        $user = $this->client();
        $previous = $user->arraycos;
        $expiry = $user->arraycos_expire;
        if ($overrides !== null) $this->storeSnapshot($user, $overrides);
        \Monday::shouldReceive('customQuery')->once()->andThrow(new \RuntimeException('Monday unavailable'));
        try {
            app(ClientCosSnapshotService::class)->refresh($user, false, true);
            $this->fail('A different Monday association cannot replace the failed refresh.');
        } catch (CosSourceRefreshException $exception) {
            $this->assertSame($previous, $user->fresh()->arraycos);
            $this->assertTrue($expiry->equalTo($user->fresh()->arraycos_expire));
        }
    }

    public static function unusableSnapshots(): array
    {
        return [
            'no copy' => [null],
            'old association' => [['id' => 'another-client']],
            'incomplete copy' => [['board' => null, 'column_values' => null]],
        ];
    }

    public function test_local_cos_worker_does_not_fetch_the_optional_monday_user_directory(): void
    {
        Queue::fake();
        $user = $this->client();
        $snapshots = $this->mock(ClientCosSnapshotService::class, fn ($mock) => $mock->shouldReceive('get')->once()
            ->andReturn(['monday' => ['source' => 'local_mirror']]));
        \Monday::shouldReceive('customQuery')->never();
        (new RefreshClientCosSnapshot($user->id, true))->handle($snapshots, app(HubspotService::class));
        $this->assertTrue(Cache::has('cos.page_refreshed.'.$user->id));
        Queue::assertPushed(\App\Jobs\Monday\RefreshMondayUsersJob::class);
        Http::assertNothingSent();
    }

    private function client(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'passport' => 'ABC123', 'monday_id' => '456',
            'arraycos' => [['servicio' => 'Española Sefardi', 'currentStepName' => 'Anterior']],
            'arraycos_expire' => now()->addDay(),
        ], $overrides));
        Negocio::create(['user_id' => $user->id, 'servicio_solicitado' => 'Española Sefardi']);
        return $user;
    }

    private function mirror(string $id = '456', string $boardId = '11', string $passport = 'ABC123'): MondayItem
    {
        MondayBoard::firstOrCreate(['id' => $boardId], ['name' => 'ANALISIS', 'state' => 'active', 'last_synced_at' => now()->subMinute()]);
        MondayColumn::firstOrCreate(['board_id' => $boardId, 'monday_id' => 'men__desplegable'], ['title' => 'Etiquetas', 'type' => 'dropdown']);
        $item = MondayItem::create(['id' => $id, 'board_id' => $boardId, 'name' => 'Expediente', 'state' => 'active', 'last_synced_at' => now()->subMinute()]);
        foreach (['enlace' => 'https://app.sefaruniversal.com/tree/'.$passport, 'men__desplegable' => 'En investigación'] as $column => $text) {
            MondayItemValue::create(['item_id' => $id, 'board_id' => $boardId, 'monday_column_id' => $column, 'text' => $text]);
        }
        return $item;
    }

    private function snapshot(): array
    {
        return ['id' => '456', 'name' => 'Expediente', 'board' => ['id' => '11', 'name' => 'ANALISIS'],
            'column_values' => [['id' => 'men__desplegable', 'text' => 'Etiqueta anterior']]];
    }

    private function storeSnapshot(User $user, array $overrides = []): MondayData
    {
        return MondayData::create(['user_id' => $user->id, 'data' => json_encode(array_merge($this->snapshot(), $overrides)), 'updated_at' => now()->subDay()]);
    }
}
