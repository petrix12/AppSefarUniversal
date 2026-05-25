<?php

namespace App\Http\Controllers;

use App\Services\TaskWorkflowOwnerDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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

        if (! Schema::hasTable('jobs')) {
            return response()->json([
                'ok' => false,
                'message' => 'No existe la tabla jobs para encolar el workflow diario.',
            ], 503);
        }

        $options = $this->commandOptions($request);

        $result = app(TaskWorkflowOwnerDispatcher::class)->dispatch($options);

        Log::channel('tasks')->info('Workflow diario encolado via HTTPS', [
            'options' => $options,
            'result' => $result,
        ]);

        return response()->json([
            'ok' => true,
            'queued' => true,
            'message' => 'Workflow diario encolado por vendedor. Procesa la cola con /cron/tasks/work; esa URL ejecuta solo 1 job por llamada.',
            'options' => $options,
            'result' => $result,
        ], 202);
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
