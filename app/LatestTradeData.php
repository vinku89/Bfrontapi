<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class LatestTradeData extends Model
{
	protected $table = 'latest_trade_data';
	protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'market_symbol', 'last_price','open','close','total_coin_supply','volume_24h','volume_24h_date','change_perc','change_perc_date'
    ];

}

