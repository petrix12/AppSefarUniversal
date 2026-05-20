<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('agclientes')) {
            return;
        }

        Schema::table('agclientes', function (Blueprint $table) {
            if (!Schema::hasColumn('agclientes', 'colorLineaPadre')) {
                $table->string('colorLineaPadre', 7)->nullable()->after('idMadreNew');
            }

            if (!Schema::hasColumn('agclientes', 'colorLineaMadre')) {
                $table->string('colorLineaMadre', 7)->nullable()->after('colorLineaPadre');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('agclientes')) {
            return;
        }

        Schema::table('agclientes', function (Blueprint $table) {
            if (Schema::hasColumn('agclientes', 'colorLineaMadre')) {
                $table->dropColumn('colorLineaMadre');
            }

            if (Schema::hasColumn('agclientes', 'colorLineaPadre')) {
                $table->dropColumn('colorLineaPadre');
            }
        });
    }
};
