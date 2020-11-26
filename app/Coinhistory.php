<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class Coinhistory extends Model
{
	protected $table = 'dbt_coinhistory';
	protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'coin_symbol', 'market_symbol','last_price','total_coin_supply','price_high_1h','price_low_1h','price_change_1h','volume_1h','price_high_24h','price_low_24h','price_change_24h','volume_24h','day_open_price','open','close','volumefrom','volumeto','change_perc','date'
    ];


}
