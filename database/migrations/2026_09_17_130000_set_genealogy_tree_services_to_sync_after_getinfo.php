<?php

use App\Services\GenealogyTreeServiceMatcher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('servicios') || ! Schema::hasColumn('servicios', 'monday_registration_timing')) {
            return;
        }

        DB::table('servicios')
            ->select(['id', 'id_hubspot', 'nombre'])
            ->orderBy('id')
            ->chunkById(100, function ($servicios): void {
                foreach ($servicios as $servicio) {
                    if (GenealogyTreeServiceMatcher::requiresGetInfo($servicio->id_hubspot, $servicio->nombre)) {
                        DB::table('servicios')
                            ->where('id', $servicio->id)
                            ->update(['monday_registration_timing' => 'after_getinfo']);
                    }
                }
            });
    }

    public function down(): void
    {
        // The earlier per-service value cannot be reconstructed safely.
    }
};
