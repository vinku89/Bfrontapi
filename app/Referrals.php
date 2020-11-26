<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class Referrals extends Model
{
	protected $table = 'referrals';
	protected $primaryKey = 'ref_id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ancestor_id', 'descendant_id','distance'
    ];


}
