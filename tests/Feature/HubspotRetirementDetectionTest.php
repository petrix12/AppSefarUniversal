<?php

namespace Tests\Feature;

use App\Services\HubspotService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use HubSpot\Factory;
use Tests\TestCase;

class HubspotRetirementDetectionTest extends TestCase
{
    public function test_the_retired_deal_page_preserves_contact_associations_and_pagination(): void
    {
        $history = [];
        $handler = HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'results' => [['id' => '456', 'properties' => ['el_cliente_se_encuentra_retirado' => 'true']]],
                'paging' => ['next' => ['after' => '300']],
            ])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'status' => 'COMPLETE', 'results' => [['from' => ['id' => '456'], 'to' => [['id' => '123', 'type' => 'deal_to_contact']]]],
            ])),
        ]));
        $handler->push(Middleware::history($history));
        $service = $this->service(new Client(['handler' => $handler]));

        $data = $service->retirementPage('deals', '200');

        $this->assertSame(['123'], $data['results'][0]['contact_ids']);
        $this->assertSame('300', $data['paging']['next']['after']);
        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame('true', $body['filterGroups'][0]['filters'][0]['value']);
        $this->assertSame('200', $body['after']);
        $this->assertStringEndsWith('/crm/v3/associations/deals/contacts/batch/read', (string) $history[1]['request']->getUri());
    }

    public function test_a_contact_page_does_not_require_a_deal_association_request(): void
    {
        $service = $this->service(new Client(['handler' => new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"results":[{"id":"123","properties":{"retirado":"Sí"}}]}'),
        ])]));
        $data = $service->retirementPage('contacts');
        $this->assertSame(['123'], $data['results'][0]['contact_ids']);
    }

    public function test_malformed_pages_are_not_treated_as_an_empty_successful_scan(): void
    {
        $service = $this->service(new Client(['handler' => new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"message":"unexpected"}'),
        ])]));
        $this->expectException(\RuntimeException::class);
        $service->retirementPage('contacts');
    }

    private function service(Client $client): HubspotService
    {
        return new class($client) extends HubspotService {
            public function __construct(Client $client)
            {
                $this->hubspot = Factory::createWithAccessToken('test-token', $client);
            }
        };
    }
}
