<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kalendia_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('business_id');
            $table->string('event_type', 80);
            $table->string('resource_id');
            $table->dateTime('occurred_at');
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->dateTime('next_attempt_at')->nullable();
            $table->uuid('lease')->nullable();
            $table->dateTime('leased_until')->nullable();
            $table->string('last_error_code', 80)->nullable();
            $table->timestamps();
            $table->index(['status', 'next_attempt_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('kalendia_webhook_events'); }
};
