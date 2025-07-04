<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('arraycos')
                ->nullable()
                ->comment('Almacena datos en formato JSON');

            $table->dateTime('arraycos_expire')
                ->nullable()
                ->after('arraycos')
                ->comment('Fecha de expiración para los datos de arraycos');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['arraycos', 'arraycos_expire']);
        });
    }
};
