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
        $schedule->command('demo:cron')->dailyAt('00:05');
        $schedule->command('app:last-monthly-credit-insert')->hourly();
        $schedule->command('app:lkp-service')->dailyAt('05:00');
        $schedule->command('app:listan-service')->dailyAt('05:00');
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
