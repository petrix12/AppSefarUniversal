<?php

namespace Tests\Feature;

use App\Jobs\Monday\ProcessMondayWebhookEventJob;
use App\Models\MondayBoard;
use App\Services\MondayWebhookSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MondayWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.monday.webhook_secret', 'mirror-secret');
        config()->set('services.monday.token', 'monday-token');
        config()->set('app.url', 'https://sefar.test');
        URL::forceRootUrl('https://sefar.test');
        URL::forceScheme('https');
    }

    public function test_it_answers_the_monday_challenge_only_with_the_shared_secret(): void
    {
        $this->postJson('/api/webhooks/monday?token=wrong', ['challenge' => 'abc'])->assertUnauthorized();

        $this->postJson('/api/webhooks/monday?token=mirror-secret', ['challenge' => 'abc'])
            ->assertOk()
            ->assertExactJson(['challenge' => 'abc']);
    }

    public function test_it_deduplicates_deliveries_and_dispatches_one_job(): void
    {
        Queue::fake();
        $payload = ['event' => [
            'triggerUuid' => 'same-event',
            'type' => 'update_column_value',
            'boardId' => 10,
            'pulseId' => 20,
        ]];

        $this->postJson('/api/webhooks/monday?token=mirror-secret', $payload)->assertOk();
        $this->postJson('/api/webhooks/monday?token=mirror-secret', $payload)->assertOk();

        $this->assertDatabaseCount('monday_webhook_events', 1);
        Queue::assertPushed(ProcessMondayWebhookEventJob::class, 1);
    }

    public function test_it_only_creates_missing_board_subscriptions(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->data()['query'], 'MirrorWebhooks')) {
                return Http::response(['data' => ['webhooks' => [[
                    'id' => '1',
                    'board_id' => '10',
                    'event' => 'change_name',
                    'config' => null,
                ]]]]);
            }

            return Http::response(['data' => ['create_webhook' => [
                'id' => '2',
                'board_id' => '10',
            ]]]);
        });

        $created = app(MondayWebhookSubscriptionService::class)
            ->ensure('10', ['change_name', 'create_item']);

        $this->assertSame(['create_item'], $created);
        Http::assertSentCount(2);
    }

    public function test_it_skips_a_known_internal_subitems_board_without_calling_monday(): void
    {
        MondayBoard::create([
            'id' => '10031441047',
            'name' => 'Subitems of board',
            'state' => 'active',
            'object_type' => 'sub_items_board',
        ]);
        Http::fake();

        $subscriptions = app(MondayWebhookSubscriptionService::class);
        $created = $subscriptions->ensure('10031441047');

        $this->assertSame([], $created);
        $this->assertTrue($subscriptions->skippedAsSubitemsBoard('10031441047'));
        Http::assertNothingSent();
    }

    public function test_it_recognizes_mondays_subitems_board_error_and_continues(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->data()['query'], 'MirrorWebhooks')) {
                return Http::response(['data' => ['webhooks' => []]]);
            }

            return Http::response(['errors' => [[
                'message' => "Creating webhook on subitems board isn't allowed",
            ]]], 200);
        });

        $subscriptions = app(MondayWebhookSubscriptionService::class);
        $created = $subscriptions->ensure('10031441047', ['change_column_value']);

        $this->assertSame([], $created);
        $this->assertTrue($subscriptions->skippedAsSubitemsBoard('10031441047'));
        Http::assertSentCount(2);
    }
}
