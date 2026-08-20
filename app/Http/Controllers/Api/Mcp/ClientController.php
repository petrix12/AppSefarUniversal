<?php

namespace App\Http\Controllers\Api\Mcp;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ClientCosSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ClientController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $limit = min(max((int) $request->query('limit', 10), 1), 25);

        $clients = User::role('Cliente')
            ->select($this->clientColumns())
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($nested) use ($query) {
                    $nested->where('id', $query)
                        ->orWhere('name', 'like', "%{$query}%")
                        ->orWhere('nombres', 'like', "%{$query}%")
                        ->orWhere('apellidos', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('passport', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%");
                });
            })
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (User $user) => $this->serializeClient($user))
            ->values();

        return response()->json([
            'data' => $clients,
            'meta' => [
                'query' => $query,
                'limit' => $limit,
            ],
        ]);
    }

    public function show(User $cliente): JsonResponse
    {
        $this->ensureClient($cliente);

        return response()->json([
            'data' => $this->serializeClient($cliente),
        ]);
    }

    public function cos(Request $request, User $cliente, ClientCosSnapshotService $snapshots): JsonResponse
    {
        $this->ensureClient($cliente);

        $forceRefresh = $request->boolean('sync', false);
        $snapshot = $snapshots->get($cliente, $forceRefresh, true);

        return response()->json([
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
        ]);
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

    private function ensureClient(User $user): void
    {
        abort_unless($user->hasRole('Cliente'), 404, 'Cliente no encontrado.');
    }
}
