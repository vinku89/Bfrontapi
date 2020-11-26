<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class AddressBook extends Model
{
	protected $table = 'address_book';
	protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'wallet_symbol','label','address','created_at','updated_at'
    ];


}
