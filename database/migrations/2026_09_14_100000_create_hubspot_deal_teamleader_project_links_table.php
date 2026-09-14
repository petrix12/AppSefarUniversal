<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hubspot_deal_teamleader_project_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->string('hubspot_deal_id')->index('hs_tl_link_hubspot_deal_idx');
            // The Teamleader project is an immutable historical record, hence
            // this is deliberately not a foreign key with cascading writes.
            $table->string('teamleader_project_id')->index('hs_tl_link_project_idx');
            $table->string('match_method', 40); // exact_title | legacy_reference | manual
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->json('evidence')->nullable();
            $table->unsignedBigInteger('linked_by')->nullable();
            $table->timestamps();

            $table->unique('negocio_id');
            $table->unique('hubspot_deal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hubspot_deal_teamleader_project_links');
    }
};
