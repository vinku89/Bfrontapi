<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class SelfTransactionsHistory extends Model
{
	protected $table = 'self_transactions_history';
	protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'transaction_id','user_id', 'amount','transaction_type','wallet_symbol','usd_amount'
    ];


}