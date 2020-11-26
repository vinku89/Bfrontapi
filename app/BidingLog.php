<?php

namespace App;

use Illuminate\Database\Eloquent\Model; 
use DB;
class BidingLog extends Model
{
	protected $table = 'dbt_biding_log';
	protected $primaryKey = 'log_id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'bid_id','bid_type', 'bid_price','complete_qty','complete_amount','user_id','coin_id','currency_symbol','market_id','market_symbol','success_time','fees_amount','available_amount','status' ,'trade_history_status'
    ];


}
