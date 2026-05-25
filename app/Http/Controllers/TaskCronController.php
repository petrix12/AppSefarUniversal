<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class TaskCronController extends Controller
{
    public function work(Request $request): JsonResponse
    {
        $this->authorizeCron($request);

        $jobs = min(200, max(1, (int) $request->query('jobs', $request->query('limit', 5))));
        $timeout = min(1800, max(60, (int) $request->query('timeout', 600)));
        $maxTime = min(1800, max(30, (int) $request->query('max_time', 600)));

        $exitCode = Artisan::call('queue:work', [
            'connection' => 'database',
            '--queue' => 'default',
            '--stop-when-empty' => true,
            '--tries' => 1,
            '--timeout' => $timeout,
            '--max-jobs' => $jobs,
            '--max-time' => $maxTime,
            '--no-interaction' => true,
        ]);

        return response()->json([
            'ok' => $exitCode === 0,
            'exit_code' => $exitCode,
            'jobs_limit' => $jobs,
            'timeout' => $timeout,
            'max_time' => $maxTime,
            'connection' => 'database',
            'queues' => ['default'],
            'output' => trim(Artisan::output()),
        ], $exitCode === 0 ? 200 : 500);
    }

    private function authorizeCron(Request $request): void
    {
        $expected = config('services.teamleader.cron_token');
        $provided = (string) $request->query('token', '');

        abort_if(blank($expected) || ! hash_equals((string) $expected, $provided), 403);
    }
}
