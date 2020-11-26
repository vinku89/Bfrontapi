<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
Broadcast::channel('OrdersList_{market_symbol}_{user_id}', function ($user,$market_symbol, $user_id) {
	Log::info("orders list authentication check ".(int) $user->user_id." ".(int) $user_id)." ".$market_symbol;
    return (int) $user->user_id === (int) $user_id;
});
Broadcast::channel('OrdersHistory_{market_symbol}_{user_id}', function ($user,$market_symbol, $user_id) {
    return (int) $user->user_id === (int) $user_id;
});
Broadcast::channel('Balance_{market_symbol}_{user_id}', function ($user,$market_symbol, $user_id) {
    return (int) $user->user_id === (int) $user_id;
});
