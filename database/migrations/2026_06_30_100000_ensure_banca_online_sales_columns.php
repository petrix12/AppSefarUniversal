<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureServiciosColumns();
        $this->ensureComprasColumns();
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            foreach (['paid_at', 'metadata', 'source', 'servicio_id'] as $column) {
                if (Schema::hasColumn('compras', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

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

    private function ensureServiciosColumns(): void
    {
        $columns = [
            'categoria' => fn (Blueprint $table) => $table->string('categoria')->default('general')->after('precio'),
            'tipo' => fn (Blueprint $table) => $table->string('tipo')->default('servicio')->after('categoria'),
            'descripcion_publica' => fn (Blueprint $table) => $table->text('descripcion_publica')->nullable()->after('tipo'),
            'activo' => fn (Blueprint $table) => $table->boolean('activo')->default(true)->after('descripcion_publica'),
            'visible_cliente' => fn (Blueprint $table) => $table->boolean('visible_cliente')->default(false)->after('activo'),
            'moneda' => fn (Blueprint $table) => $table->string('moneda', 3)->default('EUR')->after('visible_cliente'),
            'duracion_minutos' => fn (Blueprint $table) => $table->unsignedSmallInteger('duracion_minutos')->nullable()->after('moneda'),
            'requiere_agenda' => fn (Blueprint $table) => $table->boolean('requiere_agenda')->default(false)->after('duracion_minutos'),
            'orden' => fn (Blueprint $table) => $table->unsignedInteger('orden')->default(0)->after('requiere_agenda'),
            'hubspot_pipeline_id' => fn (Blueprint $table) => $table->string('hubspot_pipeline_id')->nullable()->after('orden'),
            'hubspot_stage_id' => fn (Blueprint $table) => $table->string('hubspot_stage_id')->nullable()->after('hubspot_pipeline_id'),
            'metadata' => fn (Blueprint $table) => $table->json('metadata')->nullable()->after('hubspot_stage_id'),
        ];

        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn('servicios', $column)) {
                Schema::table('servicios', $definition);
            }
        }
    }

    private function ensureComprasColumns(): void
    {
        if (! Schema::hasColumn('compras', 'servicio_id')) {
            Schema::table('compras', function (Blueprint $table) {
                $table->unsignedBigInteger('servicio_id')->nullable()->index()->after('id_user');
            });
        }

        if (! Schema::hasColumn('compras', 'source')) {
            Schema::table('compras', function (Blueprint $table) {
                $table->string('source')->default('legacy')->index()->after('servicio_id');
            });
        }

        if (! Schema::hasColumn('compras', 'metadata')) {
            $after = Schema::hasColumn('compras', 'phasenum') ? 'phasenum' : 'hash_factura';

            Schema::table('compras', function (Blueprint $table) use ($after) {
                $table->json('metadata')->nullable()->after($after);
            });
        }

        if (! Schema::hasColumn('compras', 'paid_at')) {
            Schema::table('compras', function (Blueprint $table) {
                $table->timestamp('paid_at')->nullable()->after('metadata');
            });
        }
    }
};
