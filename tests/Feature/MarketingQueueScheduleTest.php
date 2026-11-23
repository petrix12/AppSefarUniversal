<?php

namespace Tests\Feature;

use App\Console\Kernel;
use Illuminate\Console\Scheduling\Event;
use Tests\TestCase;

class MarketingQueueScheduleTest extends TestCase
{
    public function test_marketing_worker_does_not_start_without_explicit_activation(): void
    {
        config()->set('marketing.worker_enabled', false);
        $this->configureCredentials();

        $this->assertFalse($this->workerEvent()->filtersPass($this->app));
    }

    public function test_activated_worker_requires_ses_credentials(): void
    {
        config()->set('marketing.worker_enabled', true);
        $this->configureCredentials();
        config()->set('marketing.ses.secret', null);

        $this->assertFalse($this->workerEvent()->filtersPass($this->app));
    }

    public function test_activated_worker_uses_the_campaign_queue_with_bounded_background_processing(): void
    {
        config()->set([
            'marketing.worker_enabled' => true,
            'marketing.queue_connection' => 'database',
            'marketing.queue_name' => 'marketing',
        ]);
        $this->configureCredentials();

        $event = $this->workerEvent();

        $this->assertTrue($event->filtersPass($this->app));
        $this->assertTrue($event->runInBackground);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertStringContainsString('queue:work', $event->command);
        $this->assertStringContainsString('database', $event->command);
        $this->assertStringContainsString("--queue='marketing'", $event->command);
        $this->assertStringContainsString('--stop-when-empty', $event->command);
        $this->assertStringNotContainsString('--stop-when-empty=', $event->command);
        $this->assertStringContainsString('--max-jobs=5', $event->command);
        $this->assertStringContainsString('--max-time=50', $event->command);
        $this->assertStringContainsString('--timeout=60', $event->command);
    }

    private function configureCredentials(): void
    {
        config()->set([
            'marketing.ses.key' => 'test-key',
            'marketing.ses.secret' => 'test-secret',
            'marketing.ses.from_email' => 'sender@example.test',
        ]);
    }

    private function workerEvent(): Event
    {
        $events = $this->app->make(Kernel::class)->resolveConsoleSchedule()->events();

        return collect($events)->firstOrFail(fn ($event) => $event->description === 'marketing-queue-worker');
    }
}
