<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('compras', 'deal_id')) {
            Schema::table('compras', function (Blueprint $table) {
                $table->unsignedBigInteger('deal_id')->nullable()->index()->after('id_user');
            });
        }

        if (! Schema::hasColumn('compras', 'phasenum')) {
            Schema::table('compras', function (Blueprint $table) {
                $table->unsignedTinyInteger('phasenum')->nullable()->after('deal_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            if (Schema::hasColumn('compras', 'phasenum')) {
                $table->dropColumn('phasenum');
            }

            if (Schema::hasColumn('compras', 'deal_id')) {
                $table->dropIndex(['deal_id']);
                $table->dropColumn('deal_id');
            }
        });
    }
};
