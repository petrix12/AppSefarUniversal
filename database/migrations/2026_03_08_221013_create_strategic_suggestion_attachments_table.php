<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategic_suggestion_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('suggestion_id')
                ->constrained('strategic_suggestions')
                ->cascadeOnDelete();

            $table->foreignId('reply_id')
                ->nullable()
                ->constrained('strategic_suggestion_replies')
                ->cascadeOnDelete();

            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('disk')->default('s3');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);

            $table->timestamps();

            $table->index('suggestion_id');
            $table->index('reply_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategic_suggestion_attachments');
    }
};
