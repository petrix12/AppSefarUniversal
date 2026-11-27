<?php

namespace Tests\Feature;

use App\Jobs\RefreshClientCosSnapshot;
use App\Models\User;
use App\Services\ClientCosSnapshotService;
use App\Services\CosHelperService;
use App\Services\HubspotService;
use App\Services\TeamleaderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClientCosPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'sync', 'cache.default' => 'array']);
        Cache::flush();
        Http::preventStrayRequests();
        $this->mock(HubspotService::class);
        $this->mock(TeamleaderService::class);
        $this->mock(CosHelperService::class, function ($mock) {
            $mock->shouldReceive('get')->andReturn(['Española Sefardi' => [], 'Portuguesa Sefardi' => []]);
        });
        Schema::create('negocios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('servicio_solicitado')->nullable();
            $table->string('servicio_solicitado2')->nullable();
        });
        Schema::create('monday_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('data')->nullable();
        });
        (require database_path('migrations/2025_12_03_205525_create_jobs_table.php'))->up();
    }

    public function test_fresh_snapshot_does_not_sync_or_queue_work(): void
    {
        $user = $this->client();
        $user->arraycos_expire = now()->addDay();
        Cache::put('cos.page_refreshed.' . $user->id, true, 300);

        $page = app(ClientCosSnapshotService::class)->forPage($user);

        $this->assertSame($user->arraycos, $page['cos']);
        $this->assertSame(0, DB::table('jobs')->count());
        Http::assertNothingSent();
    }

    public function test_a_cached_pending_process_is_presented_first_without_syncing_or_rewriting_the_snapshot(): void
    {
        $user = $this->client();
        $saved = [
            ['servicio' => 'Española Sefardi', 'warning' => null, 'currentStepName' => '¡Nacionalidad concedida!'],
            ['servicio' => 'Portuguesa Sefardi', 'warning' => 'Solicita tu Auditoría de Expedientes'],
        ];
        $user->arraycos = $saved;
        $user->arraycos_expire = now()->addDay();
        Cache::put('cos.page_refreshed.' . $user->id, true, 300);

        $page = app(ClientCosSnapshotService::class)->forPage($user);

        $this->assertSame([$saved[1], $saved[0]], $page['cos']);
        $this->assertSame($saved, $user->arraycos);
        $this->assertSame(0, DB::table('jobs')->count());
        Http::assertNothingSent();
    }

    public function test_expired_snapshot_stays_visible_and_refresh_is_queued_once_even_with_sync_default(): void
    {
        $user = $this->client();
        $user->arraycos_expire = now()->subMinute();

        $page = app(ClientCosSnapshotService::class)->forPage($user);
        app(ClientCosSnapshotService::class)->forPage($user);

        $this->assertSame($user->arraycos, $page['cos']);
        $this->assertSame(1, DB::table('jobs')->where('queue', 'cos-refresh')->count());
        $this->assertSame('cos', (new RefreshClientCosSnapshot($user->id))->connection);
        Http::assertNothingSent();
    }

    public function test_first_load_uses_local_business_and_monday_without_remote_ai(): void
    {
        $user = $this->client();
        $user->arraycos = null;
        DB::table('negocios')->insert([
            'user_id' => $user->id,
            'servicio_solicitado' => 'Española Sefardi',
        ]);
        DB::table('monday_data')->insert([
            'user_id' => $user->id,
            'data' => json_encode([
                'board' => ['name' => 'Análisis'],
                'column_values' => [['id' => 'men__desplegable', 'text' => 'En investigación']],
            ]),
        ]);

        $page = app(ClientCosSnapshotService::class)->forPage($user);

        $this->assertSame('Española Sefardi', $page['cos'][0]['servicio']);
        $this->assertSame('Análisis', $page['monday_data']['mondaydataforAI']['tablero']);
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertNull($user->arraycos_expire);
        Http::assertNothingSent();
    }

    public function test_event_mode_does_not_enqueue_external_refreshes_when_opening_cos(): void
    {
        config(['crm_sync.local_reads_enabled' => true, 'crm_sync.processing_enabled' => true, 'crm_sync.hubspot.enabled' => true,
            'crm_sync.teamleader.enabled' => true, 'services.monday.mirror_enabled' => true]);
        (require database_path('migrations/2026_11_16_120000_create_crm_webhook_mirrors.php'))->up();
        $user = $this->client();
        $user->arraycos_expire = now()->subDay();
        DB::table('negocios')->insert(['user_id' => $user->id, 'servicio_solicitado' => 'Española Sefardi']);
        $page = app(ClientCosSnapshotService::class)->forPage($user);
        $this->assertFalse($page['refresh_pending']);
        $this->assertSame('Española Sefardi', $page['cos'][0]['servicio']);
        $this->assertSame(0, DB::table('jobs')->count());
        Http::assertNothingSent();
    }

    private function client(): User
    {
        $user = new User();
        $user->id = 101;
        $user->arraycos = [['servicio' => 'Española Sefardi', 'currentStepName' => 'Informe cargado']];

        return $user;
    }
}
