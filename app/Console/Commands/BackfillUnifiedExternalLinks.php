<?php

namespace App\Console\Commands;

use App\Models\MondayServiceRegistration;
use App\Models\User;
use App\Services\UnifiedClientProfileService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BackfillUnifiedExternalLinks extends Command
{
    protected $signature = 'unified:backfill-external-links
        {--write : Persiste los enlaces. Sin esta opción solo genera el reporte.}';

    protected $description = 'Crea los enlaces canónicos a HubSpot, Teamleader y Monday desde los IDs actuales de users.';

    public function handle(UnifiedClientProfileService $profiles): int
    {
        $stats = [
            'hubspot' => 0,
            'teamleader' => 0,
            'monday' => 0,
            'workflow_memberships' => 0,
            'conflicts' => 0,
        ];
        $write = (bool) $this->option('write');
        $columns = collect(['hs_id', 'tl_id', 'monday_id'])
            ->filter(fn (string $column) => Schema::hasColumn('users', $column));

        User::query()
            ->select(array_merge(['id'], $columns->all()))
            ->orderBy('id')
            ->chunkById(500, function ($users) use ($profiles, $write, &$stats): void {
                foreach ($users as $user) {
                    foreach ([
                        'hubspot' => ['column' => 'hs_id', 'entity' => 'contact'],
                        'teamleader' => ['column' => 'tl_id', 'entity' => 'contact'],
                        'monday' => ['column' => 'monday_id', 'entity' => 'item'],
                    ] as $provider => $reference) {
                        $id = $user->{$reference['column']} ?? null;

                        if (blank($id)) {
                            continue;
                        }

                        $stats[$provider]++;

                        if (! $write) {
                            continue;
                        }

                        try {
                            $profiles->linkExternalEntity($user, $provider, $reference['entity'], $id, [
                                'backfilled_from' => "users.{$reference['column']}",
                            ]);
                        } catch (Throwable $exception) {
                            $stats['conflicts']++;
                            $this->warn("{$provider}/{$id}: {$exception->getMessage()}");
                        }
                    }
                }
            });

        if (Schema::hasTable('monday_service_registrations')) {
            MondayServiceRegistration::query()
                ->whereNotNull('monday_item_id')
                ->orderBy('id')
                ->chunkById(250, function ($registrations) use ($profiles, $write, &$stats): void {
                    foreach ($registrations as $registration) {
                        $stats['workflow_memberships']++;

                        if (! $write || ! $registration->user_id || blank($registration->board_id) || blank($registration->group_id)) {
                            continue;
                        }

                        $client = User::find($registration->user_id);

                        if (! $client) {
                            $stats['conflicts']++;
                            $this->warn("Registro Monday {$registration->id}: el cliente ya no existe.");
                            continue;
                        }

                        try {
                            $profiles->recordMondayItem(
                                $client,
                                $registration->board_id,
                                'Monday '.$registration->board_id,
                                $registration->group_id,
                                $registration->group_id,
                                $registration->monday_item_id,
                                ['service_registration_id' => $registration->id],
                                'legacy_monday',
                            );
                        } catch (Throwable $exception) {
                            $stats['conflicts']++;
                            $this->warn("Registro Monday {$registration->id}: {$exception->getMessage()}");
                        }
                    }
                });
        }

        $this->table(['Elemento', $write ? 'Procesados' : 'Detectados'], [
            ['Enlaces HubSpot', $stats['hubspot']],
            ['Enlaces Teamleader', $stats['teamleader']],
            ['Enlaces Monday', $stats['monday']],
            ['Membresías Monday', $stats['workflow_memberships']],
            ['Conflictos', $stats['conflicts']],
        ]);

        if (! $write) {
            $this->comment('Modo seguro: no se escribió ningún dato. Repite con --write después de revisar el reporte.');
        }

        return $stats['conflicts'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
