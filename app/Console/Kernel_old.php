<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Http\Controllers\CronJobsController;
use App\Http\Controllers\ProdScriptsController;
class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */

    protected $commands = [
       Commands\consume::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->call(function () {
            //CronJobsController::cronForCryptoUsdValue();
        })->everyFifteenMinutes();

        $schedule->call(function () {
            //CronJobsController::realtime_trade_cron();
        })->everyFifteenMinutes();
        $schedule->call(function () {
            ProdScriptsController::realtime_selected_trade_cron_sell_buy();
        })->everyMinute();
        $schedule->call(function () {
            ProdScriptsController::realtime_selected_trade_cron_sell_buy();
        })->everyMinute();
        $schedule->call(function () {
            ProdScriptsController::realtime_selected_trade_cron_sell_buy();
        })->everyMinute();
        $schedule->call(function () {
            ProdScriptsController::realtime_selected_trade_cron_sell_buy();
        })->everyMinute();
        $schedule->call(function () {
            ProdScriptsController::realtime_selected_trade_cron_sell_buy();
        })->everyMinute();
        $schedule->call(function () {
            ProdScriptsController::realtime_selected_trade_cron_sell_buy();
        })->everyMinute();
        $schedule->call(function () {
            //CronJobsController::publish_buy_sell();
        })->dailyAt('14:43');
        $schedule->call(function () {
            //CronJobsController::realtime_selected_trade_cron_buy_sell();
        })->cron('*/3 * * * *');
        $schedule->call(function () {
            //CronJobsController::realtime_selected_trade_cron_sell_buy();
        })->cron('*/6 * * * *');
        

        $schedule->call(function () {
            CronJobsController::redis_refresh_cron();
        })->hourly();
        $schedule->call(function () {
            CronJobsController::expiresNodeApiAuth();
        })->everyMinute();
        $schedule->call(function () {
            CronJobsController::expireWithdraw();
        })->everyMinute();
        
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
