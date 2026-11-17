<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CosIdentityConflictException;
use App\Exceptions\CosSourceRefreshException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ClientCosSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        $startedAt = microtime(true);

        try {
            $snapshot = $snapshots->get($cliente, true, true);
        } catch (\Throwable $exception) {
            $reference = (string) Str::uuid();
            $cause = $exception;
            while ($cause->getPrevious()) {
                $cause = $cause->getPrevious();
            }

            // Keep identity and correlation separate from the exception report:
            // request bodies, credentials and exception messages are not copied here.
            Log::error('COS/API: cos_refresh_failed', [
                'reference' => $reference,
                'user_id' => $cliente->id,
                'matched_by' => $matchedBy,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'source' => $exception instanceof CosSourceRefreshException ? $exception->source : null,
                'identity_conflicts' => $exception instanceof CosIdentityConflictException ? $exception->conflicts : null,
                'exception_class' => $exception::class,
                'cause_class' => $cause::class,
                'cause_file' => $cause->getFile(),
                'cause_line' => $cause->getLine(),
            ]);
            report($exception);

            $error = [
                'message' => 'No se pudo completar la actualización del COS. Reintenta la solicitud.',
                'error' => 'cos_refresh_failed',
                'reference' => $reference,
                'data' => ['client_id' => $cliente->id],
                'meta' => ['found' => true, 'refreshed' => false, 'matched_by' => $matchedBy],
            ];

            if ($exception instanceof CosSourceRefreshException) {
                $error['message'] = $exception->getMessage();
                $error['source'] = $exception->source;
                $error['reason'] = $exception->reason();

                if ($exception->source === 'teamleader') {
                    if ($exception->reason() === 'authentication_required') {
                        $error['message'] = 'Teamleader requiere reconectar la integración antes de actualizar el COS.';
                        $error['retryable'] = false;
                        $error['action'] = ['type' => 'reconnect_teamleader', 'url' => route('teamleader.redirect')];
                    } elseif (in_array($exception->reason(), ['service_unavailable', 'invalid_authorization_response'], true)) {
                        $error['message'] = 'Teamleader no está disponible o no devolvió una respuesta válida. Se conserva el COS anterior; reintenta más tarde.';
                        $error['retryable'] = true;
                        $error['action'] = ['type' => 'retry_later'];
                    }
                }
            }

            if ($exception instanceof CosIdentityConflictException) {
                $error['retryable'] = false;
                $error['conflicting_fields'] = array_keys($exception->conflicts);
                $error['action'] = ['type' => 'review_client_identity'];
            }

            return response()->json($error, $exception instanceof CosIdentityConflictException ? 409 : 502)
                ->header('Cache-Control', 'no-store, private')
                ->header('X-Error-Reference', $reference);
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
