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
use App\Http\Controllers\LogController;

class AttendanceController extends Controller
{
    public function facultyAttendace(Request $request){
        try{
            if($request->isMethod('get')) {
                return view("faculty-attendance");
            }elseif($request->isMethod('post')) {
                // $date = $request->from_date;
                $companyid = $request->companyid;
                $from_date = Carbon::createFromFormat('d-m-Y', $request->from_date)->format('Y-m-d');

                $facultyAttendances = Faculty::selectRaw('
                    faculties.name,faculties.faculty_id,
                    IF(fa.att_status IS NOT NULL, fa.att_status, "NA") as att_status,
                    GROUP_CONCAT(TIME_FORMAT(att_time, "%h:%i %p") ORDER BY att_time ASC SEPARATOR ", ") AS attendance_record,
                    if(att_time is not null,TIME_FORMAT(MIN(att_time), "%h:%i %p"),"") AS inTime,
                    if(count(att_time)>1,TIME_FORMAT(MAX(att_time), "%h:%i %p"),"") AS outTime,
                    remark
                ')
                ->leftJoin('faculty_attendances as fa', function($join) use ($companyid,$from_date) {
                    $join->on('fa.faculty_id', '=', 'faculties.id')
                        ->where('fa.session_id', '=', $companyid)
                        ->where(DB::raw('date(fa.att_date)'), '=', $from_date);
                })
                ->join('school_sessions','school_sessions.school_id','faculties.school_id')
                ->where([['school_sessions.id', $companyid],['faculties.status','Active']])
                ->whereNotIn('faculties.faculty_id', [1, 2, 3])
                ->groupBy('faculties.faculty_id')
                ->get();
                // return $facultyAttendances;
                $holidays = Holiday::select('name as reason','h_date as entrydate')
                ->where([['session_id',$companyid],['h_date',$from_date]])
                // ->whereRaw("MONTH(h_date)=$month AND YEAR(h_date)=$year")
                ->orderBy('h_date')->first();
                $result['attendancelist'] = $facultyAttendances;
                $result['holidaylist']=$holidays;
                return customResponse(1,$result);
            }
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function machineAttendance(Request $request){
        try{
            $request->school_id = $request->schoolid;
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
                // dd([$date,$time,$record->csn,$student]);
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
                        // 'oprate_date'=>now(),
                    ];
                    
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
                            // 'oprate_date'=>now(),
                        ];
                    }
                }
            }
            // return([$student_att_record,$staff_att_record]);
            Log::info('Machine Attendance school->'.$request->servername, ['for'=>'ready to push on table.','data'=>['student'=>$student_att_record,'staff'=>$staff_att_record]]);
            if(count($student_att_record)>0){
                // Attendance::insert($student_att_record);
                // $sar_collection = collect($student_att_record);   //turn data into collection
                // $student_chunks = $sar_collection->chunk(100); //chunk into smaller pieces
                // $student_chunks->toArray(); //convert chunk to array

                // //loop through chunks:
                // foreach($student_chunks as $sc)
                // {
                //     Attendance::insert($sc);
                // }
                    
                $chunks = array_chunk( $student_att_record, 100 );
                // dd($student_att_record,$chunks,1);
                foreach ( $chunks as $chunk ) {
                    // return  [$student_att_record,$chunk];
                    Attendance::insert($chunk);
                }
            }
            if(count($staff_att_record)>0){
                FacultyAttendance::insert($staff_att_record);

                // $chunks = array_chunk( $staff_att_record, 100 );
                // // dd($student_att_record,$chunks,1);
                // foreach ( $chunks as $chunk ) {
                //     // return  [$student_att_record,$chunk];
                //     FacultyAttendance::insert($chunk);
                // }
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
        }catch(\Exception $e){
            exceptionResponse($e);
        }
    }

    public function parseLog($logString)
    {
        // Remove extra spaces and split the log string into individual fields
        $fields = preg_split('/\s+/', trim($logString));

        // Extract and format the fields
        $mac = @$fields[1];
        $time = @$fields[2];
        $date = @$fields[3];
        $alphanumericCode = @$fields[4];
        $numericalIdentifier = @$fields[5];
        $twoCharacterCode = @$fields[6];
        $rfid = @$fields[7];

        if($rfid==null){
            // $mac = @$fields[1];
            $time = @$fields[1];
            $date = @$fields[2];
            // $alphanumericCode = @$fields[4];
            // $numericalIdentifier = @$fields[5];
            // $twoCharacterCode = @$fields[6];
            $rfid = @$fields[6];
        }
        
        // Return the formatted data
        return [
            // 'mac' => $mac,
            // 'time' => $time,
            // 'date' => $date,
            // 'alphanumeric_code' => $alphanumericCode,
            // 'numerical_identifier' => $numericalIdentifier,
            // 'two_character_code' => $twoCharacterCode,
            'csn' => $rfid,
            'ldt' => $date.$time,
            'ec' => '',
            'fid' => '',
            'io' => '',
        ];
    }

    public function logtojson(Request $request){
        if($request->isMethod('get')) {
            return view("log-file-upload");
        }elseif($request->isMethod('post')) {
            $file = $request->log_file;
            $contents = file_get_contents($file); 
            // dd($contents);
            $lines = explode("\n\r", $contents);
            // dd($lines);

            $jsonData = [];
            $nullRfid = [];
            foreach ($lines as $line) {
                // Add the parsed data to the JSON array
                $j = $this->parseLog($line);
                $jsonData[] = $j;
                if($j['csn']==null)
                $nullRfid[] = $j;
            }

            return ["txn"=>$jsonData,"nullRFID"=>$nullRfid];

        }
    }
}
