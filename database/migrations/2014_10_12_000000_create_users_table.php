<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email',175)->unique();

			$table->string('passport',175)->nullable()->unique();
            $table->integer('user_id')->nullable();
            $table->string('social_id')->nullable();
            $table->string('picture')->nullable();
            $table->dateTime('created')->nullable();
            $table->string('password_md5')->nullable();

            $table->string('phone')->nullable();
            $table->string('servicio')->nullable();
            $table->integer('pay')->nullable();
            $table->double('pago_registro')->nullable();
            $table->string('pago_cupon')->nullable();
            $table->string('id_pago')->nullable();
            $table->dateTime('date_of_birth')->nullable();
            $table->string('nombres')->nullable();
            $table->string('apellidos')->nullable();
            $table->string('genero')->nullable();
            $table->string('pais_de_nacimiento')->nullable();
            $table->string('ciudad_de_nacimiento')->nullable();
            $table->string('referido_por')->nullable();
            $table->string('hs_id')->nullable();
            $table->text('stripe_cus_id')->nullable();

            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->foreignId('current_team_id')->nullable();
            $table->text('profile_photo_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
