<?php

namespace App\Console\Commands;

use App\Models\Lista;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ListImportProcesoSefardi extends Command
{
    protected $signature = 'lists:import-proceso-sefardi
        {--file= : Ruta absoluta del TXT (1 pasaporte por línea)}
        {--list="Proceso Sefardi" : Nombre de la lista}
        {--dry-run : No escribe, solo muestra}
        {--only-missing-owner : Solo procesa usuarios sin owner_id}
    ';

    protected $description = 'Importa pasaportes desde TXT, asigna owner_id equilibrado (least-loaded) si falta, y agrega usuarios a la lista.';

    public function handle(): int
    {
        $file = (string)($this->option('file') ?? '');
        $listName = trim((string)$this->option('list'));
        $dryRun = (bool)$this->option('dry-run');
        $onlyMissingOwner = (bool)$this->option('only-missing-owner');

        if ($file === '' || !is_file($file)) {
            $this->error('Debes pasar --file con ruta válida. Ej: --file="' . storage_path('app/passports.txt') . '"');
            return self::FAILURE;
        }

        // 1) Leer pasaportes (1 por línea)
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $passports = array_values(array_unique(array_filter(array_map(function ($x) {
            $x = trim((string)$x);
            return $x !== '' ? $x : null;
        }, $lines))));

        if (empty($passports)) {
            $this->warn('El TXT no contiene pasaportes válidos.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('DRY-RUN activo: no se escribirá en BD.');
        }

        // 2) Owners elegibles = users con hubspot_owner_id no vacío
        $ownerIds = User::query()
            ->whereNotNull('hubspot_owner_id')
            ->whereRaw("TRIM(hubspot_owner_id) <> ''")
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if (empty($ownerIds)) {
            $this->error('No hay owners disponibles (users con hubspot_owner_id).');
            return self::FAILURE;
        }

        // 3) Crear/obtener lista
        if ($dryRun) {
            $lista = Lista::query()->where('name', $listName)->first();
            if (!$lista) {
                $this->warn("DRY-RUN: la lista '{$listName}' se CREARÍA.");
                $lista = new Lista(['id' => 0, 'name' => $listName]);
            }
        } else {
            $lista = Lista::firstOrCreate(
                ['name' => $listName],
                [
                    'description' => null,
                    'owner_id' => null,
                    'created_by' => auth()->id() ?? null,
                ]
            );
        }

        $this->info("Lista: {$listName}" . ($dryRun ? " (simulada)" : " (id={$lista->id})"));
        $this->info("Pasaportes a procesar: " . count($passports));
        $this->info("Owners detectados: " . count($ownerIds));
        if ($onlyMissingOwner) $this->info("Modo: SOLO usuarios sin owner_id (--only-missing-owner)");

        // 4) Cargar users por passport
        $usersQ = User::query()
            ->select(['id', 'passport', 'owner_id'])
            ->whereIn('passport', $passports);

        if ($onlyMissingOwner) {
            $usersQ->whereNull('owner_id');
        }

        $usersByPassport = $usersQ->get()->keyBy(fn($u) => (string)$u->passport);

        // 5) Precargar quiénes ya están en la lista (para no duplicar)
        $existingUserIdsInList = [];
        if (!$dryRun && (int)$lista->id > 0) {
            $existingUserIdsInList = DB::table('list_user')
                ->where('list_id', $lista->id)
                ->pluck('user_id')
                ->all();

            $existingUserIdsInList = array_flip($existingUserIdsInList);
        }

        // 6) Carga actual por owner (para balanceo global)
        //    counts[owner_id] = cantidad de users que ya tienen ese owner_id
        $counts = DB::table('users')
            ->select('owner_id', DB::raw('COUNT(*) as c'))
            ->whereIn('owner_id', $ownerIds)
            ->groupBy('owner_id')
            ->pluck('c', 'owner_id')
            ->all();

        // Inicializar 0 para owners sin registros
        $ownerLoad = [];
        foreach ($ownerIds as $oid) {
            $ownerLoad[$oid] = (int)($counts[$oid] ?? 0);
        }

        // Helper: obtener owner menos cargado (tie-break por id)
        $pickLeastLoadedOwner = function () use (&$ownerLoad): int {
            $bestId = null;
            $bestLoad = null;

            foreach ($ownerLoad as $oid => $load) {
                if ($bestId === null || $load < $bestLoad || ($load === $bestLoad && $oid < $bestId)) {
                    $bestId = (int)$oid;
                    $bestLoad = (int)$load;
                }
            }

            return (int)$bestId;
        };

        // 7) Procesar
        $found = 0;
        $notFound = 0;
        $alreadyInList = 0;
        $added = 0;
        $ownersAssigned = 0;

        // Para auditoría (opcional): cuántos asigné por owner en esta corrida
        $assignedThisRun = array_fill_keys($ownerIds, 0);

        foreach ($passports as $p) {
            $u = $usersByPassport->get($p);

            if (!$u) {
                $notFound++;
                continue;
            }

            $found++;

            // Si ya tiene owner_id, no lo tocamos.
            // Si no tiene, asignamos al menos cargado.
            $newOwnerId = null;
            if (empty($u->owner_id)) {
                $newOwnerId = $pickLeastLoadedOwner();
                // incrementamos carga en memoria para mantener balance durante corrida
                $ownerLoad[$newOwnerId] = ($ownerLoad[$newOwnerId] ?? 0) + 1;
                $assignedThisRun[$newOwnerId] = ($assignedThisRun[$newOwnerId] ?? 0) + 1;
            }

            if ($dryRun) {
                $this->line(
                    "• {$p} => user_id={$u->id}"
                    . ($u->owner_id ? " | owner_id actual={$u->owner_id}" : " | owner_id se asignaría={$newOwnerId}")
                    . " | se agregaría a lista '{$listName}' (contacted=0)"
                );
                $added++;
                continue;
            }

            DB::transaction(function () use (
                $u,
                $newOwnerId,
                $lista,
                &$ownersAssigned,
                &$added,
                &$alreadyInList,
                &$existingUserIdsInList
            ) {
                // 1) Asignar owner si sigue NULL (seguro contra carreras)
                if ($newOwnerId) {
                    $affected = User::query()
                        ->where('id', $u->id)
                        ->whereNull('owner_id')
                        ->update(['owner_id' => $newOwnerId]);

                    if ($affected) {
                        $ownersAssigned++;
                    }
                }

                // 2) Insertar pivot sin duplicar
                if (isset($existingUserIdsInList[$u->id])) {
                    $alreadyInList++;
                    return;
                }

                DB::table('list_user')->insert([
                    'list_id'      => $lista->id,
                    'user_id'      => $u->id,
                    'contacted'    => 0,
                    'contacted_at' => null,
                    'contact_note' => null,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                $existingUserIdsInList[$u->id] = true;
                $added++;
            });
        }

        // 8) Reporte final
        $this->info("✅ Listo.");
        $this->info("Encontrados: {$found}");
        $this->info("No encontrados: {$notFound}");
        $this->info("Agregados a lista: {$added}");
        if (!$dryRun) {
            $this->info("Ya estaban en la lista: {$alreadyInList}");
            $this->info("Owner asignado (antes NULL): {$ownersAssigned}");
        }

        // 9) Resumen de equidad en esta corrida
        // Mostrar top 15 owners con más asignaciones en esta corrida (para validar balance)
        arsort($assignedThisRun);
        $summaryRows = [];
        $i = 0;
        foreach ($assignedThisRun as $oid => $cnt) {
            if ($cnt <= 0) continue;
            $summaryRows[] = [$oid, $cnt];
            $i++;
            if ($i >= 15) break;
        }
        if (!empty($summaryRows)) {
            $this->info("\nAsignaciones en esta corrida (top 15):");
            $this->table(['owner_id', 'nuevos_asignados'], $summaryRows);
        } else {
            $this->info("\nNo hubo asignaciones nuevas de owner_id (todos ya tenían owner_id o --only-missing-owner no encontró).");
        }

        return self::SUCCESS;
    }
}
