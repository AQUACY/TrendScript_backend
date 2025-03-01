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
        // Archive content for free users every day at midnight
        $schedule->command('content:archive-free')->dailyAt('00:00');

        // Fetch trending topics every 6 hours
        $schedule->call(function () {
            app(\App\Http\Controllers\API\TrendController::class)->fetchTrends();
        })->everyFourHours();
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
