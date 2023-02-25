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
use App\Models\Guardian;
use App\Models\Holiday;
use DB;
use Validator;
use Log;
class StudentController extends Controller
{
    public function getStudents(Request $request){
        try{
            // return ["success"=>1,"students"=>[]];
            // $companyid = $_GET['companyid'];
            // $accountid = $_GET['accountid'];	
            // $getclass = $_GET['class'];
            // $getsection = $_GET['section'];   
            $validator = Validator::make($request->all(),[
                'companyid' => 'required',
                'accountid' => 'required',
                'class' => 'required',
                'section' => 'required',
            ]);
            

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }

            $getclass = $request->class;
            $getsection = $request->section;
            $getcompanyid = $request->companyid;
            $facluty_id = $request->accountid;
            $sql = checkFacultyClassGetRaw($getclass,$getsection,$facluty_id,$getcompanyid);
            // Log::info('getStudents', ["sql"=>$sql]);
            // DB::enableQueryLog();
            $students = Registration::select('registrations.registration_id as studentid','registrations.name','registrations.dob as dob',
                'f_name as fathername','m_name as mothername','registrations.mobile as contactno','classes.class',
                'registrations.photo','registrations.address as address1','registrations.city','sections.section','photopermission',
                DB::Raw("IF(users.device_token IS NULL or users.device_token = '', 0, 1) as notificationflag"),
                DB::Raw("IF(users.status = 1,'Active','Deactive') as status"))
                ->join('guardians','guardians.re_id','registrations.id')
                ->join('classes','classes.id','registrations.class')
                ->join('sections','sections.id','registrations.section')
                ->leftJoin('users','users.username',DB::Raw("registrations.registration_id and users.role='student'"))
                ->where([['registrations.session',$getcompanyid],['registrations.status','Active']])
                ->whereRaw($sql)->orderBy('registrations.name')->get();
            // dd(DB::getQueryLog());
                // Log::info('getStudents', ["sql"=>$students]);
            return customResponse(1,['students'=>$students]);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function studentProfile(Request $request){
        try{
            // $companyid = $_GET['companyid'];   
            $validator = Validator::make($request->all(),[
                'companyid' => 'required',
                'q' => 'required',
            ]);
            
            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            $student = Registration::select('roll_no as rollno','registrations.name','classes.class','sections.section','board_reg_no as admissionno',
            'card_no as rfid','religion as caste','category','registrations.address as address1','registrations.city','registrations.mobile as contactno','r_date as admissiondate',
            'registrations.dob','f_name as fathername','m_name as mothername','registrations.photo','aadhar_no as aadharno','registrations.email','registrations.status',
            'm_mobile as mothermobile','f_mobile as fathermobile','photopermission')
            ->join('classes','classes.id','registrations.class')
            ->join('sections','sections.id','registrations.section')
            ->join('guardians','guardians.re_id','registrations.id')
            ->where([['registrations.registration_id',$request->q],['registrations.session',$request->companyid]])->first();
            if($student){
                $student['route']="";
                $student['busno']="";
                $student['address2']="";
                return customResponse(1,$student);
            }
            return customResponse(0);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function updateStudent(Request $request){
        try{
            // return customResponse(0,['msg'=>$request->all()]);
            // $activitytype=$_POST['activitytype'];
            // $companyid = $_GET['companyid'];
            // $_POST['studentid']
            $acceptFiles = accpectFiles('faculty-photo-files');
            $validator = Validator::make($request->all(),[
                'companyid' => 'required',
                'activitytype' => 'required',
                'profilephoto' => $request->activitytype=='updatephoto'?'required|mimes:'.implode(',',$acceptFiles['extension-type']).'|max:'.$acceptFiles['max-size'].'':'',
            ]);
            
            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            $activitytype = $request->activitytype;
            $companyid = $request->companyid;
            $studentid = $request->studentid;
            $user = getAuth();
            if($activitytype=='resetpassword'){
                $user_table = getUserTableRecord($user->school_id,$studentid);
                Token::where('user_id', $user_table->id)->update(['revoked' => true]);
                User::where([['role','student'],['school_id',$user->school_id],['username',$studentid]])->update(['password'=>'1234','device_token'=>null]);
                return customResponse(1,['msg'=>'password reset done.']);
            }elseif($activitytype=='updatecontactno'){
                $student = Registration::select('id','registration_id','session','school_id')->where([['session',$companyid],['registration_id',$studentid]])->first();
                Registration::where([['session',$companyid],['registration_id',$studentid]])->update(['mobile'=>$request->contactno]);
                Guardian::where([['re_id',$student->id],['registration_id',$studentid]])->update(['f_mobile'=>$request->fathermobile,'m_mobile'=>$request->mothermobile]);
                return customResponse(1,['msg'=>'contact updated.']);
            }elseif($activitytype=='photopermission'){
                $status = $request->status=='Y'?1:0;
                Registration::where([['session',$companyid],['registration_id',$studentid]])->update(['photopermission'=>$status]);
                $result['status']= $status;
                $result['studentid']= $studentid;
                $result['companyid'] = $companyid;
                $result['msg']='photo permission done.';
                return customResponse(1,$result);
            }elseif($activitytype=='updatephoto'){
                if ($request->hasFile('profilephoto')) {
                    $attachment = saveFiles($request->file('profilephoto'),$acceptFiles);
                    Registration::where([['session',$companyid],['registration_id',$studentid]])->update(['photo'=>$attachment]);
                    return customResponse(1,['msg'=>'photo update done.','imageurl'=>$attachment]);
                }
                return customResponse(0,['msg'=>'photo update not done.']);
            }elseif($activitytype=='changestatus'){
                $status = $request->status=='Y'?1:0;
                $session = getSchoolIdBySessionID($companyid);
                User::where([['username',$studentid],['school_id',$session->school_id]])->update(['status'=>$status]);
                return customResponse(1,['msg'=>'user status change done.']);
            }

            return customResponse(0);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function markAttendance(Request $request){
        try{
            if($request->isMethod('get')&&$request->has('reporttype')&&$request->reporttype=='takeattandance'){
                $class=$_GET['class'];
                $section=$_GET['section'];
                $date=$_GET['date']; 
                $GLOBALS['date'] = $_GET['date'];
                $companyid=$_GET['companyid'];
                $facluty_id = $_GET['accountid'];
                $sql = checkFacultyClassGetRaw($class,$section,$facluty_id,$companyid);
                // DB::enableQueryLog();
                $attStudent = Registration::select('registrations.id','registrations.registration_id as studentid','registrations.name','registrations.roll_no as rollno',
                'classes.class','sections.section',DB::Raw("IF(ISNULL(attendances.id)=1,'',GROUP_CONCAT(JSON_OBJECT('id',attendances.id,'entrydate',attendances.att_date,
                'attendancevalue',attendances.att_status,'remarks',attendances.remark))) as attendancerecord"))
                ->leftJoin('attendances', function ($join) {
                    $join->on('attendances.re_id', 'registrations.id')
                    ->where('attendances.att_date', $GLOBALS['date']);
                })->join('classes','classes.id','registrations.class')->join('sections','sections.id','registrations.section')
                ->where([['registrations.status','Active'],['registrations.session',$companyid]])
                ->whereRaw($sql)->orderBy('registrations.name')->groupBy('registrations.id')->get();
                // dd(DB::getQueryLog());
                // return $attStudent;
                $student_record = [];
                foreach($attStudent as $stu){
                    $student_record[] = [
                        'reg_id'=>$stu->studentid,
                        'studentid'=> $stu->id,
                        'studentname'=> $stu->name,
                        'rollno'=>$stu->rollno,
                        'admissionno'=> "",
                        'class'=> $stu->class,
                        'section'=> $stu->section,
                        'studentrecord'=> json_decode("[".$stu->attendancerecord."]",true),
                    ];
                }
                $session = getSchoolIdBySessionID($companyid);
                $result['attendancelist']=$student_record;
                $result['company'] = [
                    "session_start"=>$session->start_date,
                    "session_end"=>$session->end_date,
                    "session"=>$session->name,
                ];
                return customResponse(1,$result);
            }elseif($request->isMethod('post')&&$request->has('returnresponse')){
                $companyid = $_GET['companyid'];
                $returnresponse = $_POST['returnresponse'];
                $data = json_decode($returnresponse);
                $entrydate = $data->date;
                $current_time = date("H:i:s");
                $session = getSchoolIdBySessionID($companyid);
                $user = getAuth();
                $att_record = [];
                foreach ($data->changelist as $record){
                    $re_id = $record->studentid;
                    $value = $record->value;
                    $remarks = $record->reason;
                    Attendance::where([['re_id',$re_id],['att_date',$entrydate],['session_id',$companyid]])->delete();
                    $student = Registration::select('class','section')->where('id',$re_id)->first();
                    $att_record []=[
                        're_id'=> $re_id,
                        'class_id'=> $student->class,
                        'section_id'=> $student->section,
                        'school_id'=> $session->school_id,
                        'session_id'=> $companyid,
                        'att_date'=> $entrydate,
                        'att_time'=> $current_time,
                        'att_status'=> $value,
                        'remark'=> $remarks,
                        'oprator'=> $user->id,
                        'oprate_date'=>now(),
                    ];
                }
                Attendance::insert($att_record);
                return customResponse(1,['msg'=>'attendance mark successfully done.']);
            }
            return customResponse(0);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function getStudentAttendance(Request $request){
        try{
            $studentid=$_GET['studentid'];
            $companyid=$_GET['companyid'];
            $month = $_GET['month'];
            $year = $_GET['year'];
            $att_record = Attendance::select('att_date as entrydate','att_status as attendancevalue',
                DB::Raw("IF(ISNULL(att_time)=1,'',att_time) as time"),DB::Raw("IF(ISNULL(remark)=1,'',att_time) as remarks"))
                ->join('registrations','registrations.id','attendances.re_id')
                ->where([['registrations.registration_id',$studentid],['registrations.session',$companyid]])
                ->whereRaw("MONTH(att_date)=$month AND YEAR(att_date)=$year")->orderByRaw('att_date ASC','att_time ASC')->get();
            $holidays = Holiday::select('name as reason','h_date as entrydate')
            ->where('session_id',$companyid)->whereRaw("MONTH(h_date)=$month AND YEAR(h_date)=$year")->orderBy('h_date')->get();

            $result['attendancelist'] = $att_record;
            $result['holidaylist']=$holidays;
            return customResponse(1,$result);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function getAttendanceCount(Request $request){
        try{
            $studentid=$_GET['studentid'];
            $companyid=$_GET['companyid'];

            $att_count = Attendance::select(
                DB::Raw("COUNT(DISTINCT (CASE att_status WHEN 'P' THEN att_date end)) totalpresent"),
                DB::Raw("COUNT(DISTINCT (CASE att_status WHEN 'A' THEN att_date end)) totalabsent"),
                DB::Raw("COUNT(DISTINCT (CASE att_status WHEN 'L' THEN att_date end)) totalleave"),
                DB::Raw("DATEDIFF(IF(CURDATE()>end_date,end_date,CURDATE()), school_sessions.start_date) totaldays"),
                DB::Raw("COUNT(DISTINCT holidays.h_date) totalholiday"),'start_date as session_start','end_date as session_end'
                )->join('registrations','registrations.id','attendances.re_id')
                ->join('holidays','holidays.session_id','attendances.session_id')
                ->join('school_sessions','school_sessions.id','registrations.session')
                ->where([['registrations.registration_id',$studentid],['registrations.session',$companyid]])->get();
            $result['totalrecord'] = $att_count;
            return customResponse(1,$result);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function getAttendanceReport(Request $request){
        try{
            $validator = Validator::make($request->all(),[
                'reporttype'=>'required|in:datewise,monthwise',
                'companyid' => 'required',
                'accountid' => 'required',
                'class' => 'required',
                'section' => 'required',
                'date'=>$request->reporttype=='datewise'?'required':'',
                'month'=>$request->reporttype=='monthwise'?'required':'',
            ]);
            

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            $class=$_GET['class'];
            $section=$_GET['section'];
            $companyid=$_GET['companyid'];
            $facluty_id = $_GET['accountid'];
            $reporttype=$_GET['reporttype'];

            if($reporttype==='datewise'){
                $date=$_GET['date']; 
                $GLOBALS['date'] = $_GET['date'];
                $sql = checkFacultyClassGetRaw($class,$section,$facluty_id,$companyid);
                // DB::enableQueryLog();
                $attStudent = Registration::select('registrations.id','registrations.registration_id as studentid','registrations.name as studentname','registrations.roll_no as rollno',
                'classes.class','sections.section','registrations.photo',
                DB::Raw("IF(att_status IS NULL or att_status = '', registrations.name, CONCAT(registrations.name,' - ', att_status)) as name"),
                DB::Raw("IF(users.device_token IS NULL or users.device_token = '', 0, 1) as notificationflag"),
                'registrations.mobile','att_date as entrydate','att_status as attendancevalue','attendances.remark as remarks',
                /*DB::Raw("GROUP_CONCAT(JSON_OBJECT('time',att_time)) as time1"),
                DB::Raw("IF(att_time IS NULL or att_time = '', '', GROUP_CONCAT(JSON_OBJECT('time',att_time))) as time")*/)
                ->leftJoin('attendances', function ($join) {
                    $join->on('attendances.re_id', 'registrations.id')
                    ->where('attendances.att_date', $GLOBALS['date']);
                })->join('classes','classes.id','registrations.class')->join('sections','sections.id','registrations.section')
                ->join('users','users.username','registrations.registration_id')
                ->where([['registrations.status','Active'],['registrations.session',$companyid]])
                ->whereRaw($sql)->orderBy('registrations.name')->groupBy('registrations.id')->get();
                // dd(DB::getQueryLog());
                $holidays = Holiday::select('name as reason','h_date as entrydate')->where([['session_id',$companyid],['h_date',$date]])->first();
                $session = getSchoolIdBySessionID($companyid);
                $result['list']=$attStudent;
                $result['holiday'] = isset($holidays->reason)?$holidays->reason:'';
                return customResponse(1,$result);
            }elseif($reporttype==='monthwise'){
                $month=$_GET['month']; 
                $GLOBALS['month'] = $_GET['month'];
                $sql = checkFacultyClassGetRaw($class,$section,$facluty_id,$companyid);
                // DB::enableQueryLog();
                $attStudent = Registration::select('registrations.id','registrations.registration_id as studentid',
                'registrations.name','registrations.roll_no as rollno','registrations.scholar_id',
                'classes.class','sections.section',
                DB::Raw("IF(ISNULL(attendances.id)=1,'',GROUP_CONCAT(JSON_OBJECT('id',attendances.id,'entrydate',attendances.att_date,
                'attendancevalue',attendances.att_status,'remarks',attendances.remark))) as attendancerecord"))
                ->leftJoin('attendances', function ($join) {
                    $join->on('attendances.re_id', 'registrations.id');
                })->join('classes','classes.id','registrations.class')->join('sections','sections.id','registrations.section')
                ->where([['registrations.status','Active'],['registrations.session',$companyid]])
                ->whereRaw("MONTH(attendances.att_date)=$month")
                ->whereRaw($sql)
                ->orderBy('registrations.name')
                // ->orderByRaw('STR_TO_DATE(attendances.att_date,"%Y %M %d")')
                ->groupBy('registrations.id')
                ->get();

                $holidays = Holiday::select('name as reason','h_date as entrydate')
                ->where('session_id',$companyid)->whereRaw("MONTH(h_date)=$month")->orderBy('h_date')->get();
                $student_record = [];
                foreach($attStudent as $stu){
                    $student_record[] = [
                        // 'reg_id'=>$stu->id,
                        'studentid'=> (int)$stu->studentid,
                        'studentname'=> !empty($stu->rollno)&&$stu->rollno!=null?$stu->name.' ('.$stu->rollno.')':$stu->name,
                        'rollno'=>$stu->rollno,
                        'admissionno'=> $stu->scholar_id,
                        'class'=> $stu->class,
                        'section'=> $stu->section,
                        'studentrecord'=> json_decode("[".$stu->attendancerecord."]",true),
                    ];
                }                
                $result['maxdate']=getSchoolIdBySessionID($companyid)->end_date;
                $result['attendancelist'] = $student_record;
                $result['holidayslist']=$holidays;
                return customResponse(1,$result);
            }
            return customResponse(0);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }
}
