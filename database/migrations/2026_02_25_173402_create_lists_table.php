<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('lists', function (Blueprint $table) {
      $table->id();
      $table->string('name');
      $table->text('description')->nullable();

      // dueño/propietario de la lista (si quieres)
      $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();

      // quién la creó
      $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

      $table->timestamps();
    });
  }

  public function down(): void {
    Schema::dropIfExists('lists');
  }
};
