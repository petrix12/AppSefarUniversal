<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignRandomHubspotOwnerToUsersInAnyList extends Command
{
    protected $signature = 'hubspot:assign-random-owner-any-list
        {--dry-run : Solo muestra cambios, no escribe en BD}
        {--only-active-hubspot-owners : Solo usa owners activos}
        {--chunk=500 : Tamaño de bloque}
    ';

    protected $description = 'Asigna owner_id random a usuarios sin owner_id que estén en cualquier lista (list_user)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlyActive = (bool) $this->option('only-active-hubspot-owners');
        $chunkSize = (int) $this->option('chunk');

        // 1) Owners disponibles
        $ownersQ = DB::table('hubspot_owner_user as hou')
            ->join('users as u', 'u.id', '=', 'hou.user_id')
            ->whereNotNull('hou.hubspot_owner_id')
            ->whereRaw("TRIM(hou.hubspot_owner_id) <> ''")
            ->select('hou.user_id');

        if ($onlyActive) {
            $ownersQ->join('hubspot_owners as ho', 'ho.id', '=', 'hou.hubspot_owner_id')
                ->where('ho.active', 1);
        }

        $ownerIds = $ownersQ
            ->pluck('hou.user_id')
            ->unique()
            ->values()
            ->all();

        if (empty($ownerIds)) {
            $this->error('No hay owners disponibles.');
            return self::FAILURE;
        }

        $this->info("Owners disponibles: " . count($ownerIds));

        if ($dryRun) {
            $this->warn('DRY-RUN activo.');
        }

        // 2) Usuarios en cualquier lista SIN owner_id
        $query = User::query()
            ->select('users.id', 'users.email', 'users.owner_id')
            ->join('list_user', 'list_user.user_id', '=', 'users.id')
            ->whereNull('users.owner_id')
            ->groupBy('users.id', 'users.email', 'users.owner_id') // evita duplicados por múltiples listas
            ->orderBy('users.id');

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No hay usuarios sin owner_id en listas.');
            return self::SUCCESS;
        }

        $this->info("Usuarios a procesar: {$total}");

        $updated = 0;
        $preview = [];

        $query->chunkById($chunkSize, function ($users) use ($ownerIds, $dryRun, &$updated, &$preview) {

            foreach ($users as $user) {
                $randomOwnerId = $ownerIds[array_rand($ownerIds)];

                $preview[] = [
                    $user->id,
                    $user->email,
                    $user->owner_id,
                    $randomOwnerId,
                ];

                if (!$dryRun) {
                    $affected = User::query()
                        ->where('id', $user->id)
                        ->whereNull('owner_id')
                        ->update([
                            'owner_id' => $randomOwnerId,
                        ]);

                    $updated += $affected;
                }
            }

        }, 'users.id', 'id');

        $this->table(
            ['user_id', 'email', 'owner actual', 'owner nuevo'],
            array_slice($preview, 0, 20)
        );

        $this->info($dryRun
            ? "Usuarios que se actualizarían: " . count($preview)
            : "Usuarios actualizados: {$updated}"
        );

        return self::SUCCESS;
    }
}
