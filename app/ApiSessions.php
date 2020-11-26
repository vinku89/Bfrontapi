<?php

namespace App;

use Illuminate\Database\Eloquent\Model; 
use DB;
use Carbon\Carbon;
class ApiSessions extends Model
{
	protected $table = 'api_sessions';
	protected $primaryKey = 'session_id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id','session_key', 'expire_date','is_expired'
    ];

    //check the authentication expired or not
    public function scopeGetNodeApiAuths($query)
    {
        //DB::enableQueryLog();
        $result = $query->where(DB::raw('DATE_FORMAT(expire_date, "%Y-%m-%d %H:%i")') ,'=', Carbon::now()->format('Y-m-d H:i'))->get();
        /*$query = DB::getQueryLog();
        $lastQuery = end($query);
        print_r($lastQuery);exit;*/
        return $result;
    }
}
