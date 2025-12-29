<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('registration_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence'); // 1,2,3...
            $table->date('scheduled_for');       // fecha exacta (created_at + N*15d)
            $table->timestamp('sent_at')->nullable();
            $table->string('subject', 255);
            $table->timestamps();

            $table->unique(['user_id', 'scheduled_for']); // evita duplicado por día/usuario
        });
    }

    public function down(): void {
        Schema::dropIfExists('registration_followups');
    }
};
