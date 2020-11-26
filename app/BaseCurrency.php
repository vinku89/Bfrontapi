<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BaseCurrency extends Model
{
	protected $table = 'base_currency';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'coin_id','trading_maker_fee', 'trading_taker_fee'
    ];

}
