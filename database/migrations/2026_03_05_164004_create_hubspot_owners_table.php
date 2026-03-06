<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hubspot_owners', function (Blueprint $table) {
            $table->string('id')->primary();              // HubSpot ownerId (string)
            $table->string('email')->nullable()->index();
            $table->string('name')->nullable();
            $table->boolean('active')->nullable();
            $table->timestamp('hubspot_created_at')->nullable();
            $table->timestamp('hubspot_updated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hubspot_owners');
    }
};
