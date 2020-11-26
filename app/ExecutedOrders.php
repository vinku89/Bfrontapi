<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class ExecutedOrders extends Model
{
	protected $table = 'executed_orders';
	protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'exchange_id','bid_type', 'bid_price','bid_qty','bid_qty_available','total_amount','amount_available','currency_symbol','market_symbol','user_id','open_order','fees_amount','fee_deducted_wallet','payment_fee_mode','fee_perc','bc_usd_price','status'
    ];

}

