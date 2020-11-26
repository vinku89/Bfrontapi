<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class ReferralBonus extends Model
{
	protected $table = 'referral_bonus';
	protected $primaryKey = 'refferal_bonus';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'from_id','evr','usd'
    ];


}
