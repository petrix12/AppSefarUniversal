<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('consultation_bookings')) {
            Schema::create('consultation_bookings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('consultation_calendar_id')
                    ->constrained('consultation_calendars')
                    ->cascadeOnDelete();
                $table->unsignedBigInteger('servicio_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('compra_id')->nullable()->unique();
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->string('timezone')->default('America/Caracas');
                $table->string('status')->default('pending_payment')->index();
                $table->string('meeting_url')->nullable();
                $table->text('notes')->nullable();
                $table->string('hubspot_deal_id')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->index(['consultation_calendar_id', 'starts_at', 'ends_at'], 'consult_booking_calendar_range_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_bookings');
    }
};
