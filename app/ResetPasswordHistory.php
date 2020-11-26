<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ResetPasswordHistory extends Model
{
	protected $table = 'reset_password_history';
	protected $primaryKey = 'rec_id';
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'status','timestamp', 'req_from'
    ];
}
