<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('referral_sales')) {
            Schema::create('referral_sales', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coordinator_referral_code_id')->index();
                $table->unsignedBigInteger('coordinator_user_id')->index();
                $table->unsignedBigInteger('buyer_user_id')->index();
                $table->string('hash_factura')->unique();
                $table->string('code');
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('currency', 3)->default('EUR');
                $table->decimal('commission_amount', 12, 2)->nullable();
                $table->string('commission_status')->default('pending')->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_sales');
    }
};
