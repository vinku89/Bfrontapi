<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class Country extends Model
{
	protected $table = 'country';
	protected $primaryKey = 'countryid';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'country_name','currencycode','currency','country_status','iso','nationality'
    ];


}
