<?php

namespace Tests\Feature;

use App\Http\Controllers\DeploymentHistoryController;
use App\Models\DeploymentHistory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeploymentHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.deployment_history_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('database.default', 'deployment_history_test');
        DB::purge('deployment_history_test');

        Schema::create('deployment_histories', function (Blueprint $table) {
            $table->id();
            $table->string('version')->nullable();
            $table->string('status')->default('success');
            $table->string('before_commit', 40)->nullable();
            $table->string('after_commit', 40)->nullable();
            $table->longText('git_output')->nullable();
            $table->longText('summary')->nullable();
            $table->string('model_used')->nullable();
            $table->integer('migrate_exit_code')->nullable();
            $table->longText('migrate_output')->nullable();
            $table->integer('optimize_exit_code')->nullable();
            $table->longText('optimize_output')->nullable();
            $table->boolean('mail_sent')->default(false);
            $table->text('mail_error')->nullable();
            $table->timestamp('deployed_at');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('deployment_histories');
        DB::disconnect('deployment_history_test');

        parent::tearDown();
    }

    public function test_it_stores_and_filters_the_same_summary_used_by_the_deploy(): void
    {
        DeploymentHistory::create([
            'version' => '2026.08.26-test',
            'status' => 'success',
            'before_commit' => str_repeat('a', 40),
            'after_commit' => str_repeat('b', 40),
            'summary' => "Hola a todos,\n\nNueva actualización disponible.",
            'migrate_exit_code' => 0,
            'migrate_output' => 'Migración completada.',
            'optimize_exit_code' => 0,
            'mail_sent' => true,
            'deployed_at' => now(),
        ]);
        DeploymentHistory::create([
            'version' => '2026.08.25-failed',
            'status' => 'failed',
            'deployed_at' => now()->subDay(),
        ]);

        $view = app(DeploymentHistoryController::class)->history(
            Request::create('/actualizaciones?status=success&search=Nueva', 'GET')
        );
        $deployments = $view->getData()['deployments'];

        $this->assertSame('deployment-histories.index', $view->name());
        $this->assertCount(1, $deployments);
        $this->assertSame('2026.08.26-test', $deployments->first()->version);
        $this->assertTrue($deployments->first()->mail_sent);
        $this->assertSame("Hola a todos,\n\nNueva actualización disponible.", $deployments->first()->summary);
    }
}
