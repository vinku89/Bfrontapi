<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class BrexcoCoinSettings extends Model
{
	protected $table = 'brexco_coin_settings';
	protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'coin_id', 'coin_symbol','discount','fee','status'
    ];


}