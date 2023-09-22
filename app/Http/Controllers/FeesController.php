<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Section;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\FacultyAssignClass;
use App\Models\SchoolSession;
use App\Models\Registration;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Guardian;
use App\Models\Holiday;
use App\Models\StudentFeeDetail;
use App\Models\FeeTransection;
use App\Models\School;
use Carbon\Carbon;

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

    public function studentFeeCard(Request $request){
        $companyid = $request->companyid;
        $studentid = $request->studentid;
        $studentRecord = Registration::select([
            'registrations.id as ReID',
            'registrations.registration_id as AppID',
            'registrations.name as StudentName',
            'registrations.mobile',
            'registrations.scholar_id as ScholarID',
            'guardians.f_name as FatherName',
            'classes.class as Class',
            'sections.section as Section',
        ])
        ->join('guardians', 'registrations.id', '=', 'guardians.re_id')
        ->join('classes', 'registrations.class', '=', 'classes.id')
        ->join('sections', 'registrations.section', '=', 'sections.id')
        ->where([
            'registrations.registration_id' => $studentid,
            'registrations.session' => $companyid,
        ])
        ->first();
        
        $outstandingRecords = StudentFeeDetail::select([
            'registrations.id as ReID',
            'registrations.registration_id as AppID',
            'registrations.name as StudentName',
            'registrations.scholar_id as ScholarID',
            'student_fee_details.month',
            'student_fee_details.term',
            'student_fee_details.structure_status',
            'student_fee_details.start_month',
            DB::raw('SUM(student_fee_details.defult_amount) AS group_sum'),
            DB::raw('(CASE WHEN DATEDIFF(CURDATE(), student_fee_details.defaulter_date) > 0 THEN DATEDIFF(CURDATE(), student_fee_details.defaulter_date) ELSE 0 END) AS fine_days'),
            DB::raw('CONCAT("[", GROUP_CONCAT(JSON_OBJECT(
                        "month", student_fee_details.month,
                        "fhead", (CASE WHEN student_fee_details.structure_status="T FEE" THEN "Transport Fee" WHEN student_fee_details.structure_status="DUE FEE" THEN "Last Due" ELSE heads.head END),
                        "term", student_fee_details.term,
                        "student_fee_id", student_fee_details.id,
                        "structure_status", student_fee_details.structure_status,
                        "defult_amount", student_fee_details.defult_amount,
                        "start_month", student_fee_details.start_month,
                        "end_month", student_fee_details.end_month,
                        "defaulter_date", student_fee_details.defaulter_date
                    )), "]") as object'),
        ])
        ->join('registrations', 'registrations.id', '=', 'student_fee_details.re_id')
        ->leftJoin('heads', 'student_fee_details.head_id', '=', 'heads.id')
        ->where([
            'student_fee_details.re_id' => $studentRecord->ReID,
            'student_fee_details.session' => $companyid,
            'student_fee_details.status' => 0,
            'student_fee_details.visibility' => 0,
        ])
        ->whereRaw('student_fee_details.defult_amount > 0')
        // ->groupBy('student_fee_details.start_month')
        ->groupBy('student_fee_details.month')
        ->orderBy('student_fee_details.start_month')
        ->orderBy('student_fee_details.structure_id')
        ->get();
        // \DB::enableQueryLog();
        $paymentHistory = FeeTransection::select([
            'fee_transections.default_amount',
            'fee_transections.discount',
            'fee_transections.paid_amount',
            'fee_transections.fine',
            'fee_transections.due',
            'fee_transections.mode',
            'fee_transections.transection_id as ReceiptNo',
            DB::raw("DATE_FORMAT(fee_transections.t_date, '%d-%m-%Y') as ReceiptDate"),
            'student_fee_details.registration_id as AppID',
            DB::raw("GROUP_CONCAT(DISTINCT LEFT(student_fee_details.month, 3) ORDER BY student_fee_details.start_month ASC SEPARATOR ', ') AS Months"),
            DB::raw('CONCAT("[", GROUP_CONCAT(JSON_OBJECT(
                        "fhead",
                        (CASE WHEN student_fee_details.structure_status="T FEE" THEN "Transport Fee" WHEN student_fee_details.structure_status="DUE FEE" THEN "Last Due" ELSE heads.head END),
                        "structure_status", student_fee_details.structure_status,
                        "defult_amount", student_fee_details.defult_amount
                    )), "]") as object'),
        ])
        ->join('student_fee_details', 'fee_transections.transection_id', '=', 'student_fee_details.transection_id')
        ->leftJoin('heads', 'student_fee_details.head_id', '=', 'heads.id')
        ->where([
            'fee_transections.re_id' => $studentRecord->ReID,
            'fee_transections.session_id' => $companyid,
        ])
        ->where('fee_transections.status', '!=', 'Cancel')
        ->groupBy('fee_transections.transection_id')
        ->orderByDesc('fee_transections.t_date')
        ->get();
        // return(\DB::getQueryLog()); 
        $school_details = School::select('schools.school','schools.name as city','mobile as phone','mobile',
            'email','schools.address')
        ->join('school_sessions', 'school_sessions.school_id', '=', 'schools.id')    
        ->where('school_sessions.id',$companyid)->first();//machine value// form webview

        $out_collection = collect($outstandingRecords);
        // Calculate the sum of 'group_sum' values
        $totalDue = $out_collection->sum('group_sum');
        $lastDue = $out_collection->firstWhere('term', 'Last Due');
        $till_due_fees = $out_collection->filter(function ($value, $key) {
            $start_month = Carbon::createFromFormat('Y-m-d', $value->start_month);
            $current_date = Carbon::now()->format('Y-m-d');
            return $value['term']=='Last Due'?true:($start_month->lte($current_date))?true:false;
        });
        $tillDue = $till_due_fees->sum('group_sum');
        $history_collection = collect($paymentHistory);
        $totalPaid = $history_collection->sum('paid_amount');
        return view("student-fees", compact("school_details","totalPaid","totalDue","tillDue","lastDue","studentRecord","outstandingRecords","paymentHistory"));
        // return [$school,$lastDue,$studentRecord,$outstandingRecords,$paymentHistory];
    }

    public function feesDayBook(Request $request){
        $auth_token = $request->header('Authorization');
        $companyid = $request->companyid;
        $accountid = $request->accountid;
        if($request->isMethod('get')) {
            // Create a request with the required parameters
            $new_request = new Request([
                'companyid' => $companyid,
                'accountid' => $accountid,
            ]);
            // Call the getAllClasses function from the StaticController
            $staticController = app(StaticController::class); 
            $assigned_class = $staticController->getAllClasses($new_request);
            return view("fee-day-book", compact("assigned_class","auth_token"));
        }elseif($request->isMethod('post')) {
            $from_date = Carbon::createFromFormat('d-m-Y', $request->from_date)->format('Y-m-d');
            $to_date = Carbon::createFromFormat('d-m-Y', $request->to_date)->format('Y-m-d');
            $class_array = [['C.class',$request->class]];
            if($request->section!='All'){
                $class_array[]=['S.section',$request->section];
            }
            
            $paymentHistory = StudentFeeDetail::selectRaw('
                CONCAT("[", GROUP_CONCAT(JSON_OBJECT(
                    "fhead", (CASE WHEN student_fee_details.structure_status="T FEE" THEN "Transport Fee" WHEN student_fee_details.structure_status="DUE FEE" THEN "Old Balance" ELSE Head.head END),
                    "structure_status", student_fee_details.structure_status,
                    "defult_amount", student_fee_details.defult_amount
                )), "]") AS object,
                GROUP_CONCAT(DISTINCT LEFT(student_fee_details.month, 3) ORDER BY student_fee_details.start_month ASC SEPARATOR ", ") AS Months,
                student_fee_details.registration_id AS AppID,
                FT.default_amount,
                FT.discount,
                FT.paid_amount,
                FT.fine,
                FT.due,
                FT.mode,
                FT.transection_id AS ReceiptNo,
                DATE_FORMAT(FT.t_date, "%d-%m-%Y") as ReceiptDate,
                FG.group as FeesGroup,
                R.id as ReID,
                R.registration_id as AppID,
                R.name as StudentName,
                R.scholar_id as ScholarID,
                if(G.f_name is not null,G.f_name,"") as FatherName,
                C.class as Class,
                S.section as Section,
                PP.name as Route,
                V.name as VehicleName,
                R.mobile
            ')
            ->join('fee_transections AS FT', 'FT.transection_id', '=', 'student_fee_details.transection_id')
            ->leftJoin('heads AS Head', 'student_fee_details.head_id', '=', 'Head.id')
            ->join('registrations AS R', 'R.id', '=', 'student_fee_details.re_id')
            ->join('guardians AS G', 'R.id', '=', 'G.re_id')
            ->join('classes AS C', 'R.class', '=', 'C.id')
            ->join('sections AS S', 'R.section', '=', 'S.id')
            ->leftJoin('fee_groups AS FG', 'FG.id', '=', 'R.group_id')
            ->leftJoin('passengers AS P', 'P.re_id', '=', 'R.id')
            ->leftJoin('pick_points AS PP', 'PP.id', '=', 'P.pick_point_id')
            ->leftJoin('vehicles AS V', 'V.id', '=', 'P.vehicle_id')
            ->where('student_fee_details.session', '=', $companyid)
            ->where('student_fee_details.status', '=', 1)
            ->where('student_fee_details.transection_id', '=', DB::raw('FT.transection_id'))
            ->where([[ DB::raw('date(FT.t_date)'), '>=', $from_date],[ DB::raw('date(FT.t_date)'), '<=', $to_date]])
            ->where($class_array)
            ->groupBy('FT.transection_id')
            ->get();
            return $paymentHistory;
        }
    }

    public function feesDetails(Request $request){
        $auth_token = $request->header('Authorization');
        $companyid = $request->companyid;
        $accountid = $request->accountid;
        if($request->isMethod('get')) {
            // Create a request with the required parameters
            $new_request = new Request([
                'companyid' => $companyid,
                'accountid' => $accountid,
            ]);
            // Call the getAllClasses function from the StaticController
            $staticController = app(StaticController::class); 
            $assigned_class = $staticController->getAllClasses($new_request);
            return view("fees-details", compact("assigned_class","auth_token"));
        }elseif($request->isMethod('post')) {
            // $from_date = Carbon::createFromFormat('d-m-Y', $request->from_date)->format('Y-m-d');
            // $to_date = Carbon::createFromFormat('d-m-Y', $request->to_date)->format('Y-m-d');
            $class_array = [['C.class',$request->class]];
            if($request->section!='All'){
                $class_array[]=['S.section',$request->section];
            }
            $current_date = Carbon::now()->format('Y-m-d');
            $paymentHistory = Registration::selectRaw('
                CONCAT("[",
                    GROUP_CONCAT(
                        DISTINCT
                            CASE
                                WHEN FT.id IS NOT NULL THEN JSON_OBJECT("ft_id", FT.id, "paid_amount", FT.paid_amount)
                                ELSE NULL
                            END
                )
                , "]") AS totalPaid,
                SUM(IF(FT.id IS NULL,IF(student_fee_details.defult_amount IS NOT NULL,student_fee_details.defult_amount,0) ,0)) AS totalDue,
                SUM(IF(FT.id IS NULL AND DATEDIFF("'.$current_date.'",student_fee_details.start_month)>=0,student_fee_details.defult_amount,0)) AS tillDue,
                registrations.id as ReID,
                registrations.registration_id as AppID,
                registrations.name as StudentName,
                registrations.scholar_id as ScholarID,
                if(G.f_name is not null,G.f_name,"") as FatherName,
                C.class as Class,
                S.section as Section,
                registrations.mobile,
                registrations.session
            ')
            // ->leftJoin('student_fee_details', 'student_fee_details.re_id', '=', 'registrations.id')
            ->leftJoin('student_fee_details', function ($join) {
                $join->on('student_fee_details.re_id', '=', 'registrations.id')
                     ->where('student_fee_details.visibility', '=', 0);
            })
            ->leftJoin('fee_transections AS FT', 'FT.transection_id', '=', 'student_fee_details.transection_id')
            ->leftJoin('heads AS Head', 'student_fee_details.head_id', '=', 'Head.id')
            ->join('guardians AS G', 'registrations.id', '=', 'G.re_id')
            ->join('classes AS C', 'registrations.class', '=', 'C.id')
            ->join('sections AS S', 'registrations.section', '=', 'S.id')
            ->where([['registrations.session', '=', $companyid],['registrations.status', '=', 'Active']])
            // ->where('student_fee_details.status', '=', 1)
            // ->where('student_fee_details.transection_id', '=', DB::raw('FT.transection_id'))
            // ->where([[ DB::raw('date(FT.t_date)'), '>=', $from_date],[ DB::raw('date(FT.t_date)'), '<=', $to_date]])
            ->where($class_array)
            ->groupBy('registrations.id')
            ->orderBy('registrations.name')
            ->get();
            return $paymentHistory;
        }
    }
}
