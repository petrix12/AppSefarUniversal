<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_change_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->json('old_values');
            $table->json('new_values');
            $table->string('source')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('changed_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['user_id', 'created_at']);
            $table->index(['changed_by_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_change_audits');
    }
};
