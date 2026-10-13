<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_reporting_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('manager_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subordinate_user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index('manager_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_reporting_lines');
    }
};
