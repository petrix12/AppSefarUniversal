<?php

namespace App\Services;

use App\Models\ClientRestrictionSync;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ClientBlockPropagationService
{
    public function sync(ClientRestrictionSync $state): void
    {
        Cache::lock('client-block:'.$state->user_id.':'.$state->platform, 120)->get(function () use ($state): void {
            $state->refresh();
            $version = $state->version;
            $user = User::findOrFail($state->user_id);
            $config = config('client_restrictions.'.$state->platform, []);
            $status = 'configuration_required';
            $error = 'Configura la marca externa y los permisos nativos de esta plataforma. El bloqueo de la App permanece activo.';
            try {
                if ($config['enabled'] ?? false) {
                    match ($state->platform) {
                        'hubspot' => $this->hubspot($user, $state->blocked, $config),
                        'monday' => $this->monday($user, $state->blocked, $config),
                        'teamleader' => $this->teamleader($user, $state->blocked, $config),
                    };
                    $status = 'marker_synced';
                    $error = null;
                }
            } catch (Throwable $exception) {
                $status = 'failed';
                $error = mb_substr($exception->getMessage(), 0, 2000);
            }
            // A concurrent block/unblock supersedes this result and is retried.
            ClientRestrictionSync::whereKey($state->id)->where('version', $version)->update([
                'status' => $status, 'last_error' => $error, 'attempts' => $state->attempts + 1,
                'synced_at' => $status === 'marker_synced' ? now() : null,
                'retry_at' => $status === 'marker_synced' ? null : now()->addMinutes($status === 'failed' ? 5 : 60),
            ]);
        });
    }

    private function hubspot(User $user, bool $blocked, array $config): void
    {
        throw_if(blank($user->hs_id), \RuntimeException::class, 'Cliente sin ID de HubSpot; requiere vinculación.');
        app(HubspotService::class)->updateContact($user->hs_id, [$config['property'] => $blocked ? 'true' : 'false']);
    }

    private function monday(User $user, bool $blocked, array $config): void
    {
        $ids = collect([$user->monday_id]);
        if (\Schema::hasTable('monday_service_registrations')) {
            $ids = $ids->merge(\App\Models\MondayServiceRegistration::where('user_id', $user->id)->pluck('monday_item_id'));
        }
        $ids = $ids->filter()->unique();
        throw_if($ids->isEmpty(), \RuntimeException::class, 'Cliente sin elementos vinculados de Monday.');
        $api = app(MondayApiClient::class);
        foreach ($ids as $id) {
            $item = $api->item((string) $id);
            $boardId = data_get($item, 'board.id');
            throw_if(blank($boardId), \RuntimeException::class, 'No se pudo resolver el tablero de Monday.');
            $data = $api->query(<<<'GRAPHQL'
                mutation ClientBlock($board: ID!, $item: ID!, $values: JSON!) {
                    change_multiple_column_values(board_id: $board, item_id: $item, column_values: $values) { id }
                }
                GRAPHQL, ['board' => (string) $boardId, 'item' => (string) $id,
                    'values' => json_encode([$config['column'] => ['label' => $blocked ? $config['blocked_label'] : $config['active_label']]])]);
            throw_if(blank(data_get($data, 'change_multiple_column_values.id')), \RuntimeException::class, 'Monday no confirmó la actualización.');
        }
    }

    private function teamleader(User $user, bool $blocked, array $config): void
    {
        throw_if(blank($user->tl_id), \RuntimeException::class, 'Cliente sin ID de Teamleader; requiere vinculación.');
        app(TeamleaderService::class)->setClientDeliveryBlockTag((string) $user->tl_id, $config['tag'], $blocked);
    }
}
