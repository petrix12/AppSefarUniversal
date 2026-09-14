<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'revision_archivos')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('revision_archivos')->nullable()->index()->after('arraycos_expire');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'revision_archivos')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('revision_archivos');
            });
        }
    }
};
