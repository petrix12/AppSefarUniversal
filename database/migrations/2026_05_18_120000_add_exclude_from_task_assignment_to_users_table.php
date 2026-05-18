<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'exclude_from_task_assignment')) {
                $table->boolean('exclude_from_task_assignment')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'exclude_from_task_assignment')) {
                $table->dropColumn('exclude_from_task_assignment');
            }
        });
    }
};
