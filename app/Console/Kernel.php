<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('platforms:process-events --limit=4')->everyMinute()->withoutOverlapping(5)->runInBackground();
        $schedule->command('platforms:sync --limit=2')->everyMinute()->withoutOverlapping(5)->runInBackground();
        $schedule->command('kalendia:process-bookings')->everyMinute()
            ->when(fn () => filled(config('kalendia.token')))
            ->withoutOverlapping(10)->runInBackground();
        $schedule->command('kalendia:process-webhooks --limit=4')->everyMinute()
            ->when(fn () => filled(config('kalendia.token')) && filled(config('kalendia.webhook_secret')))
            ->withoutOverlapping(5)->runInBackground();
        $schedule->command('kalendia:sync-appointments')->everyFiveMinutes()->when(fn () => filled(config('kalendia.token')))->withoutOverlapping(5)->runInBackground();
        $schedule->command('kalendia:sync-consultations')->everyFiveMinutes()->when(fn () => filled(config('kalendia.token')))->withoutOverlapping(5)->runInBackground();
        $schedule->command('kalendia:reconcile-payments')->everyFiveMinutes()->when(fn () => filled(config('services.stripe.secret')))->withoutOverlapping(5)->runInBackground();
        $schedule->command('registration:deliver-meeting-webhooks --limit=4')
            ->everyMinute()->when(fn () => (bool) config('registration_meetings.enabled'))
            ->withoutOverlapping(5)->runInBackground();
        $schedule->command('cos:sync-payment-receipts --pending --write --limit=2')
            ->everyMinute()->when(fn () => (bool) config('cos_payments.crm_sync_enabled'))
            ->withoutOverlapping(20)->runInBackground()
            ->appendOutputTo(storage_path('logs/cos-payment-crm.log'));
        $schedule->command('clients:reconcile-blocks --limit=30')->everyMinute()->withoutOverlapping(10);
        $schedule->command('send:dailyreport')->dailyAt('12:00')->timezone('UTC');
        $schedule->command('send:weklyreport')->weekly()->mondays()->at('12:00')->timezone('UTC');
        $schedule->command('send:monthreport')->monthlyOn(1, '12:00')->timezone('UTC');
        $schedule->command('send:yearreport')->yearlyOn(1, 1, '12:00')->timezone('UTC');
        $schedule->command('app:actualizar-y-limpiar')->everyMinute()->timezone('UTC');
        $schedule->command('teamleader:refresh-token')->everyMinute()->timezone('UTC');
        $schedule->command('chat:clean-expired')->everyFiveMinutes();
        $crmEnabled = fn () => config('crm_sync.hubspot.enabled') || config('crm_sync.hubspot.outbound_enabled')
            || config('crm_sync.teamleader.enabled')
            || (\Illuminate\Support\Facades\Schema::hasTable('jobs')
                && \Illuminate\Support\Facades\DB::table('jobs')->where('queue', 'crm-sync')->exists());
        $schedule->command('crm:retry-webhooks')->everyFiveMinutes()->when($crmEnabled)->withoutOverlapping();
        $schedule->command('crm:dispatch-hubspot-outbox --limit=100')->everyMinute()
            ->when(fn () => (bool) config('crm_sync.hubspot.outbound_enabled'))
            ->withoutOverlapping(5)->runInBackground();
        $schedule->command('queue:work crm --queue=crm-sync --stop-when-empty --max-time=50 --timeout=300 --tries=5')
            ->everyMinute()->when($crmEnabled)->withoutOverlapping(10)->runInBackground()
            ->appendOutputTo(storage_path('logs/crm-worker.log'));
        $schedule->command('queue:work cos --queue=cos-refresh --stop-when-empty --max-time=50 --timeout=300 --tries=3')
            ->everyMinute()
            ->withoutOverlapping(10)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/cos-worker.log'));
        $schedule->command('followups:registration-payment')
        ->dailyAt('09:00')
        ->timezone('America/Caracas')
        ->withoutOverlapping();
        $schedule->command('cos:refresh-active-clients')
            ->dailyAt('02:15')
            ->timezone('America/Caracas')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/cos-refresh-active-clients.log'));
        $schedule->command('cos:backfill-local --scheduled')->everyFiveMinutes()
            ->when(fn () => (\App\Services\CosPortfolioBackfill::state()['status'] ?? null) === 'running')
            ->withoutOverlapping(10)->runInBackground()
            ->appendOutputTo(storage_path('logs/cos-backfill-local.log'));
        $schedule->command('crm:associate-deals-ai --write --scheduled --limit=5')->everyTenMinutes()
            ->when(fn () => config('services.openrouter.local_association_enabled') && filled(config('services.openrouter.key')))
            ->withoutOverlapping(15)->runInBackground()
            ->appendOutputTo(storage_path('logs/crm-deal-ai-association.log'));
        $schedule->command('monday:sync --queue')
            ->everySixHours()
            ->when(fn () => (bool) config('services.monday.mirror_enabled'))
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/monday-mirror.log'));
        $schedule->job(new \App\Jobs\Monday\RefreshMondayUsersJob())
            ->hourly()
            ->when(fn () => (bool) config('services.monday.mirror_enabled'));
        $schedule->command('queue:work monday --queue=monday-sync --stop-when-empty --max-time=50 --timeout=1800 --tries=3')
            ->everyMinute()
            ->when(fn () => (bool) config('services.monday.mirror_enabled'))
            ->withoutOverlapping(35)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/monday-worker.log'));
        $schedule->command('automations:run --limit=100')
            ->everyMinute()
            ->withoutOverlapping();
        $schedule->command('marketing:dispatch-scheduled')
            ->everyMinute()
            ->withoutOverlapping();
        $schedule->command('queue:work', [
            (string) config('marketing.queue_connection', 'database'),
            '--stop-when-empty',
            '--queue' => (string) config('marketing.queue_name', 'marketing'),
            '--max-time' => 50,
            '--max-jobs' => 5,
            '--timeout' => 60,
            '--tries' => 3,
        ])->name('marketing-queue-worker')
            ->everyMinute()
            ->when(fn () => (bool) config('marketing.worker_enabled')
                && filled(config('marketing.ses.key'))
                && filled(config('marketing.ses.secret'))
                && filled(config('marketing.ses.from_email')))
            ->withoutOverlapping(5)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/marketing-worker.log'));
        $schedule->command('systems:activity-report')
            ->dailyAt((string) config('systems_activity_report.run_at', '08:00'))
            ->timezone((string) config('systems_activity_report.timezone', 'America/Caracas'))
            ->when(fn () => (bool) config('systems_activity_report.enabled'))
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/systems-activity-report.log'));
        // Ejecutar flujo diario de tareas en secuencia: primero reasignaciones, luego nuevas tareas.
       /* $schedule->command('tasks:daily-workflow --force-reassign')
                 ->weekdays()
                 ->at('6:00')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/tasks-daily-workflow.log'));
        $schedule->command('tasks:remind-in-progress --days=7')
                 ->weekdays()
                 ->at('8:00')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/tasks-in-progress-reminders.log'));*/
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
