<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\TeamleaderService;

class UpdateModuloFromTeamleader extends Command
{
    protected $signature = 'teamleader:update-modulo';
    protected $description = 'Actualizar el campo modulo en assoc_tl_hs usando la API de Teamleader';

    public function handle(TeamleaderService $teamleaderService)
    {
        // 1. Obtener todos los registros de la tabla
        $records = DB::table('assoc_tl_hs')->get();

        // 2. Iterar sobre cada registro
        foreach ($records as $record) {
            // 3. Llamar a la API de Teamleader con el tl_id
            $module = $teamleaderService->getModuleByFieldId($record->tl_id);

            // 4. Actualizar el campo 'modulo'
            DB::table('assoc_tl_hs')->where('tl_id', $record->tl_id)->update(['modulo' => $module]);

            $this->info("Registro ID {$record->tl_id} actualizado con módulo: {$module}");
        }

        $this->info("Proceso completado.");
    }
}
