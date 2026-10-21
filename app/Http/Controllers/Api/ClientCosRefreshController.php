<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ClientCosSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientCosRefreshController extends Controller
{
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required_without:passport', 'nullable', 'string', 'email:rfc', 'max:175'],
            'passport' => ['required_without:email', 'nullable', 'string', 'max:175'],
        ]);

        $cliente = null;
        $matchedBy = null;

        // Email takes priority; passport is the fallback when email has no client match.
        foreach (['email', 'passport'] as $field) {
            $value = mb_strtolower(trim((string) ($data[$field] ?? '')));
            if ($value === '') {
                continue;
            }

            $cliente = User::role('Cliente')
                ->whereRaw("LOWER(TRIM({$field})) = ?", [$value])
                ->first();

            if ($cliente) {
                $matchedBy = $field;
                break;
            }
        }

        if (! $cliente) {
            $message = 'No se encontraron coincidencias en la app con el correo o pasaporte indicado.';

            return response()->json([
                'message' => $message,
                'alert' => [
                    'type' => 'warning',
                    'code' => 'client_not_found',
                    'message' => $message,
                ],
                'data' => null,
                'meta' => [
                    'found' => false,
                    'refreshed' => false,
                    'matched_by' => null,
                    'forced_refresh' => false,
                ],
            ])->header('Cache-Control', 'no-store, private');
        }

        return $this->refreshCos($cliente, app(ClientCosSnapshotService::class), $matchedBy);
    }

    public function __invoke(User $cliente, ClientCosSnapshotService $snapshots): JsonResponse
    {
        abort_unless($cliente->hasRole('Cliente'), 404, 'Cliente no encontrado.');

        return $this->refreshCos($cliente, $snapshots, 'id');
    }

    private function refreshCos(User $cliente, ClientCosSnapshotService $snapshots, string $matchedBy): JsonResponse
    {
        try {
            $snapshot = $snapshots->get($cliente, true, true);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'No se pudo completar la actualización del COS. Reintenta la solicitud.',
                'error' => 'cos_refresh_failed',
                'data' => ['client_id' => $cliente->id],
            ], 502)->header('Cache-Control', 'no-store, private');
        }

        return response()->json([
            'data' => [
                'client_id' => $snapshot['client']->id,
                'client' => $snapshot['client']->only(['id', 'name', 'email', 'hs_id', 'tl_id', 'monday_id']),
                'cos' => $snapshot['cos'],
                'cosready' => $snapshot['cosready'],
                'arraycos_expire' => $snapshot['arraycos_expire'],
                'negocios_count' => $snapshot['negocios_count'],
                'monday' => $snapshot['monday'],
            ],
            'meta' => [
                'found' => true,
                'refreshed' => true,
                'matched_by' => $matchedBy,
                'forced_refresh' => true,
                'sync' => $snapshot['sync'],
                'generated_at' => $snapshot['generated_at'],
                'duration_ms' => $snapshot['duration_ms'],
            ],
        ])->header('Cache-Control', 'no-store, private');
    }
}
