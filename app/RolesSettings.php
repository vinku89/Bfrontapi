<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class RolesSettings extends Model
{
	protected $table = 'roles_settings';
	protected $primaryKey = 'rec_id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'role', 'fee_applicable'
         ];
}
