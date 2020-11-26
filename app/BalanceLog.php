<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class BalanceLog extends Model
{
	protected $table = 'dbt_balance_log';
	protected $primaryKey = 'log_id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'balance_id','user_id', 'currency_id','currency_symbol','transaction_type','transaction_amount','transaction_fees','ip','date'
    ];


}
