<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')          // Cliente
                  ->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')     // Admin que creó la solicitud
                  ->constrained('users')->cascadeOnDelete();
            $table->string('document_name');
            $table->enum('document_type', ['juridico','genealogico']);
            $table->enum('status', [
                'en_espera_cliente',
                'resuelto',
                'no_documento',
                'aprobada',
                'rechazada'
            ])->default('en_espera_cliente');
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamp('no_document_button_at')
                  ->nullable();          // fecha en que aparece el botón (created_at + 1 mes)
            $table->string('file_path')->nullable();  // S3 (lo llenas tú luego)
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('document_requests');
    }
};
