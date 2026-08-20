<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ClientCosSnapshotService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class McpClientApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Cliente', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);

        config(['mcp.audit_log' => storage_path('framework/testing/mcp-audit.jsonl')]);
    }

    public function test_mcp_client_search_requires_mcp_read_permission(): void
    {
        Sanctum::actingAs($this->internalUser(), ['read']);

        $this->getJson('/api/mcp/v1/clientes?q=test')
            ->assertForbidden()
            ->assertJsonPath('message', 'El token no tiene permiso mcp:read.');
    }

    public function test_mcp_rejects_users_with_cliente_role(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['mcp:read']);

        $this->getJson('/api/mcp/v1/clientes?q=test')
            ->assertForbidden()
            ->assertJsonPath('message', 'El MCP no esta disponible para usuarios con rol Cliente.');
    }

    public function test_streamable_mcp_lists_tools_with_mcp_read_token(): void
    {
        Sanctum::actingAs($this->internalUser(), ['mcp:read']);

        $this->postJson('/mcp', $this->mcpRequest('tools/list'), $this->mcpHeaders('tools/list'))
            ->assertOk()
            ->assertJsonPath('result.resultType', 'complete')
            ->assertJsonPath('result.tools.0.name', 'estado_mcp');
    }

    public function test_streamable_mcp_rejects_users_with_cliente_role(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['mcp:read']);

        $this->postJson('/mcp', $this->mcpRequest('tools/list'), $this->mcpHeaders('tools/list'))
            ->assertForbidden()
            ->assertJsonPath('message', 'El MCP no esta disponible para usuarios con rol Cliente.');
    }

    public function test_streamable_mcp_can_call_client_search_tool(): void
    {
        Sanctum::actingAs($this->internalUser(), ['mcp:read']);

        $client = User::factory()->create([
            'name' => 'Cliente MCP Remoto',
            'email' => 'mcp-remoto@example.test',
            'passport' => 'MCP456',
        ]);

        $this->postJson('/mcp', $this->mcpRequest('tools/call', [
            'name' => 'buscar_cliente',
            'arguments' => [
                'query' => 'MCP456',
                'limit' => 5,
            ],
        ]), $this->mcpHeaders('tools/call', 'buscar_cliente'))
            ->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.data.0.id', $client->id)
            ->assertJsonPath('result.structuredContent.data.0.email', 'mcp-remoto@example.test');
    }

    public function test_mcp_client_search_returns_matching_clients(): void
    {
        Sanctum::actingAs($this->internalUser(), ['mcp:read']);

        $client = User::factory()->create([
            'name' => 'Maria Cliente',
            'nombres' => 'Maria',
            'apellidos' => 'Prueba',
            'email' => 'maria.cliente@example.test',
            'passport' => 'PAS123',
        ]);

        User::factory()->create([
            'name' => 'Otro Cliente',
            'email' => 'otro@example.test',
        ]);

        $this->getJson('/api/mcp/v1/clientes?q=PAS123&limit=5')
            ->assertOk()
            ->assertJsonPath('data.0.id', $client->id)
            ->assertJsonPath('data.0.email', 'maria.cliente@example.test')
            ->assertJsonPath('meta.limit', 5);
    }

    public function test_mcp_cos_endpoint_uses_snapshot_service(): void
    {
        Sanctum::actingAs($this->internalUser(), ['mcp:read']);

        $client = User::factory()->create([
            'name' => 'Cliente COS',
            'email' => 'cos@example.test',
        ]);

        $expiresAt = Carbon::now()->addDays(5);
        $snapshotClient = $client->fresh();
        $snapshotClient->forceFill([
            'cosready' => 1,
            'arraycos_expire' => $expiresAt,
        ]);

        $this->mock(ClientCosSnapshotService::class, function ($mock) use ($client, $snapshotClient, $expiresAt) {
            $mock->shouldReceive('get')
                ->once()
                ->with(Mockery::on(fn ($value) => $value instanceof User && $value->id === $client->id), false, true)
                ->andReturn([
                    'client' => $snapshotClient,
                    'cos' => [
                        [
                            'servicio' => 'Nacionalidad por Carta de Naturaleza',
                            'currentStepName' => 'Paso de prueba',
                            'currentStepGen' => 1,
                            'currentStepJur' => -1,
                        ],
                    ],
                    'cosready' => true,
                    'arraycos_expire' => $expiresAt->toIso8601String(),
                    'negocios_count' => 1,
                    'sync' => ['external' => false],
                    'monday' => ['id' => null, 'board' => null, 'has_data' => false],
                    'generated_at' => Carbon::now()->toIso8601String(),
                    'duration_ms' => 12,
                ]);
        });

        $this->postJson("/api/mcp/v1/clientes/{$client->id}/cos", ['sync' => false])
            ->assertOk()
            ->assertJsonPath('data.client.id', $client->id)
            ->assertJsonPath('data.cos.0.currentStepName', 'Paso de prueba')
            ->assertJsonPath('meta.sync.external', false);
    }

    public function test_mcp_cos_endpoint_can_force_snapshot_refresh(): void
    {
        Sanctum::actingAs($this->internalUser(), ['mcp:read']);

        $client = User::factory()->create([
            'name' => 'Cliente Sync COS',
            'email' => 'sync-cos@example.test',
        ]);

        $expiresAt = Carbon::now()->addDays(5);
        $snapshotClient = $client->fresh();
        $snapshotClient->forceFill([
            'cosready' => 1,
            'arraycos_expire' => $expiresAt,
        ]);

        $this->mock(ClientCosSnapshotService::class, function ($mock) use ($client, $snapshotClient, $expiresAt) {
            $mock->shouldReceive('get')
                ->once()
                ->with(Mockery::on(fn ($value) => $value instanceof User && $value->id === $client->id), true, true)
                ->andReturn([
                    'client' => $snapshotClient,
                    'cos' => [],
                    'cosready' => true,
                    'arraycos_expire' => $expiresAt->toIso8601String(),
                    'negocios_count' => 0,
                    'sync' => [
                        'external' => true,
                        'cache' => ['hit' => false],
                    ],
                    'monday' => ['id' => null, 'board' => null, 'has_data' => false],
                    'generated_at' => Carbon::now()->toIso8601String(),
                    'duration_ms' => 25,
                ]);
        });

        $this->postJson("/api/mcp/v1/clientes/{$client->id}/cos", ['sync' => true])
            ->assertOk()
            ->assertJsonPath('data.client.id', $client->id)
            ->assertJsonPath('meta.sync.external', true)
            ->assertJsonPath('meta.sync.cache.hit', false);
    }

    private function internalUser(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['Administrador']);

        return $user;
    }

    private function mcpRequest(string $method, array $params = []): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => array_merge($params, [
                '_meta' => [
                    'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                    'io.modelcontextprotocol/clientInfo' => [
                        'name' => 'phpunit',
                        'version' => '1.0.0',
                    ],
                    'io.modelcontextprotocol/clientCapabilities' => [],
                ],
            ]),
        ];
    }

    private function mcpHeaders(string $method, ?string $name = null): array
    {
        $headers = [
            'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => '2026-07-28',
            'Mcp-Method' => $method,
        ];

        if ($name !== null) {
            $headers['Mcp-Name'] = $name;
        }

        return $headers;
    }
}
