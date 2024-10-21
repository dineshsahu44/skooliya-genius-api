<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrderDetail;
use App\Models\Registration;
use App\Models\Faculty;

class OrderController extends Controller
{
    public function addOrder(Request $request)
    {
        try {
            // return $request->all();
            // return 
            $orderFor = @orderFor($request->OrderFor);
            $recordAppid = $request->input('record.appid');

            if (!empty($orderFor)) {
                // $session->school_id
                $activeSessionId = $request->companyid;
                $session = getSchoolIdBySessionID($request->companyid);
                // $user = getUserByUsername($request->accountid);
                
                $order = OrderDetail::where('OrderStatus', 'initiate')
                    ->where('OrderFor', $orderFor['OrderFor'])
                    ->where('SessionID', $activeSessionId)
                    ->first();

                if ($order) {
                    $orderIdsFromTable = json_decode($order->OrderForIDS, true) ?? [];

                    $appIds = array_merge($orderIdsFromTable, $recordAppid);
                    $appIds = json_encode($appIds);

                    $order->OrderForIDS = $appIds;
                    // $order->updated_at = now();
                    $saveStatus = $order->save();
                } else {
                    $appIds = json_encode($request->input('record.appid'));
                    
                    $orderData = [
                        'UniqueOrderID' => $this->createUniqueOrderID($request->accountid),
                        'GeneratedOrderDate' => now(),
                        'OrderFor'=>$orderFor['OrderFor'],
                        'OrderFromTable'=> $orderFor['OrderFromTable'],
                        'OrderBy' => $request->accountid,
                        'OrderStatus' => 'initiate',
                        'OrderForIDS' => $appIds,
                        'SessionID' => $activeSessionId,
                        'SchoolID' => $session->school_id,
                        // 'created_at' => now(),
                        // 'updated_at' => now(),
                    ];

                    $saveStatus = OrderDetail::create($orderData);
                }

                if ($saveStatus) {
                    if ($orderFor['OrderFor'] == "StudentIDCard") {
                        Registration::whereIn('registration_id', $recordAppid)
                            ->where('session', $activeSessionId)
                            ->update([
                                'icard_status' => 1,
                                'updated_at' => now(),
                            ]);
                    }
                    if ($orderFor['OrderFor'] == "StaffIDCard") {
                        Faculty::whereIn('faculty_id', $recordAppid)
                            ->where('school_id', $session->school_id)
                            ->update([
                                'icard_status' => 1,
                                // 'updated_at' => now(),
                            ]);
                    }
                    return customResponse(1,['msg'=>"Successfully added into the cart!"]);
                }
            }

            return customResponse(0,["msg"=>"Somthing went wrong!"]);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function createUniqueOrderID($accountid){
        $serverName = getServerInfo()->servername;
        return strtoupper($serverName).date("YmdHis").$accountid;
    }
}
