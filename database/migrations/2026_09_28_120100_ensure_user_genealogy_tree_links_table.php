<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_genealogy_tree_links')) {
            Schema::create('user_genealogy_tree_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('tree_id', 175);
                $table->timestamps();

                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            });
        }

        // Supports environments where the first version of the migration was
        // already applied with a column in users before this link table existed.
        if (! Schema::hasColumn('users', 'genealogy_tree_id')) {
            return;
        }

        DB::table('users')
            ->select(['id', 'genealogy_tree_id'])
            ->whereNotNull('genealogy_tree_id')
            ->where('genealogy_tree_id', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                $now = now();

                foreach ($users as $user) {
                    DB::table('user_genealogy_tree_links')->updateOrInsert(
                        ['user_id' => $user->id],
                        [
                            'tree_id' => trim($user->genealogy_tree_id),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            });
    }

    public function down(): void
    {
        // The original migration owns the table when both are rolled back.
    }
};
