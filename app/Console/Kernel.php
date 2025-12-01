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
        $schedule->command('app:lkp-service')->dailyAt('05:00')->timezone('Asia/Jakarta');

        // Running On First Month
        // $schedule->command('app:monthly-run-sp')->monthlyOn(1, '02:00')->timezone('Asia/Jakarta');
        $schedule->command('app:listan-service')->monthlyOn(1, '05:00')->timezone('Asia/Jakarta');

        $schedule->command('app:monthly-run-sp')->dailyAt('12:56');
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
