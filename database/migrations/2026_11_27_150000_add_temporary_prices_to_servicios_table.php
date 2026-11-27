<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            $table->unsignedInteger('precio_temporal')->nullable();
            $table->date('precio_temporal_desde')->nullable();
            $table->date('precio_temporal_hasta')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            $table->dropColumn(['precio_temporal', 'precio_temporal_desde', 'precio_temporal_hasta']);
        });
    }
};
