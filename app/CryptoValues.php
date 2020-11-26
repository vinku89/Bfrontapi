<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CryptoValues extends Model
{
	protected $table = 'crypto_values';
	protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'crypto_id','fiat_type', 'fiat_value','created_at','updated_at'
    ];

}