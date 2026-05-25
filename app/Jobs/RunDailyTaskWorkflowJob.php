<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RunDailyTaskWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    public function __construct(
        private array $options,
        private ?int $adminUserId = null
    ) {
    }

    public function handle(): void
    {
        $exitCode = Artisan::call('tasks:daily-workflow', $this->options);
        $output = Artisan::output();

        Log::info('Workflow diario de tareas ejecutado en cola', [
            'admin_user_id' => $this->adminUserId,
            'exit_code' => $exitCode,
            'options' => $this->options,
            'output' => $output,
        ]);

        if ($exitCode !== 0) {
            throw new \RuntimeException("tasks:daily-workflow termino con codigo {$exitCode}.");
        }
    }
}
