<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CoinListing extends Model
{
	protected $table = 'coin_listing';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'coin_name','coin_symbol', 'contract_address','is_base_currency','is_stablecoin','status','coin_description','coin_decimals','coin_image','deposit','withdraw','advance','staking','minimum_withdrawal_amt','minimum_deposit','withdrawal_fee','deposit_fee','website_url','explorer_link','github','twitter','discord','telegram','others','coin_market_cap','coin_cecko','created_by','updated_by','audit_trail','links'
    ];

}
