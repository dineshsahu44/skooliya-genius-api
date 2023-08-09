<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Section;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Holiday;
use App\Models\School;
use App\Models\FacultyAssignClass;
use App\Models\MainScreenOption;
use App\Models\Feedback;
use DB;
use Validator;

class StaticController extends Controller
{
    public function getAllClasses(Request $request){
        try{
            // $accountid = $_GET['accountid'];
            // $companyid = $_GET['companyid'];
            $validator = Validator::make($request->all(),[
                'companyid' => 'required',
                'accountid' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            $school = getSchoolIdBySessionID($request->companyid);
            // $classSesction = Section::select('class','section')->join('classes','classes.id','sections.class_id')
            //     ->where('sections.school_id',$school->school_id)->get();
            $classSesction = Classes::select('class',DB::raw("group_concat(section) as section"))->join('sections','sections.class_id','classes.id')
                ->where('classes.school_id',$school->school_id)->orderBy('class')->groupBy('class')->get();
            $allclass = [];
            foreach($classSesction as $class){
                $sec = explode(',',$class->section);
                sort($sec);
                $allclass[] = [
                    'class'=>$class->class,
                    'section'=>$sec,
                ];
            }
            $faculty = Faculty::select('assignclass')->where([['faculty_id',$request->accountid],['school_id',$school->school_id]])->first();
            $assignclass = !empty($faculty->assignclass)&&$faculty->assignclass!=null?$faculty->assignclass=='all'?$allclass:json_decode($faculty->assignclass,true):[];
            $result = [
                'allclass'=> $allclass,
                'permittedclass'=> $assignclass
            ];
            return customResponse(1,$result);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function changeFacultyPermission(Request $request){
        try{
            // $accountid=$_POST['accountid'];
            // $permvalue=$_POST['permvalue'];
            // $permtype = $_POST['permtype'];
            $validator = Validator::make($request->all(),[
                'permvalue' => 'required',
                'permtype' => 'required',
                'accountid' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }

            $user = getAuth();
            if($request->permtype=='classcontrol'){
                if($request->permvalue!='all'&&$request->permvalue!='[]'&&@count($assignclass = json_decode($request->permvalue,true))>0){
                    // $assignclass = json_decode($request->permvalue,true);
                    $facultyAssignClass = [];
                    foreach($assignclass as $class){
                        foreach($class['section'] as $section){
                            $facultyAssignClass[] = [
                                'class'=> $class['class'],
                                'section'=> $section,
                                'school_id'=> $user->school_id,
                                'accountid'=> $request->accountid,
                            ];
                        }
                    }
                    Faculty::where([['school_id',$user->school_id],['faculty_id',$request->accountid]])->update(['assignclass'=>$request->permvalue,'oprate_date'=>now()]);
                    FacultyAssignClass::where([['school_id',$user->school_id],['accountid',$request->accountid]])->delete();
                    FacultyAssignClass::insert($facultyAssignClass);
                }elseif($request->permvalue=='all'||$request->permvalue=='[]'){
                    Faculty::where([['school_id',$user->school_id],['faculty_id',$request->accountid]])->update(['assignclass'=>$request->permvalue,'oprate_date'=>now()]);
                    FacultyAssignClass::where([['school_id',$user->school_id],['accountid',$request->accountid]])->delete();
                }else{
                    return customResponse(0,['msg'=>'incorrect class format.']);
                }
            }else if($request->permtype=='makeadmin'){
                $permvalue = $request->permvalue=='Y'?'admin':'teacher';
                User::where([['school_id',$user->school_id],['username',$request->accountid]])->update(['role'=>$permvalue]);
            }else if($request->permtype=='teacherstatus'){
                $permvalue = $request->permvalue=='Y'?1:0;
                User::where([['school_id',$user->school_id],['username',$request->accountid]])->update(['status'=>$permvalue]);
            }else{
                Faculty::where([['school_id',$user->school_id],['faculty_id',$request->accountid]])->update([$request->permtype=>$request->permvalue]);
            }
            return customResponse(1);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
        
    }

    public function createFeedback(Request $request){
        try{
            // return customResponse(1,['msg'=>$request->all()]);
            // $postedby=$_POST['postedby'];
            // $apptype=$_POST['apptype'];
            // $subject=$_POST['subject'];
            // $description=$_POST['description'];
            // $accountid=$_POST['accountid'];
            // $attachment ='';
            $acceptFiles = accpectFiles('feedback-files');
            $validator = Validator::make($request->all(),[
                'postedby' => 'required',
                'apptype' => 'required',
                'accountid' => 'required',
                'subject' => 'required',
                'description' => 'required',
                'attachment' => 'mimes:'.implode(',',$acceptFiles['extension-type']).'|max:'.$acceptFiles['max-size'].''
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }

            if ($request->hasFile('attachment')) {
                $attachment = saveFiles($request->file('attachment'),$acceptFiles);
            }
            $currentSession = currentSession();
            $feedback = [
                'postedby'=> $request->postedby,
                'apptype'=> $request->apptype,
                'subject'=> $request->subject,
                'description'=> $request->description,
                'companyid' =>$currentSession->id,
                'dateposted'=>now(),
                'postedbyid'=> $request->accountid,
                'attachment'=> @$attachment,
            ];
            Feedback::create($feedback);
            return customResponse(1,['msg'=>'feedback done.']);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function getFeedback(Request $request){
        try{
            // return $request->all();
            // $g_compnayid = $companyid;
            // $companyid=$_GET['companyid'];
            // $accountid=$_GET['accountid'];
            $validator = Validator::make($request->all(),[
                'companyid' => 'required',
                'accountid' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }

            // $currentSession = currentSession();
            $GLOBALS['companyid'] = $companyid = $request->companyid;
            $user = getAuth();
            $faculty = Faculty::select('assignclass')->where([['faculty_id',$request->accountid],['school_id',$user->school_id]])->first();
            $assignclass = !empty($faculty->assignclass)&&$faculty->assignclass!=null?$faculty->assignclass=='all'?'all':$faculty->assignclass:[];
            $feedback = [];
            if(json_decode($assignclass,true)>0){
                $pageLimit = pageLimit(@$request->page);
                $temp = implodeClass($assignclass);
                $feedback = Feedback::select('feedbackid','postedbyid',DB::Raw("concat(postedby,'(',classes.class,'-',sections.section,')')"),'apptype','subject','description','attachment','dateposted','classes.class','sections.section')
                    ->join('registrations', function ($join) {
                        $join->on('registrations.registration_id', 'feedback.postedbyid')
                        ->where('registrations.session',$GLOBALS['companyid']);
                    })->join('classes','classes.id','registrations.class')->join('sections','sections.id','registrations.section')
                    ->where('feedback.companyid',$companyid)->whereRaw("concat_ws('-',classes.class,sections.section) IN ($temp)")
                    ->orderBy('dateposted')->offset($pageLimit->offset)->limit($pageLimit->limit)->get();
            }elseif($assignclass=='all'){
                $pageLimit = pageLimit(@$request->page);
                // $temp = implodeClass($assignclass);
                $feedback = Feedback::select('feedbackid','postedbyid',DB::Raw("concat(postedby,'(',classes.class,'-',sections.section,')')"),'apptype','subject','description','attachment','dateposted','classes.class','sections.section')
                    ->join('registrations', function ($join) {
                        $join->on('registrations.registration_id', 'feedback.postedbyid')
                        ->where('registrations.session',$GLOBALS['companyid']);
                    })->join('classes','classes.id','registrations.class')->join('sections','sections.id','registrations.section')
                    ->where('feedback.companyid',$companyid)->orderBy('dateposted')->offset($pageLimit->offset)->limit($pageLimit->limit)->get();
            }
            return customResponse(1,['list'=>$feedback]);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function getBirthdayCard(Request $request){
        try{
            // $accounttype=$_GET['accounttype'];
            $validator = Validator::make($request->all(),[
                'accounttype' => 'required|in:teacher,student',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            $birthdayCard = BirthdayCard::select('cardimageurl','status')->where([['apptype',$request->accounttype],['enabled',1]])->first();
            return customResponse(1,$birthdayCard);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }

    }
    
    public function getBuses(Request $request){
        try{
            return customResponse(0);
            // $companyid=$_GET['companyid'];
            $validator = Validator::make($request->all(),[
                'companyid' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            $details = array(); 
            $temp = array();
            $temp['busid']= 1;
            $temp['busno'] = "";
            $temp['busregnno'] = "";
            $temp['drivername'] = "";
            $temp['drivermobile'] = "";
            $temp['conductorname'] = "";
            $temp['conductormobile'] = "";
            array_push($details, $temp);
            return customResponse(1,['list'=>$details]);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }

    }

    public function getBusInfo(Request $request){
        try{
            return customResponse(0);
            // $companyid=$_GET['companyid'];
            // $studentid=$_GET['studentid'];
            $validator = Validator::make($request->all(),[
                'companyid' => 'required',
                'studentid' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            $details = array(); 
            $temp = array();
            $temp['busid']= 1;
            $temp['busno'] = "";
            $temp['busregnno'] = "";
            $temp['drivername'] = "";
            $temp['drivermobile'] = "";
            $temp['conductorname'] = "";
            $temp['conductormobile'] = "";
            array_push($details, $temp);
            return customResponse(1,['list'=>$details]);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }

    }

    public function getHolidays(Request $request){
        try{
            // $companyid=$_GET['companyid'];
            $validator = Validator::make($request->all(),[
                'companyid' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            // SELECT entrydate,reason FROM holiday WHERE companyid=$companyid  ORDER BY entrydate
            $holidays = Holiday::select('h_date as entrydate',DB::Raw("concat(name,'(',name,')') as reason"))->where('session_id',$request->companyid)->orderBy('h_date')->get();
            return customResponse(1,['list'=>$holidays]);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function createDeleteHoliday(Request $request){
        try{
            // $companyid=$_POST['companyid'];
            // $date=$_POST['date'];
            // $action=$_POST['action'];
            // $reason=$_POST['reason'];
            $validator = Validator::make($request->all(),[
                'companyid' => $request->action=='delete'?'required':'',
                'reason'=> $request->action=='create'?'required':'',
                'date' => 'required|date',
                'action' => 'required|in:delete,create',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            if($request->action=='delete'){
                Holiday::where([['session_id',$request->companyid],['h_date',$request->date]])->delete();
            }elseif($request->action=='create'){
                $current =currentSession();
                $holiday = new Holiday();
                $holiday->name = $request->reason;
                $holiday->h_date = $request->date;
                $holiday->status = 'Public holiday';
                $holiday->school_id = $current->school_id;
                $holiday->session_id = $current->id;
                $holiday->save();
                
            }
            return customResponse(1);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function schoolProfile(Request $request){
        try{
            // $_GET['accounttype'];   
            $validator = Validator::make($request->all(),[
                'accounttype' => 'required'
            ]);
            
            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            $current = currentSession();
            $school = School::select('school','name as city','mobile as phone','mobile',
            'email','fees_webview as machine')->where('id',$current->school_id)->first();//machine value// form webview
            if($school){
                $schoolName = explode(' ',trim(strtoupper($school['school'])));
                $acronym = "";
                foreach ($schoolName as $s) {
                    $acronym .= mb_substr($s, 0, 1);
                }
                $school['shortschoolname'] = $acronym.', '.ucwords(trim(strtolower($school['city'])));
                $school['schoolname'] = ucwords(trim(strtolower($school['school']))).', '.ucwords(trim(strtolower($school['city'])));
                $school['currsessionid'] = $current->id;
                $school['currsessionyear'] = $current->name;
                $school['session_start'] = $current->start_date;
                $school['session_end'] = $current->end_date;
                $school['smspart1'] = '';
                $school['smspart2'] = '';
                $school['smspart3'] = '';
                $school['smspart4'] = '';
                $school['success']=1;
                $school['machine']=1;//machine value// form webview
            }
            $main = MainScreenOption::where([['accounttype',$request->accounttype],['status',1],['school_id',$current->school_id]])
            ->orderBy('id')
            ->get();
            $main_screen = mainScreenToAll($request->accounttype);
            if(@$main_screen){
                foreach($main_screen as $m){
                    $main[] = $m;
                }
            }
            $school['optionslist']=$main;
            return customResponse(1,$school);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function autoAttendance(Request $request){
        // Log::info('Attendance Record', ['Request' => $request,"urlFull"=>$request->fullUrl(),"url"=>$request->url(),"param"=>$_POST]);
        Log::info('Attendance Record', ['Request' =>json_decode(file_get_contents('php://input')),"urlFull"=>$request->fullUrl()]);
        
        $output = [
            'st' => "true" ,
        ]; 
        // echo json_encode($output);
        return $output;
    }

    public function setEpt(Request $request){
        $output = [
            'sdt' => date('dmyHi') ,
        ]; 
        // echo json_encode($output);
        return $output;
    }

    public function show(Request $request){
        echo "ok";
    }

    public function takeATour(Request $request){
        Log::info('Static Controller', ['Request' => $request,"urlFull"=>$request->fullUrl(),"url"=>$request->url(),"param"=>$_POST]);
        return "Comming soon";
    }
}
