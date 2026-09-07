<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CosMondayLookup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class CosMondayLookupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'services.monday.token' => 'test-token']);
        Cache::flush();
    }

    public function test_all_twelve_boards_are_searched_and_absence_is_cached(): void
    {
        $data = [];
        foreach (array_keys(config('cos_snapshot.monday_search_boards')) as $i => $id) {
            $data['b'.$i] = ['items' => []];
        }
        Http::fake(['api.monday.com/*' => Http::response(['data' => $data])]);
        $user = new User();
        $user->id = 123;
        $service = app(CosMondayLookup::class);
        $this->assertNull($service->find('TEST', $user));
        $this->assertNull($service->find('TEST', $user));
        Http::assertSentCount(12);
        Http::assertSent(fn ($request) => substr_count($request['query'], 'items_page_by_column_values(') === 1);
    }

    public function test_multiple_matches_keep_configured_priority(): void
    {
        config(['cos_snapshot.monday_search_boards' => [11 => 'First', 22 => 'Second']]);
        Http::fake(['api.monday.com/*' => Http::response(['data' => [
            'b1' => ['items' => [['id' => '222']]],
            'b0' => ['items' => [['id' => '111']]],
        ]])]);
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 123;
        $user->shouldReceive('save')->once()->andReturnTrue();
        $result = app(CosMondayLookup::class)->find('TEST', $user);
        $this->assertSame('111', $result['id']);
        $this->assertSame('111', $user->monday_id);
    }

    public function test_partial_failure_is_not_cached_as_absence(): void
    {
        Http::fake(['api.monday.com/*' => Http::response(['errors' => [['message' => 'Rate limit']]])]);
        $user = new User();
        $user->id = 123;
        for ($i = 0; $i < 2; $i++) {
            try {
                app(CosMondayLookup::class)->find('TEST', $user);
                $this->fail('Expected a failure for an incomplete response.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('Monday', $exception->getMessage());
            }
        }
        Http::assertSentCount(24);
    }
}
