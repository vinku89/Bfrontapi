<?php

namespace App;

use Illuminate\Database\Eloquent\Model; 
use DB;
class UserAddresses extends Model
{
	protected $table = 'user_addresses';
	protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id','wallet_symbol', 'wallet_address','qrcode','status'
    ];

}
