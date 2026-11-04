<?php

namespace Tests\Unit;

use Illuminate\Auth\TokenGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TokenGuardSecurityTest extends TestCase
{
    #[DataProvider('invalidTokens')]
    public function test_non_string_tokens_cannot_trigger_an_authentication_lookup(string $source, mixed $token): void
    {
        $provider = $this->createMock(UserProvider::class);
        $provider->expects($this->never())->method('retrieveByCredentials');

        $request = $source === 'query'
            ? Request::create('/token-check', 'GET', ['api_token' => $token])
            : Request::create('/token-check', 'POST', [], [], [],
                ['CONTENT_TYPE' => 'application/json'], json_encode(['api_token' => $token]));

        $this->assertNull((new TokenGuard($provider, $request))->user());
    }

    public static function invalidTokens(): array
    {
        $cases = [];
        foreach (['query', 'json'] as $source) {
            foreach (['integer' => 1, 'zero' => 0, 'float' => 0.5, 'boolean' => true,
                'false' => false, 'array' => ['token'], 'empty array' => [], 'null' => null] as $name => $token) {
                $cases[$source.' '.$name] = [$source, $token];
            }
        }

        return $cases;
    }

    #[DataProvider('validTokens')]
    public function test_valid_string_tokens_still_authenticate(bool $hash): void
    {
        $token = 'valid-test-api-token';
        $user = $this->createMock(Authenticatable::class);
        $provider = $this->createMock(UserProvider::class);
        $provider->expects($this->once())->method('retrieveByCredentials')
            ->with(['api_token' => $hash ? hash('sha256', $token) : $token])->willReturn($user);
        $request = Request::create('/token-check', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$token);

        $this->assertSame($user, (new TokenGuard($provider, $request, 'api_token', 'api_token', $hash))->user());
    }

    public static function validTokens(): array
    {
        return ['plain token' => [false], 'hashed token' => [true]];
    }
}
