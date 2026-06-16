<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('consultation_calendars')) {
            Schema::create('consultation_calendars', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('servicio_id')->nullable()->index();
                $table->string('nombre');
                $table->text('descripcion')->nullable();
                $table->string('timezone')->default('America/Caracas');
                $table->unsignedSmallInteger('slot_duration_minutes')->default(60);
                $table->unsignedSmallInteger('buffer_minutes')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('consultation_availability_rules')) {
            Schema::create('consultation_availability_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('consultation_calendar_id')
                    ->constrained('consultation_calendars')
                    ->cascadeOnDelete();
                $table->unsignedTinyInteger('weekday');
                $table->time('starts_at');
                $table->time('ends_at');
                $table->unsignedSmallInteger('slot_duration_minutes')->nullable();
                $table->unsignedSmallInteger('buffer_minutes')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->index(['consultation_calendar_id', 'weekday'], 'consult_avail_calendar_weekday_idx');
            });
        }

        if (! Schema::hasTable('consultation_blackouts')) {
            Schema::create('consultation_blackouts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('consultation_calendar_id')
                    ->constrained('consultation_calendars')
                    ->cascadeOnDelete();
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->string('reason')->nullable();
                $table->timestamps();

                $table->index(['consultation_calendar_id', 'starts_at', 'ends_at'], 'consult_blackout_calendar_range_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_blackouts');
        Schema::dropIfExists('consultation_availability_rules');
        Schema::dropIfExists('consultation_calendars');
    }
};
