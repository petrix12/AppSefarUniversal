<?php

namespace Tests\Unit;

use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignEvent;
use App\Models\MarketingCampaignRecipient;
use App\Models\MarketingContact;
use App\Models\MarketingList;
use App\Models\MarketingListMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MarketingCampaignDataTest extends TestCase
{
    private object $migration;
    private string $previousConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousConnection = config('database.default');
        config()->set('database.connections.marketing_test', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        config()->set('database.default', 'marketing_test');
        DB::purge('marketing_test');
        Schema::create('users', fn ($table) => $table->id());
        $this->migration = require database_path('migrations/2026_09_29_120000_create_marketing_campaign_tables.php');
        $this->migration->up();
    }

    protected function tearDown(): void
    {
        $this->migration->down();
        DB::disconnect('marketing_test');
        config()->set('database.default', $this->previousConnection);
        parent::tearDown();
    }

    public function test_campaign_analytics_use_unique_recipient_states_and_keep_event_totals(): void
    {
        $contact = MarketingContact::create(['email' => 'ana@example.org', 'attributes' => ['country' => 'VE']]);
        $list = MarketingList::create(['name' => 'Leads', 'source_type' => 'csv']);
        MarketingListMember::create(['marketing_list_id' => $list->id, 'marketing_contact_id' => $contact->id]);
        $campaign = MarketingCampaign::create([
            'name' => 'Octubre', 'subject' => 'Novedades', 'from_email' => 'campanas@example.org',
            'body_html' => '<p>Hola</p>', 'status' => MarketingCampaign::STATUS_SENT, 'marketing_list_id' => $list->id,
        ]);
        $recipient = MarketingCampaignRecipient::create([
            'marketing_campaign_id' => $campaign->id, 'marketing_contact_id' => $contact->id,
            'email' => $contact->email, 'tracking_token' => 'b5fb2a6d-4b2b-4c0e-8dbb-d0d06650b4c0',
            'sent_at' => now(), 'delivered_at' => now(), 'opened_at' => now(), 'clicked_at' => now(),
        ]);
        MarketingCampaignEvent::create(['marketing_campaign_id' => $campaign->id, 'marketing_campaign_recipient_id' => $recipient->id, 'event_type' => 'open', 'occurred_at' => now()]);
        MarketingCampaignEvent::create(['marketing_campaign_id' => $campaign->id, 'marketing_campaign_recipient_id' => $recipient->id, 'event_type' => 'open', 'occurred_at' => now()]);

        $analytics = $campaign->analytics();

        $this->assertSame(1, $analytics['total']);
        $this->assertSame(1, $analytics['sent']);
        $this->assertSame(1, $analytics['opened']);
        $this->assertSame(1, $analytics['clicked']);
        $this->assertSame(2, $analytics['open_events']);
    }
}
