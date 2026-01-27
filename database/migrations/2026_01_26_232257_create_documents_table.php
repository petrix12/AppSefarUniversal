<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();

            // Info básica
            $table->string('title');
            $table->text('description')->nullable();

            // Organización
            $table->string('category')->nullable(); // guias, manuales, scripts, etc.

            // Storage (S3)
            $table->string('disk')->default('s3');
            $table->string('path');                 // documents/guias/2026/01/archivo.pdf
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);

            // Control de acceso
            $table->string('visibility')->default('coordventas');
            // proveedores | todos | admins

            // Auditoría
            $table->unsignedBigInteger('uploaded_by')->nullable();

            $table->timestamps();

            // Índices útiles
            $table->index('visibility');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
