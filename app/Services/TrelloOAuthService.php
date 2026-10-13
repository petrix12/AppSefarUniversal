<?php

namespace App\Services;

use App\Models\TrelloOAuthToken;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TrelloOAuthService
{
    private const TOKEN_URL = 'https://auth.atlassian.com/oauth/token';

    public function isConfigured(): bool
    {
        $trello = (array) config('systems_activity_report.trello');

        return trim((string) ($trello['oauth_client_id'] ?? '')) !== ''
            && trim((string) ($trello['oauth_redirect_uri'] ?? '')) !== '';
    }

    public function clientId(): string
    {
        return trim((string) config('systems_activity_report.trello.oauth_client_id', ''));
    }

    public function redirectUri(): string
    {
        return trim((string) config('systems_activity_report.trello.oauth_redirect_uri', ''));
    }

    public function isConnected(): bool
    {
        return TrelloOAuthToken::query()->whereKey(1)->exists();
    }

    public function authorizationUrl(string $state, string $codeChallenge): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Falta configurar TRELLO_OAUTH_CLIENT_ID o TRELLO_OAUTH_REDIRECT_URI.');
        }

        return 'https://auth.atlassian.com/authorize?'.http_build_query([
            'client_id' => $this->clientId(),
            'scope' => 'read:board:trello offline_access',
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'prompt' => 'consent',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeAuthorizationCode(string $code, string $codeVerifier): void
    {
        $payload = [
            'client_id' => $this->clientId(),
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri(),
            'code' => $code,
            'code_verifier' => $codeVerifier,
        ];
        $secret = trim((string) config('systems_activity_report.trello.oauth_client_secret', ''));
        if ($secret !== '') {
            $payload['client_secret'] = $secret;
        }

        $tokens = $this->requestTokens($payload);
        if (empty($tokens['refresh_token'])) {
            throw new RuntimeException('Trello no devolvió un refresh token. Verifica que la autorización incluya offline_access.');
        }

        $this->saveTokens($tokens);
    }

    public function accessToken(): string
    {
        $stored = TrelloOAuthToken::query()->find(1);
        if (! $stored) {
            throw new RuntimeException('Conecta Trello desde la página del informe manual.');
        }

        if ($stored->expires_at && $stored->expires_at->isAfter(now()->addMinute())) {
            return (string) $stored->access_token;
        }

        $payload = [
            'client_id' => $this->clientId(),
            'grant_type' => 'refresh_token',
            'refresh_token' => (string) $stored->refresh_token,
        ];
        $secret = trim((string) config('systems_activity_report.trello.oauth_client_secret', ''));
        if ($secret !== '') {
            $payload['client_secret'] = $secret;
        }

        try {
            $tokens = $this->requestTokens($payload);
        } catch (RuntimeException $exception) {
            throw new RuntimeException('La autorización de Trello venció o fue revocada. Vuelve a conectar Trello desde el informe manual.');
        }

        $tokens['refresh_token'] ??= (string) $stored->refresh_token;
        $this->saveTokens($tokens, $stored);

        return (string) $tokens['access_token'];
    }

    private function requestTokens(array $payload): array
    {
        $response = Http::acceptJson()
            ->asJson()
            ->timeout(30)
            ->post(self::TOKEN_URL, $payload);

        $tokens = (array) $response->json();
        if (! $response->successful() || empty($tokens['access_token'])) {
            throw new RuntimeException('Trello rechazó la autorización OAuth. Verifica el client ID, el client secret y el callback registrado.');
        }

        return $tokens;
    }

    private function saveTokens(array $tokens, ?TrelloOAuthToken $stored = null): void
    {
        $stored ??= TrelloOAuthToken::query()->find(1) ?? new TrelloOAuthToken(['id' => 1]);
        $stored->forceFill([
            'access_token' => (string) $tokens['access_token'],
            'refresh_token' => (string) ($tokens['refresh_token'] ?? $stored->refresh_token ?? ''),
            'expires_at' => now()->addSeconds(max(60, (int) ($tokens['expires_in'] ?? 3600))),
        ])->save();
    }
}
