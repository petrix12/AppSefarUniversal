<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployment_histories', function (Blueprint $table) {
            $table->id();
            $table->string('version')->nullable()->index();
            $table->string('status')->default('success')->index();
            $table->string('before_commit', 40)->nullable();
            $table->string('after_commit', 40)->nullable()->index();
            $table->longText('git_output')->nullable();
            $table->longText('summary')->nullable();
            $table->string('model_used')->nullable();
            $table->integer('migrate_exit_code')->nullable();
            $table->longText('migrate_output')->nullable();
            $table->integer('optimize_exit_code')->nullable();
            $table->longText('optimize_output')->nullable();
            $table->boolean('mail_sent')->default(false);
            $table->text('mail_error')->nullable();
            $table->timestamp('deployed_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_histories');
    }
};
