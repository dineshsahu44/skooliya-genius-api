<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class checkDatabase
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if(!empty($request->from_database)&&!empty($request->to_database)){
            return $next($request);
        }
        return response()->json(["status"=>false,"msg"=>"Database can't be blank."]);
        
    }
}
