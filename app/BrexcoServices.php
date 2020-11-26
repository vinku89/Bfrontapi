<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class BrexcoServices extends Model
{
	protected $table = 'brexco_services';
	protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'service_name', 'transferto_service','transferto_service_id','status'
    ];


}
