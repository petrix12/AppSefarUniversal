<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            if (! Schema::hasColumn('servicios', 'categoria')) {
                $table->string('categoria')->default('general')->after('precio');
            }

            if (! Schema::hasColumn('servicios', 'tipo')) {
                $table->string('tipo')->default('servicio')->after('categoria');
            }

            if (! Schema::hasColumn('servicios', 'descripcion_publica')) {
                $table->text('descripcion_publica')->nullable()->after('tipo');
            }

            if (! Schema::hasColumn('servicios', 'activo')) {
                $table->boolean('activo')->default(true)->after('descripcion_publica');
            }

            if (! Schema::hasColumn('servicios', 'visible_cliente')) {
                $table->boolean('visible_cliente')->default(false)->after('activo');
            }

            if (! Schema::hasColumn('servicios', 'moneda')) {
                $table->string('moneda', 3)->default('EUR')->after('visible_cliente');
            }

            if (! Schema::hasColumn('servicios', 'duracion_minutos')) {
                $table->unsignedSmallInteger('duracion_minutos')->nullable()->after('moneda');
            }

            if (! Schema::hasColumn('servicios', 'requiere_agenda')) {
                $table->boolean('requiere_agenda')->default(false)->after('duracion_minutos');
            }

            if (! Schema::hasColumn('servicios', 'orden')) {
                $table->unsignedInteger('orden')->default(0)->after('requiere_agenda');
            }

            if (! Schema::hasColumn('servicios', 'hubspot_pipeline_id')) {
                $table->string('hubspot_pipeline_id')->nullable()->after('orden');
            }

            if (! Schema::hasColumn('servicios', 'hubspot_stage_id')) {
                $table->string('hubspot_stage_id')->nullable()->after('hubspot_pipeline_id');
            }

            if (! Schema::hasColumn('servicios', 'metadata')) {
                $table->json('metadata')->nullable()->after('hubspot_stage_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            foreach ([
                'metadata',
                'hubspot_stage_id',
                'hubspot_pipeline_id',
                'orden',
                'requiere_agenda',
                'duracion_minutos',
                'moneda',
                'visible_cliente',
                'activo',
                'descripcion_publica',
                'tipo',
                'categoria',
            ] as $column) {
                if (Schema::hasColumn('servicios', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
