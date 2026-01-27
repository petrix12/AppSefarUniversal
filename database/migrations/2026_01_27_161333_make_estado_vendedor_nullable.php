<?php

// database/migrations/2026_01_27_000002_make_estado_vendedor_nullable.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // estado_vendedor: NULL = aprobado, 'Pendiente' = pendiente
            $table->string('estado_vendedor', 20)->nullable()->default(null)->change();

            // opcional: si quieres, deja fecha_activacion_proveedor nullable (ya lo es)
            $table->timestamp('fecha_activacion_proveedor')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('estado_vendedor', 20)->nullable(false)->default('Pendiente')->change();
        });
    }
};
