<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32);
            $table->string('subject');
            $table->text('details')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('follow_up_at')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'occurred_at']);
            $table->index(['client_id', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
        });

        Schema::create('crm_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('quote_number', 32)->unique();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->string('status', 24)->default('draft');
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->index(['status', 'valid_until']);
            $table->index(['lead_id', 'status']);
            $table->index(['client_id', 'status']);
        });

        Schema::create('crm_quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_quote_id')->constrained('crm_quotes')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_quote_items');
        Schema::dropIfExists('crm_quotes');
        Schema::dropIfExists('crm_activities');
    }
};
