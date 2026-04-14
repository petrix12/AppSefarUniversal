<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_audits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('email')->nullable();

            $table->boolean('is_authenticated')->default(false);

            $table->string('method', 10)->nullable();
            $table->string('route_name')->nullable();
            $table->text('url')->nullable();
            $table->string('path')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('visited_at');

            $table->timestamps();

            $table->index(['user_id', 'visited_at']);
            $table->index(['email']);
            $table->index(['route_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_audits');
    }
};
