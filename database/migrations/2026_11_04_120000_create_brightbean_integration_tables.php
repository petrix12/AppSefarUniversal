<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brightbean_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('ai_enabled')->default(false);
            $table->text('instructions')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('brightbean_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('workspace_id');
            $table->uuid('external_id');
            $table->uuid('conversation_id');
            $table->uuid('contact_id');
            $table->uuid('connection_id');
            $table->string('platform', 32);
            $table->string('direction', 3);
            $table->string('source', 32)->nullable();
            $table->string('status', 32);
            $table->boolean('internal')->default(false);
            $table->text('text')->nullable();
            $table->json('body');
            $table->json('contact');
            $table->timestamp('source_created_at', 6);
            $table->timestamp('source_updated_at', 6);
            $table->text('ai_reply')->nullable();
            $table->timestamp('ai_replied_at')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'external_id'], 'brightbean_message_unique');
            $table->index(['workspace_id', 'conversation_id', 'source_created_at'], 'brightbean_conversation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brightbean_messages');
        Schema::dropIfExists('brightbean_settings');
    }
};
