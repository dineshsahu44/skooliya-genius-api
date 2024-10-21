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
use Carbon\Carbon;

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
            $students = Registration::select(
                    'registrations.registration_id as studentid',
                    'registrations.id as re_id',
                    'registrations.name',
                    'classes.id as classid',
                    'sections.id as sectionid',
                    'registrations.name',
                    DB::raw("IF(registrations.dob IS NOT NULL, DATE_FORMAT(registrations.dob, '%d-%m-%Y'), '') AS dob"),
                    DB::raw("IF(registrations.dob IS NOT NULL, DATE_FORMAT(registrations.dob, '%Y-%m-%d'), '') AS dobStudent"),
                    DB::raw('COALESCE(f_name, "") as fathername'),
                    DB::raw('COALESCE(m_name, "") as mothername'),
                    DB::raw('COALESCE(registrations.mobile, "") as contactno'),
                    'classes.class',
                    'registrations.photo',
                    DB::raw('COALESCE(registrations.address, "") as address1'),
                    DB::raw('COALESCE(registrations.city, "") as city'),
                    'sections.section',
                    'registrations.photopermission',
                    DB::raw("IF(users.device_token IS NULL OR users.device_token = '', 0, 1) as notificationflag"),
                    DB::raw("IF(users.status = 1, 'Active', 'Deactive') as userstatus"),
                    DB::raw("IF(registrations.status = 'Active', 'Active', 'Deactive') as status")
                )
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
            $student = Registration::select('roll_no as rollno','registrations.id as studentreid','registrations.name','classes.class','sections.section',
                'board_reg_no as admissionno',
                'card_no as rfid','religion as caste','category','registrations.address as address1',
                'registrations.city','registrations.mobile as contactno','r_date as admissiondate',
                'registrations.dob','f_name as fathername','m_name as mothername','registrations.photo',
                'aadhar_no as aadharno','registrations.email','registrations.status',
                'm_mobile as mothermobile','f_mobile as fathermobile','photopermission',
                DB::raw("IF(dob IS NOT NULL, DATE_FORMAT(dob, '%m-%d'), '00-00') AS dobForMachine"),
            )->join('classes','classes.id','registrations.class')
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
                // $user = getAuth();
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
            // dd($e);
            return exceptionResponse($e);
        }
    }

    public function updateStudentProfilePhoto(Request $request){
        try{
            $acceptFiles = accpectFiles('student-photo-files');
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
            if($activitytype=='updatephoto'){
                if ($request->hasFile('profilephoto')) {
                    $attachment = saveFiles1($request->file('profilephoto'),$acceptFiles,$companyid);
                    Registration::where([['session',$companyid],['registration_id',$studentid]])->update(['photo'=>$attachment]);
                    return customResponse(1,['msg'=>'photo update done.','imageurl'=>$attachment]);
                }
                return customResponse(0,['msg'=>'photo update not done.']);
            }
            return customResponse(0);
        }catch(\Exception $e){
            // dd($e);
            return exceptionResponse($e);
        }
    }

    public function markAttendance(Request $request){
        try{
            // return $request->all();
            
            // dd($holidays);

            if($request->isMethod('get')&&$request->has('reporttype')&&$request->reporttype=='takeattandance'){
                $holidays = Holiday::select('h_date as entrydate',DB::Raw("concat(name,'(',name,')') as reason"))->where([['session_id',$request->companyid],['h_date',$request->date]])->orderBy('h_date')->get();
                if($holidays->count() > 0){
                    return customResponse(0,['msg'=>"Can't take attendance. Already have holiday!"]);
                }

                $dateCheck = Carbon::parse($request->date); // Parse the given date

                if ($dateCheck->isSunday()) {
                    // The given date is Sunday
                    return customResponse(0, ['msg' => "The given date is Sunday!"]);
                }

                $class=$_GET['class'];
                $section=$_GET['section'];
                $date=$_GET['date']; 
                $GLOBALS['date'] = $_GET['date'];
                $companyid=$_GET['companyid'];
                $facluty_id = $_GET['accountid'];
                $sql = checkFacultyClassGetRaw($class,$section,$facluty_id,$companyid);
                // DB::enableQueryLog();
                $attStudent = Registration::select('registrations.id','registrations.registration_id as studentid','registrations.name',DB::Raw("IF(ISNULL(registrations.roll_no), 0, registrations.roll_no) as rollno"),
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
                
                $holidays = Holiday::select('h_date as entrydate',DB::Raw("concat(name,'(',name,')') as reason"))->where([['session_id',$request->companyid],['h_date',$entrydate]])->orderBy('h_date')->get();
                if($holidays->count() > 0){
                    return customResponse(0,['msg'=>"Can't take attendance. Already have holiday!"]);
                }

                $dateCheck = Carbon::parse($entrydate); // Parse the given date

                if ($dateCheck->isSunday()) {
                    // The given date is Sunday
                    return customResponse(0, ['msg' => "The given date is Sunday!"]);
                }

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
                DB::Raw("COALESCE(att_time,'') as time, COALESCE(remark,'') as remarks"))
                ->join('registrations','registrations.id','attendances.re_id')
                ->where([['registrations.registration_id',$studentid],['registrations.session',$companyid]])
                ->whereRaw("MONTH(att_date)=$month AND YEAR(att_date)=$year")
                ->orderByRaw('att_date ASC,att_time ASC')->get();
                
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

    public function studentPhotoList(Request $request){
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
            // dd($assigned_class);
            $servername = $request->servername;
            $serverinfo = getServerInfo();
            $serverinfo = [
                'addpermission'=>$serverinfo->addpermission,
                'updatepermission'=>$serverinfo->updatepermission
            ];

            // $mainParameter = "api/studentprofileapi.php?companyid=".$request->companyid."&servername=".$request->servername;
            return view("student-photo-list", compact("assigned_class","auth_token","companyid","servername","accountid","serverinfo"));
        }elseif($request->isMethod('post')) {
            return $this->getStudents($request);
        }
    }

    public function updateProfile(Request $request)
    {
        
        if(getServerInfo()->addpermission==1){
            
            $validator = Validator::make($request->all(),[
                'studentreid' => 'required',
                'studentid' => 'required',
                'name' => 'required',
                'class' => 'required',
                'section' => 'required',
                'companyid' => 'required',
                'accountid' => 'required',
            ]);
            
            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            // return $request->all();
            $companyid = $request->companyid;
            $studentreid = $request->studentreid;
            $studentid = $request->studentid;
            $session = getSchoolIdBySessionID($companyid);
            $user = getUserByUsername($request->accountid);
            $classsection = Section::select('classes.id as classid','sections.id as sectionid','classes.class','sections.section')
                ->join('classes','classes.id','sections.class_id')
                ->where([["classes.class",$request->class],["sections.section",$request->section],['sections.school_id',$session->school_id]])
                ->first();
            // return $classsection;    
            $student = [
                'name'=>$request->name,
                'address'=>$request->address,
                'dob'=> ((Carbon::hasFormat($request->dob, 'd-m-Y'))?Carbon::createFromFormat('d-m-Y', $request->dob):null),
                'mobile'=>$request->contactno,
                'class'=>$classsection->classid,
                'section'=>$classsection->sectionid,
                // 'school_id'=>$session->school_id,
                // 'session'=>$session->id,
                'sync_property_id' => 2,
                'oprator' => $user->id,
                'status'=> $request->status,
                // 'oprate_date' => now(),
                // 'updated_at' =>now(),
            ];
            $guardian = [
                'f_name'=> $request->fathername,
                'm_name'=> $request->mothername,
                'sync_property_id' => 2,
                // 'updated_at' =>now(),
            ];

            Registration::where([['session',$session->id],['id',$studentreid],['registration_id',$studentid]])
            ->update($student);
            Guardian::where([['re_id',$studentreid],['registration_id',$studentid]])
            ->update($guardian);
            $students = $this->getSutdentInfo($studentreid,$session->id);
            
            return customResponse(1,['msg'=>'successfully updated done.',"studentinfo"=>$students]);
        }else{
            return customResponse(0,['msg'=>'not allow to upate profile.']);
        }
    }

    public function addStudent(Request $request)
    {
        try{
            if(getServerInfo()->addpermission==1){
                // Validate the request data
                $validator = Validator::make($request->all(), [
                    'name' => 'required',
                    'class' => 'required',
                    'section' => 'required',
                    'companyid' => 'required',
                    'accountid' => 'required',
                    // // 'dob' => 'required|date',
                    // 'contactno' => 'required',
                    // 'address' => 'required',
                    // 'fathername' => 'required',
                    // 'mothername' => 'required'
                ]);

                
                if ($validator->fails()) {
                    return response()->json(validatorMessage($validator));
                }

                $session = getSchoolIdBySessionID($request->companyid);
                $user = getUserByUsername($request->accountid);
                
                $classsection = Section::select('classes.id as classid','sections.id as sectionid','classes.class','sections.section')
                ->join('classes','classes.id','sections.class_id')
                ->where([["classes.class",$request->class],["sections.section",$request->section],['sections.school_id',$session->school_id]])
                ->first();

                $masters = DB::table('masters')
                    ->where('name', 'Registration_id')
                    ->where('school_id', $session->school_id)
                    ->orderBy('id', 'asc')
                    ->first();

                if (!$masters) {
                    return response()->json(['error' => 'Master record not found'], 404);
                }
            
                // Start transaction
                DB::beginTransaction();
                // Fetch the generated registration_id
                $generatedIdResult = DB::select("SELECT getImpId('StudentAppId', 1, 0) AS registration_id");
                $registrationId = $generatedIdResult[0]->registration_id;
                // Create the student registration
                $registration = new Registration([
                    'registration_id' => $registrationId,
                    'scholar_id' => $masters->value,
                    'name' => $request->name,
                    'dob' =>  ((Carbon::hasFormat($request->dob, 'd-m-Y'))?Carbon::createFromFormat('d-m-Y', $request->dob):null),
                    'class'=>$classsection->classid,
                    'section'=>$classsection->sectionid,
                    'mobile' => $request->mobile,
                    'address' => $request->address,
                    'school_id' => $session->school_id,
                    'oprator' => $user->id,
                    'status' => 'Active',
                    'session' => $session->id,
                    'oprate_date' => now(),
                    'transport' => 'Personal',
                    's_type' => 'APIform',
                    'fee_status' => 0,
                    'sync_property_id' => 1,
                    // 'created_at' => now(),
                    // 'updated_at' => now(),
                ]);
                
                if($registration->save()){
                    // Get the last inserted ID and registration_id
                    $lastInsertedId = $registration->id;
                    $registrationId = $registration->registration_id;
                    // dd([$lastInsertedId,$registrationId]);
                    DB::table('masters')
                        ->where('name', 'Registration_id')
                        ->where('school_id', $session->school_id)
                        ->update(['value' => ($masters->value + 1)]);

                    // Create the guardian details
                    Guardian::create([
                        're_id' => $lastInsertedId,
                        'registration_id' => $registrationId,
                        'f_name' => $request->f_name,
                        'm_name' => $request->m_name,
                        'sync_property_id' => 1,
                        // 'created_at' => now(),
                        // 'updated_at' => now(),
                    ]);

                    // Create the user
                    User::create([
                        'name' => $request->name,
                        'password' => $request->contactno, // Encrypting password
                        'username' => $registrationId,
                        'school_id' => $session->school_id,
                        'role' => 'student',
                        'status' => 1,
                    ]);
                }
                // Commit transaction
                DB::commit();
                $students = $this->getSutdentInfo($lastInsertedId,$session->id);
                return customResponse(1,['msg'=>'successfully added.',"studentinfo"=>$students]);
            }else{
                return customResponse(0,['msg'=>'not allow to add profile.']);
            }
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function addStudentToMachine(Request $request){
        try{
            // return $request->all();
            $servername = $request->servername;
            $machine = getMachinesInSchool($servername);

            $companyid = $request->companyid;
            $studentreid = $request->studentreid;
            $studentid = $request->studentid;

            $new_request = new Request([
                'companyid' => $companyid,
                'q' => $studentid,
            ]);

            $studentdata = $this->studentProfile($new_request);
            // dd($studentdata);
            $insertData = [];
            
            $photoUrlForBasename = $studentdata->photo;
            $imageUrl = str_replace(" ", "%20", $photoUrlForBasename);
            if (!empty($imageUrl)) {
                if ($imageInfo = @getimagesize($imageUrl)) {
                    $imageData = @file_get_contents($imageUrl);
                    $base64Image = base64_encode($imageData);
                    if ($imageData !== false) {        
                        if($imageUrl!=null){                                        
                            $cmdsetup = [
                                "cmd" => "setuserinfo",
                                "enrollid" => (int)$studentid,
                                "name" => $studentdata->name,
                                "backupnum" => 50,
                                "admin" => 0,
                                "birthday" => $studentdata->dobForMachine,
                                "record" => $base64Image
                            ];
                        }                  
                        foreach($machine as $m){                
                            $insertData[] = [
                                "serial" => $m,
                                "name" => "setuserinfo",
                                "content" => json_encode($cmdsetup),
                                "gmt_crate" => now(),
                                "gmt_modified" => now(),
                            ];
                        }
                        if(count($insertData)>0){
                            // Temporary connection configuration
                            $tempDBConfig = [
                                'driver'    => 'mysql',
                                'host'      => "127.0.0.1",
                                'database'  => 'faceskooliya_realtime',
                                'username'  => 'faceskooliya_realtime',
                                'password'  => 'Skooliya@123',
                                'charset'   => 'utf8',
                                'collation' => 'utf8_unicode_ci',
                                'prefix'    => '',
                                'strict'    => false,
                            ];
                    
                            // Set temporary connection
                            config(['database.connections.temp_mysql' => $tempDBConfig]);
                            $tempDB = DB::connection('temp_mysql');
                            // Insert data into the temporary database
                            $tempDB->table('machine_command')->insert($insertData);
                            $tempDB->disconnect();
                            // temp connection close;
                            Registration::where('id', $studentdata->studentreid)
                            ->update(['s_position' => 1]);
                            return customResponse(1,['msg'=>'successfully Face insertion done.']);
                        }
                    }
                }
            }
            return customResponse(0,['msg'=>'Please check image first!']);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }    
    }

    
    public function studentShortProfile(Request $request)
    {
        try{        
            // dd([$request->servername,$request->appid]);
            $user = getUserByUsername($request->accountid);
            $cursession = getcurrentSchoolAndSession($user->school_id);
            $companyid = $cursession->sessionid;
            $accountid = $request->accountid;
            $new_request = new Request([
                'companyid' => $companyid,
                'q' => $accountid,
            ]);
            $studentdata = $this->studentProfile($new_request);
            // return [$studentdata,$cursession];
            $servername = $request->servername;
            // $serverinfo = getServerInfo();
            // $mainParameter = "api/studentprofileapi.php?companyid=".$request->companyid."&servername=".$request->servername;
            // return view("student-photo-list", compact("assigned_class","auth_token","companyid","servername","accountid","serverinfo"));
            return view("student-profile-short",compact("studentdata","cursession","companyid","servername","accountid"));
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function getSutdentInfo($studentreid,$sessionid){
        $students = Registration::select(
            'registrations.registration_id as studentid',
            'registrations.id as re_id',
            'registrations.name',
            'classes.id as classid',
            'sections.id as sectionid',
            'registrations.name',
            DB::raw("IF(registrations.dob IS NOT NULL, DATE_FORMAT(registrations.dob, '%d-%m-%Y'), '') AS dob"),
            DB::raw("IF(registrations.dob IS NOT NULL, DATE_FORMAT(registrations.dob, '%Y-%m-%d'), '') AS dobStudent"),
            DB::raw('COALESCE(f_name, "") as fathername'),
            DB::raw('COALESCE(m_name, "") as mothername'),
            DB::raw('COALESCE(registrations.mobile, "") as contactno'),
            'classes.class',
            'registrations.photo',
            DB::raw('COALESCE(registrations.address, "") as address1'),
            DB::raw('COALESCE(registrations.city, "") as city'),
            'sections.section',
            'registrations.photopermission',
            DB::raw("IF(registrations.status = 'Active', 'Active', 'Deactive') as status")
        )
        ->join('guardians','guardians.re_id','registrations.id')
        ->join('classes','classes.id','registrations.class')
        ->join('sections','sections.id','registrations.section')
        // ->leftJoin('users','users.username',DB::Raw("registrations.registration_id and users.role='student'"))
        ->where([
            ['registrations.session',$sessionid],
            // ['registrations.status','Active'],
            ['registrations.id',$studentreid],
            // ['registrations.registration_id',$studentappid]
        ])->first();
        return $students;
    }
}
