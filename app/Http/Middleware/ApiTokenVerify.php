<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; 

class ApiTokenVerify
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
        // dd($request->header('Authorization'));
        if($request->header('Authorization')){
            if(Auth::guard('api')->check()){
                return $next($request);
            }else{
                return response()->json([
                    'msg' => 'Token not found!',
                    'success'=>0
                ]);
            }
        }
        return response()->json([
            'msg' => 'Not a valid API request.',
            'success'=>0
        ]);
    }
}
