<?php

namespace Database\Seeders;

use App\Services\BancaOnlineCatalog;
use Illuminate\Database\Seeder;

class BancaOnline2026Seeder extends Seeder
{
    public function run(BancaOnlineCatalog $catalog): void
    {
        $purge = $catalog->purgeSeededCatalog();
        $result = $catalog->syncBaseCatalog();

        $this->command?->info(
            "Banca Online 2026 reiniciada. Eliminados: {$purge['deleted']}. Creados: {$result['created']}. Actualizados: {$result['updated']}."
        );
    }
}
