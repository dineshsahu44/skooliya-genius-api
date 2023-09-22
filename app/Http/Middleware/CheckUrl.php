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
                'database'  => 'u210117126_test',
                'username'  => 'u210117126_test',
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
                if($server['dbhost']=='localhost'){$host='localhost';}elseif(env('APP_ENV')=="local"){$host='217.21.80.2';}else{ $host='localhost';}
                $datadb = [
                    'driver'    => 'mysql',
                    'host'      => $host,
                    'database'  => $server['dbname'],
                    'username'  => $server['dbuser'],
                    'password'  => ($server['dbhost']=='localhost')?'':'Skooliya@123',
                    'charset'   => 'utf8',
                    'collation' => 'utf8_unicode_ci',
                    'prefix'    => '',
                    'strict'    => false,
                ];
                // dd([$datadb,$server]);
                config::set(['database.connections.mysql' => $datadb]);
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
