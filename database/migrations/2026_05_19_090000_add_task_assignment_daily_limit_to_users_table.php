<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'task_assignment_daily_limit')) {
                $table->unsignedSmallInteger('task_assignment_daily_limit')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'task_assignment_daily_limit')) {
                $table->dropColumn('task_assignment_daily_limit');
            }
        });
    }
};
