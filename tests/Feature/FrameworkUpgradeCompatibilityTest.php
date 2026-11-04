<?php

namespace Tests\Feature;

use App\Exports\TaskStatusReportExport;
use App\Http\Controllers\ExternalClientImportController;
use App\Http\Controllers\InvoicePdfController;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FrameworkUpgradeCompatibilityTest extends TestCase
{
    public function test_imported_timestamps_keep_the_app_timezone_at_day_boundaries(): void
    {
        config(['app.timezone' => 'America/Caracas']);
        $controller = new ExternalClientImportController();
        $method = new \ReflectionMethod($controller, 'parseDate');
        $timestamp = Carbon::parse('2026-11-04T01:00:00Z')->getTimestamp();

        $this->assertSame('2026-11-03', $method->invoke($controller, $timestamp));
        $this->assertSame('2026-11-03', $method->invoke($controller, $timestamp * 1000));
    }

    #[DataProvider('invoiceDates')]
    public function test_invoice_payment_days_remain_absolute_whole_numbers(string $issueDate, string $expiryDate): void
    {
        $invoice = Mockery::mock(Invoice::class)->makePartial();
        $invoice->invoice_date = $issueDate;
        $invoice->expiry_date = $expiryDate;
        $invoice->setRelation('lines', collect());
        $invoice->shouldReceive('load')->once()->with('lines')->andReturnSelf();
        $method = new \ReflectionMethod(new InvoicePdfController(), 'prepareData');

        $data = $method->invoke(new InvoicePdfController(), $invoice);

        $this->assertSame(14, $data['payment_days']);
    }

    public static function invoiceDates(): array
    {
        return [
            'normal dates' => ['2026-11-01', '2026-11-15'],
            'reversed dates' => ['2026-11-15', '2026-11-01'],
        ];
    }

    public function test_task_exports_keep_overdue_days_as_whole_numbers(): void
    {
        $this->travelTo(Carbon::parse('2026-11-04 12:00:00'));
        $task = new Task(['due_date' => '2026-11-01', 'status' => Task::STATUS_PENDING]);
        foreach (['assignee', 'contact', 'creator'] as $relation) {
            $task->setRelation($relation, null);
        }
        $export = new TaskStatusReportExport(now()->startOfMonth(), now(), [], 'Test');
        $sheets = $export->sheets();

        $row = $sheets[3]->map($task);

        $this->assertSame('Si', $row[3]);
        $this->assertSame(3, $row[4]);
    }

    public function test_registration_followups_still_select_only_fifteen_day_intervals(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array']);
        DB::purge('sqlite');
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-04 12:00:00'));
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('nombres');
            $table->string('apellidos');
            $table->integer('pay');
            $table->timestamps();
        });
        Schema::create('registration_followups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('scheduled_for');
        });
        require_once database_path('migrations/2021_03_08_173429_create_permission_tables.php');
        (new \CreatePermissionTables())->up();
        $roleId = DB::table('roles')->insertGetId(['name' => 'cliente', 'guard_name' => 'web']);
        foreach (['fifteen' => 15, 'fourteen' => 14, 'thirty' => 30, 'future' => -1] as $name => $days) {
            $id = DB::table('users')->insertGetId([
                'email' => $name.'@example.test', 'nombres' => $name, 'apellidos' => 'Test',
                'pay' => 0, 'created_at' => today()->subDays($days), 'updated_at' => now(),
            ]);
            DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => User::class, 'model_id' => $id]);
        }

        $this->assertSame(0, Artisan::call('followups:registration-payment', ['--dry-run' => true]));
        $output = Artisan::output();
        $this->assertStringContainsString('fifteen@example.test', $output);
        $this->assertStringContainsString('seq 2', $output);
        $this->assertStringContainsString('thirty@example.test', $output);
        $this->assertStringContainsString('seq 3', $output);
        $this->assertStringNotContainsString('fourteen@example.test', $output);
        $this->assertStringNotContainsString('future@example.test', $output);
        $this->assertSame(0, DB::table('registration_followups')->count());
        Mail::assertNothingSent();
    }
}
