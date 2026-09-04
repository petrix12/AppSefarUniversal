<?php

namespace App\Services;

use App\Models\Agcliente;
use App\Models\MondayServiceRegistration;
use App\Models\Compras;
use App\Models\Servicio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class MondayRegistrationService
{
    private const API_URL = 'https://api.monday.com/v2';

    public const TIMING_AFTER_PAYMENT = 'after_payment';

    public const TIMING_AFTER_GETINFO = 'after_getinfo';

    /**
     * Synchronize all configured services from a confirmed payment.
     *
     * @return array<int, bool>
     */
    public function syncAfterPayment(User $user, iterable $servicios): array
    {
        return $this->syncForTiming($user, $servicios, self::TIMING_AFTER_PAYMENT);
    }

    /**
     * Synchronize all configured services after the client completes GetInfo.
     *
     * @return array<int, bool>
     */
    public function syncAfterGetInfo(User $user, iterable $servicios): array
    {
        return $this->syncForTiming($user, $servicios, self::TIMING_AFTER_GETINFO);
    }

    /**
     * Resolve the purchased services and synchronize only those configured for the given moment.
     *
     * @return array<int, bool>
     */
    public function syncPurchasedServices(User $user, iterable $compras, string $timing): array
    {
        $servicios = collect($compras)
            ->map(function ($compra): ?Servicio {
                if ($compra instanceof Compras) {
                    return $compra->servicio
                        ?: Servicio::find($compra->servicio_id)
                        ?: Servicio::where('id_hubspot', $compra->servicio_hs_id)->first();
                }

                $servicioId = data_get($compra, 'servicio_id');
                $serviceCode = data_get($compra, 'servicio_hs_id');

                return ($servicioId ? Servicio::find($servicioId) : null)
                    ?: ($serviceCode ? Servicio::where('id_hubspot', $serviceCode)->first() : null);
            })
            ->filter()
            ->unique('id')
            ->values();

        return $this->syncForTiming($user, $servicios, $timing);
    }

    /**
     * @param iterable<int, Servicio> $servicios
     * @return array<int, bool>
     */
    private function syncForTiming(User $user, iterable $servicios, string $timing): array
    {
        if (! in_array($timing, [self::TIMING_AFTER_PAYMENT, self::TIMING_AFTER_GETINFO], true)) {
            throw new \InvalidArgumentException("Momento de registro en Monday no válido: {$timing}");
        }

        return collect($servicios)
            ->filter(fn ($servicio): bool => $servicio instanceof Servicio)
            ->unique('id')
            ->filter(function (Servicio $servicio) use ($timing): bool {
                $configuredTiming = $servicio->monday_registration_timing ?: self::TIMING_AFTER_PAYMENT;

                if ($configuredTiming === $timing) {
                    return true;
                }

                Log::info('Registro en Monday aplazado hasta el momento configurado para el servicio', [
                    'servicio_id' => $servicio->id,
                    'configured_timing' => $configuredTiming,
                    'current_timing' => $timing,
                ]);

                return false;
            })
            ->mapWithKeys(fn (Servicio $servicio): array => [$servicio->id => $this->sync($user, $servicio)])
            ->all();
    }

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

            // Projecting a successful Monday registration into the new
            // canonical workflow is opt-in after the process audit. The old
            // Monday registration remains untouched while the gate is off.
            if (config('unification.canonical_writes_enabled') && Schema::hasTable('workflow_boards')) {
                try {
                    app(UnifiedClientProfileService::class)->recordMondayItem(
                        $user,
                        (string) $servicio->monday_board_id,
                        'Monday '.$servicio->monday_board_id,
                        (string) $servicio->monday_group_id,
                        (string) $servicio->monday_group_id,
                        (string) $itemId,
                        ['servicio_id' => $servicio->id],
                    );
                } catch (Throwable $exception) {
                    // The item already exists in Monday. Keep the successful
                    // registration and surface this repairable local mismatch.
                    Log::error('No se pudo registrar el item de Monday en el flujo unificado', [
                        'user_id' => $user->id,
                        'servicio_id' => $servicio->id,
                        'monday_item_id' => $itemId,
                        'error' => $exception->getMessage(),
                    ]);
                }
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
        $parents = $this->parentNames($user);
        $values = [
            'texto' => $user->passport ?: 'N/A',
            'enlace' => ['url' => $treeUrl, 'text' => $treeUrl],
            'estado54' => 'Arbol Incompleto',
            'texto1' => $servicio->id_hubspot ?: $servicio->nombre,
            'texto4' => $user->hs_id ?: '',
            'texto_largo88' => $user->nombre_de_familiar_realizando_procesos ?: '',
            // Column IDs from the client registration board: father and mother.
            'texto_largo8' => $parents['father'],
            'texto_largo75' => $parents['mother'],
        ];

        if ($user->date_of_birth) {
            $values['fecha75'] = ['date' => Carbon::parse($user->date_of_birth)->format('Y-m-d')];
        }

        return $values;
    }

    /**
     * GetInfo persists the direct parents as IDPersona 2 and 3. Querying by
     * those IDs is stable even when a tree has been manually reordered.
     *
     * @return array{father: string, mother: string}
     */
    private function parentNames(User $user): array
    {
        $passport = trim((string) $user->passport);

        if ($passport === '') {
            return ['father' => '', 'mother' => ''];
        }

        $parents = Agcliente::query()
            ->where('IDCliente', $passport)
            ->whereIn('IDPersona', [2, 3])
            ->get(['IDPersona', 'Nombres', 'Apellidos'])
            ->keyBy('IDPersona');

        return [
            'father' => $this->personName($parents->get(2)),
            'mother' => $this->personName($parents->get(3)),
        ];
    }

    private function personName(?Agcliente $person): string
    {
        if (! $person) {
            return '';
        }

        return trim(implode(' ', array_filter([
            trim((string) $person->Nombres),
            trim((string) $person->Apellidos),
        ])));
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
