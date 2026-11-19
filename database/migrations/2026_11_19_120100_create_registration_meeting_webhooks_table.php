<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_meeting_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->unique()->constrained('compras');
            $table->uuid('event_id')->unique();
            $table->json('payload');
            $table->string('status')->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->uuid('lease')->nullable();
            $table->timestamp('leased_until')->nullable();
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_meeting_webhooks');
    }
};
