<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'last_task_reassigned_at')) {
                $column = $table->timestamp('last_task_reassigned_at')->nullable();

                if (Schema::hasColumn('users', 'task_assignment_daily_limit')) {
                    $column->after('task_assignment_daily_limit');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'last_task_reassigned_at')) {
                $table->dropColumn('last_task_reassigned_at');
            }
        });
    }
};
