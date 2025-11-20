<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWhatsappRequestsTable extends Migration
{
    public function up()
    {
        Schema::create('whatsapp_requests', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number');
            $table->string('message');
            $table->string('file_url')->nullable();
            $table->string('file_name')->nullable();
            $table->enum('status', ['pending', 'processing', 'sent', 'error'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_requests');
    }
}
