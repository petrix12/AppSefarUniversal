<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('aa')->nullable()->after('notes');
            $table->string('payment_terms')->nullable()->after('aa');
            $table->unsignedBigInteger('captador_id')->nullable()->after('payment_terms');
            $table->string('send_email')->nullable()->after('captador_id');
            $table->string('sales_team')->nullable()->after('send_email');
            $table->string('payment_method')->nullable()->after('sales_team');
            $table->string('deposit_number_client')->nullable()->after('payment_method');
            $table->string('deposit_number_sefar')->nullable()->after('deposit_number_client');
            $table->string('paid_by')->nullable()->after('deposit_number_sefar');
            $table->string('product_service')->nullable()->after('paid_by');
            $table->string('bank_account')->nullable()->after('product_service');

            $table->foreign('captador_id')
                  ->references('id')->on('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['captador_id']);
            $table->dropColumn([
                'aa', 'payment_terms', 'captador_id', 'send_email',
                'sales_team', 'payment_method', 'deposit_number_client',
                'deposit_number_sefar', 'paid_by', 'product_service', 'bank_account',
            ]);
        });
    }
};
