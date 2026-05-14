<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class TeamleaderCronController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $this->authorizeCron($request);

        $exitCode = Artisan::call('teamleader:sync', [
            '--entity' => 'all',
            '--force' => $request->boolean('force'),
            '--no-docs' => false,
            '--no-pdfs' => true,
            '--no-interaction' => true,
        ]);

        return $this->artisanResponse($exitCode);
    }

    public function work(Request $request): JsonResponse
    {
        $this->authorizeCron($request);

        $jobs = min(200, max(1, (int) $request->query('jobs', $request->query('limit', 10))));
        $timeout = min(60, max(10, (int) $request->query('timeout', 45)));

        $exitCode = Artisan::call('queue:work', [
            '--queue' => 'teamleader-sync,teamleader-documents',
            '--stop-when-empty' => true,
            '--tries' => 3,
            '--timeout' => $timeout,
            '--max-jobs' => $jobs,
            '--no-interaction' => true,
        ]);

        return response()->json([
            'ok' => $exitCode === 0,
            'exit_code' => $exitCode,
            'jobs_limit' => $jobs,
            'queues' => ['teamleader-sync', 'teamleader-documents'],
            'output' => trim(Artisan::output()),
        ], $exitCode === 0 ? 200 : 500);
    }

    private function authorizeCron(Request $request): void
    {
        $expected = config('services.teamleader.cron_token');
        $provided = (string) $request->query('token', '');

        abort_if(blank($expected) || !hash_equals((string) $expected, $provided), 403);
    }

    private function artisanResponse(int $exitCode): JsonResponse
    {
        return response()->json([
            'ok' => $exitCode === 0,
            'exit_code' => $exitCode,
            'output' => trim(Artisan::output()),
        ], $exitCode === 0 ? 200 : 500);
    }
}
