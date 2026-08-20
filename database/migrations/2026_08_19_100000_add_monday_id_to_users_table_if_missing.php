<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'monday_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('monday_id')->nullable()->after('hs_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'monday_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('monday_id');
            });
        }
    }
};
