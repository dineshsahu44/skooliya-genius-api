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
use App\Models\User;
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
            // dd([$getsessioninfo,$getsessioninfo->school_id]);
            // "SELECT companyid,accountid,accountname,accounttype,status,photo,address1,city,state,mobile,birthday,anniversary,notificationtoken FROM teachers WHERE companyid='$companyid' and flag!=1"
            $faculties = Faculty::select('faculty_id as accountid',DB::Raw('TRIM(faculties.name) as accountname'), 'users.status','photo',
            'address as address1',
            'city','state','phone as mobile','dob as birthday','dob as anniversary',
            DB::Raw("IF(users.device_token IS NULL or users.device_token = '', 0, 1) as notificationflag"))
            ->join('users','users.username',DB::Raw('faculties.faculty_id and users.role!="student"'))
            ->where([['faculties.status','Active'],['faculties.school_id',$getsessioninfo->school_id]])
            ->orderBy('accountname')
            ->get();
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
                if($request->permvalue!='all'&&$request->permvalue!='[]' && @count($assignclass = json_decode($request->permvalue,true))>0){
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
                // alsochange in faculties role_id
                User::where([['school_id',$user->school_id],['role','!=','student'],['username',$request->accountid]])->update(['role'=>$permvalue]);
            }else if($request->permtype=='teacherstatus'){
                $permvalue = $request->permvalue=='Y'?1:0;
                User::where([['school_id',$user->school_id],['role','!=','student'],['username',$request->accountid]])->update(['status'=>$permvalue]);
            }else{
                Faculty::where([['school_id',$user->school_id],['faculty_id',$request->accountid]])->update([$request->permtype."permission"=>$request->permvalue]);
            }
            return customResponse(1);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
        
    }

    public function facultyPermission(Request $request){
        try{
            $accountid=$_GET['accountid'];
            $companyid=$_GET['companyid'];
            $facultyPermission = Faculty::select('noticepermission','gallerypermission','eventspermission','homeworkpermission','quizpermission','smspermission',
            'contactnopermission',
            DB::Raw('if(roles.role_name IN ("admin","superadmin"),"Y","N") as accounttype'),
            DB::Raw('if(users.status=1,"Y","N") as teacherstatus'),'onlineclasspermission')
            ->join('school_sessions','faculties.school_id','school_sessions.school_id')
            ->join('roles','faculties.role_id','roles.id')
            ->Join('users','users.username',DB::Raw('faculties.faculty_id and users.role!="student"'))
            ->where([['faculties.status','Active'],['faculties.faculty_id',$accountid],['school_sessions.id',$companyid]])
            ->first();
            if($facultyPermission)
                return customResponse(1,["permissions"=>$facultyPermission]);
            else
                return customResponse(0,["msg"=>"accountid is not active!"]);
            // $stmt = $conn->prepare("SELECT noticepermission,gallerypermission,eventspermission,homeworkpermission,quizpermission,smspermission,
            // contactnopermission,accounttype,status,onlineclasspermission FROM teachers WHERE accountid=$accountid AND companyid=$companyid");
            
            //executing the query 
            // $stmt->execute();
            
            // //binding results to the query 
            // $stmt->bind_result($noticepermission,$gallerypermission,$eventspermission,$homeworkpermission,$quizpermission,$smspermission,$contactnopermission,$accounttype,$status,$onlineclasspermission);
            
            
            // //traversing through all the result 
            // if($stmt->fetch())
            // {

            //     $temp['noticepermission'] = $noticepermission; 
            //     $temp['gallerypermission'] = $gallerypermission; 
            //     $temp['eventspermission'] = $eventspermission; 
            //     $temp['homeworkpermission'] = $homeworkpermission; 
            //     $temp['quizpermission'] = $quizpermission; 
            //     $temp['smspermission'] = $smspermission;
            //     $temp['contactnopermission'] = $contactnopermission;
            //     $temp['onlineclasspermission'] = $onlineclasspermission;
            //     if($status==1)
            //             $teacherstatus='Y';
            //     else
            //             $teacherstatus='N';
                        
            //     if($accounttype=='admin')
            //             $accounttype='Y';
            //     else
            //             $accounttype='N';
                        
            //     $temp['teacherstatus'] = $teacherstatus;
            //     $temp['accounttype'] = $accounttype;

            //     $result['success']=1;
            //     $result['permissions']=$temp;
            //     echo json_encode($result);
            // }
            // else
            // {
            //     $result['success']=0;
            //     echo json_encode($result);
            // }
            
            // $conn->close();
            
            //displaying the result in json format 
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }
}
