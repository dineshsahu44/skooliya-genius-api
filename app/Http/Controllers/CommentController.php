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
use App\Models\Comment;
use App\Models\Guardian;
use App\Models\Holiday;
use DB;
use Validator;

class CommentController extends Controller
{
    public function comments(Request $request){
        try{
            $acceptFiles = accpectFiles('comment-files');
            $validator = Validator::make($request->all(),[
                'accounttype'=>'required',
                'companyid' => 'required',
                'accountid' => 'required',
                'activityname' => 'required',
                'activityid' => 'required',
                'work'=>'required',
                'time'=>'required',
                'attachment' => $request->activitytype=='insertcomment'?'required|mimes:'.implode(',',$acceptFiles['extension-type']).'|max:'.$acceptFiles['max-size'].'':'',
            ]);
            

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            $currenttime = now();
            $accountid = $_POST['accountid'];
            $accounttype = $_POST['accounttype'];
            $activityname = $_POST['activityname'];
            $activityid = $_POST['activityid'];
            $work = $_POST['work'];
            $lasttime = $_POST['time'];
            
            if($work=="insertcomment"){

                $comment = $request->comment;

                if ($request->hasFile('attachment')) {
                    $attachment = saveFiles($request->file('attachment'),$acceptFiles);
                }
                
                if($accounttype=="student"){
                    $record = Registration::select('registrations.name',DB::Raw("CONCAT('classes.class','-','sections.section') as accounttype"))
                        ->join('classes','classes.id','registrations.class')->join('sections','sections.id','registrations.section')
                        ->where([['registration_id',$accountid],['session',$companyid]])->first();
                }else if($accounttype=="teacher"){
                    $record = Faculty::select('faculties.name',DB::Raw("IF(users.role IS NULL or users.role = '', 'teacher', users.role) as accounttype"))
                        ->join('school_sessions','school_sessions.school_id','faculties.school_id')
                        ->join('users','users.username','faculties.faculty_id')
                        ->where([['faculty_id',$accountid],['school_sessions.id',$companyid]])->first();
                }

                $commentData = [
                    'accountid'=> $accountid,
                    'comment'=> $comment, 
                    'attachment'=> @$attachment, 
                    'accounttype'=> $record->accounttype, 
                    'apptype'=> $accounttype, 
                    'accountname'=> $record->name, 
                    'activityname'=> $activityname, 
                    'activityid'=> $activityid,
                    'time'=> now(),
                ];
                Comment::create($commentData);
                $result['activityid']=$activityid;
                return customResponse(1,$result);
            }elseif($work=="fetchcomment"){
                if($accounttype=='student'){
                    $sql = "SELECT `comment_id`,`accountid`, `accountname`,`comment`,`attachment`, 
                    `accounttype`, `activityname`, `activityid`, `time` FROM 
                    (SELECT `comment_id`,`accountid`, `accountname`,`comment`,`attachment`, `accounttype`, 
                    `activityname`, `apptype`, `activityid`, `time` from `comment` where activityid='$activityid' 
                    and activityname='$activityname' and time>'$lasttime' order by time ASC) as t1 
                    WHERE t1.accountid='$accountid' OR t1.apptype='teacher'";
                    $data = DB::select($sql)->get();
                }else{
                    $sql = "SELECT `comment_id`,`accountid`, 
                    concat(`accountid`,' - ',`accountname`),`comment`,`attachment`, `accounttype`, `activityname`, `activityid`, 
                    `time` from `comment` where activityid='$activityid' and activityname='$activityname' and time>'$lasttime' 
                    order by time ASC";
                    Comment::where([['activityid',$activityid],['activityname',$activityname],['time','<=',$lasttime]])->update(['readstatus'=>1]);
                    $data = DB::select($sql)->get();
                }
                $result['currenttime']=$currenttime;
                $result['data']=$data;
                return customResponse(1,$result);
            }
            return customResponse(0);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }
}
