<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class EverusAddresses extends Model
{
	protected $table = 'everus_addresses';
	protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'everus_user_id','evr_address','eth_address','btc_address','ltc_address','erc20_address','created_at','updated_at'
    ];


}