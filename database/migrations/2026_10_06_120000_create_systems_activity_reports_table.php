<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('systems_activity_reports', function (Blueprint $table): void {
            $table->id();
            $table->date('report_date');
            $table->char('subject_hash', 64);
            $table->string('display_name', 160);
            $table->json('source_counts');
            $table->longText('summary');
            $table->string('trello_card_id', 64)->nullable();
            $table->string('status', 24)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['report_date', 'subject_hash'], 'systems_activity_report_subject_date_unique');
            $table->index(['report_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('systems_activity_reports');
    }
};
