<?php

namespace Tests\Feature;

use App\Services\CrmMirrorSource;
use App\Services\HubspotService;
use App\Services\TeamleaderService;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class CrmMirrorSourceTest extends TestCase
{
    public function test_hubspot_association_reader_follows_every_page_and_deduplicates_ids(): void
    {
        $client = Mockery::mock();
        $client->shouldReceive('apiRequest')->once()->with(Mockery::on(fn ($args) => $args['path'] === '/crm/v4/objects/contacts/10/associations/deals' && ! isset($args['qs']['after'])))
            ->andReturn(new Response(200, [], json_encode(['results' => [['toObjectId' => 20]], 'paging' => ['next' => ['after' => 'next']]])));
        $client->shouldReceive('apiRequest')->once()->with(Mockery::on(fn ($args) => $args['qs']['after'] === 'next'))
            ->andReturn(new Response(200, [], json_encode(['results' => [['toObjectId' => 20], ['toObjectId' => 21]]])));
        $service = Mockery::mock(HubspotService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        (new \ReflectionProperty(HubspotService::class, 'hubspot'))->setValue($service, $client);
        $this->assertSame(['20', '21'], $service->webhookAssociations('contacts', '10', 'deals'));
    }

    public function test_invalid_association_response_is_not_treated_as_a_lead(): void
    {
        $client = Mockery::mock();
        $client->shouldReceive('apiRequest')->once()->andReturn(new Response(200, [], '{}'));
        $service = Mockery::mock(HubspotService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        (new \ReflectionProperty(HubspotService::class, 'hubspot'))->setValue($service, $client);
        $this->expectException(\UnexpectedValueException::class);
        $service->webhookAssociations('contacts', '10', 'deals');
    }

    public function test_teamleader_contact_deals_follow_pagination_and_keep_won_and_lost_deals(): void
    {
        $hubspot = Mockery::mock(HubspotService::class);
        $teamleader = Mockery::mock(TeamleaderService::class);
        $firstPage = array_map(fn ($id) => ['id' => (string) $id], range(1, 100));
        $teamleader->shouldReceive('listDealsByContactId')->once()->with('contact', 1, 100)->andReturn(['data' => $firstPage]);
        $teamleader->shouldReceive('listDealsByContactId')->once()->with('contact', 2, 100)->andReturn(['data' => [['id' => '101']]]);
        $teamleader->shouldReceive('webhookObject')->times(101)->andReturnUsing(fn ($entity, $id) => ['id' => $id, 'status' => $id === '101' ? 'lost' : 'won']);
        $deals = (new CrmMirrorSource($hubspot, $teamleader))->contactDeals('teamleader', 'contact');
        $this->assertCount(101, $deals);
        $this->assertSame('lost', $deals[100]['status']);
    }

    public function test_teamleader_strict_read_distinguishes_deletion_from_outages(): void
    {
        config(['services.teamleader.min_request_gap_ms' => 0]);
        $service = Mockery::mock(TeamleaderService::class)->makePartial();
        $service->shouldReceive('getAccessToken')->twice()->andReturn('test-token');
        Http::fakeSequence()->push([], 404)->push([], 503);
        $id = '76c9d03c-ec5c-4b21-9fc6-7ffee488b12d';
        $this->assertNull($service->webhookObject('contact', $id));
        $this->expectException(\App\Exceptions\TeamleaderServiceUnavailableException::class);
        $service->webhookObject('contact', $id);
    }

    public function test_a_deal_whose_customer_is_a_company_does_not_import_its_contacts(): void
    {
        $hubspot = Mockery::mock(HubspotService::class);
        $teamleader = Mockery::mock(TeamleaderService::class);
        $teamleader->shouldReceive('webhookObject')->once()->with('deal', 'deal-1')
            ->andReturn(['lead' => ['customer' => ['id' => 'company-1', 'type' => 'company']]]);
        $this->assertSame([], (new CrmMirrorSource($hubspot, $teamleader))->dealContacts('teamleader', 'deal-1'));
    }
}
