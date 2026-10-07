<?php

namespace App\Services\SystemsActivitySources;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AppActivitySource implements SystemsActivitySource
{
    public function collect(string $email, CarbonImmutable $from, CarbonImmutable $to): array
    {
        // App timestamps are stored in the application's configured timezone.
        $from = $from->setTimezone((string) config('app.timezone'));
        $to = $to->setTimezone((string) config('app.timezone'));
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->first(['id']);

        if ($user === null) {
            return [];
        }

        $events = DB::table('workflow_transitions as transition')
            ->leftJoin('workflow_stages as previous_stage', 'previous_stage.id', '=', 'transition.from_workflow_stage_id')
            ->leftJoin('workflow_stages as next_stage', 'next_stage.id', '=', 'transition.to_workflow_stage_id')
            ->where('transition.actor_user_id', $user->id)
            ->where('transition.source', 'app')
            ->where('transition.created_at', '>=', $from)
            ->where('transition.created_at', '<', $to)
            ->orderBy('transition.created_at')
            ->get([
                'transition.created_at',
                'transition.entity_type',
                'previous_stage.name as previous_stage',
                'next_stage.name as next_stage',
            ])
            ->map(fn ($event): array => [
                'source' => 'AppSefarUniversal',
                'occurred_at' => (string) $event->created_at,
                'kind' => 'workflow_transition',
                'title' => 'Cambio de etapa',
                'detail' => trim(($event->previous_stage ?: 'inicio').' → '.($event->next_stage ?: 'fin')),
            ])
            ->all();

        if (Schema::hasTable('user_change_audits')) {
            $changes = DB::table('user_change_audits')
                ->where('changed_by_user_id', $user->id)
                ->where('created_at', '>=', $from)
                ->where('created_at', '<', $to)
                ->orderBy('created_at')
                ->get(['created_at'])
                ->map(fn ($event): array => [
                    'source' => 'AppSefarUniversal',
                    'occurred_at' => (string) $event->created_at,
                    'kind' => 'user_profile_change',
                    'title' => 'Actualización de perfil de usuario',
                    'detail' => 'Se registró un cambio de perfil; los valores personales se excluyeron del resumen.',
                ])
                ->all();

            $events = array_merge($events, $changes);
        }

        return $events;
    }
}
