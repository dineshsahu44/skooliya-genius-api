<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; 
use App\Models\Admission;
use App\Models\Server;
use App\Models\User;
use DB;


class TestController extends Controller
{
    public static function test(Request $request){
        return DB::table('admission')->get();
        // return [$server,Admission::get()];
    }
    public static function login(Request $request){
        $user = User::where([['username',$request->username],['password',$request->password]])->first();
        if($user&&Auth::loginUsingId($user->id)){
            $user = Auth::user();
            $success['token'] =  $user->createToken('AuthToken')->accessToken;
            return response()->json(['success' => $success]);
        }
        else{
            return response()->json(['error'=>'Unauthorised'], 401);
        }
    }

    public function details() 
    { 
        $user = Auth::user(); 
        return response()->json(['success' => $user]); 
    } 
}
