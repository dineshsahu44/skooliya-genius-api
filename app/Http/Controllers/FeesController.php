<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Section;
use App\Models\Classes;
use DB;
use Validator;

class FeesController extends Controller
{
    public function feeCollection(Request $request){
        // $classname=$_GET['classname'];
        // $section=$_GET['section'];
        // $startdate=$_GET['startdate'];
        // $enddate=$_GET['enddate'];
        // $companyid=$_GET['companyid'];

        return customResponse(1,['totalfees'=>0]);
    }

    public function feesVoucher(Request $request){
        // $studentid=$_GET['studentid'];
        // $companyid=$_GET['companyid'];
        $details = array();
        $temp = array();
        $temp['voucherid'] = "";
        $temp['date'] = "";
        $temp['months'] = "";
        $temp['oldbalance'] ="";
        $temp['latefees'] = "";
        $temp['totalamt'] = "";
        $temp['concessionamt'] = "";
        $temp['netfees'] = "";
        $temp['receiptamt'] = "";
        $temp['balanceamt'] = "";
        array_push($details, $temp);
        return customResponse(1,['list'=>$details]);
    }
}
