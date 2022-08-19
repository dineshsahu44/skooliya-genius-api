<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Passport\Token;
use Illuminate\Support\Facades\Auth; 
use Illuminate\Support\Facades\Log;
use App\Models\Admission;
use App\Models\Server;
use App\Models\User;
use App\Models\Registration;
use App\Models\Faculty;
use App\Models\HwMessage;
use App\Models\HwMessageFor;
use App\Models\BirthdayCard;
use App\Models\LiveClass;
use App\Models\LiveClassFor;

use DB;

class AuthController extends Controller
{
    public static function login(Request $request){
        Log::info('Auth Controller', ['Request' => $request,"urlFull"=>$request->fullUrl(),"url"=>$request->url(),"param"=>$_POST]);
        $msgUnauth = ["msg"=>"unauthorized access!"];
        $msgTable = ["msg"=>"record not found in table!"];
        try{
            $user = User::where([['username',$request->username],['password',$request->password]])->first();
            if($user){
                Token::where('user_id', $user->id)->update(['revoked' => true]);
            }
            if($user&&Auth::loginUsingId($user->id)){
                $user = Auth::user();
                if($request->apptype=="student"){
                    // DB::enableQueryLog();
                    $student = Registration::select('registrations.id as re_id','registrations.registration_id','registrations.name','classes.class',
                        'sections.section','registrations.category','registrations.address','registrations.city','registrations.mobile','registrations.r_date',
                        'registrations.dob','registrations.photo','guardians.f_name','guardians.m_name')
                        ->join('guardians','guardians.re_id','registrations.id')
                        ->join('classes','classes.id','registrations.class')
                        ->join('sections','sections.id','registrations.section')
                        ->where([['registrations.registration_id',$user->username],['registrations.school_id',$user->school_id]])
                        ->orderBy('registrations.id','DESC')->take(1)->first();
                    if($student){
                        $studentSessionList = Registration::select('school_sessions.id as companyid','school_sessions.name as session','school_sessions.start_date',
                            'school_sessions.end_date')
                            ->join('school_sessions','school_sessions.id','registrations.session')
                            ->where([['registrations.registration_id',$user->username],['registrations.school_id',$user->school_id]])->orderBy('school_sessions.id','DESC')->get();
                            // dd(DB::getQueryLog());
                        $success = [
                            "studentinfo"=>[
                                "success"=> 1,
                                "studentid"=> $student->registration_id,
                                "name"=> $student->name,
                                "class"=> $student->class,
                                "section"=> $student->section,
                                "admissionno"=> $student->registration_id,
                                "route"=> "N.A.",
                                "busno"=> "",
                                "caste"=> $student->category,
                                "address1"=> $student->address,
                                "address2"=> "",
                                "city"=> $student->city,
                                "contactno"=> $student->mobile,
                                "admissiondate"=> !empty($student->r_date)||$student->r_date!=null?date('Y-m-d',strtotime($student->r_date)):'',//"2021-02-28",
                                "dob"=> !empty($student->dob)||$student->dob!=null?date('Y-m-d',strtotime($student->dob)):'',//"2021-02-28",
                                "fathername"=> $student->f_name,
                                "mothername"=> $student->m_name,
                                "photo"=> $student->photo,
                                "password"=> $user->password
                            ],
                            "sessionlist"=> $studentSessionList,
                        ];
                    }else{
                        return customResponse(0,$msgTable);
                    }
                }elseif($request->apptype=="teacher"){
                    $faculty = Faculty::where([['faculties.faculty_id',$user->username],['faculties.status','Active'],['faculties.school_id',$user->school_id]])
                        ->orderBy('faculties.id','DESC')->take(1)->first();
                    if($faculty){
                        $facultySessionList = Faculty::select('school_sessions.id as companyid','school_sessions.name as session','school_sessions.start_date',
                            'school_sessions.end_date')
                            ->join('school_sessions','school_sessions.school_id','faculties.school_id')
                            ->where([['faculties.faculty_id',$user->username],['faculties.school_id',$user->school_id]])->orderBy('school_sessions.id','DESC')->get();
                        $success = [
                            "teacherinfo"=> [
                                "success"=> 1,
                                "accountid"=> $faculty->faculty_id,
                                "accounttype"=> $user->role,
                                "accountname"=> $faculty->name,
                                "photo"=> $faculty->photo,
                                "address1"=> $faculty->address,
                                "city"=> $faculty->city,
                                "state"=> $faculty->state,
                                "mobile"=> $faculty->phone,
                                "birthday"=> !empty($faculty->dob)||$faculty->dob!=null?date('Y-m-d',strtotime($faculty->dob)):'',
                                "anniversary"=> null,
                                "noticepermission"=> $faculty->noticepermission,
                                "gallerypermission"=> $faculty->gallerypermission,
                                "eventspermission"=> $faculty->eventspermission,
                                "homeworkpermission"=> $faculty->homeworkpermission,
                                "quizpermission"=> $faculty->quizpermission,
                                "smspermission"=> $faculty->smspermission,
                                "onlineclasspermission"=> $faculty->onlineclasspermission,
                                "contactnopermission"=> $faculty->contactnopermission,
                                "teacherstatus"=> $faculty->status=='Active'?'Y':'N',
                            ],
                            "sessionlist"=> $facultySessionList,
                        ];
                    }else{
                        return customResponse(0,$msgTable);
                    }
                }

                $success['token'] =  'Bearer '.$user->createToken('AuthToken')->accessToken;
                Log::info('Auth Controller  Response', ['Response' => $success]);
                return customResponse(1,$success);
            }
            else{
                Log::info('Auth Controller  Unauth', ['Response' => $msgUnauth]);
                return customResponse(0,$msgUnauth);
            }
        }catch(\Exception $e){
            Log::Error('Auth Controller Exception ', ['Exception' => $e]);
            return exceptionResponse($e);
        }
    }

    public static function checkUsPass(Request $request){
        $msgUnauth = ["msg"=>"unauthorized access!"];
        $msgTable = ["msg"=>"record not found in table!"];
        $link = [['title' => 'Learn hindi Alphabets and words', 'link' => 'https://www.youtube.com/watch?v=U3MfXjiL0rM', 'subject' => 'Hindi'], ['title' => 'Classroom Demonstration English', 'link' => 'https://www.youtube.com/watch?v=-X7okpS9Ufc', 'subject' => 'English']];
        try{
            if($user = getAuth()){
                if($request->apptype=="student"){
                    $currentSession = currentSession();
                    $companyid= $currentSession->id;
                    // DB::enableQueryLog();
                    $student = Registration::select('registrations.id as re_id','registrations.registration_id','registrations.name','classes.class',
                        'sections.section','registrations.category','registrations.address','registrations.city','registrations.mobile','registrations.r_date',
                        'registrations.dob','registrations.photo','guardians.f_name','guardians.m_name')
                        ->join('guardians','guardians.re_id','registrations.id')
                        ->join('classes','classes.id','registrations.class')
                        ->join('sections','sections.id','registrations.section')
                        ->where([['registrations.registration_id',$user->username],['registrations.session',$companyid]])->first();
                    if($student){
                        $studentSessionList = Registration::select('school_sessions.id as companyid','school_sessions.name as session','school_sessions.start_date',
                            'school_sessions.end_date')
                            ->join('school_sessions','school_sessions.id','registrations.session')
                            ->where([['registrations.registration_id',$user->username],['registrations.school_id',$user->school_id]])->orderBy('school_sessions.id','DESC')->get();
                            // dd(DB::getQueryLog());
                        $announcement = HwMessage::join('hwmessagefor','hwmessagefor.msgid','hwmessage.msgid')
                            ->where([['hwmessage.companyid',$companyid],['hwmessagefor.studentid',$user->username]])
                            ->get()->count();
                        
                        $video = LiveClassFor::join('live_classes', function ($join) {
                                $join->on('live_classes.live_classes_id', 'live_classes_for.live_classes_id')
                                ->where('live_classes.status', 1);
                            })->join('live_docs', function ($join) {
                                $join->on('live_docs.live_classes_id', 'live_classes.live_classes_id')
                                ->where([['live_docs.type', 'videolink'],['live_docs.status', 1]]);
                            })->where([['live_classes_for.class',$student->class],['live_classes_for.section',$student->section]])->get()->count();

                        $birthdayCard = BirthdayCard::where([['apptype','teacher'],['enabled',1]])->first();
                        $videolink = LiveClass::select('live_docs.title','live_docs.attachment as link','live_classes.subject')
                            ->join('live_docs', function ($join) {
                                $join->on('live_docs.live_classes_id', 'live_classes.live_classes_id')
                                ->where([['live_docs.type', 'videolink'],['live_docs.status', 1]]);
                            })->where([['live_classes.status',1],['companyid',$companyid]])
                            ->orderByDesc('live_docs.postdate')->offset(0)->limit(2)->get();
                        
                        if($videolink->count()==2)
                            $youtubelink = $videolink;
                        elseif($videolink->count()==1){
                            $videolink[]=$link[1];
                            $youtubelink = $videolink;
                        }else
                            $youtubelink =$link;
                            
                        $success = [
                            "success"=>1,
                            "studentid"=> $student->registration_id,
                            "name"=> $student->name,
                            "birthdaycard"=>[
                                "success"=> 1,
                                "cardimageurl"=> $birthdayCard->cardimageurl,
                                "status"=> $birthdayCard->status,
                                "btntext"=>  $birthdayCard->btntext,
                                "type"=> !empty($student->dob)||$student->dob!=null?date('m-d',strtotime($student->dob))==date('m-d')?1:0:0,//$type 0-nothing, 1-birthdaycard can skip, 2-never skip the warning;
                                "url"=> $birthdayCard->url,
                            ],
                            "total"=>[
                                "quiz"=> 10,
                                "announcement"=> $announcement,
                                "video"=> $video
                            ],
                            "youtubelink"=>$youtubelink,
                            "sessionlist"=> $studentSessionList,
                        ];
                    }else{
                        $success = $msgTable;
                    }
                }elseif($request->apptype=="teacher"){
                    $currentSession = currentSession();
                    $companyid= $currentSession->id;
                    $faculty = Faculty::where([['faculties.faculty_id',$user->username],['faculties.status','Active'],['faculties.school_id',$user->school_id]])
                        ->orderBy('faculties.id','DESC')->take(1)->first();
                    if($faculty){
                        $facultySessionList = Faculty::select('school_sessions.id as companyid','school_sessions.name as session','school_sessions.start_date',
                            'school_sessions.end_date')
                            ->join('school_sessions','school_sessions.school_id','faculties.school_id')
                            ->where([['faculties.faculty_id',$user->username],['faculties.school_id',$user->school_id]])->orderBy('school_sessions.id','DESC')->get();
                        
                        $assignClasses = $faculty->assignclass!='all'?$faculty->assignclass!=''||$faculty->assignclass!=null?implodeClass($faculty->assignclass):null:$faculty->assignclass;
                        
                        $studentCount = Registration::join('classes','classes.id','registrations.class')
                            ->join('sections','sections.id','registrations.section')
                            ->join('school_sessions','school_sessions.id','registrations.session')
                            ->where([['school_sessions.school_id',$user->school_id],['school_sessions.status','Active']])
                            ->whereRaw($assignClasses=='all'?'1=1':"CONCAT_WS('-',classes.class,sections.section) IN ($assignClasses)")
                            ->get()->count();
                            
                        $announcement = HwMessage::where([['companyid',$companyid],['postedbyid',$user->username]])->get()->count();
                        $video = LiveClass::join('live_docs', function ($join) {
                                $join->on('live_docs.live_classes_id', 'live_classes.live_classes_id')
                                ->where('live_docs.type', 'videolink');
                            })->where([['live_classes.accountid',$user->username],['companyid',$companyid]])->get()->count();
                        
                        $birthdayCard = BirthdayCard::where([['apptype','teacher'],['enabled',1]])->first();
                        $videolink = LiveClass::select('live_docs.title','live_docs.attachment as link','live_classes.subject')
                            ->join('live_docs', function ($join) {
                                $join->on('live_docs.live_classes_id', 'live_classes.live_classes_id')
                                ->where([['live_docs.type', 'videolink'],['live_docs.status', 1]]);
                            })->where([['live_classes.status',1],['companyid',$companyid]])
                            ->orderByDesc('live_docs.postdate')->offset(0)->limit(2)->get();

                        if($videolink->count()==2)
                            $youtubelink = $videolink;
                        elseif($videolink->count()==1){
                            $videolink[]=$link[1];
                            $youtubelink = $videolink;
                        }else
                            $youtubelink =$link;

                        $success = [
                            "success"=> 1,
                            "accountid"=> $faculty->faculty_id,
                            "accountname"=> $faculty->name,
                            "permission"=> [
                                "accounttype"=> $user->role=="admin"?"admin":"teacher",
                                "noticepermission"=> $faculty->noticepermission=='Y'?'Y':'N',
                                "gallerypermission"=> $faculty->gallerypermission=='Y'?'Y':'N',
                                "eventspermission"=> $faculty->eventspermission=='Y'?'Y':'N',
                                "homeworkpermission"=> $faculty->homeworkpermission=='Y'?'Y':'N',
                                "quizpermission"=> $faculty->quizpermission=='Y'?'Y':'N',
                                "smspermission"=> $faculty->smspermission=='Y'?'Y':'N',
                                "onlineclasspermission"=> $faculty->onlineclasspermission=='Y'?'Y':'N',
                                "contactnopermission"=> $faculty->contactnopermission=='Y'?'Y':'N',
                                "teacherstatus"=> $faculty->status=='Active'?'Y':'N',
                            ],
                            "birthday"=> !empty($faculty->dob)||$faculty->dob!=null?date('m-d',strtotime($faculty->dob))==date('m-d')?1:0:0,
                            "total"=>[
                                "student"=> $studentCount,
                                "announcement"=> $announcement,
                                "video"=> $video
                            ],
                            "birthdaycard"=>[
                                "success"=>1,
                                "cardimageurl"=>$birthdayCard->cardimageurl,
                                "status"=>$birthdayCard->status,
                            ],
                            "youtubelink"=>$youtubelink,
                            "sessionlist"=> $facultySessionList,
                        ];
                    }else{
                        $success = $msgTable;
                    }
                }
                $success['license'] = 1;
                return customResponse(@$success['success']?1:0,$success);
            }
            else{
                return customResponse(0,$msgUnauth);
            }
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function changePassword(Request $request){
        try{
            // $accountid=$_POST['accountid'];
            // $password=$_POST['password'];
            // $accounttype=$_POST['accounttype'];
            $validator = Validator::make($request->all(),[
                'password' => 'required',
                'accountid' => 'required',
                'accounttype' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            $user = getAuth();
            User::where([['school_id',$user->school_id],['username',$request->accountid]])->update(['password'=>$request->password]);
            return customResponse(1,['msg'=>'successfully changed']);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }
}
