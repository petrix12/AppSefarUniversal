<?php

namespace Tests\Unit;

use App\Exceptions\CosSourceRefreshException;
use App\Exceptions\TeamleaderAuthenticationException;
use App\Exceptions\TeamleaderServiceUnavailableException;
use App\Http\Controllers\Api\ClientCosRefreshController;
use App\Models\User;
use App\Services\ClientCosSnapshotService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Throwable;

class ClientCosRefreshDiagnosticsTest extends TestCase
{
    #[DataProvider('failures')]
    public function test_failures_have_a_private_correlated_diagnostic_without_exposing_exception_details(
        string $type,
        ?string $reason,
        ?bool $retryable,
    ): void {
        $cause = match ($type) {
            'temporary' => new TeamleaderServiceUnavailableException(503),
            'authentication' => new TeamleaderAuthenticationException('private-refresh-token'),
            default => new \TypeError('private technical detail'),
        };
        $exception = $type === 'internal' ? $cause : new CosSourceRefreshException('teamleader', $cause);
        $client = Mockery::mock(User::class)->makePartial();
        $client->id = 7295;
        $client->shouldReceive('hasRole')->with('Cliente')->once()->andReturnTrue();
        $snapshots = Mockery::mock(ClientCosSnapshotService::class);
        $snapshots->shouldReceive('get')->once()->with($client, true, true)->andThrow($exception);
        $this->mock(ExceptionHandler::class, fn ($handler) => $handler
            ->shouldReceive('report')->once()->with($exception));
        Log::spy();
        Mail::fake();

        $response = (new ClientCosRefreshController())($client, $snapshots);
        $body = $response->getData(true);
        $reference = $body['reference'];

        $this->assertSame(502, $response->getStatusCode());
        $this->assertTrue(Str::isUuid($reference));
        $this->assertSame($reference, $response->headers->get('X-Error-Reference'));
        $this->assertSame('cos_refresh_failed', $body['error']);
        $this->assertSame(['client_id' => 7295], $body['data']);
        $this->assertFalse($body['meta']['refreshed']);
        $this->assertSame('id', $body['meta']['matched_by']);
        $this->assertSame($reason, $body['reason'] ?? null);
        $this->assertSame($retryable, $body['retryable'] ?? null);
        $this->assertStringNotContainsString($cause->getMessage(), $response->getContent());
        $this->assertStringNotContainsString($cause->getFile(), $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        Log::shouldHaveReceived('error')->once()->with('COS/API: cos_refresh_failed', Mockery::on(
            function (array $context) use ($reference, $exception, $cause): bool {
                $this->assertSame($reference, $context['reference']);
                $this->assertSame(7295, $context['user_id']);
                $this->assertSame($exception::class, $context['exception_class']);
                $this->assertSame($cause::class, $context['cause_class']);
                $this->assertSame($cause->getFile(), $context['cause_file']);
                $this->assertSame($cause->getLine(), $context['cause_line']);
                $this->assertGreaterThanOrEqual(0, $context['duration_ms']);
                $this->assertArrayNotHasKey('message', $context);
                $this->assertArrayNotHasKey('authorization', $context);

                return true;
            }
        ));
        Mail::assertNothingSent();
    }

    public static function failures(): array
    {
        return [
            'local failure' => ['internal', null, null],
            'temporary Teamleader failure' => ['temporary', 'service_unavailable', true],
            'Teamleader requires reconnection' => ['authentication', 'authentication_required', false],
        ];
    }

    public function test_each_failed_attempt_has_its_own_reference(): void
    {
        $client = Mockery::mock(User::class)->makePartial();
        $client->id = 7295;
        $client->shouldReceive('hasRole')->with('Cliente')->twice()->andReturnTrue();
        $snapshots = Mockery::mock(ClientCosSnapshotService::class);
        $snapshots->shouldReceive('get')->twice()->andThrow(new \RuntimeException('Failure'));
        $this->mock(ExceptionHandler::class, fn ($handler) => $handler
            ->shouldReceive('report')->twice()->with(Mockery::type(Throwable::class)));
        Log::spy();
        $controller = new ClientCosRefreshController();

        $first = $controller($client, $snapshots)->getData(true)['reference'];
        $second = $controller($client, $snapshots)->getData(true)['reference'];

        $this->assertNotSame($first, $second);
    }
}
