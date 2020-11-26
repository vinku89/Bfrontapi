<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class Balance extends Model
{
	protected $table = 'dbt_balance';
	protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'main_balance' ,  'currency_id','currency_symbol','balance','trading_balance','last_update'
    ];


}
