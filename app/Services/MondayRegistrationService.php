<?php

namespace App\Services;

use App\Models\MondayServiceRegistration;
use App\Models\Servicio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MondayRegistrationService
{
    private const API_URL = 'https://api.monday.com/v2';

    public function sync(User $user, Servicio $servicio): bool
    {
        if ($this->isAuditoriaProcedimientos($servicio)) {
            return false;
        }

        if (! $servicio->monday_sync_enabled
            || blank($servicio->monday_board_id)
            || blank($servicio->monday_group_id)) {
            Log::info('Registro en Monday omitido: servicio sin destino habilitado', [
                'user_id' => $user->id,
                'servicio_id' => $servicio->id,
            ]);

            return false;
        }

        $registration = MondayServiceRegistration::firstOrNew([
            'user_id' => $user->id,
            'servicio_id' => $servicio->id,
            'board_id' => (string) $servicio->monday_board_id,
            'group_id' => (string) $servicio->monday_group_id,
        ]);

        if ($registration->status === 'synced' && filled($registration->monday_item_id)) {
            return true;
        }

        $token = config('services.monday.token');

        if (blank($token)) {
            $this->markFailed($registration, 'Falta MONDAY_TOKEN.');

            return false;
        }

        $registration->forceFill([
            'status' => 'pending',
            'attempts' => ((int) $registration->attempts) + 1,
            'last_error' => null,
        ])->save();

        $query = <<<'GRAPHQL'
            mutation ($boardId: ID!, $groupId: String!, $itemName: String!, $columnValues: JSON!) {
                create_item (
                    board_id: $boardId,
                    group_id: $groupId,
                    item_name: $itemName,
                    column_values: $columnValues
                ) {
                    id
                    name
                }
            }
            GRAPHQL;

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => $token,
            ])->post(self::API_URL, [
                'query' => $query,
                'variables' => [
                    'boardId' => (string) $servicio->monday_board_id,
                    'groupId' => (string) $servicio->monday_group_id,
                    'itemName' => $this->clientName($user),
                    'columnValues' => json_encode($this->columnValues($user, $servicio)),
                ],
            ]);

            $responseData = $response->json();
            $itemId = data_get($responseData, 'data.create_item.id');

            if (! $response->successful() || ! empty($responseData['errors']) || blank($itemId)) {
                $error = ! empty($responseData['errors'])
                    ? json_encode($responseData['errors'], JSON_UNESCAPED_UNICODE)
                    : 'Respuesta HTTP '.$response->status().' sin item creado.';

                $this->markFailed($registration, $error);

                return false;
            }

            $registration->forceFill([
                'monday_item_id' => (string) $itemId,
                'status' => 'synced',
                'last_error' => null,
                'synced_at' => now(),
            ])->save();

            if (blank($user->monday_id)) {
                $user->forceFill(['monday_id' => (string) $itemId])->save();
            }

            Log::info('Cliente registrado en Monday según la configuración del servicio', [
                'user_id' => $user->id,
                'servicio_id' => $servicio->id,
                'board_id' => $servicio->monday_board_id,
                'group_id' => $servicio->monday_group_id,
                'monday_item_id' => $itemId,
            ]);

            return true;
        } catch (Throwable $exception) {
            $this->markFailed($registration, $exception->getMessage());

            return false;
        }
    }

    private function columnValues(User $user, Servicio $servicio): array
    {
        $treeUrl = rtrim((string) config('app.url'), '/').'/tree/'.urlencode((string) $user->passport);
        $values = [
            'texto' => $user->passport ?: 'N/A',
            'enlace' => ['url' => $treeUrl, 'text' => $treeUrl],
            'estado54' => 'Arbol Incompleto',
            'texto1' => $servicio->id_hubspot ?: $servicio->nombre,
            'texto4' => $user->hs_id ?: '',
            'texto_largo88' => $user->nombre_de_familiar_realizando_procesos ?: '',
        ];

        if ($user->date_of_birth) {
            $values['fecha75'] = ['date' => Carbon::parse($user->date_of_birth)->format('Y-m-d')];
        }

        return $values;
    }

    private function clientName(User $user): string
    {
        return trim(($user->apellidos ?? '').' '.($user->nombres ?? '')) ?: $user->name;
    }

    private function isAuditoriaProcedimientos(Servicio $servicio): bool
    {
        $name = Str::lower(Str::ascii(trim(($servicio->id_hubspot ?? '').' '.($servicio->nombre ?? ''))));

        return str_contains($name, 'auditoria')
            && str_contains($name, 'procedimiento');
    }

    private function markFailed(MondayServiceRegistration $registration, string $error): void
    {
        $registration->forceFill([
            'status' => 'failed',
            'last_error' => Str::limit($error, 65000, ''),
        ])->save();

        Log::error('No se pudo registrar el cliente en Monday', [
            'user_id' => $registration->user_id,
            'servicio_id' => $registration->servicio_id,
            'board_id' => $registration->board_id,
            'group_id' => $registration->group_id,
            'error' => $error,
        ]);
    }
}
