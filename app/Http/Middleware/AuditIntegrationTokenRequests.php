<?php

namespace App\Http\Middleware;

use App\Services\Mcp\McpAuditLogger;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AuditIntegrationTokenRequests
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $secret = config('mcp.audit_secret');
        $logger = new McpAuditLogger(
            (string) config('mcp.audit_log'),
            is_string($secret) && $secret !== '' ? $secret : null,
        );
        $user = $request->user();
        $token = $user?->currentAccessToken();
        $callId = bin2hex(random_bytes(8));
        $startedAt = microtime(true);

        // Only identity and outcome metadata; never headers, query strings or bodies.
        $context = [
            'call_id' => $callId,
            'transport' => 'integration_http',
            'actor' => ['id' => $user?->id, 'email' => $user?->email],
            'target' => [
                'type' => 'integration_token',
                'token_id' => $token instanceof PersonalAccessToken ? $token->id : null,
                'token_name' => $token instanceof PersonalAccessToken ? $token->name : null,
            ],
            'method' => $request->method(),
            'route_name' => $request->route()?->getName(),
            'path' => $request->path(),
            'required_ability' => $ability,
            'ip_address' => $request->ip(),
        ];
        $logger->append('integration_request_started', $context);

        try {
            $response = $next($request);
            $statusCode = $response->getStatusCode();
        } catch (Throwable $exception) {
            $statusCode = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            $logger->append('integration_request_finished', array_merge($context, [
                'status' => 'error',
                'status_code' => $statusCode,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]));

            throw $exception;
        }

        $logger->append('integration_request_finished', array_merge($context, [
            'status' => $statusCode < 400 ? 'ok' : 'error',
            'status_code' => $statusCode,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]));

        return $response;
    }
}
