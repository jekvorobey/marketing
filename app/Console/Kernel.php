<?php

namespace App\Console;

use App\Console\Commands\ExpiredBonusStatus;
use App\Console\Commands\ExpiredDiscountStatus;
use App\Console\Commands\ExpiredPromoCodeStatus;
use App\Console\Commands\BirthdayDiscounts;
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
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command(ExpiredDiscountStatus::class)->everyFiveMinutes();
        $schedule->command(ExpiredPromoCodeStatus::class)->dailyAt('00:00');
        $schedule->command(ExpiredBonusStatus::class)->dailyAt('00:00');
        $schedule->command(BirthdayDiscounts::class)->dailyAt('09:00');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
