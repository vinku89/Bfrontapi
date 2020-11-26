<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class InternalTransactionHistory extends Model
{
	protected $table = 'internal_transaction_history';
	protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'transaction_id','user_id','received_user_id', 'amount','wallet_symbol','usd_amount'
    ];


}