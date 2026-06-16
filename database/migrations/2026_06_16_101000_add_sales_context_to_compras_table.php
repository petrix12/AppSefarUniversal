<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            if (! Schema::hasColumn('compras', 'servicio_id')) {
                $table->unsignedBigInteger('servicio_id')->nullable()->index()->after('id_user');
            }

            if (! Schema::hasColumn('compras', 'source')) {
                $table->string('source')->default('legacy')->index()->after('servicio_id');
            }

            if (! Schema::hasColumn('compras', 'metadata')) {
                $column = $table->json('metadata')->nullable();
                $column->after(Schema::hasColumn('compras', 'phasenum') ? 'phasenum' : 'hash_factura');
            }

            if (! Schema::hasColumn('compras', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('metadata');
            }
        });
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
    }
};
