<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Config;
use DB;
use App\Models\Server;
use App\Models\User;
use Session;
use Illuminate\Support\Facades\Log;
class CheckUrl
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
        Log::info('CheckUrl', ['Request' => $request,"urlFull"=>$request->fullUrl(),"url"=>$request->url()]);
        try{
            config::set(['database.connections.mysql' => [
                'driver'    => 'mysql',
                'host'      => env('APP_ENV')=="local"?'217.21.80.2':"localhost",
                'database'  => 'u210117126_3050884_test',
                'username'  => 'u210117126_skooliya',
                'password'  => 'Skooliya@123',
                'charset'   => 'utf8',
                'collation' => 'utf8_unicode_ci',
                'prefix'    => '',
                'strict'    => false,
            ]]);
            $server = Server::where('servername',strtolower($request->servername))->first();
            // dd($server);
            DB::disconnect();
            if($server){
                config::set(['database.connections.mysql' => [
                    'driver'    => 'mysql',
                    'host'      => env('APP_ENV')=="local"?'217.21.80.2':"localhost",//$server['dbhost'],
                    'database'  => $server['dbname'],
                    'username'  => $server['dbuser'],
                    'password'  => 'Skooliya@123',
                    'charset'   => 'utf8',
                    'collation' => 'utf8_unicode_ci',
                    'prefix'    => '',
                    'strict'    => false,
                ]]);
                Session::put('server',$server);
                return $next($request);
            }else{
                return response()->json(['success'=>0,'msg'=>'school name is not found!',]);
            }
        }catch(\Exception $e){
            // dd($e);
            return response()->json(['success'=>0,'msg'=>'error on server config!','errormsg'=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }
}
