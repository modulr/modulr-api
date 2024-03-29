<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('app:process-notifications')->everyMinute()->sendOutputTo(storage_path("logs/scheduler.log"))->emailOutputOnFailure('alfredobarronc@gmail.com');
        //$schedule->command('app:fill-autoparts-data')->everyFiveMinutes()->withoutOverlapping()->sendOutputTo(storage_path("logs/scheduler.log"))->emailOutputOnFailure('alfredobarronc@gmail.com');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
