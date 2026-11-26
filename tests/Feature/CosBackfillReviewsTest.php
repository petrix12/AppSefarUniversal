<?php

namespace Tests\Feature;

use App\Services\CosBackfillReviews;
use App\Services\CosPortfolioBackfill;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CosBackfillReviewsTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/sefar-cos-reviews-'.bin2hex(random_bytes(8));
        $this->app->useStoragePath($this->directory);
        config(['app.timezone' => 'America/Bogota']);
        config(['cos_snapshot.backfill_progress_path' => $this->directory.'/private/progress.json']);
        config([
            'logging.channels.single.path' => $this->directory.'/logs/laravel.log',
            'logging.channels.daily.path' => $this->directory.'/logs/laravel.log',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_records_reviews_once_per_user_and_hides_rows_after_the_progress_cursor(): void
    {
        $service = app(CosBackfillReviews::class);
        $state = ['started_at' => '2026-11-26T10:00:00-04:00', 'last_id' => 3];
        $service->record($state, 2, 'conflict');
        $service->record($state, 2, 'unlinked');
        $service->record($state, 4, 'conflict');

        $visible = $service->forRun($state);
        $this->assertCount(1, $visible);
        $this->assertSame(['user_id', 'reason', 'recorded_at'], array_keys($visible[0]));
        $this->assertSame(2, $visible[0]['user_id']);
        $this->assertSame('conflict', $visible[0]['reason']);

        $state['last_id'] = 4;
        $this->assertCount(2, $service->forRun($state));
    }

    public function test_recovers_only_exact_post_start_log_lines_with_confirmed_ids_and_is_idempotent(): void
    {
        $state = [
            'status' => 'completed',
            'started_at' => '2026-11-26T10:00:00-05:00',
            'last_id' => 9,
            'max_id' => 9,
            'hubspot_review' => 2,
            'calculated' => 8,
            'completed_at' => '2026-11-26T11:00:00-04:00',
        ];
        CosPortfolioBackfill::save($state);
        $logPath = $this->directory.'/logs/laravel.log';
        File::ensureDirectoryExists(dirname($logPath));
        File::put($logPath, implode("\n", [
            '[2026-11-26 09:59:59] production.WARNING: COS: revisión pendiente durante la carga inicial de HubSpot. {"user_id":2,"reason":"conflict"}',
            '[2026-11-26 10:01:00] production.WARNING: Otro evento {"user_id":3,"reason":"conflict"}',
            '[2026-11-26 10:02:00] production.WARNING: COS: revisión pendiente durante la carga inicial de HubSpot. {"user_id":4,"reason":"unlinked"} []',
            '[2026-11-26 10:03:00] production.WARNING: COS: revisión pendiente durante la carga inicial de HubSpot. {"user_id":12,"reason":"conflict"}',
        ])."\n");

        $service = app(CosBackfillReviews::class);
        $this->assertSame(1, $service->recoverHistorical($state));
        $this->assertSame(0, $service->recoverHistorical($state));
        $rows = $service->forRun($state);
        $this->assertCount(1, $rows);
        $this->assertSame(4, $rows[0]['user_id']);
        $this->assertSame('unlinked', $rows[0]['reason']);
        $this->assertSame('2026-11-26T10:02:00-05:00', $rows[0]['recorded_at']);

        $this->assertSame(2, CosPortfolioBackfill::state()['hubspot_review']);
        $this->assertSame(8, CosPortfolioBackfill::state()['calculated']);
        $this->assertSame(9, CosPortfolioBackfill::state()['last_id']);
    }

    public function test_recovery_appends_an_earlier_original_timestamp_once_for_an_existing_review(): void
    {
        $started = CarbonImmutable::now()->subMinutes(10);
        $historical = CarbonImmutable::now()->subMinutes(5);
        $state = ['started_at' => $started->toIso8601String(), 'last_id' => 5, 'max_id' => 5];
        $service = app(CosBackfillReviews::class);
        $service->record($state, 5, 'conflict');

        $logPath = $this->directory.'/logs/laravel.log';
        File::ensureDirectoryExists(dirname($logPath));
        File::put($logPath, '['.$historical->format('Y-m-d H:i:s').'] production.WARNING: COS: revisión pendiente durante la carga inicial de HubSpot. {"user_id":5,"reason":"conflict"}'."\n");
        touch($logPath, $started->getTimestamp() + 1);

        $this->assertSame(0, $service->recoverHistorical($state));
        $this->assertSame(0, $service->recoverHistorical($state));
        $this->assertSame($historical->toIso8601String(), $service->forRun($state)[0]['recorded_at']);
        $this->assertSame(2, substr_count(File::get($this->directory.'/private/progress.json.reviews.ndjson'), "\n"));
    }

    public function test_recover_reviews_command_preserves_completed_cursor_and_counts(): void
    {
        $state = [
            'status' => 'completed', 'started_at' => '2026-11-26T10:00:00-05:00',
            'last_id' => 9, 'max_id' => 9, 'hubspot_review' => 1, 'calculated' => 8,
            'completed_at' => '2026-11-26T11:00:00-04:00',
        ];
        CosPortfolioBackfill::save($state);
        $logPath = $this->directory.'/logs/laravel-2026-11-26.log';
        File::ensureDirectoryExists(dirname($logPath));
        File::put($logPath, '[2026-11-26 10:15:00] production.WARNING: COS: revisión pendiente durante la carga inicial de HubSpot. {"user_id":5,"reason":"conflict"}' ."\n");

        $this->artisan('cos:backfill-local --recover-reviews')->assertSuccessful();
        $after = CosPortfolioBackfill::state();
        $this->assertSame(9, $after['last_id']);
        $this->assertSame(8, $after['calculated']);
        $this->assertSame(1, $after['hubspot_review']);
        $this->assertSame(1, $after['hubspot_review_recovered']);
        $this->assertSame(5, app(CosBackfillReviews::class)->forRun($after)[0]['user_id']);
    }
}
