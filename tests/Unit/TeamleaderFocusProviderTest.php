<?php

namespace Tests\Unit;

use App\Exceptions\TeamleaderServiceUnavailableException;
use App\Services\Teamleader\TeamleaderFocusProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TeamleaderFocusProviderTest extends TestCase
{
    #[DataProvider('temporaryFailures')]
    public function test_gateway_and_rate_limit_responses_are_temporary_even_when_the_body_is_not_json(int $status, string $contentType, string $body): void
    {
        $provider = $this->provider(new Response($status, ['Content-Type' => $contentType], $body));

        try {
            $provider->getAccessToken('refresh_token', ['refresh_token' => 'dummy-refresh']);
            $this->fail('An unavailable OAuth server must not produce a token.');
        } catch (TeamleaderServiceUnavailableException $exception) {
            $this->assertSame($status, $exception->getCode());
            $this->assertStringNotContainsString($body, $exception->getMessage());
        }
    }

    public static function temporaryFailures(): array
    {
        return [
            '500 HTML' => [500, 'text/html', '<html>Internal server error</html>'],
            '502 HTML' => [502, 'text/html', '<html>Bad gateway</html>'],
            '503 JSON' => [503, 'application/json', '{"error":"temporarily_unavailable"}'],
            '504 HTML' => [504, 'text/html', '<html>CloudFront gateway timeout</html>'],
            '429 JSON' => [429, 'application/json', '{"error":"too_many_requests"}'],
        ];
    }

    public function test_it_requests_json_and_accepts_a_valid_refreshed_token(): void
    {
        $history = [];
        $handler = HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'access_token' => 'dummy-access', 'refresh_token' => 'dummy-refresh', 'expires_in' => 3600,
            ])),
        ]));
        $handler->push(Middleware::history($history));
        $provider = new TeamleaderFocusProvider(['clientId' => 'dummy-id', 'clientSecret' => 'dummy-secret'], [
            'httpClient' => new Client(['handler' => $handler]),
        ]);

        $token = $provider->getAccessToken('refresh_token', ['refresh_token' => 'dummy-refresh']);

        $this->assertSame('dummy-access', $token->getToken());
        $this->assertSame('application/json', $history[0]['request']->getHeaderLine('Accept'));
    }

    public function test_a_rejected_refresh_token_remains_an_authentication_error(): void
    {
        $provider = $this->provider(new Response(400, ['Content-Type' => 'application/json'], '{"error":"invalid_grant"}'));

        $this->expectException(IdentityProviderException::class);
        $this->expectExceptionMessage('invalid_grant');
        $provider->getAccessToken('refresh_token', ['refresh_token' => 'dummy-refresh']);
    }

    public function test_an_html_success_response_is_rejected_without_exposing_the_body(): void
    {
        $provider = $this->provider(new Response(200, ['Content-Type' => 'text/html'], '<html>Login page</html>'));

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Teamleader no devolvió JSON');
        $provider->getAccessToken('refresh_token', ['refresh_token' => 'dummy-refresh']);
    }

    private function provider(Response $response): TeamleaderFocusProvider
    {
        return new TeamleaderFocusProvider([], [
            'httpClient' => new Client(['handler' => HandlerStack::create(new MockHandler([$response]))]),
        ]);
    }
}
