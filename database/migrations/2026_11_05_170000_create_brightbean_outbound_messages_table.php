<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brightbean_outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->unsignedBigInteger('created_by_user_id');
            $table->uuid('workspace_id');
            $table->uuid('conversation_id');
            $table->uuid('contact_id');
            $table->uuid('connection_id');
            $table->text('text');
            $table->string('status', 20)->default('queued');
            $table->uuid('external_id')->nullable();
            $table->uuid('lease_token')->nullable();
            $table->timestamp('leased_until')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('error_code', 64)->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'status', 'leased_until'], 'brightbean_outbox_pending_idx');
            $table->index(['workspace_id', 'conversation_id'], 'brightbean_outbox_conversation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brightbean_outbound_messages');
    }
};
