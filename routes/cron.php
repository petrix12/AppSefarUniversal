<?php

use App\Http\Controllers\InternalTaskWorkflowController;
use App\Http\Controllers\JotformCouponCronController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TaskCronController;
use App\Http\Controllers\TeamleaderCronController;
use App\Http\Middleware\AuditIntegrationTokenRequests;
use App\Http\Middleware\EnsureInternalApiToken;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

// La plataforma central administra los permisos y la revocación de cada cron.
$tokenMiddleware = fn (string $ability): array => [
    'auth:sanctum',
    AuditIntegrationTokenRequests::class . ':' . $ability,
    EnsureInternalApiToken::class . ':' . $ability,
];
Route::get('/internal/tasks/daily-workflow', InternalTaskWorkflowController::class)
    ->name('internal.tasks.daily-workflow')->middleware($tokenMiddleware('tasks:workflow'));

Route::prefix('cron/teamleader')
    ->name('cron.teamleader.')
    ->group(function () use ($tokenMiddleware) {
        Route::get('/sync', [TeamleaderCronController::class, 'sync'])->name('sync')->middleware($tokenMiddleware('cron:teamleader:sync'));
        Route::get('/work', [TeamleaderCronController::class, 'work'])->name('work')->middleware($tokenMiddleware('cron:teamleader:work'));
    });

Route::get('/cron/tasks/work', [TaskCronController::class, 'work'])->name('cron.tasks.work')->middleware($tokenMiddleware('cron:tasks:work'));
Route::get('/cron/jotform/coupons', JotformCouponCronController::class)->name('cron.jotform.coupons')->middleware($tokenMiddleware('cron:jotform:sync'));

// Se mantienen las URLs GET; un token real de la plataforma es obligatorio.
Route::middleware(['auth:sanctum', 'throttle:6,1,maintenance-cron'])->group(function () use ($tokenMiddleware) {
    Route::get('/cron/scheduler-run', function () {
        Artisan::call('schedule:run');

        return response()->json([
            'status' => 'ok',
            'message' => 'Laravel scheduler ejecutado correctamente',
            'time' => now()->toDateTimeString(),
        ]);
    })->middleware($tokenMiddleware('cron:scheduler:run'));

    Route::get('/cron/queue-worker', function () {
        Artisan::call('queue:work', [
            '--stop-when-empty' => true,
            '--tries' => 3,
            '--max-time' => 60,
        ]);

        return response()->json(['status' => 'ok', 'timestamp' => now()]);
    })->middleware($tokenMiddleware('cron:queue:work'));

    Route::get('/cron/followups-registration-payment', function () {
        Artisan::call('followups:registration-payment', ['--no-interaction' => true]);

        return response()->json(['ok' => true, 'output' => Artisan::output(), 'ts' => now()]);
    })->middleware($tokenMiddleware('cron:followups:run'));

    Route::get('makereport', [ReportController::class, 'makereport'])->name('makereport')->middleware($tokenMiddleware('cron:reports:generate'));
});
