<?php

// database/migrations/xxxx_xx_xx_create_list_user_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('list_user', function (Blueprint $table) {
      $table->id();

      $table->foreignId('list_id')->constrained('lists')->cascadeOnDelete();
      $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

      // ✅ tracking de contacto
      $table->boolean('contacted')->default(false);
      $table->dateTime('contacted_at')->nullable();

      // opcional: notas del contacto
      $table->text('contact_note')->nullable();

      $table->timestamps();

      $table->unique(['list_id', 'user_id']);
      $table->index(['list_id', 'contacted']);
    });
  }

  public function down(): void {
    Schema::dropIfExists('list_user');
  }
};
