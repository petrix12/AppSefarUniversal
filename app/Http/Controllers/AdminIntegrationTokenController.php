<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Mcp\McpAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class AdminIntegrationTokenController extends Controller
{
    private const API_ABILITIES = [
        'read' => 'Lectura',
        'create' => 'Crear',
        'update' => 'Actualizar',
        'delete' => 'Eliminar',
    ];

    public function apiTokens()
    {
        return view('admin.integrations.api-tokens', [
            'tokens' => $this->tokensQuery(false)->paginate(20),
            'users' => $this->internalUsers(),
            'abilities' => self::API_ABILITIES,
        ]);
    }

    public function mcp()
    {
        return view('admin.integrations.mcp', [
            'tokens' => $this->tokensQuery(true)->paginate(20),
            'users' => $this->internalUsers(),
        ]);
    }

    public function audit(Request $request)
    {
        $limit = min(max((int) $request->query('limit', 250), 50), 1000);
        $source = (string) $request->query('source', 'all');
        $query = trim((string) $request->query('q', ''));
        $events = collect($this->readAuditEvents($limit * 4))
            ->reverse()
            ->map(fn (array $event) => $this->normalizeAuditEvent($event))
            ->filter(function (array $event) use ($source, $query) {
                if ($source !== 'all' && $event['source'] !== $source) {
                    return false;
                }

                if ($query === '') {
                    return true;
                }

                return str_contains(
                    mb_strtolower(json_encode($event['raw'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: ''),
                    mb_strtolower($query)
                );
            })
            ->take($limit)
            ->values();

        return view('admin.integrations.audit', [
            'events' => $events,
            'source' => $source,
            'query' => $query,
            'limit' => $limit,
            'auditPath' => (string) config('mcp.audit_log'),
            'summary' => [
                'total' => $events->count(),
                'tokens' => $events->where('source', 'tokens')->count(),
                'mcp_http' => $events->where('source', 'mcp_http')->count(),
                'mcp_stdio' => $events->where('source', 'mcp_stdio')->count(),
                'errors' => $events->where('status', 'error')->count(),
            ],
        ]);
    }

    public function storeApiToken(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'max:80'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in(array_keys(self::API_ABILITIES))],
        ]);

        $user = $this->findInternalUser((int) $data['user_id']);
        $abilities = array_values(array_unique($data['abilities']));
        $callId = bin2hex(random_bytes(8));
        $logger = $this->auditLogger();

        $logger->append('token_create_started', [
            'call_id' => $callId,
            'actor' => $this->auditActor($request),
            'target' => $this->auditTokenTarget('api', null, $user, $data['name'], $abilities),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $token = $user->createToken($data['name'], $abilities);

        try {
            $logger->append('token_create_finished', [
                'call_id' => $callId,
                'actor' => $this->auditActor($request),
                'status' => 'ok',
                'target' => $this->auditTokenTarget('api', $token->accessToken, $user, $data['name'], $abilities),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            $token->accessToken->delete();

            throw $e;
        }

        return back()
            ->with('success', 'Token API creado correctamente.')
            ->with('created_token', $token->plainTextToken)
            ->with('created_token_name', $data['name']);
    }

    public function storeMcpToken(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'name' => ['nullable', 'string', 'max:80'],
        ]);

        $user = $this->findInternalUser((int) $data['user_id']);
        $name = trim((string) ($data['name'] ?? ''));
        $tokenName = $name !== '' ? $name : 'MCP privado - ' . now()->format('Y-m-d H:i');
        $callId = bin2hex(random_bytes(8));
        $logger = $this->auditLogger();

        $logger->append('token_create_started', [
            'call_id' => $callId,
            'actor' => $this->auditActor($request),
            'target' => $this->auditTokenTarget('mcp', null, $user, $tokenName, ['mcp:read']),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $token = $user->createToken($tokenName, ['mcp:read']);

        try {
            $logger->append('token_create_finished', [
                'call_id' => $callId,
                'actor' => $this->auditActor($request),
                'status' => 'ok',
                'target' => $this->auditTokenTarget('mcp', $token->accessToken, $user, $tokenName, ['mcp:read']),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            $token->accessToken->delete();

            throw $e;
        }

        return back()
            ->with('success', 'Token MCP creado correctamente.')
            ->with('created_token', $token->plainTextToken)
            ->with('created_token_name', $tokenName);
    }

    public function destroy(Request $request, PersonalAccessToken $token): RedirectResponse
    {
        $token->load('tokenable');
        $tokenType = in_array('mcp:read', $token->abilities ?? [], true) ? 'mcp' : 'api';
        $callId = bin2hex(random_bytes(8));
        $logger = $this->auditLogger();
        $target = $this->auditTokenTarget(
            $tokenType,
            $token,
            $token->tokenable instanceof User ? $token->tokenable : null,
            $token->name,
            $token->abilities ?? []
        );

        $logger->append('token_revoke_started', [
            'call_id' => $callId,
            'actor' => $this->auditActor($request),
            'target' => $target,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $token->delete();

        $logger->append('token_revoke_finished', [
            'call_id' => $callId,
            'actor' => $this->auditActor($request),
            'status' => 'ok',
            'target' => $target,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Token revocado correctamente.');
    }

    private function tokensQuery(bool $mcp)
    {
        return PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->when(
                $mcp,
                fn ($query) => $query->where('abilities', 'like', '%"mcp:read"%'),
                fn ($query) => $query->where(function ($nested) {
                    $nested->whereNull('abilities')
                        ->orWhere('abilities', 'not like', '%"mcp:read"%');
                })
            )
            ->with('tokenable')
            ->latest();
    }

    private function internalUsers()
    {
        return User::query()
            ->select(['id', 'name', 'email'])
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'Cliente'))
            ->orderBy('name')
            ->get();
    }

    private function findInternalUser(int $userId): User
    {
        $user = User::with('roles')->findOrFail($userId);

        abort_if($user->hasRole('Cliente'), 422, 'No se pueden crear tokens para usuarios con rol Cliente.');

        return $user;
    }

    private function auditLogger(): McpAuditLogger
    {
        $secret = config('mcp.audit_secret');

        return new McpAuditLogger(
            (string) config('mcp.audit_log'),
            is_string($secret) && $secret !== '' ? $secret : null
        );
    }

    private function auditActor(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $user?->id,
            'email' => $user?->email,
            'roles' => $user?->getRoleNames()->values()->all() ?? [],
            'authenticated' => $user !== null,
        ];
    }

    private function auditTokenTarget(
        string $type,
        ?PersonalAccessToken $token,
        ?User $user,
        string $name,
        array $abilities
    ): array {
        return [
            'type' => "{$type}_token",
            'token_id' => $token?->id,
            'token_name' => $name,
            'token_hash_prefix' => $token ? substr((string) $token->token, 0, 12) : null,
            'abilities' => array_values($abilities),
            'assigned_user' => [
                'id' => $user?->id,
                'name' => $user?->name,
                'email' => $user?->email,
            ],
        ];
    }

    private function readAuditEvents(int $limit): array
    {
        $path = (string) config('mcp.audit_log');

        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        $start = max(0, $lastLine - $limit);
        $events = [];

        $file->seek($start);

        while (! $file->eof()) {
            $line = trim((string) $file->fgets());

            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                $events[] = $decoded;
            }
        }

        return $events;
    }

    private function normalizeAuditEvent(array $event): array
    {
        $eventName = (string) ($event['event'] ?? '');
        $target = is_array($event['target'] ?? null) ? $event['target'] : [];
        $actor = is_array($event['actor'] ?? null) ? $event['actor'] : [];

        return [
            'timestamp' => $event['timestamp'] ?? null,
            'event' => $eventName,
            'source' => $this->auditSource($eventName, $event),
            'status' => $event['status'] ?? null,
            'actor_label' => $actor['email'] ?? (($actor['id'] ?? null) ? 'Usuario '.$actor['id'] : 'No autenticado'),
            'target_label' => $this->auditTargetLabel($target, $event),
            'route_or_tool' => $event['tool'] ?? ($event['route_name'] ?? ($event['path'] ?? null)),
            'ip_address' => $event['ip_address'] ?? null,
            'hash' => $event['hash'] ?? null,
            'raw' => $event,
        ];
    }

    private function auditSource(string $eventName, array $event): string
    {
        if (str_starts_with($eventName, 'token_')) {
            return 'tokens';
        }

        if (in_array(($event['transport'] ?? null), ['http', 'streamable_http'], true) || str_starts_with($eventName, 'http_')) {
            return 'mcp_http';
        }

        if (str_starts_with($eventName, 'tool_')) {
            return 'mcp_stdio';
        }

        return 'other';
    }

    private function auditTargetLabel(array $target, array $event): string
    {
        if (isset($target['token_id']) || isset($target['token_name'])) {
            return trim(($target['token_name'] ?? 'Token') . ' #' . ($target['token_id'] ?? 'pendiente'));
        }

        if (isset($target['client_id'])) {
            return 'Cliente #' . $target['client_id'];
        }

        if (isset($target['type'])) {
            return (string) $target['type'];
        }

        return (string) ($event['path'] ?? '-');
    }
}
