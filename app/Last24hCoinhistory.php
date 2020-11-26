<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class Last24hCoinhistory extends Model
{
	protected $table = 'last_24h_coinhistory';
	protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'order_id', 'market_symbol','last_price','total_coin_supply','volume_24h','day_open_price','open','close','volumefrom','volumeto','change_perc','date'
    ];


}
