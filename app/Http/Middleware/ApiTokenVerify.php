<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; 
use Log;

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
                $data = [
                    'msg' => 'Token not found!',
                    // 'license' => 0,
                    'success'=>0
                ];
                Log::info('ApiTokenVerify-Middleware', ["response"=>$data]);
                return response()->json($data);
            }
        }
        $data = [
            'msg' => 'Not a valid API request.',
            // 'license' => 0,
            'success'=>0
        ];
        Log::info('ApiTokenVerify-Middleware', ["response"=>$data]);
        return response()->json($data);
    }
}
