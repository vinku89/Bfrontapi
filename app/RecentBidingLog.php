<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class RecentBidingLog extends Model
{
    protected $table = 'recent_biding_log';
    protected $primaryKey = 'log_id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
       'order_id','bid_id','bid_type', 'bid_price','complete_qty','complete_amount','user_id','coin_id','currency_symbol','market_id','market_symbol','success_time','fees_amount','available_amount','status' ,'trade_history_status'
    ];


}
