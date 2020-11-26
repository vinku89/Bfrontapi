<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CryptoValueHistory extends Model
{
	protected $table = 'crypto_value_history';
	protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'crypto_name','crypto_symbol', 'fiat_value','fiat_type','created_at','updated_at'
    ];

}