<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class BrexcoPackages extends Model
{
	protected $table = 'brexco_packages';
	protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'country_iso_code', 'service','operator_id','operator_name','packages'
    ];
}
