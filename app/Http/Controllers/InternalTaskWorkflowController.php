<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class InternalTaskWorkflowController extends Controller
{
    public function __invoke(Request $request)
    {
        $expectedToken = (string) config('services.tasks_daily_workflow.token', '');
        $providedToken = (string) ($request->bearerToken() ?: $request->query('token', ''));

        if ($expectedToken === '') {
            return response()->json([
                'ok' => false,
                'message' => 'TASKS_DAILY_WORKFLOW_TOKEN no esta configurado.',
            ], 503);
        }

        if (! hash_equals($expectedToken, $providedToken)) {
            abort(403);
        }

        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $lock = Cache::lock('tasks-daily-workflow-http', 1800);

        if (! $lock->get()) {
            return response()->json([
                'ok' => false,
                'message' => 'El workflow diario ya esta en ejecucion.',
            ], 409);
        }

        try {
            $options = $this->commandOptions($request);
            $exitCode = Artisan::call('tasks:daily-workflow', $options);
            $output = Artisan::output();

            Log::info('Workflow diario ejecutado via HTTPS', [
                'exit_code' => $exitCode,
                'options' => array_keys(array_filter($options)),
            ]);

            return response($output, $exitCode === 0 ? 200 : 500)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        } finally {
            optional($lock)->release();
        }
    }

    private function commandOptions(Request $request): array
    {
        $options = [
            '--force-reassign' => $request->boolean('force_reassign', true),
            '--force-limit' => max(1, (int) $request->query('force_limit', 200)),
            '--per' => max(1, (int) $request->query('per', 10)),
        ];

        if ($request->filled('date')) {
            $options['--date'] = $request->query('date');
        }

        if ($request->boolean('dry_run')) {
            $options['--dry-run'] = true;
        }

        return $options;
    }
}
