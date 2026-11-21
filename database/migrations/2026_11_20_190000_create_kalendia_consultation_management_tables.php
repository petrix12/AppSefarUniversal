<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kalendia_consultation_offers', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('type', 100); $t->text('description')->nullable();
            $t->string('remote_service_id'); $t->decimal('price', 12, 2)->nullable();
            $t->string('currency', 3)->default('EUR'); $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::create('kalendia_consultation_orders', function (Blueprint $t) {
            $t->id(); $t->uuid('uuid')->unique(); $t->uuid('checkout_key')->unique();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('offer_id')->constrained('kalendia_consultation_offers');
            $t->foreignId('compra_id')->nullable()->unique()->constrained('compras');
            $t->string('name'); $t->string('type', 100); $t->string('remote_service_id');
            $t->decimal('amount', 12, 2); $t->string('currency', 3)->default('EUR');
            $t->string('status')->default('pending')->index(); $t->string('payment_intent_id')->nullable()->unique();
            $t->timestamp('payment_started_at')->nullable(); $t->timestamp('paid_at')->nullable();
            $t->uuid('lease')->nullable(); $t->timestamp('leased_until')->nullable(); $t->timestamps();
        });
        Schema::create('kalendia_appointments', function (Blueprint $t) {
            $t->id(); $t->string('remote_id')->unique(); $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('remote_customer_id')->nullable()->index(); $t->text('customer')->nullable();
            $t->string('remote_service_id')->nullable(); $t->string('service_name')->nullable();
            $t->string('staff_id')->nullable(); $t->string('staff_name')->nullable();
            $t->dateTime('starts_at')->index(); $t->dateTime('ends_at'); $t->string('timezone')->default('America/Bogota');
            $t->string('status', 60); $t->text('meeting_url')->nullable(); $t->timestamp('checked_at'); $t->timestamps();
            $t->index(['user_id','starts_at']);
        });
        Schema::create('kalendia_appointment_actions', function (Blueprint $t) {
            $t->id(); $t->uuid('uuid')->unique(); $t->foreignId('actor_id')->constrained('users');
            $t->foreignId('appointment_id')->constrained('kalendia_appointments');
            $t->string('action', 20); $t->longText('payload'); $t->string('status')->default('processing');
            $t->uuid('lease')->nullable(); $t->timestamp('leased_until')->nullable();
            $t->unsignedSmallInteger('http_status')->nullable(); $t->timestamps();
        });
        Schema::create('kalendia_sync_states', function (Blueprint $t) {
            $t->id(); $t->string('name')->unique(); $t->timestamp('since')->nullable();
            $t->timestamp('window_started_at')->nullable(); $t->unsignedInteger('page')->default(1);
            $t->uuid('lease')->nullable(); $t->timestamp('leased_until')->nullable(); $t->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['kalendia_sync_states','kalendia_appointment_actions','kalendia_appointments','kalendia_consultation_orders','kalendia_consultation_offers'] as $table) Schema::dropIfExists($table);
    }
};
