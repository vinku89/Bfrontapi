<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ShortLinks extends Model
{
	protected $table = 'short_links';
	protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'link_key','link_value'
    ];

}