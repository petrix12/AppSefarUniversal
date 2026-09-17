<?php

use App\Services\GenealogyTreeServiceMatcher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('servicios') || ! Schema::hasColumn('servicios', 'monday_registration_timing')) {
            return;
        }

        if (! Schema::hasColumn('servicios', 'requires_getinfo')) {
            Schema::table('servicios', function (Blueprint $table) {
                $table->boolean('requires_getinfo')
                    ->default(false)
                    ->after('monday_registration_timing');
            });
        }

        DB::table('servicios')
            ->select(['id', 'id_hubspot', 'nombre'])
            ->orderBy('id')
            ->chunkById(100, function ($servicios): void {
                foreach ($servicios as $servicio) {
                    if (GenealogyTreeServiceMatcher::requiresGetInfo($servicio->id_hubspot, $servicio->nombre)) {
                        DB::table('servicios')
                            ->where('id', $servicio->id)
                            ->update([
                                'requires_getinfo' => true,
                                'monday_registration_timing' => 'after_getinfo',
                            ]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('servicios') && Schema::hasColumn('servicios', 'requires_getinfo')) {
            Schema::table('servicios', function (Blueprint $table) {
                $table->dropColumn('requires_getinfo');
            });
        }
    }
};
