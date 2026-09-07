<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teamleader_small_balance_notices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('teamleader_project_id');
            $table->unsignedTinyInteger('phase');
            $table->decimal('preestablished_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2);
            $table->decimal('balance_amount', 12, 2);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['teamleader_project_id', 'phase'], 'tl_small_balance_notice_project_phase_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teamleader_small_balance_notices');
    }
};
