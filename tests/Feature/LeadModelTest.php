<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use App\Services\LeadConversionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
        });

        $migration = require database_path('migrations/2026_11_23_090000_create_leads_table.php');
        $migration->up();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('leads');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_lead_defaults_and_conversion_history_are_persisted(): void
    {
        $assigneeId = DB::table('users')->insertGetId([]);
        $convertedUserId = DB::table('users')->insertGetId([]);

        $lead = Lead::create([
            'nombre' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'user_id' => $assigneeId,
            'converted_user_id' => $convertedUserId,
            'converted_at' => '2026-11-23 12:30:00',
            'estado' => Lead::STATUS_CONVERTED,
        ])->fresh();

        $this->assertSame(Lead::STAGE_NEW, $lead->etapa);
        $this->assertSame(Lead::STATUS_CONVERTED, $lead->estado);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $lead->converted_at);
        $this->assertSame('Ada Lovelace', $lead->nombre);
        $this->assertSame($assigneeId, $lead->assignee->id);
        $this->assertSame($convertedUserId, $lead->convertedUser->id);
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'converted_user_id' => $convertedUserId,
        ]);
    }

    public function test_registration_conversion_closes_all_matching_leads_without_deleting_them(): void
    {
        $userId = DB::table('users')->insertGetId([]);
        Lead::create(['nombre' => 'Lead por correo', 'email' => 'CLIENTE@example.test']);
        Lead::create(['nombre' => 'Lead por pasaporte', 'passport' => 'AB12345']);

        $user = new User();
        $user->exists = true;
        $user->setRawAttributes([
            'id' => $userId,
            'email' => 'cliente@example.test',
            'passport' => 'ab12345',
        ]);

        $this->assertSame(2, app(LeadConversionService::class)->convertFor($user));
        $this->assertDatabaseCount('leads', 2);
        $this->assertDatabaseMissing('leads', ['estado' => Lead::STATUS_OPEN]);
        $this->assertDatabaseHas('leads', [
            'converted_user_id' => $userId,
            'estado' => Lead::STATUS_CONVERTED,
        ]);
    }
}
