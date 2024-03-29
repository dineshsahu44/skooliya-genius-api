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

class CheckOfflineApi
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
        // dd($request->schoolname);
        try{
            config::set(['database.connections.mysql' => [
                'driver'    => 'mysql',
                'host'      => 'localhost',//env('APP_ENV')=="local"?'217.21.80.2':"localhost",
                'database'  => '3050884_test',//'u210117126_test',
                'username'  => 'root',//'u210117126_test',
                'password'  => '',//'Skooliya@123',
                'charset'   => 'utf8',
                'collation' => 'utf8_unicode_ci',
                'prefix'    => '',
                'strict'    => false,
            ]]);
            $server = Server::where('servername',strtolower($request->schoolname))->first();
            // dd($server);
            DB::disconnect();
            if($server){
                config::set(['database.connections.mysql' => [
                    'driver'    => 'mysql',
                    'host'      => /*env('APP_ENV')=="local"?'217.21.80.2':"localhost",*/$server['dbhost'],
                    'database'  => $server['dbname'],
                    'username'  => $server['dbuser'],
                    'password'  => '','Skooliya@123',
                    'charset'   => 'utf8',
                    'collation' => 'utf8_unicode_ci',
                    'prefix'    => '',
                    'strict'    => false,
                ]]);
                Session::put('server',$server);
                Log::info('CheckOfflineApi middleware', ['Request' => $request,"urlFull"=>$request->fullUrl(),"url"=>$request->url()]);
                return $next($request);
            }else{
                Log::info('CheckOfflineApi middleware', ['Request' => $request,"urlFull"=>$request->fullUrl(),"url"=>$request->url()]);
                return response()->json(['success'=>0,'msg'=>'school name is not found!',]);
            }
        }catch(\Exception $e){
            Log::info('CheckOfflineApi middleware', ['Request' => $request,"urlFull"=>$request->fullUrl(),"url"=>$request->url(),'msg'=>'error on server config!','errormsg'=>@$e->getMessage(),"line"=>@$e->getLine()]);
            // dd($e);
            return response()->json(['success'=>0,'msg'=>'error on server config!','errormsg'=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }
}
