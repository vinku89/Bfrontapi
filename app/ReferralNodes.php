<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class ReferralNodes extends Model
{
	protected $table = 'referrals_nodes';
	protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ancestor_id', 'descendant_id','is_deleted'
    ];


}
