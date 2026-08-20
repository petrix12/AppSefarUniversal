<?php

namespace App\Http\Middleware;

use App\Services\Mcp\McpAuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuditMcpRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('mcp.audit_secret');
        $logger = new McpAuditLogger(
            (string) config('mcp.audit_log'),
            is_string($secret) && $secret !== '' ? $secret : null,
        );

        $callId = bin2hex(random_bytes(8));
        $startedAt = microtime(true);
        $actor = $this->actor($request);

        $logger->append('http_request_started', [
            'process_id' => $this->processId(),
            'call_id' => $callId,
            'transport' => 'http',
            'actor' => $actor,
            'method' => $request->method(),
            'route_name' => optional($request->route())->getName(),
            'path' => $request->path(),
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'input' => $logger->sanitize($request->all()),
            'target' => $this->target($request),
        ]);

        try {
            $response = $next($request);

            $logger->append('http_request_finished', [
                'process_id' => $this->processId(),
                'call_id' => $callId,
                'transport' => 'http',
                'actor' => $actor,
                'status' => 'ok',
                'status_code' => $response->getStatusCode(),
                'duration_ms' => $this->durationMs($startedAt),
                'result_summary' => $this->responseSummary($response),
            ]);

            return $response;
        } catch (Throwable $e) {
            $logger->append('http_request_finished', [
                'process_id' => $this->processId(),
                'call_id' => $callId,
                'transport' => 'http',
                'actor' => $actor,
                'status' => 'error',
                'duration_ms' => $this->durationMs($startedAt),
                'error' => [
                    'class' => $e::class,
                    'message' => $e->getMessage(),
                ],
            ]);

            throw $e;
        }
    }

    private function actor(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $user?->id,
            'email' => $user?->email,
            'roles' => $user?->getRoleNames()->values()->all() ?? [],
            'authenticated' => $user !== null,
            'non_client_allowed' => $user ? ! $user->hasRole('Cliente') : null,
        ];
    }

    private function target(Request $request): array
    {
        $route = $request->route();
        $client = $route?->parameter('cliente');

        return [
            'type' => 'mcp_http',
            'client_id' => is_object($client) && isset($client->id) ? $client->id : $client,
            'route_parameters' => $this->routeParameters($request),
        ];
    }

    private function routeParameters(Request $request): array
    {
        $route = $request->route();

        if (! $route) {
            return [];
        }

        $parameters = [];

        foreach ($route->parameters() as $key => $value) {
            $parameters[$key] = is_object($value) && isset($value->id) ? $value->id : $value;
        }

        return $parameters;
    }

    private function responseSummary(Response $response): array
    {
        $content = (string) $response->getContent();
        $decoded = json_decode($content, true);
        $summary = [
            'response_hash' => hash('sha256', $content),
            'response_bytes' => strlen($content),
        ];

        if (! is_array($decoded)) {
            return $summary;
        }

        return array_merge($summary, [
            'top_level_keys' => array_keys($decoded),
            'data_count' => is_countable($decoded['data'] ?? null) ? count($decoded['data']) : null,
            'client_id' => $decoded['data']['id'] ?? ($decoded['data']['client']['id'] ?? null),
        ]);
    }

    private function processId(): string
    {
        return implode(':', [
            gethostname() ?: 'unknown-host',
            (string) getmypid(),
        ]);
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
