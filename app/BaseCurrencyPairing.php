<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BaseCurrencyPairing extends Model
{
	protected $table = 'base_currency_pairing';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'coin_id','pairing_id', 'trading_pairs', 'coin_pairing', 'status'
    ];

}
