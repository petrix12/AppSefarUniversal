<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monday_boards', function (Blueprint $table): void {
            $table->string('object_type')->nullable()->index()->after('state');
            $table->string('hierarchy_type')->nullable()->index()->after('object_type');
        });
    }

    public function down(): void
    {
        Schema::table('monday_boards', function (Blueprint $table): void {
            $table->dropIndex(['object_type']);
            $table->dropIndex(['hierarchy_type']);
            $table->dropColumn(['object_type', 'hierarchy_type']);
        });
    }
};
