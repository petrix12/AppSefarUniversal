<?php

namespace App\Http\Controllers\Api\Mcp;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ClientCosSnapshotService;
use App\Services\Mcp\McpAuditLogger;
use App\Services\Mcp\SefarMcpReadToolService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class StreamableHttpController extends Controller
{
    private const SERVER_NAME = 'sefar-private-laravel';
    private const SERVER_VERSION = '1.0.0';
    private const DEFAULT_PROTOCOL_VERSION = '2026-07-28';
    private const SUPPORTED_PROTOCOL_VERSIONS = [
        '2024-11-05',
        '2025-03-26',
        '2025-06-18',
        '2025-11-25',
        '2026-07-28',
    ];

    private ClientCosSnapshotService $snapshots;
    private McpAuditLogger $audit;
    private SefarMcpReadToolService $readTools;

    public function __invoke(
        Request $request,
        ClientCosSnapshotService $snapshots,
        SefarMcpReadToolService $readTools
    ): Response
    {
        $this->snapshots = $snapshots;
        $this->readTools = $readTools;
        $this->audit = $this->auditLogger();

        if (! $this->originAllowed($request)) {
            return response()->json(
                $this->rpcError(null, -32000, 'Origin no permitido para el endpoint MCP.'),
                403
            );
        }

        if (! $request->isMethod('post')) {
            return response()->json([
                'message' => 'El endpoint MCP remoto acepta solo POST.',
                'endpoint' => url('/mcp'),
            ], 405)->header('Allow', 'POST');
        }

        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return response()->json($this->rpcError(null, -32700, 'JSON invalido.'), 400);
        }

        if (array_is_list($payload)) {
            return $this->handleBatch($request, $payload);
        }

        return $this->jsonRpcResponse($this->handleMessage($request, $payload));
    }

    private function handleBatch(Request $request, array $messages): Response
    {
        if ($messages === []) {
            return response()->json($this->rpcError(null, -32600, 'Batch JSON-RPC vacio.'), 400);
        }

        $responses = [];

        foreach ($messages as $message) {
            if (! is_array($message)) {
                $responses[] = $this->rpcError(null, -32600, 'Mensaje JSON-RPC invalido.');
                continue;
            }

            $response = $this->handleMessage($request, $message);

            if ($response !== null) {
                $responses[] = $response;
            }
        }

        if ($responses === []) {
            return response('', 202);
        }

        return response()->json($responses);
    }

    private function handleMessage(Request $request, array $message): ?array
    {
        $hasId = array_key_exists('id', $message);
        $id = $message['id'] ?? null;
        $method = (string) ($message['method'] ?? '');
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        if (($message['jsonrpc'] ?? '2.0') !== '2.0' || $method === '') {
            return $this->rpcError($id, -32600, 'Mensaje JSON-RPC invalido.');
        }

        $headerError = $this->validateMirroredHeaders($request, $message);

        if ($headerError !== null) {
            return $headerError;
        }

        if (! $hasId) {
            $this->auditNotification($request, $method, $params);

            return null;
        }

        try {
            return match ($method) {
                'initialize' => $this->response($id, [
                    'protocolVersion' => $this->resolveProtocolVersion($request, $params),
                    'capabilities' => [
                        'tools' => [
                            'listChanged' => false,
                        ],
                    ],
                    'serverInfo' => [
                        'name' => self::SERVER_NAME,
                        'version' => self::SERVER_VERSION,
                    ],
                    'instructions' => implode(' ', [
                        'MCP privado de App Sefar ejecutado dentro de Laravel.',
                        'La autenticacion usa Bearer Token de Sanctum con permiso mcp:read.',
                        'Usuarios con rol Cliente no pueden usar este MCP.',
                        'Las consultas y herramientas se auditan antes y despues de ejecutarse.',
                    ]),
                ]),
                'ping' => $this->response($id, new \stdClass()),
                'tools/list' => $this->response($id, [
                    'resultType' => 'complete',
                    'tools' => $this->tools(),
                    'ttlMs' => 300000,
                    'cacheScope' => 'public',
                ]),
                'tools/call' => $this->handleToolCall($request, $id, $params),
                'resources/list' => $this->response($id, [
                    'resultType' => 'complete',
                    'resources' => [],
                ]),
                'prompts/list' => $this->response($id, [
                    'resultType' => 'complete',
                    'prompts' => [],
                ]),
                default => $this->rpcError($id, -32601, "Metodo no soportado: {$method}"),
            };
        } catch (Throwable $e) {
            return $this->rpcError($id, -32000, $e->getMessage());
        }
    }

    private function tools(): array
    {
        return array_merge([
            [
                'name' => 'estado_mcp',
                'description' => 'Verifica que el token MCP esta autenticado y muestra el usuario interno asociado.',
                'inputSchema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'buscar_cliente',
                'description' => 'Busca clientes por nombre, email, pasaporte, telefono o ID desde Laravel.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Texto de busqueda.',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'minimum' => 1,
                            'maximum' => 25,
                            'default' => 10,
                        ],
                    ],
                    'required' => ['query'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'ver_cliente',
                'description' => 'Lee informacion basica de un cliente por ID.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                            'minimum' => 1,
                        ],
                    ],
                    'required' => ['id'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'ver_cos_cliente',
                'description' => 'Lee COS usando cache de 5 dias. Si el cache vencio o no existe, recalcula con el servicio Laravel oficial.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                            'minimum' => 1,
                        ],
                    ],
                    'required' => ['id'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'refrescar_cos_cliente',
                'description' => 'Fuerza el recalculo de COS usando el servicio Laravel oficial. Puede actualizar cache, negocios y fecha en base de datos.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                            'minimum' => 1,
                        ],
                        'sync' => [
                            'type' => 'boolean',
                            'default' => true,
                            'description' => 'Si true, refresca fuentes externas antes de calcular COS.',
                        ],
                    ],
                    'required' => ['id'],
                    'additionalProperties' => false,
                ],
            ],
        ], $this->readTools->tools());
    }

    private function handleToolCall(Request $request, mixed $id, array $params): array
    {
        $name = (string) ($params['name'] ?? '');
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
        $callId = bin2hex(random_bytes(8));
        $startedAt = microtime(true);
        $actor = $this->auditActor($request);

        $this->audit->append('tool_call_started', [
            'process_id' => $this->processId(),
            'call_id' => $callId,
            'transport' => 'streamable_http',
            'actor' => $actor,
            'tool' => $name,
            'arguments' => $this->audit->sanitize($arguments),
            'target' => $this->auditTarget($name, $arguments),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        try {
            $result = $this->callTool($request, $name, $arguments);

            $this->audit->append('tool_call_finished', [
                'process_id' => $this->processId(),
                'call_id' => $callId,
                'transport' => 'streamable_http',
                'actor' => $actor,
                'tool' => $name,
                'status' => 'ok',
                'duration_ms' => $this->durationMs($startedAt),
                'result_summary' => $this->summarizeResult($name, $result),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->response($id, $this->toolContent($result));
        } catch (Throwable $e) {
            $this->audit->append('tool_call_finished', [
                'process_id' => $this->processId(),
                'call_id' => $callId,
                'transport' => 'streamable_http',
                'actor' => $actor,
                'tool' => $name,
                'status' => 'error',
                'duration_ms' => $this->durationMs($startedAt),
                'error' => [
                    'class' => $e::class,
                    'message' => $e->getMessage(),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->response($id, $this->toolContent([
                'error' => $e->getMessage(),
            ], true));
        }
    }

    private function callTool(Request $request, string $name, array $arguments): array
    {
        if ($this->readTools->supports($name)) {
            return $this->readTools->call($name, $arguments);
        }

        return match ($name) {
            'estado_mcp' => $this->estadoMcp($request),
            'buscar_cliente' => $this->buscarCliente($arguments),
            'ver_cliente' => $this->verCliente($arguments),
            'ver_cos_cliente' => $this->verCosCliente($arguments),
            'refrescar_cos_cliente' => $this->refrescarCosCliente($arguments),
            default => throw new RuntimeException("Herramienta no soportada: {$name}"),
        };
    }

    private function estadoMcp(Request $request): array
    {
        $user = $request->user();
        $token = $user && method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;

        return [
            'authenticated' => $user !== null,
            'non_client_allowed' => $user ? ! $user->hasRole('Cliente') : false,
            'endpoint' => url('/mcp'),
            'user' => $user ? $this->serializeActor($user) : null,
            'token' => [
                'id' => is_object($token) && isset($token->id) ? $token->id : null,
                'name' => is_object($token) && isset($token->name) ? $token->name : null,
                'abilities' => is_object($token) && isset($token->abilities) ? $token->abilities : [],
            ],
        ];
    }

    private function buscarCliente(array $arguments): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));

        if ($query === '') {
            throw new RuntimeException('query es requerido.');
        }

        $limit = max(1, min(25, (int) ($arguments['limit'] ?? 10)));

        $clients = User::role('Cliente')
            ->select($this->clientColumns())
            ->where(function ($builder) use ($query) {
                $builder->where('id', $query)
                    ->orWhere('name', 'like', "%{$query}%")
                    ->orWhere('nombres', 'like', "%{$query}%")
                    ->orWhere('apellidos', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('passport', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
            })
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (User $user) => $this->serializeClient($user))
            ->values();

        return [
            'data' => $clients,
            'meta' => [
                'query' => $query,
                'limit' => $limit,
            ],
        ];
    }

    private function verCliente(array $arguments): array
    {
        $client = $this->findClient($this->positiveId($arguments['id'] ?? null));

        return [
            'data' => $this->serializeClient($client),
        ];
    }

    private function verCosCliente(array $arguments): array
    {
        $client = $this->findClient($this->positiveId($arguments['id'] ?? null));
        $snapshot = $this->snapshots->get($client, false, true);

        return $this->cosResponse($snapshot);
    }

    private function refrescarCosCliente(array $arguments): array
    {
        $client = $this->findClient($this->positiveId($arguments['id'] ?? null));
        $sync = array_key_exists('sync', $arguments) ? (bool) $arguments['sync'] : true;
        $snapshot = $this->snapshots->get($client, true, $sync);

        return $this->cosResponse($snapshot);
    }

    private function cosResponse(array $snapshot): array
    {
        return [
            'data' => [
                'client' => $this->serializeClient($snapshot['client']),
                'cos' => $snapshot['cos'],
                'cosready' => $snapshot['cosready'],
                'arraycos_expire' => $snapshot['arraycos_expire'],
                'negocios_count' => $snapshot['negocios_count'],
                'monday' => $snapshot['monday'],
            ],
            'meta' => [
                'sync' => $snapshot['sync'],
                'generated_at' => $snapshot['generated_at'],
                'duration_ms' => $snapshot['duration_ms'],
            ],
        ];
    }

    private function findClient(int $id): User
    {
        $user = User::find($id);

        if (! $user || ! $user->hasRole('Cliente')) {
            throw new RuntimeException('Cliente no encontrado.');
        }

        return $user;
    }

    private function serializeActor(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->values()->all(),
        ];
    }

    private function serializeClient(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'nombres' => $user->nombres,
            'apellidos' => $user->apellidos,
            'email' => $user->email,
            'phone' => $user->phone,
            'passport' => $user->passport,
            'servicio' => $user->servicio,
            'pay' => $user->pay,
            'contrato' => $user->contrato,
            'cosready' => (bool) $user->cosready,
            'arraycos_expire' => optional($user->arraycos_expire)->toIso8601String(),
            'hs_id' => $user->hs_id,
            'tl_id' => $user->tl_id,
            'monday_id' => $user->monday_id,
            'created_at' => optional($user->created_at)->toIso8601String(),
            'updated_at' => optional($user->updated_at)->toIso8601String(),
        ];
    }

    private function clientColumns(): array
    {
        $columns = [
            'id',
            'name',
            'nombres',
            'apellidos',
            'email',
            'phone',
            'passport',
            'servicio',
            'pay',
            'contrato',
            'cosready',
            'arraycos_expire',
            'hs_id',
            'tl_id',
            'monday_id',
            'created_at',
            'updated_at',
        ];

        return array_values(array_filter($columns, function (string $column) {
            return Schema::hasColumn('users', $column);
        }));
    }

    private function validateMirroredHeaders(Request $request, array $message): ?array
    {
        $id = $message['id'] ?? null;
        $method = (string) ($message['method'] ?? '');
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];
        $meta = is_array($params['_meta'] ?? null) ? $params['_meta'] : [];
        $protocolHeader = $request->headers->get('MCP-Protocol-Version');
        $methodHeader = $request->headers->get('Mcp-Method');
        $nameHeader = $request->headers->get('Mcp-Name');

        if ($protocolHeader && ! in_array($protocolHeader, self::SUPPORTED_PROTOCOL_VERSIONS, true)) {
            return $this->rpcError($id, -32001, 'UnsupportedProtocolVersionError: version MCP no soportada.', [
                'supported' => self::SUPPORTED_PROTOCOL_VERSIONS,
            ]);
        }

        $bodyProtocol = is_string($meta['io.modelcontextprotocol/protocolVersion'] ?? null)
            ? $meta['io.modelcontextprotocol/protocolVersion']
            : null;

        if ($protocolHeader && $bodyProtocol && $protocolHeader !== $bodyProtocol) {
            return $this->rpcError($id, -32002, 'HeaderMismatch: MCP-Protocol-Version no coincide con _meta.');
        }

        if ($methodHeader && $this->decodeMcpHeader($methodHeader) !== $method) {
            return $this->rpcError($id, -32002, 'HeaderMismatch: Mcp-Method no coincide con method.');
        }

        $bodyName = match ($method) {
            'tools/call' => is_string($params['name'] ?? null) ? $params['name'] : null,
            'resources/read', 'prompts/get' => is_string($params['uri'] ?? null) ? $params['uri'] : null,
            default => null,
        };

        if ($nameHeader && $bodyName !== null && $this->decodeMcpHeader($nameHeader) !== $bodyName) {
            return $this->rpcError($id, -32002, 'HeaderMismatch: Mcp-Name no coincide con el cuerpo.');
        }

        return null;
    }

    private function decodeMcpHeader(string $value): string
    {
        if (! str_starts_with($value, '=?base64?') || ! str_ends_with($value, '?=')) {
            return $value;
        }

        $encoded = substr($value, 9, -2);
        $decoded = base64_decode($encoded, true);

        return $decoded === false ? $value : $decoded;
    }

    private function originAllowed(Request $request): bool
    {
        $origin = $request->headers->get('Origin');

        if (! $origin) {
            return true;
        }

        $originHost = parse_url($origin, PHP_URL_HOST);
        $originScheme = parse_url($origin, PHP_URL_SCHEME);

        if (! is_string($originHost) || $originHost === '') {
            return false;
        }

        return $originHost === $request->getHost();
    }

    private function resolveProtocolVersion(Request $request, array $params): string
    {
        $version = (string) ($params['protocolVersion'] ?? $request->headers->get('MCP-Protocol-Version', self::DEFAULT_PROTOCOL_VERSION));

        return in_array($version, self::SUPPORTED_PROTOCOL_VERSIONS, true)
            ? $version
            : self::DEFAULT_PROTOCOL_VERSION;
    }

    private function auditLogger(): McpAuditLogger
    {
        $secret = config('mcp.audit_secret');

        return new McpAuditLogger(
            (string) config('mcp.audit_log'),
            is_string($secret) && $secret !== '' ? $secret : null
        );
    }

    private function auditNotification(Request $request, string $method, array $params): void
    {
        $this->audit->append('mcp_notification_received', [
            'process_id' => $this->processId(),
            'transport' => 'streamable_http',
            'actor' => $this->auditActor($request),
            'method' => $method,
            'params' => $this->audit->sanitize($params),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function auditActor(Request $request): array
    {
        $user = $request->user();
        $token = $user && method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;

        return [
            'id' => $user?->id,
            'email' => $user?->email,
            'roles' => $user?->getRoleNames()->values()->all() ?? [],
            'authenticated' => $user !== null,
            'non_client_allowed' => $user ? ! $user->hasRole('Cliente') : null,
            'token_id' => is_object($token) && isset($token->id) ? $token->id : null,
        ];
    }

    private function auditTarget(string $tool, array $arguments): array
    {
        if ($this->readTools->supports($tool)) {
            return $this->readTools->auditTarget($tool, $arguments);
        }

        return match ($tool) {
            'buscar_cliente' => [
                'type' => 'client_search',
                'query' => trim((string) ($arguments['query'] ?? '')),
                'limit' => $arguments['limit'] ?? 10,
            ],
            'ver_cliente' => [
                'type' => 'client_read',
                'client_id' => $arguments['id'] ?? null,
            ],
            'ver_cos_cliente' => [
                'type' => 'client_cos_read',
                'client_id' => $arguments['id'] ?? null,
                'cache_aware' => true,
                'may_write_database' => true,
            ],
            'refrescar_cos_cliente' => [
                'type' => 'client_cos_refresh',
                'client_id' => $arguments['id'] ?? null,
                'may_write_database' => true,
            ],
            'estado_mcp' => [
                'type' => 'connection_status',
            ],
            default => [
                'type' => 'unknown',
            ],
        };
    }

    private function summarizeResult(string $tool, array $result): array
    {
        $json = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $json = $json === false ? '' : $json;
        $summary = [
            'result_hash' => hash('sha256', $json),
            'result_bytes' => strlen($json),
        ];

        if ($this->readTools->supports($tool)) {
            return array_merge($summary, $this->readTools->summarizeResult($tool, $result));
        }

        return array_merge($summary, match ($tool) {
            'estado_mcp' => [
                'authenticated' => $result['authenticated'] ?? null,
                'user_id' => $result['user']['id'] ?? null,
                'token_id' => $result['token']['id'] ?? null,
            ],
            'buscar_cliente' => [
                'query' => $result['meta']['query'] ?? null,
                'limit' => $result['meta']['limit'] ?? null,
                'results_count' => is_countable($result['data'] ?? null) ? count($result['data']) : null,
                'result_ids' => $this->extractResultIds($result['data'] ?? []),
            ],
            'ver_cliente' => [
                'client_id' => $result['data']['id'] ?? null,
                'fields_present' => array_keys($result['data'] ?? []),
            ],
            'ver_cos_cliente', 'refrescar_cos_cliente' => [
                'client_id' => $result['data']['client']['id'] ?? null,
                'cosready' => $result['data']['cosready'] ?? null,
                'negocios_count' => $result['data']['negocios_count'] ?? null,
                'cos_count' => is_countable($result['data']['cos'] ?? null) ? count($result['data']['cos']) : null,
                'cache_hit' => $result['meta']['sync']['cache']['hit'] ?? null,
                'sync_external' => $result['meta']['sync']['external'] ?? null,
                'duration_ms' => $result['meta']['duration_ms'] ?? null,
            ],
            default => [
                'top_level_keys' => array_keys($result),
            ],
        });
    }

    private function extractResultIds(mixed $results): array
    {
        if (! is_iterable($results)) {
            return [];
        }

        $ids = [];

        foreach ($results as $result) {
            $id = filter_var($result['id'] ?? null, FILTER_VALIDATE_INT);

            if ($id !== false) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    private function positiveId(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);

        if ($id === false || $id < 1) {
            throw new RuntimeException('id debe ser un entero positivo.');
        }

        return (int) $id;
    }

    private function toolContent(array $payload, bool $isError = false): array
    {
        $text = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($text === false) {
            throw new RuntimeException('No se pudo serializar el resultado de la herramienta MCP.');
        }

        return [
            'resultType' => 'complete',
            'content' => [
                [
                    'type' => 'text',
                    'text' => $text,
                ],
            ],
            'structuredContent' => $payload,
            'isError' => $isError,
        ];
    }

    private function jsonRpcResponse(?array $response): Response
    {
        if ($response === null) {
            return response('', 202);
        }

        return response()->json($response, $this->httpStatus($response));
    }

    private function httpStatus(array $response): int
    {
        $code = $response['error']['code'] ?? null;

        return match ($code) {
            -32601 => 404,
            -32700, -32600, -32602, -32001, -32002 => 400,
            default => 200,
        };
    }

    private function response(mixed $id, mixed $result): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    private function rpcError(mixed $id, int $code, string $message, array $data = []): array
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($data !== []) {
            $error['data'] = $data;
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => $error,
        ];
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
