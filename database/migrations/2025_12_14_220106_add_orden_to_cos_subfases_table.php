<?php

// database/migrations/xxxx_add_orden_to_cos_subfases_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cos_subfases', function (Blueprint $table) {
            $table->unsignedInteger('orden')
                  ->default(0)
                  ->after('titulo')
                  ->index();
        });
    }

    public function down(): void
    {
        Schema::table('cos_subfases', function (Blueprint $table) {
            $table->dropColumn('orden');
        });
    }
};
