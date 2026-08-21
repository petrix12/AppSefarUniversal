<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ClientCosSnapshotService;
use App\Services\Mcp\McpAuditLogger;
use App\Services\Mcp\SefarMcpReadToolService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use RuntimeException;
use Throwable;

class SefarMcpServer extends Command
{
    protected $signature = 'sefar:mcp {--audit-log= : Ruta del archivo JSONL de auditoria MCP}';

    protected $description = 'Run the private App Sefar MCP server inside the Laravel environment';

    private ClientCosSnapshotService $snapshots;
    private McpAuditLogger $audit;
    private SefarMcpReadToolService $readTools;
    private ?User $actor = null;
    private ?string $sessionId = null;
    private string $processId;

    public function handle(ClientCosSnapshotService $snapshots, SefarMcpReadToolService $readTools): int
    {
        $this->snapshots = $snapshots;
        $this->readTools = $readTools;
        $this->processId = implode(':', [
            gethostname() ?: 'unknown-host',
            (string) getmypid(),
            bin2hex(random_bytes(4)),
        ]);

        $auditLog = (string) ($this->option('audit-log') ?: config('mcp.audit_log'));
        $auditSecret = config('mcp.audit_secret');
        $this->audit = new McpAuditLogger($auditLog, is_string($auditSecret) && $auditSecret !== '' ? $auditSecret : null);

        set_time_limit(0);

        while (($message = $this->readMessage()) !== null) {
            $response = $this->handleMessage($message);

            if ($response !== null) {
                $this->writeMessage($response);
            }
        }

        return self::SUCCESS;
    }

    private function handleMessage(array $message): ?array
    {
        $id = $message['id'] ?? null;
        $method = (string) ($message['method'] ?? '');
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        try {
            return match ($method) {
                'initialize' => $this->response($id, [
                    'protocolVersion' => $params['protocolVersion'] ?? '2024-11-05',
                    'capabilities' => [
                        'tools' => new \stdClass(),
                    ],
                    'serverInfo' => [
                        'name' => 'sefar-private-laravel',
                        'version' => '1.0.0',
                    ],
                    'instructions' => implode(' ', [
                        'MCP privado de App Sefar ejecutado dentro de Laravel.',
                        'Requiere iniciar sesion con un usuario sin rol Cliente.',
                        'Todas las consultas tools/call se auditan en JSONL antes y despues de ejecutarse.',
                    ]),
                ]),
                'notifications/initialized' => null,
                'ping' => $this->response($id, new \stdClass()),
                'tools/list' => $this->response($id, ['tools' => $this->tools()]),
                'tools/call' => $this->handleToolCall($id, $params),
                'resources/list' => $this->response($id, ['resources' => []]),
                'prompts/list' => $this->response($id, ['prompts' => []]),
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
                'name' => 'iniciar_sesion',
                'description' => 'Inicia una sesion MCP dinamica con credenciales Laravel. Rechaza usuarios con rol Cliente.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'email' => [
                            'type' => 'string',
                            'description' => 'Correo del usuario interno.',
                        ],
                        'password' => [
                            'type' => 'string',
                            'description' => 'Contrasena del usuario interno.',
                        ],
                        'two_factor_code' => [
                            'type' => 'string',
                            'description' => 'Codigo 2FA si el usuario lo tiene activo.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'estado_sesion',
                'description' => 'Devuelve el estado de la sesion MCP actual.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
            ],
            [
                'name' => 'cerrar_sesion',
                'description' => 'Cierra la sesion MCP dinamica del proceso actual.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
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
                ],
            ],
            [
                'name' => 'refrescar_cos_cliente',
                'description' => 'Fuerza el recalculo de COS usando el servicio Laravel oficial. Esta herramienta puede actualizar base de datos.',
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
                ],
            ],
        ], $this->readTools->tools());
    }

    private function handleToolCall(mixed $id, array $params): array
    {
        $name = (string) ($params['name'] ?? '');
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
        $callId = bin2hex(random_bytes(8));
        $startedAt = microtime(true);
        $actorBefore = $this->auditActor();

        $this->audit->append('tool_call_started', [
            'process_id' => $this->processId,
            'session_hash' => $this->sessionHash(),
            'actor' => $actorBefore,
            'tool' => $name,
            'arguments' => $this->audit->sanitize($arguments),
            'target' => $this->auditTarget($name, $arguments),
            'call_id' => $callId,
        ]);

        try {
            $result = $this->callTool($name, $arguments);

            $this->audit->append('tool_call_finished', [
                'process_id' => $this->processId,
                'session_hash' => $this->sessionHash(),
                'actor' => $actorBefore,
                'actor_after' => $this->auditActor(),
                'tool' => $name,
                'status' => 'ok',
                'duration_ms' => $this->durationMs($startedAt),
                'result_summary' => $this->summarizeResult($name, $result),
                'call_id' => $callId,
            ]);

            return $this->response($id, $this->toolContent($result));
        } catch (Throwable $e) {
            $this->audit->append('tool_call_finished', [
                'process_id' => $this->processId,
                'session_hash' => $this->sessionHash(),
                'actor' => $actorBefore,
                'actor_after' => $this->auditActor(),
                'tool' => $name,
                'status' => 'error',
                'duration_ms' => $this->durationMs($startedAt),
                'error' => [
                    'class' => $e::class,
                    'message' => $e->getMessage(),
                ],
                'call_id' => $callId,
            ]);

            return $this->rpcError($id, -32000, $e->getMessage());
        }
    }

    private function callTool(string $name, array $arguments): array
    {
        if ($this->readTools->supports($name)) {
            $this->requireActor();

            return $this->readTools->call($name, $arguments);
        }

        return match ($name) {
            'iniciar_sesion' => $this->iniciarSesion($arguments),
            'estado_sesion' => $this->estadoSesion(),
            'cerrar_sesion' => $this->cerrarSesion(),
            'buscar_cliente' => $this->buscarCliente($arguments),
            'ver_cliente' => $this->verCliente($arguments),
            'ver_cos_cliente' => $this->verCosCliente($arguments),
            'refrescar_cos_cliente' => $this->refrescarCosCliente($arguments),
            default => throw new RuntimeException("Herramienta no soportada: {$name}"),
        };
    }

    private function iniciarSesion(array $arguments): array
    {
        $email = trim((string) (($arguments['email'] ?? '') ?: (env('SEFAR_MCP_LOGIN_EMAIL') ?: env('SEFAR_LOGIN_EMAIL'))));
        $password = (string) (($arguments['password'] ?? '') ?: (env('SEFAR_MCP_LOGIN_PASSWORD') ?: env('SEFAR_LOGIN_PASSWORD')));
        $twoFactorCode = trim((string) ($arguments['two_factor_code'] ?? ''));

        if ($email === '' || $password === '') {
            throw new RuntimeException('email y password son requeridos.');
        }

        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, (string) $user->password)) {
            throw new RuntimeException('Credenciales invalidas.');
        }

        if (! empty($user->two_factor_secret)) {
            if ($twoFactorCode === '') {
                return [
                    'authenticated' => false,
                    'two_factor_required' => true,
                    'message' => 'La cuenta requiere 2FA. Vuelve a llamar iniciar_sesion con two_factor_code.',
                ];
            }

            $provider = app(TwoFactorAuthenticationProvider::class);

            if (! $provider->verify(decrypt($user->two_factor_secret), $twoFactorCode)) {
                throw new RuntimeException('Codigo 2FA invalido.');
            }
        }

        $user->load('roles');

        if ($user->hasRole('Cliente')) {
            $this->actor = null;
            $this->sessionId = null;

            throw new RuntimeException('Sesion rechazada: el usuario autenticado tiene rol Cliente.');
        }

        $this->actor = $user;
        $this->sessionId = bin2hex(random_bytes(16));

        return [
            'authenticated' => true,
            'non_client_allowed' => true,
            'session_id' => $this->sessionId,
            'user' => $this->serializeActor($user),
            'message' => 'Sesion MCP iniciada dentro de Laravel.',
        ];
    }

    private function estadoSesion(): array
    {
        return [
            'authenticated' => $this->actor !== null,
            'non_client_allowed' => $this->actor !== null,
            'session_id' => $this->sessionId,
            'user' => $this->actor ? $this->serializeActor($this->actor) : null,
        ];
    }

    private function cerrarSesion(): array
    {
        $this->actor = null;
        $this->sessionId = null;

        return [
            'authenticated' => false,
            'message' => 'Sesion MCP cerrada.',
        ];
    }

    private function buscarCliente(array $arguments): array
    {
        $this->requireActor();

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
        $this->requireActor();
        $client = $this->findClient($this->positiveId($arguments['id'] ?? null));

        return [
            'data' => $this->serializeClient($client),
        ];
    }

    private function refrescarCosCliente(array $arguments): array
    {
        $this->requireActor();
        $client = $this->findClient($this->positiveId($arguments['id'] ?? null));
        $sync = array_key_exists('sync', $arguments) ? (bool) $arguments['sync'] : true;
        $snapshot = $this->snapshots->get($client, true, $sync);

        return $this->cosResponse($snapshot);
    }

    private function verCosCliente(array $arguments): array
    {
        $this->requireActor();
        $client = $this->findClient($this->positiveId($arguments['id'] ?? null));
        $snapshot = $this->snapshots->get($client, false, true);

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

    private function requireActor(): void
    {
        if (! $this->actor) {
            throw new RuntimeException('No hay sesion MCP activa. Llama iniciar_sesion primero.');
        }

        $this->actor->loadMissing('roles');

        if ($this->actor->hasRole('Cliente')) {
            $this->actor = null;
            $this->sessionId = null;

            throw new RuntimeException('Sesion revocada: el usuario tiene rol Cliente.');
        }
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

    private function auditActor(): array
    {
        return [
            'id' => $this->actor?->id,
            'email' => $this->actor?->email,
            'roles' => $this->actor?->getRoleNames()->values()->all() ?? [],
            'authenticated' => $this->actor !== null,
            'non_client_allowed' => $this->actor ? ! $this->actor->hasRole('Cliente') : null,
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
            'iniciar_sesion', 'estado_sesion', 'cerrar_sesion' => [
                'type' => 'session',
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
            'iniciar_sesion', 'estado_sesion' => [
                'authenticated' => $result['authenticated'] ?? null,
                'non_client_allowed' => $result['non_client_allowed'] ?? null,
                'user_id' => $result['user']['id'] ?? null,
                'roles' => $result['user']['roles'] ?? [],
                'two_factor_required' => $result['two_factor_required'] ?? null,
            ],
            'cerrar_sesion' => [
                'authenticated' => $result['authenticated'] ?? null,
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

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function sessionHash(): ?string
    {
        return $this->sessionId ? hash('sha256', $this->sessionId) : null;
    }

    private function toolContent(array $payload): array
    {
        $text = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($text === false) {
            throw new RuntimeException('No se pudo serializar el resultado de la herramienta MCP.');
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $text,
                ],
            ],
        ];
    }

    private function response(mixed $id, mixed $result): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    private function rpcError(mixed $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }

    private function readMessage(): ?array
    {
        $headers = '';

        while (($line = fgets(STDIN)) !== false) {
            $line = rtrim($line, "\r\n");

            if ($line === '') {
                break;
            }

            $headers .= $line . "\n";
        }

        if ($headers === '' && feof(STDIN)) {
            return null;
        }

        if (! preg_match('/Content-Length:\s*(\d+)/i', $headers, $match)) {
            return null;
        }

        $length = (int) $match[1];
        $body = '';

        while (strlen($body) < $length && ! feof(STDIN)) {
            $chunk = fread(STDIN, $length - strlen($body));

            if ($chunk === false || $chunk === '') {
                break;
            }

            $body .= $chunk;
        }

        if (strlen($body) !== $length) {
            return null;
        }

        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Mensaje JSON-RPC invalido.');
        }

        return $decoded;
    }

    private function writeMessage(array $message): void
    {
        $json = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new RuntimeException('No se pudo serializar la respuesta JSON-RPC.');
        }

        fwrite(STDOUT, 'Content-Length: ' . strlen($json) . "\r\n\r\n" . $json);
        fflush(STDOUT);
    }
}
