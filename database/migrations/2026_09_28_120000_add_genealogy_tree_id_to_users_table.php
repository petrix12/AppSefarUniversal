<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_genealogy_tree_links')) {
            return;
        }

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

    public function down(): void
    {
        if (Schema::hasTable('user_genealogy_tree_links')) {
            Schema::drop('user_genealogy_tree_links');
        }
    }
};
