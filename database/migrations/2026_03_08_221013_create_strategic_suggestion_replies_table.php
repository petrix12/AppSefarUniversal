<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategic_suggestion_replies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('suggestion_id')
                ->constrained('strategic_suggestions')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->longText('message');
            $table->boolean('is_admin_reply')->default(false);

            $table->timestamps();

            $table->index(['suggestion_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategic_suggestion_replies');
    }
};
