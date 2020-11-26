<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class Withdraw extends Model
{
	protected $table = 'dbt_withdraw';
	protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'wallet_id','currency_id','withdraw_address','currency_symbol','amount','usd_amount','method','fees_amount','net_amount','description','request_date','email_confirm_date','cancel_date','status','ip','approved_cancel_by'
    ];


}
