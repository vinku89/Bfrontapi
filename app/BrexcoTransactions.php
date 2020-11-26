<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class BrexcoTransactions extends Model
{
	protected $table = 'brexco_transactions';
	protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'external_id','payment_id', 'product_id','country','crypto_symbol','user_id','service_id','account_number','amount','currency_type','usd_amount','crypto_amount','discount','fee','status','status_text'
    ];


}
