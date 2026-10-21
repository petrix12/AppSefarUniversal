<?php

namespace Tests\Feature;

use App\Services\TeamleaderService;
use App\Exceptions\TeamleaderServiceUnavailableException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeamleaderForcedRefreshTest extends TestCase
{
    private const CUSTOMER_ID = '12345678-1234-1234-1234-123456789012';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.teamleader.min_request_gap_ms' => 0]);
        Http::preventStrayRequests();
        $this->partialMock(TeamleaderService::class, fn ($mock) => $mock
            ->shouldReceive('getAccessToken')->andReturn('test-token'));
    }

    public function test_forced_project_fetch_reads_all_pages(): void
    {
        $firstPage = array_map(fn ($id) => ['id' => (string) $id], range(1, 100));
        Http::fake(['api.focus.teamleader.eu/projects.list' => Http::sequence()
            ->push(['data' => $firstPage])->push(['data' => [['id' => '101']]])]);

        $projects = app(TeamleaderService::class)->listProjectsByCustomerId(self::CUSTOMER_ID, true);

        $this->assertCount(101, $projects);
        $this->assertSame('101', $projects[100]['id']);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['page']['number'] === 2);
    }

    public function test_forced_project_fetch_propagates_api_failures(): void
    {
        Http::fake(['api.focus.teamleader.eu/projects.list' => Http::response(['errors' => [['title' => 'Unavailable']]], 503)]);

        $this->expectException(TeamleaderServiceUnavailableException::class);
        app(TeamleaderService::class)->listProjectsByCustomerId(self::CUSTOMER_ID, true);
    }

    public function test_a_contact_lookup_does_not_treat_a_gateway_failure_as_a_missing_contact(): void
    {
        Http::fake(['api.focus.teamleader.eu/contacts.info' => Http::response('<html>Gateway timeout</html>', 504)]);

        $this->expectException(TeamleaderServiceUnavailableException::class);
        app(TeamleaderService::class)->getContactById(self::CUSTOMER_ID);
    }

    public function test_forced_project_fetch_rejects_malformed_success_responses(): void
    {
        Http::fake(['api.focus.teamleader.eu/projects.list' => Http::response(['data' => null])]);

        $this->expectException(\RuntimeException::class);
        app(TeamleaderService::class)->listProjectsByCustomerId(self::CUSTOMER_ID, true);
    }

    public function test_forced_fetch_does_not_accept_a_project_without_its_details(): void
    {
        $this->partialMock(TeamleaderService::class, function ($mock) {
            $mock->shouldReceive('listProjectsByCustomerId')->with(self::CUSTOMER_ID, true)
                ->once()->andReturn([['id' => 'project-id']]);
            $mock->shouldReceive('getProjectDetails')->with('project-id')->once()
                ->andThrow(new \RuntimeException('Project unavailable'));
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Project unavailable');
        app(TeamleaderService::class)->getProjectsWithDetailsByCustomerId(self::CUSTOMER_ID, true);
    }
}
