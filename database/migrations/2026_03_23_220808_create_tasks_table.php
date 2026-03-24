<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Assignee
            $table->foreignId('contact_id')->constrained('users')->onDelete('cascade'); // Contacto (assuming User model)
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date'); // La fecha límite del día
            $table->string('status')->default('pending'); // pending, in_progress, completed, canceled
            $table->boolean('call_effective')->nullable(); // null: no llamada, 0: No, 1: Sí
            $table->string('reason_no_effective')->nullable(); // no_attend, wrong_number, etc.
            $table->boolean('interest_level')->nullable(); // null: no determinada, 0: No, 1: Sí
            $table->string('reason_no_interest')->nullable(); // Motivo de desinterés
            $table->string('product_of_interest')->nullable(); // Producto de interés
            $table->date('follow_up_date')->nullable(); // Para generar la tarea futura
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null'); // Auditoría
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
