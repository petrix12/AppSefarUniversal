<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('metodo_pago_preferido', 80)->nullable()->after('address');

            $table->timestamp('fecha_activacion_proveedor')->nullable()->after('metodo_pago_preferido');
            $table->string('estado_vendedor', 20)->default('Pendiente')->after('fecha_activacion_proveedor');

            $table->string('motivo_coordinador', 255)->nullable()->after('estado_vendedor');
            $table->tinyInteger('tiene_contactos_sociales')->default(0)->after('motivo_coordinador');

            $table->tinyInteger('acepta_politicas_comisiones')->default(0)->after('tiene_contactos_sociales');

            // opcional: index por estado si vas a filtrar mucho
            $table->index('estado_vendedor');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['estado_vendedor']);
            $table->dropColumn([
                'metodo_pago_preferido',
                'fecha_activacion_proveedor',
                'estado_vendedor',
                'motivo_coordinador',
                'tiene_contactos_sociales',
                'acepta_politicas_comisiones',
            ]);
        });
    }
};
