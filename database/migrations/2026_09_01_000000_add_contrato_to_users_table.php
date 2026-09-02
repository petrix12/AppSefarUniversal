<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'contrato')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // Solo un valor explícito de 1 habilita la actualización automática del COS.
            $table->boolean('contrato')->default(false)->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'contrato')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['contrato']);
            $table->dropColumn('contrato');
        });
    }
};
