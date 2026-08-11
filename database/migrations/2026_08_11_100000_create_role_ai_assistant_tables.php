<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_ai_assistants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')
                ->constrained(config('permission.table_names.roles', 'roles'))
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('model')->nullable();
            $table->longText('instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('training_enabled')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('role_id');
        });

        Schema::create('role_ai_knowledge_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_id')->constrained('role_ai_assistants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title')->nullable();
            $table->longText('content');
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['assistant_id', 'status']);
            $table->index('user_id');
        });

        Schema::create('role_ai_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_id')->constrained('role_ai_assistants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('session_id')->unique();
            $table->json('messages');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['assistant_id', 'user_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_ai_chat_sessions');
        Schema::dropIfExists('role_ai_knowledge_entries');
        Schema::dropIfExists('role_ai_assistants');
    }
};
