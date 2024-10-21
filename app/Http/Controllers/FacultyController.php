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
use Carbon\Carbon;

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
            $faculties = Faculty::select('faculties.id as f_id','faculty_id as accountid',DB::Raw('TRIM(faculties.name) as accountname'), 'users.status','photo',
            'address as address1','faculties.role_id',
            'city','state',DB::raw('COALESCE(phone, "") as mobile'),'dob as birthday','faculties.status',
            DB::raw("IF(dob IS NOT NULL, DATE_FORMAT(dob, '%Y-%m-%d'), '') AS dobStaff"),
            DB::raw("IF(dob IS NOT NULL, DATE_FORMAT(dob, '%d-%m-%Y'), '') AS dob"),
            'dob as anniversary','faculties.status as facultystatus',
            DB::Raw("IF(users.device_token IS NULL or users.device_token = '', 0, 1) as notificationflag"))
            ->join('users','users.username',DB::Raw('faculties.faculty_id and users.role!="student"'))
            ->whereNotIn('faculties.faculty_id',array(1,2,3))
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
            // dd($request);
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
            Faculty::where([['faculty_id',$request->accountid],['school_id',$user->school_id]])->update(['photo'=>$attachment]);
            return customResponse(1,["msg"=>"profile pic updated","imageurl"=>$attachment]);
        }catch(\Exception $e){
            // dd($e)
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
            $session = getSchoolIdBySessionID($request->companyid);
            if($request->permtype=='classcontrol'){
                if($request->permvalue!='all'&&$request->permvalue!='[]' && @count($assignclass = json_decode($request->permvalue,true))>0){
                    // $assignclass = json_decode($request->permvalue,true);
                    $facultyAssignClass = [];
                    foreach($assignclass as $class){
                        foreach($class['section'] as $section){
                            $facultyAssignClass[] = [
                                'class'=> $class['class'],
                                'section'=> $section,
                                'school_id'=> $session->school_id,
                                'accountid'=> $request->accountid,
                            ];
                        }
                    }
                    Faculty::where([['school_id',$session->school_id],['faculty_id',$request->accountid]])->update(['assignclass'=>$request->permvalue,'oprate_date'=>now()]);
                    FacultyAssignClass::where([['school_id',$session->school_id],['accountid',$request->accountid]])->delete();
                    FacultyAssignClass::insert($facultyAssignClass);
                }elseif($request->permvalue=='all'||$request->permvalue=='[]'){
                    Faculty::where([['school_id',$session->school_id],['faculty_id',$request->accountid]])->update(['assignclass'=>$request->permvalue,'oprate_date'=>now()]);
                    FacultyAssignClass::where([['school_id',$session->school_id],['accountid',$request->accountid]])->delete();
                }else{
                    return customResponse(0,['msg'=>'incorrect class format.']);
                }
            }else if($request->permtype=='makeadmin'){
                $permvalue = $request->permvalue=='Y'?'admin':'teacher';
                // alsochange in faculties role_id
                User::where([['school_id',$session->school_id],['role','!=','student'],['username',$request->accountid]])->update(['role'=>$permvalue]);
            }else if($request->permtype=='teacherstatus'){
                $permvalue = $request->permvalue=='Y'?1:0;
                User::where([['school_id',$session->school_id],['role','!=','student'],['username',$request->accountid]])->update(['status'=>$permvalue]);
            }else{
                Faculty::where([['school_id',$session->school_id],['faculty_id',$request->accountid]])->update([$request->permtype."permission"=>$request->permvalue]);
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

    public function staffPhotoList(Request $request){
        $auth_token = $request->header('Authorization');
        $companyid = $request->companyid;
        $accountid = $request->accountid;
        if($request->isMethod('get')) {
            // Create a request with the required parameters
            // $new_request = new Request([
            //     'companyid' => $companyid,
            //     'accountid' => $accountid,
            // ]);
            // Call the getAllClasses function from the StaticController
            // $staticController = app(StaticController::class); 
            // $assigned_class = $staticController->getAllClasses($new_request);
            // dd($assigned_class);
            
            $servername = $request->servername;
            $serverinfo = getServerInfo();
            $serverinfo = [
                'addpermission'=>$serverinfo->addpermission,
                'updatepermission'=>$serverinfo->updatepermission
            ];

            $session = getSchoolIdBySessionID($companyid);
            
            $roles = DB::table('roles')->select('id','role_name')
            ->where('school_id', $session->school_id)
            ->orderBy('role_name')->get()
            ->pluck('role_name', 'id')->toArray();
            
            // $mainParameter = "api/studentprofileapi.php?companyid=".$request->companyid."&servername=".$request->servername;
            return view("staff-photo-list", compact("auth_token","companyid","servername","accountid","serverinfo","roles"));
        }
        elseif($request->isMethod('post')) {
            return $this->getTeachers($request);
        }
    }

    public function updateFacultyPhotoForIDCard(Request $request){
        try{
            // $accountid=$_POST['accountid'];	
            // dd($request);
            // return $request->all();
            $acceptFiles = accpectFiles('faculty-photo-files');
            $validator = Validator::make($request->all(),[
                'facultyaccountid' => 'required',
                'attachment' => 'required|mimes:'.implode(',',$acceptFiles['extension-type']).'|max:'.$acceptFiles['max-size'].''
            ]);
            $companyid = $request->companyid;

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }

            if ($request->hasFile('attachment')) {
                $attachment = saveFiles1($request->file('attachment'),$acceptFiles,$companyid);
                $session = getSchoolIdBySessionID($companyid);
                // return ($session);
                Faculty::where([['faculty_id',$request->facultyaccountid],['school_id',$session->school_id]])->update(['photo'=>$attachment]);
                return customResponse(1,['msg'=>'photo update done.','imageurl'=>$attachment]);
            }
            return customResponse(0,['msg'=>'photo update not done.']);
            // $user = getAuth();

        }catch(\Exception $e){
            // dd($e)
            return exceptionResponse($e);
        }
    }

    public function updateFacultyProfile(Request $request){
        try{
            // return $request->all();
            if(getServerInfo()->addpermission==1){
                
                $validator = Validator::make($request->all(),[
                    'staff_f_id' => 'required',
                    'facultyaccountid' => 'required',
                    'name' => 'required',
                    'companyid' => 'required',
                    'accountid' => 'required',
                    'role_id' => 'required',
                ]);
                
                if ($validator->fails()) {
                    return response()->json(validatorMessage($validator));
                }
                
                $companyid = $request->companyid;
                $staff_f_id = $request->staff_f_id;
                $facultyaccountid = $request->facultyaccountid;
                $session = getSchoolIdBySessionID($companyid);
                $user = getUserByUsername($request->accountid);
                
                $role = DB::table('roles')->select('id','role_name')
                ->where([['school_id',$session->school_id],['id',$request->role_id]])->first();

                $faculty = [
                    'role_id'=>$role->id,
                    'name'=>$request->name,
                    'address'=>$request->address,
                    'dob'=> ((Carbon::hasFormat($request->dob, 'd-m-Y'))?Carbon::createFromFormat('d-m-Y', $request->dob):null),
                    'phone'=>$request->contactno,
                    'oprator' => $user->id,
                    'status'=> $request->status,
                    // 'updated_at' =>now(),
                ];

                Faculty::where([['school_id',$session->school_id],['id',$staff_f_id],['faculty_id',$facultyaccountid]])
                ->update($faculty);
                User::where([['username',$facultyaccountid],['school_id',$session->school_id]])
                ->where('role', '!=', 'student')
                ->update(['status'=>($request->status=="Active"?1:0),'role'=>$role->role_name]);

                $faculty = $this->getFacultyInfo($staff_f_id,$session->school_id);
                
                return customResponse(1,['msg'=>'successfully updated done.',"facultyinfo"=>$faculty]);
            }else{
                return customResponse(0,['msg'=>'not allow to update profile.']);
            }
        }catch(\Exception $e){
            // dd($e)
            return exceptionResponse($e);
        }
    }

    public function addFacultyProfile(Request $request){
        try{
            // return $request->all();
            if(getServerInfo()->addpermission==1){
                
                $validator = Validator::make($request->all(),[
                    'name' => 'required',
                    'companyid' => 'required',
                    'accountid' => 'required',
                    'role_id' => 'required',
                ]);
                
                if ($validator->fails()) {
                    return response()->json(validatorMessage($validator));
                }
                
                $companyid = $request->companyid;
                
                $session = getSchoolIdBySessionID($companyid);
                $user = getUserByUsername($request->accountid);
                
                // Start transaction
                DB::beginTransaction();

                $role = DB::table('roles')->select('id','role_name')
                ->where([['school_id',$session->school_id],['id',$request->role_id]])->first();
                // Fetch the generated registration_id
                $generatedIdResult = DB::select("SELECT getImpId('StaffAppId', 1, 0) AS faculty_id");
                $faculty_Id = $generatedIdResult[0]->faculty_id;

                $faculty = new Faculty([
                    'school_id'=>$session->school_id,
                    'faculty_id'=>$faculty_Id,
                    'role_id'=>$role->id,
                    'name'=>$request->name,
                    'address'=>$request->address,
                    'dob'=> ((Carbon::hasFormat($request->dob, 'd-m-Y'))?Carbon::createFromFormat('d-m-Y', $request->dob):null),
                    'phone'=>$request->mobile,
                    'oprator' => $user->id,
                    'status'=> "Active",
                    // 'updated_at' =>now(),
                ]);
                if($faculty->save()){
                    // Get the last inserted ID and registration_id
                    $lastInsertedId = $faculty->id;
                    $facultyId = $faculty->faculty_id;

                    // Create the user
                    User::create([
                        'name' => $request->name,
                        'password' => $request->mobile, // Encrypting password
                        'username' => $facultyId,
                        'school_id' => $session->school_id,
                        'role' => $role->role_name,
                        'status' => 1,
                    ]);
                }
                // Commit transaction
                DB::commit();

                $faculty = $this->getFacultyInfo($lastInsertedId,$session->school_id);
                
                return customResponse(1,['msg'=>'successfully added done.',"facultyinfo"=>$faculty]);
            }else{
                return customResponse(0,['msg'=>'not allow to update profile.']);
            }
        }catch(\Exception $e){
            // dd($e)
            return exceptionResponse($e);
        }
    }

    public function getFacultyInfo($staff_f_id,$schoolid){
        $faculties = Faculty::select('faculties.id as f_id','faculty_id as accountid',
            DB::Raw('TRIM(faculties.name) as accountname'),
            DB::raw('COALESCE(photo, "") as photo'),
            'address as address1','faculties.role_id',
            'city','state',
            DB::raw('COALESCE(phone, "") as mobile'),
            'dob as birthday','faculties.status',
            DB::raw("IF(dob IS NOT NULL, DATE_FORMAT(dob, '%Y-%m-%d'), '') AS dobStaff"),
            DB::raw("IF(dob IS NOT NULL, DATE_FORMAT(dob, '%d-%m-%Y'), '') AS dob"),
            'dob as anniversary','faculties.status as facultystatus',
            // DB::Raw("IF(users.device_token IS NULL or users.device_token = '', 0, 1) as notificationflag")
            )
            // ->join('users','users.username',DB::Raw('faculties.faculty_id and users.role!="student"'))
            ->whereNotIn('faculties.faculty_id',array(1,2,3))
            ->where([['faculties.id',$staff_f_id],['faculties.school_id',$schoolid]])
            // ->orderBy('accountname')
            ->first();
        return $faculties;
    }
}
