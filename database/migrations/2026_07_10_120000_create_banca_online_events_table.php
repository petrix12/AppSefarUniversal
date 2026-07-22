<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banca_online_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('compra_id')->nullable()->constrained('compras')->nullOnDelete();
            $table->string('event', 80);
            $table->string('email', 175)->nullable();
            $table->string('country_slug', 80)->nullable();
            $table->string('plan_slug', 160)->nullable();
            $table->unsignedBigInteger('package_id')->nullable();
            $table->string('entry_point', 40)->nullable();
            $table->string('case_status', 80)->nullable();
            $table->string('quote_id', 160)->nullable();
            $table->string('checkout_token', 160)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->text('url')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamps();

            $table->index(['event', 'occurred_at']);
            $table->index(['country_slug', 'plan_slug']);
            $table->index(['entry_point', 'case_status']);
            $table->index('checkout_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banca_online_events');
    }
};
