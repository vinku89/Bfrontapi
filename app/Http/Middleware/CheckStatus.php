<?php

namespace App\Http\Middleware;
use Auth;
use Closure;

class CheckStatus
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
    	if(Auth::check() && Auth::user()->is_user_blocked == 1){
    		return response()->json(["Success"=>false,'status' => 401,'Result' => "User is blocked"], 401);
    	}
            return $next($request);
    }
}
