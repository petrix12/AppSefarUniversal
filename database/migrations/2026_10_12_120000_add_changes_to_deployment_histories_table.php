<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployment_histories', function (Blueprint $table): void {
            $table->longText('changes')->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('deployment_histories', function (Blueprint $table): void {
            $table->dropColumn('changes');
        });
    }
};
