<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;
	protected $primaryKey = 'user_id';
	const UPDATED_AT = null;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
      'name', 'email', 'password', 'status','is_email_verified' ,'is_user_locked','lock_release_date','terms_and_conditions'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}
