<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kalendia_consultation_offers', function (Blueprint $table) {
            $table->string('catalog_service_id')->nullable()->unique();
            $table->boolean('catalog_available')->default(true);
            $table->timestamp('catalog_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('kalendia_consultation_offers', function (Blueprint $table) {
            $table->dropUnique(['catalog_service_id']);
            $table->dropColumn(['catalog_service_id', 'catalog_available', 'catalog_synced_at']);
        });
    }
};
