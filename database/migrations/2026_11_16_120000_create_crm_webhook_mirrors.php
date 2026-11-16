<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crm_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('event_key', 64);
            $table->json('payload'); // Routing metadata only; no property values or credentials.
            $table->string('status', 20)->default('pending')->index();
            $table->string('error_class')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_key']);
        });
        Schema::create('crm_contact_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('contact_id', 64);
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('classification', 20)->index();
            $table->json('deal_ids');
            $table->json('contact')->nullable();
            $table->string('content_hash', 64);
            $table->timestamp('synced_at');
            $table->timestamps();
            $table->unique(['provider', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_contact_snapshots');
        Schema::dropIfExists('crm_webhook_events');
    }
};
