<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DepositTransaction extends Model
{
	protected $table = 'deposit_transaction';
	protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id','deposit_id', 'crypto_type_id','crypto_symbol','bc_address','bc_value','internal_address','internal_value','transaction_hash','bc_fee','fiat_value','fiat_currency','description','status','transaction_from','verify_email_status','created_by','created_at','updated_at'
    ];

}