<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kalendia_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('compra_id')->unique()->constrained('compras');
            $table->uuid('idempotency_key')->unique();
            $table->string('status')->default('processing');
            $table->timestamp('leased_until')->nullable();
            $table->uuid('lease')->nullable();
            $table->string('remote_id')->nullable()->unique();
            $table->string('remote_customer_id')->nullable();
            $table->string('remote_status')->nullable();
            $table->string('remote_service_id');
            $table->date('date');
            $table->string('time', 5);
            $table->string('timezone')->default('America/Bogota');
            $table->decimal('paid_amount', 14, 2);
            $table->string('currency', 3);
            $table->longText('request_payload');
            $table->text('meeting_url')->nullable();
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kalendia_bookings');
    }
};
