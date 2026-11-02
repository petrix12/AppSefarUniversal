<?php

namespace Tests\Feature;

use App\Http\Controllers\DeployController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class DeploymentLockTest extends TestCase
{
    private string $originalStorage;
    private string $temporaryStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalStorage = $this->app->storagePath();
        $this->temporaryStorage = sys_get_temp_dir().'/sefar-deploy-lock-'.bin2hex(random_bytes(8));
        mkdir($this->temporaryStorage.'/framework', 0700, true);
        $this->app->useStoragePath($this->temporaryStorage);
        Http::preventStrayRequests();
        Artisan::shouldReceive('call')->never();
    }

    protected function tearDown(): void
    {
        $this->app->useStoragePath($this->originalStorage);
        @unlink($this->temporaryStorage.'/framework/deploy.lock');
        rmdir($this->temporaryStorage.'/framework');
        rmdir($this->temporaryStorage);
        parent::tearDown();
    }

    public function test_an_overlapping_deployment_is_rejected_before_any_action_runs(): void
    {
        $lock = fopen(storage_path('framework/deploy.lock'), 'c');
        flock($lock, LOCK_EX);
        $controller = $this->partialMock(DeployController::class)->shouldAllowMockingProtectedMethods();
        $controller->shouldNotReceive('performDeployment');

        try {
            $response = $controller->deploy(Request::create('/api/deploy', 'POST'));
            $this->assertSame(409, $response->getStatusCode());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function test_a_completed_deployment_releases_the_lock(): void
    {
        $controller = $this->partialMock(DeployController::class)->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('performDeployment')->once()->andReturn(response()->json(['ok' => true]));
        $this->assertSame(200, $controller->deploy(Request::create('/api/deploy', 'POST'))->getStatusCode());
        $this->assertLockAvailable();
    }

    public function test_a_failed_deployment_releases_the_lock(): void
    {
        $controller = $this->partialMock(DeployController::class)->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('performDeployment')->once()->andThrow(new RuntimeException('Test failure'));
        try {
            $controller->deploy(Request::create('/api/deploy', 'POST'));
            $this->fail('The deployment exception should propagate.');
        } catch (RuntimeException $error) {
            $this->assertSame('Test failure', $error->getMessage());
        }
        $this->assertLockAvailable();
    }

    private function assertLockAvailable(): void
    {
        $lock = fopen(storage_path('framework/deploy.lock'), 'c');
        try {
            $this->assertTrue(flock($lock, LOCK_EX | LOCK_NB));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
