<?php

namespace App\Http\Middleware;
use Auth;
use Closure;
use App\Userinfo;

class TwofaStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $userData = Userinfo::select("Token2fa_validation")->where("user_id","=",Auth::user()->user_id)->first();
    	if(Auth::check() && $userData->Token2fa_validation == 'D'){
    		return response()->json(["Success"=>false,'status' => 401,'Result' => "Twofa Status Inactive"], 401);
    	}
            return $next($request);
    }
}
