<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class FeeDeductions extends Model
{
	protected $table = 'fee_deductions';
	protected $primaryKey = 'rec_id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'bid_id','bid_type','total_amount','total_amount_usd', 'paid_date', 'fee_percentage', 'fee_amount', 'fee_in_usd', 'currency', 'status', 'order_completed_at'
         ];
}
