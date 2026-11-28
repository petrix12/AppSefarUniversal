<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_mail_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('from_email');
            $table->json('recipients');
            $table->string('subject')->nullable();
            $table->boolean('is_test')->default(false);
            $table->string('status', 30)->default('sending')->index();
            $table->string('provider_message_id')->nullable()->index();
            $table->string('error_code', 100)->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('complained_at')->nullable();
            $table->timestamps();
        });

        Schema::create('app_mail_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_mail_log_id')->constrained('app_mail_logs')->cascadeOnDelete();
            $table->string('provider_event_id')->unique();
            $table->string('event_type', 30);
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_mail_events');
        Schema::dropIfExists('app_mail_logs');
    }
};
