<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lists', function (Blueprint $table) {
            $table->boolean('include_in_task_pool')
                ->default(true)
                ->after('created_by');

            $table->boolean('disable_hubspot_reassignment')
                ->default(false)
                ->after('include_in_task_pool');
        });
    }

    public function down(): void
    {
        Schema::table('lists', function (Blueprint $table) {
            $table->dropColumn([
                'include_in_task_pool',
                'disable_hubspot_reassignment',
            ]);
        });
    }
};
