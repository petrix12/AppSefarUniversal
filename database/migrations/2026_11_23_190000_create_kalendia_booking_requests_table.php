<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kalendia_booking_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('compra_id')->unique()->constrained('compras')->cascadeOnDelete();
            $table->string('status', 20);
            $table->date('requested_date')->nullable();
            $table->text('input')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('available_at');
            $table->uuid('lease')->nullable();
            $table->timestamp('leased_until')->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at', 'id'], 'kalendia_booking_queue_dispatch');
            $table->index(['user_id', 'status'], 'kalendia_booking_queue_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kalendia_booking_requests');
    }
};
