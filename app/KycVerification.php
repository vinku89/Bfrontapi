<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class KycVerification extends Model
{
	protected $table = 'kyc_verification';
	protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'first_name','middle_name','last_name','birth_date','gender','country','city','street_address','pin_code','proof','proof_path_1','proof_path_2','selfie_path','status','created_at','created_by','updated_at','updated_by'
    ];


}
