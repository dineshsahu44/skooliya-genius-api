<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Section;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Holiday;
use App\Models\School;
use App\Models\FacultyAssignClass;
use App\Models\MainScreenOption;
use DB;
use Validator;

class FacultyController extends Controller
{
    public function getTeachers(Request $request){
        try{
            // $companyid=$_GET['companyid'];
            $validator = Validator::make($request->all(),[
                'companyid' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            $getsessioninfo = getSchoolIdBySessionID($request->companyid);
            // "SELECT companyid,accountid,accountname,accounttype,status,photo,address1,city,state,mobile,birthday,anniversary,notificationtoken FROM teachers WHERE companyid='$companyid' and flag!=1"
            $faculties = Faculty::select('faculty_id as accountid','name as accountname', 'users.status','photo','address as address1',
            'city','state','phone as mobile','dob as birthday','dob anniversary',
            DB::Raw("IF(users.device_token IS NULL or users.device_token = '', 0, 1) as notificationflag"))
            ->join('users','users.username','faculties.faculty_id')
            ->where([['status','Active'],['school_id'->$getsessioninfo->school_id]])->get();
            return customResponse(1,['teachers'=>$faculties]);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function updateFacultyPhoto(Request $request){
        try{
            // $accountid=$_POST['accountid'];	
            $acceptFiles = accpectFiles('faculty-photo-files');
            $validator = Validator::make($request->all(),[
                'accountid' => 'required',
                'attachment' => 'required|mimes:'.implode(',',$acceptFiles['extension-type']).'|max:'.$acceptFiles['max-size'].''
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }

            if ($request->hasFile('attachment')) {
                $attachment = saveFiles($request->file('attachment'),$acceptFiles);
            }
            $user = getAuth();
            Faculty::where([['faculty_id',$request->accountid],['school_id'=>$user->school_id]])->update(['photo'=>$attachment]);
            return customResponse(1,["msg"=>"profile pic updated","imageurl"=>$attachment]);
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
}
