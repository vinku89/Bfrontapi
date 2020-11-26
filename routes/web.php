<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('sendsocket', 'HomeController@home');
Route::get('/home', 'HomeController@index')->name('home');


Route::get('buyorders', 'rabbitmq\RabbitmqController@buyorders');

Route::get('/rabbitmq', 'rabbitmq\RabbitmqController@index');

Route::get('/consume', 'rabbitmq\RabbitmqController@consume');

Route::get('/getversion', 'rabbitmq\RabbitmqController@getversion');

Route::get('/orderslistTest', 'rabbitmq\RabbitmqController@orderslistTest');

Route::get('/redis_refresh', 'HomeController@redis_refresh');
Route::get('/redis_coins', 'HomeController@redis_coins');

//coincapmarket calling
Route::get('/cronForCryptoUsdValue', 'CronJobsController@cronForCryptoUsdValue');
Route::any('/redis_refresh_cron', 'CronJobsController@redis_refresh_cron');
//Route::any('/script_run', 'CronJobsController@script_run');

//Route::any('/add_referal_bonus_to_bal', 'CronJobsController@add_referal_bonus_to_bal');
Route::any('/onetime_script_run', 'CronJobsController@onetime_script_run');
Route::any('/realtime_trade_cron', 'CronJobsController@realtime_trade_cron');
Route::any('/realtime_selected_trade_cron_buy_sell', 'CronJobsController@realtime_selected_trade_cron_buy_sell');
Route::any('/realtime_selected_trade_cron_sell_buy', 'ProdScriptsController@realtime_selected_trade_cron_sell_buy');
Route::any('/realtime_btc_trade_cron_sell_buy', 'ProdScriptsController@realtime_btc_trade_cron_sell_buy');
Route::any('/cancel_orders', 'ProdScriptsController@cancel_orders');

//Graph
   Route::any('/graph_data_link/{market_symbol?}', 'GraphWebController@graph_data');
   Route::any('/get_graph_pairs_link', 'GraphWebController@get_graph_pairs');

Route::any('/expireWithdraw', 'CronJobsController@expireWithdraw');
Route::any('/brexco_transaction_status', 'CronJobsController@brexco_transaction_status');
Route::any('/migrateEverus', 'CronJobsController@migrateEverus');
Route::any('/createAddresses', 'CronJobsController@createAddresses');
Route::any('/brexcoServices', 'CronJobsController@brexcoServices');
Route::any('/blockUsers', 'CronJobsController@blockUsers');
Route::any('/addEverusUserstoReferrals', 'CronJobsController@addEverusUserstoReferrals');

 
//accessries 
//Route::any('coinInfo', 'AccessController@coininfo');

Route::get('testing', 'AccessController@home');

   






