<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_error_audits', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('environment', 30);

            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable()->index();

            $table->string('method', 10)->nullable();
            $table->text('url')->nullable();
            $table->string('route_name')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('request_headers')->nullable();
            $table->json('request_data')->nullable();
            $table->json('request_files')->nullable();

            $table->unsignedSmallInteger('status_code')->default(500)->index();
            $table->string('response_content_type')->nullable();
            $table->longText('response_body')->nullable();

            $table->string('exception_class');
            $table->longText('exception_message');
            $table->text('exception_file');
            $table->unsignedInteger('exception_line');
            $table->longText('exception_trace')->nullable();

            $table->string('email_recipient')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->text('email_error')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_error_audits');
    }
};
