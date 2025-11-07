<?php

namespace App\Console;

use DateTime;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    protected $commands = [
        Commands\DemoCron::class,
        \App\Console\Commands\RunListBanEveryTenSeconds::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('demo:cron')->dailyAt('00:05');
        $schedule->command('app:saving-services')->dailyAt('22:30');
        $schedule->command('demo:cron')->dailyAt('09:45');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
