<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('tasks', 'task_pool_list_name')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->text('task_pool_list_name')
                    ->nullable()
                    ->after('created_by_user_id');
            });
        }

        if (! Schema::hasColumn('tasks', 'skip_hubspot_reassignment')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->boolean('skip_hubspot_reassignment')
                    ->default(false)
                    ->after('task_pool_list_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tasks', 'skip_hubspot_reassignment')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropColumn('skip_hubspot_reassignment');
            });
        }

        if (Schema::hasColumn('tasks', 'task_pool_list_name')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropColumn('task_pool_list_name');
            });
        }
    }
};
