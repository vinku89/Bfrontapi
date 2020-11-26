<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class Userinfo extends Model
{
	protected $table = 'userinfo';
	protected $primaryKey = 'userinfo_id';
	const UPDATED_AT = null;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id','first_name','last_name','birth_date','gender','nationality','mobile_number','ref_code','google_2fa_key','Twofa_status','login_tfa_status','Token2fa_validation','Token2fa_validation_initial','applied_ref_code','bonus_on_kyc'
    ];


}
