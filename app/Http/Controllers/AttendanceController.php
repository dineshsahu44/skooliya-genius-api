<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Passport\Token;
use App\Models\Section;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\FacultyAssignClass;
use App\Models\SchoolSession;
use App\Models\Registration;
use App\Models\User;
use App\Models\Attendance;
use App\Models\FacultyAttendance;
use App\Models\Guardian;
use App\Models\Holiday;
use DB;
use Validator;
use Log;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function machineAttendance(Request $request){

        $json = file_get_contents("php://input");

        //for before resopnce
        $check = 1;

        if ($check==1){
            $output = [
                'st' => true,
            ]; 
            echo json_encode($output);
        }
        else{
            $output = [
                'st' => false,
            ]; 
            echo json_encode($output); 
        }

        if(function_exists('litespeed_finish_request')){
            litespeed_finish_request();
        }

        Log::info('Machine Attendance school->'.$request->servername, ['for'=>'pass to itration and insertion.']);
        //End for before resopnce
        $currentSession = currentSession($request->school_id);
        // return $currentSession;
        // $time = 
        $currenttime = date('Y-m-d H:i:s');

        $data = json_decode($json);

        $smsrecord = [];
        $student_att_record = [];
        $staff_att_record = [];
        foreach ($data->txn as $record)
        {
            $GLOBALS['date'] = $date = '20'.substr($record->ldt,4, 2).'-'.substr($record->ldt,2, 2).'-'.substr($record->ldt,0,2);
            $GLOBALS['time'] = $time = substr($record->ldt,-6, 2).':'.substr($record->ldt,-4, 2).':'.substr($record->ldt,-2,2);
            $hittime = date('h:i a', strtotime($time));
            $hitdate = date('d/m/Y', strtotime($date));
            
            $student = Registration::select('registrations.id as re_id','registrations.registration_id as accountid','registrations.name',
            'classes.class','sections.section','classes.id as class_id','sections.id as section_id',
            'card_no as rfid','registrations.mobile as contactno',
            'm_mobile as mothermobile','f_mobile as fathermobile',
            'users.device_token as notificationtoken',
            DB::Raw("TIMEDIFF('".$GLOBALS['time']."',MAX(attendances.att_time)) AS timedif")
            )
            ->join('classes','classes.id','registrations.class')
            ->join('sections','sections.id','registrations.section')
            ->join('guardians','guardians.re_id','registrations.id')
            ->join('users','users.username','registrations.registration_id')
            // LEFT JOIN attendances a ON a.re_id=r.id AND att_date='2022-10-13'
            ->leftJoin('attendances',function($join){
                $join->on("attendances.re_id","=","registrations.id")
                    ->where("attendances.att_date","=",$GLOBALS['date']);
            })
            ->where([['registrations.card_no',$record->csn],['registrations.session',$currentSession->id]])
            ->groupBy('registrations.id')->first();
            // return [$student,$currentSession];
            // $stm->bind_result($companyid,$accountid,$name,$class,$section,$admissionno,$contactno,$fathermobile,$mothermobile,$notificationtoken,$rfid,$timediff);
            if($student){
                // $name = ucwords($name);
                $student_att_record []=[
                    're_id'=> $student->re_id,
                    'class_id'=> $student->class_id,
                    'section_id'=> $student->section_id,
                    'school_id'=> $currentSession->school_id,
                    'session_id'=> $currentSession->id,
                    'att_date'=> $date,
                    'att_time'=> $time,
                    'att_status'=> 'P',
                    'rfid'=>$record->csn,
                    // 'remark'=> null,
                    'rfid_flag'=>1,
                    'oprator'=> 3,
                    'oprate_date'=>now(),
                ];
                
                // $stmt = $conn->prepare("SELECT TempleteID, TempleteContent, TempleteDltID, TempleteSendTo, TempleteSmsFlag, TempleteFcmFlag, TempleteUnicodeFlag, TempletePageVar FROM smstemplete INNER JOIN smstempletefor on smstemplete.TempleteForID=smstempletefor.TempleteForID where smstempletefor.TempletePageName='autoattendance.php' and smstemplete.TempleteDefault=1");
                // $stmt->execute();
                // $stmt->bind_result($TempleteID,$TempleteContent,$TempleteDltID,$TempleteSendTo,$TempleteSmsFlag,$TempleteFcmFlag,$TempleteUnicodeFlag,$TempletePageVar);
                // if($stmt->fetch()){
                    // $TempleteContent = urldecode($TempleteContent);
                    // foreach(json_decode($TempletePageVar,true)['TempleteVar'] as $key=>$val){
                    //     $TempleteContent = str_replace('{{'.$key.'}}', $$key, $TempleteContent);
                    // }
                    
                    // $mobileno = [];
                    // foreach(json_decode($TempleteSendTo,true) as $mobile){
                    //     $temp = $$mobile;
                    //     if(strlen($temp)==10&&is_numeric($temp))
                    //         array_push($mobileno,$temp);
                    // }
                    // echo $TempleteContent.json_encode($mobileno)."\r\n";
                    
                    // exit();
                // }
                // $stmt->close();
                $student_detail = ucwords(strtolower($student->name))." (".$student->class." - ".$student->section.")";
                $msg  = "Dear Parent Your Child $student_detail has punch the Card at $hittime - $hitdate, CardNo. $record->csn";
                // Dear Parent, Your Child  Akhand Singh (UKG -  B)  has punch the Card at  08:26 am -  12/10/2022 , CardNo.  0005092163
                
                $timeDiffFlag = ((date('Y-m-d', strtotime($currenttime))==$date)&&($student->timedif>'00:10:00'||empty($student->timedif)))?1:0;
                // dd([$student->timedif,$timeDiffFlag]);
                // $SmsFlag = count($mobileno)>0?(($timeDiffFlag==1)?($TempleteSmsFlag==1?1:0):0):0;
                // $FcmFlag = ($timeDiffFlag==1)?($TempleteFcmFlag==1?1:0):0;
                $FcmFlag = ($timeDiffFlag==1)?1:0;
                $temp = [
                    "Name"=> $student->name,
                    "AccountID"=> $student->accountid,
                    "SendFor"=> $student->class."-".$student->section,
                    "EntryDate"=> date('Y-m-d', strtotime($currenttime)),
                    "Time"=> date('H:i:s', strtotime($currenttime)),
                    // "ContactNo"=> implode(",",$mobileno),
                    "SmsContent"=> $msg,//$TempleteContent,
                    "Result"=> "Sent",
                    "msgtype"=> "notice",
                    "msgheading"=> $hitdate." at ".$hittime,
                    "notificationtoken"=> $student->notificationtoken,
                    // "Dlt_Tem_Id"=> $TempleteDltID,
                    // "SmsFlag"=> $SmsFlag,
                    "FcmFlag"=> $FcmFlag,
                    // "UnicodeFlag"=> $TempleteUnicodeFlag,
                ];
                // echo json_encode($temp);
                // exit();
                // array_push($smsrecord,$temp);
                // echo $timeDiffFlag;
                // exit();
                $smsrecord[]=$temp;
            }else{
                $faculty = Faculty::select('faculties.id','faculty_id','faculties.name', 'users.device_token as notificationtoken','phone as mobile')
                ->join('users','users.username',DB::Raw('faculties.faculty_id and users.role!="student"'))
                ->whereNotIn('faculties.faculty_id',array(1,2,3))
                ->where([['faculties.status','Active'],['faculties.school_id',$currentSession->school_id],['faculties.card_no',$record->csn]])
                ->first();
                if($faculty){
                    $staff_att_record []=[
                        'faculty_id'=> $faculty->id,
                        'faculty_reg_id'=>$faculty->faculty_id,
                        'school_id'=> $currentSession->school_id,
                        'session_id'=> $currentSession->id,
                        'att_date'=> $date,
                        'att_time'=> $time,
                        'att_status'=> 'P',
                        'rfid'=>$record->csn,
                        // 'remark'=> null,
                        'rfid_flag'=>1,
                        'oprator'=> 3,
                        'oprate_date'=>now(),
                    ];
                }
            }
        }

        if(count($student_att_record)>0){
            Attendance::insert($student_att_record);
        }
        if(count($staff_att_record)>0){
            FacultyAttendance::insert($staff_att_record);
        }
        $data1 = [
            "jsondata"=> $smsrecord,
            "pagefrom"=> "Auto Attendance",
            "postedby"=> "School Attendacne",
            "postedbyid"=> "3",
            "commentstatus"=> "0",
            "attachment"=> '',
        ];
        Log::info('Machine Attendance school->'.$request->servername, ['for'=>'attendance insertion done send to fcm notification.','data'=>$data1]);
        if(count($smsrecord)>0){
            handle_notification_sms($data1,$currentSession);
        }
    }
}
