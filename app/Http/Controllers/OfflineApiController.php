<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Config;
use DB;

class OfflineApiController extends Controller
{
    public function company(Request $request){
        if($request->comp){
            $data = json_decode($request->comp);
            foreach ($data->data as $record)
            {
                $company = [
                    'companyname' => $record->companyname,
                    'city' => $record->city,
                    'phone' => $record->Phone,
                    'mobile' => $record->mobile,
                    'email' => $record->email,
                    'software' => $record->software,
                    'dealer' => $record->dealer,
                    'status' => $record->status,
                    'license' => $record->license,
                    'password' => $record->password,
                    'macid' => $record->macid,
                ];
                $lastInsertId = DB::table('company')->insertGetId($company);
                if ($lastInsertId){
                    $response["success"] = 1;
                    $response["message"] = "done";	 
                    $response["id"] = $lastInsertId;
                    return $response;
                }
                return 0;
            }
        }
    }

    public function deleteCompany(Request $request){
        // Log::info(['Request' => $request->companyid]);
        // die;
        // return json_decode($_POST['comp']);
        if($request->companyid){
            DB::table('admission')->where(['companyid'=>$request->companyid])->delete();
            DB::table('enquiry')->where(['companyid'=>$request->companyid])->delete();
            DB::table('feesvoucher')->where(['companyid'=>$request->companyid])->delete();
            DB::table('feestransaction')->where(['companyid'=>$request->companyid])->delete();
            // DB::table('attendancemain')->where(['companyid'=>$request->companyid])->delete();
            DB::table('attendance')->where(['companyid'=>$request->companyid])->delete();
            DB::table('teachers')->where(['companyid'=>$request->companyid])->delete();
            return "success";
        }
    }
}
