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
        $schedule->command('send:dailyreport')->dailyAt('12:00')->timezone('UTC');
        $schedule->command('send:weklyreport')->weekly()->mondays()->at('12:00')->timezone('UTC');
        $schedule->command('send:monthreport')->monthlyOn(1, '12:00')->timezone('UTC');
        $schedule->command('app:actualizar-y-limpiar')->everyMinute()->timezone('UTC');
        $schedule->command('jotform:obtener-datos 242624572998370')->everyMinute()->timezone('UTC');
        $schedule->command('teamleader:refresh-token')->everyMinute()->timezone('UTC');
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
