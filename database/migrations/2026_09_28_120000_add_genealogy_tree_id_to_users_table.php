<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'genealogy_tree_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('genealogy_tree_id', 175)
                ->nullable()
                ->after('passport');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'genealogy_tree_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('genealogy_tree_id');
            });
        }
    }
};
