<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_vat')->nullable();
            $table->string('customer_address')->nullable();
            $table->string('customer_country')->nullable();
            $table->date('invoice_date');
            $table->date('expiry_date')->nullable();
            $table->string('currency')->default('EUR');
            $table->string('status')->default('draft'); // draft, sent, paid
            $table->text('notes')->nullable();
            $table->decimal('total_excl_tax', 12, 2)->default(0);
            $table->decimal('total_tax', 12, 2)->default(0);
            $table->decimal('total_incl_tax', 12, 2)->default(0);
            $table->date('paid_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
