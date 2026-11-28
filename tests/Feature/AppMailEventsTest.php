<?php

namespace Tests\Feature;

use App\Models\{AppMailLog, Setting};
use App\Services\{AppMailEvents, AppMailSettings, SnsMessageVerifier};
use Illuminate\Support\Facades\{DB, Http};
use Tests\TestCase;

class AppMailEventsTest extends TestCase
{
    private string $topic = 'arn:aws:sns:us-east-1:123456789012:transactional';

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'app_mail.ses_sns_topic_arns' => [$this->topic], 'marketing.ses_sns_topic_arns' => []]);
        Http::preventStrayRequests();
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2026_05_07_100000_create_settings_table.php',
            'database/migrations/2026_11_27_190000_create_app_mail_logs_table.php',
        ]])->assertExitCode(0);
    }

    private function log(): AppMailLog
    {
        return AppMailLog::create(['provider' => 'ses', 'from_email' => 'app@example.test', 'recipients' => ['client@example.test'],
            'status' => 'accepted', 'provider_message_id' => 'ses-id', 'is_test' => true, 'accepted_at' => now()]);
    }

    private function event(AppMailLog $log, string $type): array
    {
        return ['eventType' => $type, 'mail' => ['messageId' => 'ses-id', 'tags' => ['app_mail_log_id' => [(string) $log->id]],
            'timestamp' => '2026-11-27T12:00:00Z'], strtolower($type) => ['timestamp' => '2026-11-27T12:00:01Z']];
    }

    public function test_signed_app_events_are_correlated_without_entering_the_campaign_pipeline(): void
    {
        $log = $this->log();
        $payload = ['Type' => 'Notification', 'MessageId' => 'sns-delivery', 'TopicArn' => $this->topic,
            'Message' => json_encode($this->event($log, 'Delivery'))];
        $this->mock(SnsMessageVerifier::class)->shouldReceive('verify')->with($payload, [$this->topic])->once()->andReturn(true);
        $this->postJson('/api/marketing/ses-events', $payload)->assertOk();
        $this->assertSame('delivered', $log->fresh()->status);
        $this->assertNotNull($log->fresh()->delivered_at);
        $this->assertDatabaseCount('app_mail_events', 1);
    }

    public function test_duplicate_and_out_of_order_events_preserve_bounces_and_complaints(): void
    {
        $log = $this->log(); $events = app(AppMailEvents::class);
        $events->record($this->event($log, 'Bounce'), 'bounce');
        $events->record($this->event($log, 'Delivery'), 'delivery');
        $this->assertSame('bounced', $log->fresh()->status);
        $events->record($this->event($log, 'Complaint'), 'complaint');
        $events->record($this->event($log, 'Delivery'), 'delivery');
        $this->assertSame('complained', $log->fresh()->status);
        $this->assertDatabaseCount('app_mail_events', 3);
    }

    public function test_invalid_signatures_and_unallowed_topics_cannot_modify_logs(): void
    {
        $log = $this->log();
        $this->postJson('/api/marketing/ses-events', ['Type' => 'Notification', 'TopicArn' => 'other', 'MessageId' => 'fake',
            'Message' => json_encode($this->event($log, 'Complaint'))])->assertForbidden();
        Http::assertNothingSent();
        $this->assertSame('accepted', $log->fresh()->status); $this->assertDatabaseCount('app_mail_events', 0);
    }

    public function test_events_for_a_different_message_or_provider_are_ignored(): void
    {
        $log = $this->log(); $event = $this->event($log, 'Complaint');
        $event['mail']['messageId'] = 'another-message';
        app(AppMailEvents::class)->record($event, 'fake');
        $log->update(['provider' => 'default']);
        app(AppMailEvents::class)->record($this->event($log, 'Complaint'), 'another');
        $this->assertSame('accepted', $log->fresh()->status); $this->assertDatabaseCount('app_mail_events', 0);
    }

    public function test_a_bounced_test_cannot_be_used_as_proof_to_activate_ses(): void
    {
        $log = $this->log(); $settings = app(AppMailSettings::class);
        $settings->recordTest($log); $this->assertNotNull($settings->tested());
        app(AppMailEvents::class)->record($this->event($log, 'Bounce'), 'bounce');
        $this->assertNull($settings->tested());
    }
}
