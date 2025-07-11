<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    protected $commands = [
        Commands\DemoCron::class,
        \App\Console\Commands\RunListBanEveryTenSeconds::class,
        \App\Console\Commands\MakeService::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('demo:cron')->dailyAt('16:52');
        // $schedule->command('demo:cron')->dailyAt('00:30');
        $schedule->command('demo:cron')->dailyAt('00:05');
        $schedule->command('app:last-monthly-credit-insert')->hourly();
        // $schedule->command('demo:cron')->dailyAt('10:12');
        // $schedule->command('app:send-telegram-messages')->everyMinute();
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
