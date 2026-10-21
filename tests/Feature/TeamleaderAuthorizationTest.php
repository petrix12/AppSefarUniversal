<?php

namespace Tests\Feature;

use App\Console\Commands\RefreshTeamleaderToken;
use App\Exceptions\TeamleaderAuthenticationException;
use App\Exceptions\TeamleaderServiceUnavailableException;
use App\Http\Controllers\TeamleaderController;
use App\Models\TeamleaderToken;
use App\Services\Teamleader\TeamleaderFocusProvider;
use App\Services\TeamleaderService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TeamleaderAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'services.teamleader.min_request_gap_ms' => 0]);
        Cache::flush();
        Http::preventStrayRequests();
    }

    #[DataProvider('temporaryAuthorizationFailures')]
    public function test_temporary_oauth_failures_propagate_from_contact_lookup_and_preserve_the_refresh_token(int $status, string $contentType, string $body, string $exceptionClass): void
    {
        $record = $this->expiredToken();
        $previous = $record->fresh()->getRawOriginal();
        $service = $this->serviceWithResponse(new Response($status, ['Content-Type' => $contentType], $body));

        try {
            $service->getContactById('12345678-1234-1234-1234-123456789012');
            $this->fail('A failed authorization must not be interpreted as a missing contact.');
        } catch (\Throwable $exception) {
            $this->assertInstanceOf($exceptionClass, $exception);
        }

        $this->assertFalse(Cache::has('teamleader:auth-invalid'));
        $this->assertSame($previous, $record->fresh()->getRawOriginal());
        Http::assertNothingSent();
    }

    public static function temporaryAuthorizationFailures(): array
    {
        return [
            'gateway HTML' => [504, 'text/html', '<html>Gateway timeout</html>', TeamleaderServiceUnavailableException::class],
            'server HTML' => [500, 'text/html', '<html>Server error</html>', TeamleaderServiceUnavailableException::class],
            'unavailable JSON' => [503, 'application/json', '{"error":"temporarily_unavailable"}', TeamleaderServiceUnavailableException::class],
            'unexpected HTML' => [200, 'text/html', '<html>Login</html>', \UnexpectedValueException::class],
            'malformed JSON' => [200, 'application/json', 'not JSON', \UnexpectedValueException::class],
        ];
    }

    public function test_a_rejected_refresh_token_requires_reauthentication(): void
    {
        $this->expiredToken();
        $service = $this->serviceWithResponse(new Response(400, ['Content-Type' => 'application/json'], '{"error":"invalid_grant"}'));

        try {
            $service->getAccessToken();
            $this->fail('A rejected refresh token must require reauthentication.');
        } catch (TeamleaderAuthenticationException $exception) {
            $this->assertTrue(Cache::get('teamleader:auth-invalid'));
        }
    }

    public function test_all_oauth_entry_points_use_laravel_configuration(): void
    {
        config([
            'services.teamleader.client_id' => 'configured-client-id',
            'services.teamleader.client_secret' => 'configured-client-secret',
            'services.teamleader.redirect' => 'https://app.example.test/teamleader/callback',
        ]);

        foreach ([new TeamleaderService(), new TeamleaderController(), new RefreshTeamleaderToken()] as $entryPoint) {
            $provider = (new \ReflectionProperty($entryPoint, 'provider'))->getValue($entryPoint);
            parse_str(parse_url($provider->getAuthorizationUrl(), PHP_URL_QUERY), $query);

            $this->assertSame('configured-client-id', $query['client_id']);
            $this->assertSame('https://app.example.test/teamleader/callback', $query['redirect_uri']);
        }
    }

    private function expiredToken(): TeamleaderToken
    {
        return TeamleaderToken::create([
            'access_token' => Crypt::encryptString('dummy-access'),
            'refresh_token' => Crypt::encryptString('dummy-refresh'),
            'expires' => now()->subMinute()->timestamp,
        ]);
    }

    private function serviceWithResponse(Response $response): TeamleaderService
    {
        $provider = new TeamleaderFocusProvider([], [
            'httpClient' => new Client(['handler' => HandlerStack::create(new MockHandler([$response]))]),
        ]);
        $service = new TeamleaderService();
        (new \ReflectionProperty($service, 'provider'))->setValue($service, $provider);

        return $service;
    }
}
