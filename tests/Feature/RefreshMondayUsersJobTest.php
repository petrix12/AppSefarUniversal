<?php

namespace Tests\Feature;

use App\Jobs\Monday\RefreshMondayUsersJob;
use App\Services\MondayApiClient;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RefreshMondayUsersJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    public function test_the_independent_job_updates_the_selector_and_uses_the_monday_queue(): void
    {
        $client = $this->mock(MondayApiClient::class, fn ($mock) => $mock->shouldReceive('query')->once()->andReturn(['users' => [
            ['id' => '1', 'name' => 'Asesor', 'email' => 'asesor@example.test', 'enabled' => true],
            ['id' => '2', 'name' => 'Desactivado', 'email' => 'otro@example.test', 'enabled' => false],
        ]]));
        $job = new RefreshMondayUsersJob();
        $job->handle($client);
        $this->assertSame('monday', $job->connection);
        $this->assertSame('monday-sync', $job->queue);
        $this->assertSame([['id' => '1', 'name' => 'Asesor', 'email' => 'asesor@example.test']], Cache::get('cos.monday_users'));
    }

    public function test_an_incomplete_response_preserves_the_previous_staff_directory(): void
    {
        $previous = [['id' => '1', 'name' => 'Asesor', 'email' => 'asesor@example.test']];
        Cache::forever('cos.monday_users', $previous);
        $client = $this->mock(MondayApiClient::class, fn ($mock) => $mock->shouldReceive('query')->once()->andReturn([]));
        try {
            (new RefreshMondayUsersJob())->handle($client);
            $this->fail('An incomplete directory must not clear the last successful copy.');
        } catch (\RuntimeException $exception) {
            $this->assertSame($previous, Cache::get('cos.monday_users'));
        }
    }
}
