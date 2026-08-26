<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            $table->boolean('monday_sync_enabled')->default(false)->after('metadata');
            $table->string('monday_board_id')->nullable()->after('monday_sync_enabled');
            $table->string('monday_group_id')->nullable()->after('monday_board_id');
        });

        DB::table('servicios')
            ->select(['id', 'id_hubspot', 'nombre'])
            ->orderBy('id')
            ->get()
            ->each(function ($servicio): void {
                $name = Str::lower(Str::ascii(trim(($servicio->id_hubspot ?? '').' '.($servicio->nombre ?? ''))));
                $isProcedureAudit = str_contains($name, 'auditoria')
                    && str_contains($name, 'procedimiento');

                if (! $isProcedureAudit) {
                    DB::table('servicios')->where('id', $servicio->id)->update([
                        'monday_sync_enabled' => true,
                        'monday_board_id' => '878831315',
                        'monday_group_id' => 'duplicate_of_en_proceso',
                    ]);
                }
            });

        Schema::create('monday_service_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();
            $table->string('board_id');
            $table->string('group_id');
            $table->string('monday_item_id')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'servicio_id', 'board_id', 'group_id'],
                'monday_service_registration_destination_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monday_service_registrations');

        Schema::table('servicios', function (Blueprint $table) {
            $table->dropColumn([
                'monday_sync_enabled',
                'monday_board_id',
                'monday_group_id',
            ]);
        });
    }
};
