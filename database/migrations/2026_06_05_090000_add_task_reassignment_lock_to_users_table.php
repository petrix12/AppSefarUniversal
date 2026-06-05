<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'task_reassignment_locked_at')) {
                $table->timestamp('task_reassignment_locked_at')
                    ->nullable()
                    ->after('last_task_reassigned_at');
            }

            if (! Schema::hasColumn('users', 'task_reassignment_locked_owner_id')) {
                $table->foreignId('task_reassignment_locked_owner_id')
                    ->nullable()
                    ->after('task_reassignment_locked_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'task_reassignment_locked_hubspot_owner_id')) {
                $table->string('task_reassignment_locked_hubspot_owner_id')
                    ->nullable()
                    ->after('task_reassignment_locked_owner_id')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'task_reassignment_locked_owner_id')) {
                $table->dropConstrainedForeignId('task_reassignment_locked_owner_id');
            }

            if (Schema::hasColumn('users', 'task_reassignment_locked_hubspot_owner_id')) {
                $table->dropIndex(['task_reassignment_locked_hubspot_owner_id']);
                $table->dropColumn('task_reassignment_locked_hubspot_owner_id');
            }

            if (Schema::hasColumn('users', 'task_reassignment_locked_at')) {
                $table->dropColumn('task_reassignment_locked_at');
            }
        });
    }
};
