<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Config;
use App\Models\Section;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Holiday;
use App\Models\School;
use App\Models\FacultyAssignClass;
use App\Models\MainScreenOption;
use App\Models\Feedback;

use Carbon\Carbon;
use DB;
use Validator;
use GuzzleHttp\Client;

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
            // \DB::enableQueryLog(); 
            $classSesction = Classes::select('class',DB::raw("group_concat(section) as section"))
                ->join('sections','sections.class_id','classes.id')
                ->where('classes.school_id',$school->school_id)
                ->orderBy('position_by')->groupBy('class')->get();
                // dd(\DB::getQueryLog());
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
            $userinfo = getUserRecordByUsername($request->accountid);
            $result = [
                'allclass'=> $allclass,
                'permittedclass'=> $assignclass,
                'userinfo'=>$userinfo,
            ];
            return customResponse(1,$result);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function getAllClassesWithNumeric(Request $request){
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
            
            $allclass = $this->facultyClassesToNumericValue('all',$school->school_id);
            $faculty = Faculty::select('assignclass')->where([['faculty_id',$request->accountid],['school_id',$school->school_id]])->first();
            $assignclass = !empty($faculty->assignclass)&&$faculty->assignclass!=null?$faculty->assignclass=='all'?$allclass:$this->facultyClassesToNumericValue($faculty->assignclass,$school->school_id):[];
            $result = [
                'allclass'=> $allclass,
                'permittedclass'=> $assignclass
            ];
            return customResponse(1,$result);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    function facultyClassesToNumericValue($assignedclass,$school_id){
        if($assignedclass=='all'){
            // DB::raw("JSON_OBJECT(classes.id,JSON_ARRAYAGG(JSON_OBJECT('id',sections.id,'section',sections.section))) as section")
            $classSesction = Classes::select('class','classes.id',
                    DB::raw("JSON_ARRAYAGG(JSON_OBJECT('id',sections.id,'section',sections.section)) as section")
                )
                ->join('sections','sections.class_id','classes.id')
                ->where('classes.school_id',$school_id)
                ->groupBy('classes.id')->orderBy('classes.position_by','asc')->orderBy('classes.class','asc')->get();
        }else{
            $a = json_decode($assignedclass,true);
            $maparray = [];
            foreach($a as $c){
                foreach($c['section'] as $s){
                    $maparray[]=$c['class'].'-'.$s;
                }
            }
            $classSesction = Classes::select('class','classes.id',
                    DB::raw("JSON_ARRAYAGG(JSON_OBJECT('id',sections.id,'section',sections.section)) as section")
                )
                ->join('sections','sections.class_id','classes.id')
                ->where('classes.school_id',$school_id)
                ->whereIn(DB::raw("CONCAT(class, '-', section)"), $maparray)
                ->groupBy('classes.id')->orderBy('classes.position_by','asc')->orderBy('classes.class','asc')->get();
        }
        $allclasss= [];
        foreach($classSesction as $d){
            $allclasss[]=[
                'id'=>$d->id,
                'class'=>$d->class,
                'section'=>json_decode($d->section,true)
            ];
        }
        // dd($allclasss);
        return $allclasss;
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
            return customResponse(1,['list'=>[]]);
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
            return customResponse(1,['list'=>[]]);
            // return customResponse(0);
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
            if ($request->has('schoolid')) {
                $schoolid = $request->schoolid;
                $current = currentSession($schoolid);
            } else {

                $current = currentSession();
                $schoolid = $current->school_id;
            }
            

            $school = School::select('school','name as city','mobile as phone','mobile',
            'email','fees_webview as machine')->where('id',$schoolid)->first();//machine value// form webview
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

    public function videoPlayer(Request $request){
        if($request->isMethod('get')) {
            // return $request->all();
            return view('video-player')->with('request',$request->all());
        }elseif($request->isMethod('post')) {
            $fileContent = file_get_contents($request->file('filename')->getRealPath());
            $fileType = $request->file('filename')->getClientMimeType();
            return response($fileContent)->header('Content-Type', $fileType);
        }
    }

    public function biometricapi(Request $request)
    {
        // Get the IP address of the client
        $ipAddress = $request->ip();

        // Get the user-agent of the client (device information)
        $userAgent = $request->header('User-Agent');

        // Get all request data
        $requestData = $request->all();
        
        // Add IP address and device information to the request data
        $requestData['ip_address'] = $ipAddress;
        $requestData['device_info'] = $userAgent;

        // Add timestamp to the request data
        $requestData['timestamp'] = now()->toDateTimeString();
        
        // Convert the request data to JSON format
        $requestDataJson = json_encode($requestData);
        
        // Define the directory path
        $directoryPath = storage_path('app/requests/');
        
        // Create the directory if it doesn't exist
        if (!file_exists($directoryPath)) {
            mkdir($directoryPath, 0777, true);
        }
        
        // Define the file path where you want to save or concatenate the request data
        $filePath = $directoryPath . 'request_data.json';
        
        // Concatenate or append the request data to the file
        file_put_contents($filePath, $requestDataJson . PHP_EOL, FILE_APPEND);
        
        // Return a response if needed
        return response()->json(['message' => 'Request data saved successfully'], 200);
    }

    public function getFile($filename)
    {
        $filePath = storage_path('app/requests/' . $filename);
        
        if (!file_exists($filePath)) {
            abort(404);
        }
        
        // Set the appropriate MIME type
        $headers = [
            'Content-Type' => 'application/json',
        ];
        
        // Return the file as a response
        return Response::download($filePath, $filename, $headers);
    }

    public function getFileFolder(Request $request)
    {
        $directory = 'F:\SHARDA SOLUTIONS\2024\SBVMIC-RATH\1st-round';

        // Get all folders within the directory
        $folders = File::directories($directory);

        // Initialize an empty array to hold folder and file data
        $folderFiles = [];

        // Loop through each folder
        foreach ($folders as $folder) {
            // Get the folder name
            $folderName = basename($folder);

            // Get all files within the folder
            $files = File::files($folder);

            // Store the folder name and its files in the array
            $folderFiles[$folderName] = $files;
        }

        // Now you have an array $folderFiles where keys are folder names
        // and values are arrays of files in each folder

        return view('filefolderview', compact('folderFiles'));
    }

    public function uploadFaceData(Request $request){
        try{
            set_time_limit(600);
            $photoDirectory = "F:\\SHARDA SOLUTIONS\\2024\\ndpshamirpur_\\1st-round\\all\\Compressed";
            
            $excelRecord = array(
                0 => array('Serial No.' => '1', 'AppID' => '5264', 'Name' => 'AADHYA SINGH', 'Admno.' => '202300265', 'Mobile' => '7905098418', 'Address' => 'CHAURA DEVI', 'Date of Birth' => '31-07-2020', 'Father Name' => 'MAHENDRA SINGH', 'Mother Name' => 'GITA', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_105600.jpg'),
                1 => array('Serial No.' => '2', 'AppID' => '5254', 'Name' => 'AANVI SINGH', 'Admno.' => '202300255', 'Mobile' => '6307031715', 'Address' => '', 'Date of Birth' => '15-10-2019', 'Father Name' => 'SANDEEP SINGH', 'Mother Name' => 'KALPNA SINGH', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_104101.jpg'),
                2 => array('Serial No.' => '3', 'AppID' => '5263', 'Name' => 'ABHIRAJ', 'Admno.' => '202300264', 'Mobile' => '7398169073', 'Address' => 'VIVEK NAGAR', 'Date of Birth' => '16-10-2020', 'Father Name' => 'AMAN CHAURASIA', 'Mother Name' => 'MANISHA CHAURASIA', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_105638.jpg'),
                3 => array('Serial No.' => '4', 'AppID' => '5261', 'Name' => 'ANIKA GUPTA', 'Admno.' => '202300262', 'Mobile' => '8737037105', 'Address' => 'HAMIRPUR', 'Date of Birth' => '28-10-2019', 'Father Name' => 'HEMANT KUMAR OMAR', 'Mother Name' => 'DIVYA GUPTA', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_105442.jpg'),
                4 => array('Serial No.' => '5', 'AppID' => '5258', 'Name' => 'ANIKA SONI', 'Admno.' => '202300259', 'Mobile' => '8318481711', 'Address' => 'RAMEDI HAMIRPUR', 'Date of Birth' => '27-03-2020', 'Father Name' => 'SUNIL ANAND SONI', 'Mother Name' => 'SHIKHA SONI', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_104115.jpg'),
                5 => array('Serial No.' => '6', 'AppID' => '5259', 'Name' => 'ARADHY SONI', 'Admno.' => '202300260', 'Mobile' => '8887921435', 'Address' => 'VIVEK NAGAR', 'Date of Birth' => '24-01-2019', 'Father Name' => 'AKHILESH GUPTA', 'Mother Name' => 'SWEETY SONI', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_104450.jpg'),
                6 => array('Serial No.' => '7', 'AppID' => '5255', 'Name' => 'ARADHYA SONKAR', 'Admno.' => '202300256', 'Mobile' => '7991289166', 'Address' => 'RAMEDI HAMIRPUR', 'Date of Birth' => '31-01-2019', 'Father Name' => 'SHAILENDRA KUMAR', 'Mother Name' => 'MANJU SANKAR', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_104138.jpg'),
                7 => array('Serial No.' => '8', 'AppID' => '5260', 'Name' => 'ARHAM', 'Admno.' => '202300261', 'Mobile' => '950650466', 'Address' => 'BANGALI MOHAL HAMIRPUR', 'Date of Birth' => '19-09-2019', 'Father Name' => 'MOHAMMAD IMRAN', 'Mother Name' => 'AFSAR', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_104213.jpg'),
                8 => array('Serial No.' => '9', 'AppID' => '5257', 'Name' => 'AROHI AWASTHI', 'Admno.' => '202300258', 'Mobile' => '8059997718', 'Address' => 'RAMEDI HAMIRPUR', 'Date of Birth' => '25-12-2020', 'Father Name' => 'PRAKHAR AWASTHI', 'Mother Name' => 'MADHU AWASTHI', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_104405.jpg'),
                9 => array('Serial No.' => '10', 'AppID' => '5256', 'Name' => 'AROHI DAKSH', 'Admno.' => '202300257', 'Mobile' => '9005488148', 'Address' => 'LODIPUR NIWADA', 'Date of Birth' => '31-01-2019', 'Father Name' => 'ABHISHEK KUMAR', 'Mother Name' => 'SAMPAT', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_104236.jpg'),
                10 => array('Serial No.' => '11', 'AppID' => '5262', 'Name' => 'AYANSH', 'Admno.' => '202300263', 'Mobile' => '6394957296', 'Address' => 'RANI LAXMI BAI', 'Date of Birth' => '11-01-2020', 'Father Name' => 'BALKRISHAN', 'Mother Name' => 'SUNEETA DEVI', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_105511.jpg'),
                11 => array('Serial No.' => '12', 'AppID' => '5267', 'Name' => 'CHIRAG', 'Admno.' => '202300268', 'Mobile' => '8303165493', 'Address' => 'RAMEDI HAMIRPUR', 'Date of Birth' => '21-04-2019', 'Father Name' => 'CHANDAN BHARTI', 'Mother Name' => 'PREETI', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_104316.jpg'),
                12 => array('Serial No.' => '13', 'AppID' => '5266', 'Name' => 'JANVI GUPTA', 'Admno.' => '202300267', 'Mobile' => '8922887845', 'Address' => 'RAMEDI TARAUS HAMIRPUR', 'Date of Birth' => '24-08-2018', 'Father Name' => 'AMIT KUMAR GUPTA', 'Mother Name' => 'LUXMI GUPTA', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_104337.jpg'),
                13 => array('Serial No.' => '14', 'AppID' => '5279', 'Name' => 'JAYANT KUMAR', 'Admno.' => '202300280', 'Mobile' => '9793035241', 'Address' => 'BANGALI MOHAL HAMIRPUR', 'Date of Birth' => '26-01-2020', 'Father Name' => 'RAVI KUMAR', 'Mother Name' => 'PRIYANKA SINGH', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_104846.jpg'),
                14 => array('Serial No.' => '15', 'AppID' => '5281', 'Name' => 'JENNY SINGH', 'Admno.' => '202300282', 'Mobile' => '8423723211', 'Address' => 'POLICE LINE', 'Date of Birth' => '24-08-2020', 'Father Name' => 'JITENDRA SINGH', 'Mother Name' => 'POONAM SINGH', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_105032.jpg'),
                15 => array('Serial No.' => '16', 'AppID' => '5288', 'Name' => 'MANVIK PATEL', 'Admno.' => '202300289', 'Mobile' => '9889276677', 'Address' => 'SARDAR PATEL HAMIRPUR', 'Date of Birth' => '15-05-2020', 'Father Name' => 'DESHRAJ PATEL', 'Mother Name' => 'SUMAN PATEL', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_105459.jpg'),
                16 => array('Serial No.' => '17', 'AppID' => '5271', 'Name' => 'MISHIKA SINGH', 'Admno.' => '202300272', 'Mobile' => '6387741446', 'Address' => 'VIVEK NAGAR HAMIRPUR', 'Date of Birth' => '09-08-2019', 'Father Name' => 'KAPIL DEV SINGH', 'Mother Name' => 'NIHARIKA SINGH', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_105700.jpg'),
                17 => array('Serial No.' => '18', 'AppID' => '5278', 'Name' => 'MRITUNJAY SINGH', 'Admno.' => '202300279', 'Mobile' => '9793035241', 'Address' => 'VIVEK NAGAR HAMIRPUR', 'Date of Birth' => '20-02-2020', 'Father Name' => 'MANVENDRA SINGH', 'Mother Name' => 'SAPNA SINGH', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_105327.jpg'),
                18 => array('Serial No.' => '19', 'AppID' => '5269', 'Name' => 'MUAJJAM', 'Admno.' => '202300270', 'Mobile' => '8953378413', 'Address' => 'GWALTOLI HAMIRPUR', 'Date of Birth' => '17-11-2018', 'Father Name' => 'IKABAL AHMAD', 'Mother Name' => 'JUNTUL FRIDAUS', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_104705.jpg'),
                19 => array('Serial No.' => '20', 'AppID' => '5270', 'Name' => 'NITYA PAL', 'Admno.' => '202300271', 'Mobile' => '8887869572', 'Address' => 'HAMIRPUR', 'Date of Birth' => '24-10-2020', 'Father Name' => 'RAVENDRA PAL', 'Mother Name' => 'SHALINI PAL', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_104754.jpg'),
                20 => array('Serial No.' => '21', 'AppID' => '5285', 'Name' => 'NITYA SAINI', 'Admno.' => '202300286', 'Mobile' => '9140247227', 'Address' => 'KAJIYANA JAMUNAGHAT', 'Date of Birth' => '18-03-2020', 'Father Name' => 'DHARMENDRA KUMAR', 'Mother Name' => 'ARCHANA SAINI', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_105235.jpg'),
                21 => array('Serial No.' => '22', 'AppID' => '5287', 'Name' => 'PRANSHU', 'Admno.' => '202300288', 'Mobile' => '9838747314', 'Address' => 'AMAN SHAHID HAMIRPUR', 'Date of Birth' => '22-03-2019', 'Father Name' => 'SANDEEP KUMAR', 'Mother Name' => 'RACHNA', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_105627.jpg'),
                22 => array('Serial No.' => '23', 'AppID' => '5268', 'Name' => 'PRATHVI SINGH', 'Admno.' => '202300269', 'Mobile' => '7972297622', 'Address' => 'HAMIRPUR', 'Date of Birth' => '29-11-2019', 'Father Name' => 'KULDEEP SINGH', 'Mother Name' => 'PIYA SINGH', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_104521.jpg'),
                23 => array('Serial No.' => '24', 'AppID' => '5276', 'Name' => 'PRAYER KUSHWAHA', 'Admno.' => '202300277', 'Mobile' => '9450168171', 'Address' => 'RAMEDI PHOOLARANI HAMIRPUR', 'Date of Birth' => '07-10-2020', 'Father Name' => 'UDIT NARAYAN', 'Mother Name' => 'PREETI DEVI', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_104622.jpg'),
                24 => array('Serial No.' => '25', 'AppID' => '5280', 'Name' => 'RACHIT SINGH', 'Admno.' => '202300281', 'Mobile' => '8303723423', 'Address' => 'RAMEDI HAMIRPUR', 'Date of Birth' => '18-08-2019', 'Father Name' => 'ROHIT SINGH', 'Mother Name' => 'REKHA SINGH', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_104734.jpg'),
                25 => array('Serial No.' => '26', 'AppID' => '5272', 'Name' => 'SHANVI', 'Admno.' => '202300273', 'Mobile' => '7007221852', 'Address' => 'RAMEDI HAMIRPUR', 'Date of Birth' => '15-09-2018', 'Father Name' => 'HARIOM', 'Mother Name' => 'SANGEETA', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_104603.jpg'),
                26 => array('Serial No.' => '27', 'AppID' => '5277', 'Name' => 'SIDDHARTH', 'Admno.' => '202300278', 'Mobile' => '9319348185', 'Address' => 'MAHARANI LAXMINAGAR', 'Date of Birth' => '07-07-2020', 'Father Name' => 'SANDEEP KUMAR', 'Mother Name' => 'RUHI SRIVAS', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_104820.jpg'),
                27 => array('Serial No.' => '28', 'AppID' => '5282', 'Name' => 'VANYA', 'Admno.' => '202300283', 'Mobile' => '8810760151', 'Address' => 'KUMHAUPUR', 'Date of Birth' => '22-06-2019', 'Father Name' => 'RAKSHADEEN', 'Mother Name' => 'SHALINI', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_105101.jpg'),
                28 => array('Serial No.' => '29', 'AppID' => '5273', 'Name' => 'VED SONI', 'Admno.' => '202300274', 'Mobile' => '7398927172', 'Address' => 'RAMEDI HAMIRPUR', 'Date of Birth' => '11-09-2019', 'Father Name' => 'ANAND SONI', 'Mother Name' => 'DIVYA SONI', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_104929.jpg'),
                29 => array('Serial No.' => '30', 'AppID' => '5275', 'Name' => 'YASHVI DUBEY', 'Admno.' => '202300276', 'Mobile' => '8528473478', 'Address' => 'HAMIRPUR', 'Date of Birth' => '04-09-2019', 'Father Name' => 'BIPIN DUBEY', 'Mother Name' => 'ANUPAM DUBEY', 'Class' => 'LKG', 'Section' => 'A', 'Photo' => 'IMG_20231220_104905.jpg'),
                30 => array('Serial No.' => '41', 'AppID' => '5337', 'Name' => 'AARUHI', 'Admno.' => '202300338', 'Mobile' => '8840986226', 'Address' => 'POLICE LINE HAMIRPUR', 'Date of Birth' => '05-10-2019', 'Father Name' => 'RAJESH KUMAR', 'Mother Name' => 'POOJA', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221094747.jpg'),
                31 => array('Serial No.' => '42', 'AppID' => '5290', 'Name' => 'AARVI YADAV', 'Admno.' => '202300291', 'Mobile' => '9120258661', 'Address' => 'RAMEDI TARAUS HAMIRPUR', 'Date of Birth' => '16-02-2019', 'Father Name' => 'RAHUL YADAV', 'Mother Name' => 'VANDANA DEVI', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221094106.jpg'),
                32 => array('Serial No.' => '43', 'AppID' => '5291', 'Name' => 'ABDULLA', 'Admno.' => '202300292', 'Mobile' => '', 'Address' => 'KHALEPURA HAMIRPUR', 'Date of Birth' => '00-01-1900', 'Father Name' => 'AAMIRKHAN', 'Mother Name' => 'RUKSAR', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221094136.jpg'),
                33 => array('Serial No.' => '44', 'AppID' => '5292', 'Name' => 'ADITYA SINGH RAJPUT', 'Admno.' => '202300293', 'Mobile' => '9936560794', 'Address' => 'RAMEDI DANDA HAMIRPUR', 'Date of Birth' => '28-07-2019', 'Father Name' => 'JAI HIND SINGH', 'Mother Name' => 'ALPANA RAJPUT', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221094210.jpg'),
                34 => array('Serial No.' => '45', 'AppID' => '5293', 'Name' => 'AIZA', 'Admno.' => '202300294', 'Mobile' => '6306824489', 'Address' => 'GWALTOLI HAMIRPUR', 'Date of Birth' => '22-07-2019', 'Father Name' => 'GULAM KADIL', 'Mother Name' => 'AKHATAR JAHAN', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221100021.jpg'),
                35 => array('Serial No.' => '46', 'AppID' => '5295', 'Name' => 'AMRITA', 'Admno.' => '202300296', 'Mobile' => '9691093659', 'Address' => 'CHANDUPUR, DANDA HAMIRPUR', 'Date of Birth' => '08-05-2017', 'Father Name' => 'SHIVKUMAR', 'Mother Name' => 'RAJKALI', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221094252.jpg'),
                36 => array('Serial No.' => '47', 'AppID' => '5296', 'Name' => 'ANIKA DHURIYA', 'Admno.' => '202300297', 'Mobile' => '7007517730', 'Address' => 'AMAN SHAHID NEAR DURGA TEMPLE HAMIRPUR', 'Date of Birth' => '19-11-2018', 'Father Name' => 'PRATEEK DHURIYA', 'Mother Name' => 'PRIYANKA DHURIYA', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221094312.jpg'),
                37 => array('Serial No.' => '48', 'AppID' => '5297', 'Name' => 'ANIKA SHRIVASTAV', 'Admno.' => '202300298', 'Mobile' => '7408404428', 'Address' => 'LUCKNOW', 'Date of Birth' => '23-03-2020', 'Father Name' => 'MAHENDRA SHRIVASTAV', 'Mother Name' => 'GARIMA SHRIVASTAV', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221094348.jpg'),
                38 => array('Serial No.' => '49', 'AppID' => '5298', 'Name' => 'ANSH SINGH', 'Admno.' => '202300299', 'Mobile' => '', 'Address' => '', 'Date of Birth' => '20-08-2017', 'Father Name' => 'LATE DHARMENDRA', 'Mother Name' => 'KAVITA', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221094420.jpg'),
                39 => array('Serial No.' => '50', 'AppID' => '5299', 'Name' => 'ANSHIKA SAINI', 'Admno.' => '202300300', 'Mobile' => '9451577274', 'Address' => 'TAHSIL COLONY HAMIRPUR', 'Date of Birth' => '21-07-2017', 'Father Name' => 'VIRENDRA KUMAR', 'Mother Name' => 'ROHANI', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221094506.jpg'),
                40 => array('Serial No.' => '51', 'AppID' => '5301', 'Name' => 'ARADHYA SHARMA', 'Admno.' => '202300302', 'Mobile' => '6306442012', 'Address' => 'RAMERI TARAUS HAMIRPUR', 'Date of Birth' => '17-07-2018', 'Father Name' => 'HEMANT KUMAR SHARMA', 'Mother Name' => 'KSHAMA SHARMA', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221094556.jpg'),
                41 => array('Serial No.' => '52', 'AppID' => '5303', 'Name' => 'ARYDEEP GUPTA', 'Admno.' => '202300304', 'Mobile' => '8090086551', 'Address' => 'BHILAWA HAMIRPUR', 'Date of Birth' => '09-07-2018', 'Father Name' => 'DEEP GUPTA', 'Mother Name' => 'CHITRA GUPTA', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221094852.jpg'),
                42 => array('Serial No.' => '53', 'AppID' => '5304', 'Name' => 'ASHVI', 'Admno.' => '202300305', 'Mobile' => '7880465946', 'Address' => 'GAURA DEVI NEAR BASTI HAMIRPUR', 'Date of Birth' => '25-04-2018', 'Father Name' => 'MUKESH NISHAD', 'Mother Name' => 'SUSHMA', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221095038.jpg'),
                43 => array('Serial No.' => '54', 'AppID' => '5305', 'Name' => 'AVNI SONI', 'Admno.' => '202300306', 'Mobile' => '8924950022', 'Address' => 'BANGALI MOHAL, GAUSHALA, HAMIRPUR', 'Date of Birth' => '07-07-2018', 'Father Name' => 'ASHISH SONI', 'Mother Name' => 'POONAM SONI', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221095119.jpg'),
                44 => array('Serial No.' => '55', 'AppID' => '5306', 'Name' => 'AYUSH PRAJAPATI', 'Admno.' => '202300307', 'Mobile' => '7880621343', 'Address' => 'BHILAWA BANDH HAMIRPUR', 'Date of Birth' => '28-06-2018', 'Father Name' => 'MAHENDRA KUMAR', 'Mother Name' => 'RACHNA DEVI', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221095149.jpg'),
                45 => array('Serial No.' => '56', 'AppID' => '5307', 'Name' => 'DAXH', 'Admno.' => '202300308', 'Mobile' => '', 'Address' => 'BANGALI MOHAL HAMIRPUR', 'Date of Birth' => '14-10-2018', 'Father Name' => 'KAMLESH KUMAR', 'Mother Name' => 'GOMATI', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221095240.jpg'),
                46 => array('Serial No.' => '57', 'AppID' => '5308', 'Name' => 'DISHA', 'Admno.' => '202300309', 'Mobile' => '7309867007', 'Address' => 'BANGALI MOHAL HAMIRPUR', 'Date of Birth' => '05-08-2019', 'Father Name' => 'AKHILESH KUMAR', 'Mother Name' => 'ANJALI', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221095230.jpg'),
                47 => array('Serial No.' => '58', 'AppID' => '5309', 'Name' => 'DIVYA PAL', 'Admno.' => '202300310', 'Mobile' => '7497957043', 'Address' => 'GAURA DEVI HAMIRPUR', 'Date of Birth' => '19-12-2018', 'Father Name' => 'LATE RAJENDRA KUMAR PAL', 'Mother Name' => 'ANJU PAL', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221100841.jpg'),
                48 => array('Serial No.' => '59', 'AppID' => '5310', 'Name' => 'DIVYANSHI', 'Admno.' => '202300311', 'Mobile' => '7007642694', 'Address' => 'KHAPTIHA KALAN', 'Date of Birth' => '00-01-1900', 'Father Name' => 'DERA PRASAD', 'Mother Name' => 'PRIYANKA', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221095307.jpg'),
                49 => array('Serial No.' => '60', 'AppID' => '5311', 'Name' => 'EVA RAJ', 'Admno.' => '202300312', 'Mobile' => '8765059528', 'Address' => '', 'Date of Birth' => '10-09-2018', 'Father Name' => 'UMESH KUMAR YADAV', 'Mother Name' => 'JYOTI YADAV', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221095355.jpg'),
                50 => array('Serial No.' => '61', 'AppID' => '5312', 'Name' => 'HAIDAR HASAN', 'Admno.' => '202300313', 'Mobile' => '9453593625', 'Address' => '', 'Date of Birth' => '15-11-2017', 'Father Name' => 'TASLEEM', 'Mother Name' => 'AHMADI', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221095421.jpg'),
                51 => array('Serial No.' => '62', 'AppID' => '5313', 'Name' => 'HARISHA SIDDIQUI', 'Admno.' => '202300314', 'Mobile' => '9125366110', 'Address' => 'C65 PARED COLONY HAMIRPUR', 'Date of Birth' => '28-04-2020', 'Father Name' => 'MOHD. AFTAB', 'Mother Name' => 'TABASUM KHATOON', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221100634.jpg'),
                52 => array('Serial No.' => '63', 'AppID' => '5314', 'Name' => 'INDRAKSHI SINGH', 'Admno.' => '202300315', 'Mobile' => '8005398163', 'Address' => 'VIVEK NAGAR HAMIRPUR', 'Date of Birth' => '28-07-2017', 'Father Name' => 'KAPIL DEV SINGH', 'Mother Name' => 'NIHARIKA SINGH', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221100825.jpg'),
                53 => array('Serial No.' => '64', 'AppID' => '5315', 'Name' => 'ISHIKA ANAND', 'Admno.' => '202300316', 'Mobile' => '9140475225', 'Address' => 'NEAR OLD GAS GODAM RAMEDI TARAUS', 'Date of Birth' => '21-09-2018', 'Father Name' => 'AMIT KUMAR', 'Mother Name' => 'VANDANA', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221100735.jpg'),
                54 => array('Serial No.' => '65', 'AppID' => '5317', 'Name' => 'MO. ZAID AALAM', 'Admno.' => '202300318', 'Mobile' => '6394502323', 'Address' => 'BANGALI MOHALLA RAMEDI HAMIRPUR', 'Date of Birth' => '10-09-2018', 'Father Name' => 'MOHD. MINAHAJ', 'Mother Name' => 'SHAHJHAH', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221100529.jpg'),
                55 => array('Serial No.' => '66', 'AppID' => '5318', 'Name' => 'NAMYA SAHU', 'Admno.' => '202300319', 'Mobile' => '9450261703', 'Address' => 'BANGALI MUHAL RAMEDI HAMIRPUR', 'Date of Birth' => '13-09-2018', 'Father Name' => 'SACHIN KUMAR SAHU', 'Mother Name' => 'SNEHA SAHU', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221100619.jpg'),
                56 => array('Serial No.' => '67', 'AppID' => '5319', 'Name' => 'NISHANT SINGH', 'Admno.' => '202300320', 'Mobile' => '9670806881', 'Address' => 'RAMEDI TARAUS HAMIRPUR', 'Date of Birth' => '18-07-2019', 'Father Name' => 'DHANANJAY SINGH', 'Mother Name' => 'VAISHALI SINGH', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221100659.jpg'),
                57 => array('Serial No.' => '68', 'AppID' => '5321', 'Name' => 'PRIYAM', 'Admno.' => '202300322', 'Mobile' => '8168588716', 'Address' => 'RAMEDI TARAUS HAMIRPUR', 'Date of Birth' => '02-11-2019', 'Father Name' => 'KAPIL KUMAR', 'Mother Name' => 'ANTIMA', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221100035.jpg'),
                58 => array('Serial No.' => '69', 'AppID' => '5322', 'Name' => 'RANVEER MATHUR', 'Admno.' => '202300323', 'Mobile' => '6392847859', 'Address' => 'RAMEDI TARAUS HAMIRPUR', 'Date of Birth' => '08-10-2018', 'Father Name' => 'CHANDRA SHEKHAR', 'Mother Name' => 'SEEMA', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221100051.jpg'),
                59 => array('Serial No.' => '70', 'AppID' => '5323', 'Name' => 'RUDRANSH SHRIVAS', 'Admno.' => '202300324', 'Mobile' => '9936545249', 'Address' => 'AMBEDKAR NAGAR NAUBASTA HAMIRPUR', 'Date of Birth' => '19-04-2019', 'Father Name' => 'PRAMOD KUMAR', 'Mother Name' => 'REKHA', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221095939.jpg'),
                60 => array('Serial No.' => '71', 'AppID' => '5324', 'Name' => 'SACHIN SAINI', 'Admno.' => '202300325', 'Mobile' => '8318743455', 'Address' => 'TAHSIL COLONY HAMIRPUR', 'Date of Birth' => '25-08-2018', 'Father Name' => 'SHARDA', 'Mother Name' => 'POONAM', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221095952.jpg'),
                61 => array('Serial No.' => '72', 'AppID' => '5325', 'Name' => 'SANKALP SONI', 'Admno.' => '202300326', 'Mobile' => '8887921435', 'Address' => 'VIVEK NAGAR HAMIRPUR', 'Date of Birth' => '24-01-2019', 'Father Name' => 'AKHILESH SONI', 'Mother Name' => 'SWEETY SONI', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221100512.jpg'),
                62 => array('Serial No.' => '73', 'AppID' => '5326', 'Name' => 'SARTHAK AWASTHI', 'Admno.' => '202300327', 'Mobile' => '8318340173', 'Address' => 'MANJHKHOR HAMIRPUR', 'Date of Birth' => '28-09-2019', 'Father Name' => 'ASHWANI AWASTHI', 'Mother Name' => 'ARTI AWASTHI', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG_20231222_114638.jpg'),
                63 => array('Serial No.' => '74', 'AppID' => '5327', 'Name' => 'SHIV DIXIT', 'Admno.' => '202300328', 'Mobile' => '9452075790', 'Address' => 'BANGALI MOHAL HAMIRPUR', 'Date of Birth' => '14-02-2019', 'Father Name' => 'BRAJESH KUMAR', 'Mother Name' => 'MAINA', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221100220.jpg'),
                64 => array('Serial No.' => '75', 'AppID' => '5328', 'Name' => 'SHIVANSHI KANNAUJIYA', 'Admno.' => '202300329', 'Mobile' => '7703092118', 'Address' => 'MANJHKHOR HAMIRPUR', 'Date of Birth' => '18-01-2019', 'Father Name' => 'SUNIL', 'Mother Name' => 'SONAM', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221100322.jpg'),
                65 => array('Serial No.' => '76', 'AppID' => '5329', 'Name' => 'SHIVAY GUPTA', 'Admno.' => '202300330', 'Mobile' => '8887662566', 'Address' => 'CHAURA DEVI TEMPLE HAMIRPUR', 'Date of Birth' => '13-04-2018', 'Father Name' => 'RAJESH KUMAR GUPTA', 'Mother Name' => 'KALPNA GUPTA', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221100419.jpg'),
                66 => array('Serial No.' => '77', 'AppID' => '5330', 'Name' => 'SHIVAY PAL', 'Admno.' => '202300331', 'Mobile' => '9559712511', 'Address' => '', 'Date of Birth' => '15-11-2019', 'Father Name' => 'UDAY NARAYAN', 'Mother Name' => 'SUMAN PAL', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221100343.jpg'),
                67 => array('Serial No.' => '78', 'AppID' => '5333', 'Name' => 'SHRESTH AWASTHI', 'Admno.' => '202300334', 'Mobile' => '8318340173', 'Address' => 'MANJHKHOR HAMIRPUR', 'Date of Birth' => '28-09-2019', 'Father Name' => 'ASHWANI AWASTHI', 'Mother Name' => 'ARTI AWASTHI', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG_20231222_114725.jpg'),
                68 => array('Serial No.' => '79', 'AppID' => '5334', 'Name' => 'SHREYANSH GUPTA', 'Admno.' => '202300335', 'Mobile' => '9695667564', 'Address' => 'RAMEDI DANDA I.T.I. HAMIRPUR', 'Date of Birth' => '20-05-2018', 'Father Name' => 'VINAY KUMAR GUPTA', 'Mother Name' => 'JYATI', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221100804.jpg'),
                69 => array('Serial No.' => '80', 'AppID' => '5332', 'Name' => 'SIDDHARTH BASU', 'Admno.' => '202300333', 'Mobile' => '9936604259', 'Address' => 'JAIL TALAB ROAD HAMIRPUR', 'Date of Birth' => '07-04-2021', 'Father Name' => 'RANJEET KUMAR', 'Mother Name' => 'PUSHPA', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221100301.jpg'),
                70 => array('Serial No.' => '81', 'AppID' => '5335', 'Name' => 'UJJWAL', 'Admno.' => '202300336', 'Mobile' => '7398861616', 'Address' => 'RAMEDI HAMIRPUR', 'Date of Birth' => '23-03-2019', 'Father Name' => 'ASHANK NARAYAN DWIVEDI', 'Mother Name' => 'SHWETA', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221095507.jpg'),
                71 => array('Serial No.' => '82', 'AppID' => '5336', 'Name' => 'ZAINAB', 'Admno.' => '202300337', 'Mobile' => '9335394042', 'Address' => 'KHALEPURA HAMIRPUR', 'Date of Birth' => '22-04-2019', 'Father Name' => 'MOH. TARIK', 'Mother Name' => 'SEEMA', 'Class' => 'UKG', 'Section' => 'A', 'Photo' => 'IMG20231221095552.jpg'),
                72 => array('Serial No.' => '121', 'AppID' => '5351', 'Name' => 'ABHINAV MATHUR', 'Admno.' => '202300352', 'Mobile' => '8953109833', 'Address' => 'RANI LAXMI BAI HAMIRPUR', 'Date of Birth' => '13-05-2017', 'Father Name' => 'JAY PRAKASH', 'Mother Name' => 'REENU DEVI', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_102553.jpg'),
                73 => array('Serial No.' => '122', 'AppID' => '5352', 'Name' => 'ADHIRA', 'Admno.' => '202300353', 'Mobile' => '6387486974', 'Address' => 'POLICE LINE HAMIRPUR', 'Date of Birth' => '22-02-2014', 'Father Name' => 'CHANDRESH PRATAP', 'Mother Name' => 'NEHA', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_102611.jpg'),
                74 => array('Serial No.' => '123', 'AppID' => '5355', 'Name' => 'ADITYA NISHAD', 'Admno.' => '202300356', 'Mobile' => '9651871678', 'Address' => 'RAMEDI FULARANI HAMIRPUR', 'Date of Birth' => '05-10-2017', 'Father Name' => 'NEERAJ', 'Mother Name' => 'KRANTI', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_102727.jpg'),
                75 => array('Serial No.' => '124', 'AppID' => '5360', 'Name' => 'ADITYA PRAJAPATI', 'Admno.' => '202300361', 'Mobile' => '9580533299', 'Address' => 'MANJHKHOR HAMIRPUR', 'Date of Birth' => '28-01-2017', 'Father Name' => 'MANOJ', 'Mother Name' => 'MALTI', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_102657.jpg'),
                76 => array('Serial No.' => '125', 'AppID' => '5358', 'Name' => 'AGRIMA SHUKLA', 'Admno.' => '202300359', 'Mobile' => '7607579091', 'Address' => 'GAURA DEVI HAMIRPUR', 'Date of Birth' => '20-06-2018', 'Father Name' => 'VIKAS SHUKLA', 'Mother Name' => 'NEELAM SHUKLA', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_102428.jpg'),
                77 => array('Serial No.' => '126', 'AppID' => '5340', 'Name' => 'AKRITI MAURYA', 'Admno.' => '202300341', 'Mobile' => '9793689991', 'Address' => 'POLICE LINE HAMIRPUR', 'Date of Birth' => '11-01-2017', 'Father Name' => 'RAMESH KUMAR MAURYA', 'Mother Name' => 'RAJ KUMARI', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_102503.jpg'),
                78 => array('Serial No.' => '127', 'AppID' => '5353', 'Name' => 'ANSH KUMAR', 'Admno.' => '202300354', 'Mobile' => '9651929636', 'Address' => 'RAMEDI HAMIRPUR', 'Date of Birth' => '21-01-2017', 'Father Name' => 'RAJU', 'Mother Name' => 'ARCHANA DEVI', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_102628.jpg'),
                79 => array('Serial No.' => '128', 'AppID' => '5354', 'Name' => 'ANSH NISHAD', 'Admno.' => '202300355', 'Mobile' => '9559054917', 'Address' => 'BANWASI KA DERA HAMIRPUR', 'Date of Birth' => '28-04-2018', 'Father Name' => 'KAMLESH KUMAR', 'Mother Name' => 'PUSHPA DEVI', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_102745.jpg'),
                80 => array('Serial No.' => '129', 'AppID' => '5365', 'Name' => 'ANSHU SINGH', 'Admno.' => '202300366', 'Mobile' => '9005306599', 'Address' => 'VIVEK NAGAR HAMIRPUR', 'Date of Birth' => '00-01-1900', 'Father Name' => 'SARDAR SINGH', 'Mother Name' => 'GUDIYA SINGH', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_102757.jpg'),
                81 => array('Serial No.' => '130', 'AppID' => '5368', 'Name' => 'ANUKARAN', 'Admno.' => '202300369', 'Mobile' => '9140740532', 'Address' => 'POLICE LINE HAMIRPUR', 'Date of Birth' => '21-12-2018', 'Father Name' => 'ANURAG SINGH', 'Mother Name' => 'SHASHI SINGH', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_102648.jpg'),
                82 => array('Serial No.' => '131', 'AppID' => '5369', 'Name' => 'ASHUTOSH', 'Admno.' => '202300370', 'Mobile' => '9452913256', 'Address' => 'VILLAGE PATYARA HAMIRPUR', 'Date of Birth' => '27-02-2007', 'Father Name' => 'RAMBIHARI', 'Mother Name' => 'SUDHA DEVI', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_103453.jpg'),
                83 => array('Serial No.' => '132', 'AppID' => '5338', 'Name' => 'BHAVYA', 'Admno.' => '202300339', 'Mobile' => '9794606133', 'Address' => 'AKIL TIRAHA', 'Date of Birth' => '19-09-2018', 'Father Name' => 'DEVENDRA PRATAP', 'Mother Name' => 'VIJAY LAXMI', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_102811.jpg'),
                84 => array('Serial No.' => '133', 'AppID' => '5367', 'Name' => 'HARSH VARDHAN SINGH', 'Admno.' => '202300368', 'Mobile' => '8009068735', 'Address' => 'KALPI CHAURAHA HAMIRPUR', 'Date of Birth' => '30-11-2015', 'Father Name' => 'MAHENDRA SINGH', 'Mother Name' => 'HEMENT SINGH', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_103059.jpg'),
                85 => array('Serial No.' => '134', 'AppID' => '5342', 'Name' => 'JANVI', 'Admno.' => '202300343', 'Mobile' => '8810347367', 'Address' => 'POLICE LINE HAMIRPUR', 'Date of Birth' => '20-08-2018', 'Father Name' => 'PANKAJ', 'Mother Name' => 'RUCHI', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_103035.jpg'),
                86 => array('Serial No.' => '135', 'AppID' => '5343', 'Name' => 'JAYASH', 'Admno.' => '202300344', 'Mobile' => '6394651756', 'Address' => 'RAMEDI HAMIRPUR', 'Date of Birth' => '21-07-2018', 'Father Name' => 'RAMMILAN', 'Mother Name' => 'REKHA', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_103047.jpg'),
                87 => array('Serial No.' => '136', 'AppID' => '5372', 'Name' => 'KAJAL NISHAD', 'Admno.' => '202300373', 'Mobile' => '8866209653', 'Address' => 'BANVASI KA DERA HAMIRPUR', 'Date of Birth' => '30-12-2015', 'Father Name' => 'RAM KARAN', 'Mother Name' => 'KANTI', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_103111.jpg'),
                88 => array('Serial No.' => '137', 'AppID' => '5347', 'Name' => 'MO. SHAN', 'Admno.' => '202300348', 'Mobile' => '9450328874', 'Address' => 'KHALEPURA HAMIRPUR', 'Date of Birth' => '14-04-2018', 'Father Name' => 'MO. ASIF', 'Mother Name' => 'GULGANIA', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_103209.jpg'),
                89 => array('Serial No.' => '138', 'AppID' => '5344', 'Name' => 'NEER', 'Admno.' => '202300345', 'Mobile' => '6392621889', 'Address' => 'RAMEDI HAMIRPUR', 'Date of Birth' => '16-11-2016', 'Father Name' => 'SANTOSH NISHAD', 'Mother Name' => 'LEELA NISHAD', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_103138.jpg'),
                90 => array('Serial No.' => '139', 'AppID' => '5345', 'Name' => 'PRATYUSH', 'Admno.' => '202300346', 'Mobile' => '9451587953', 'Address' => 'RAMEDI HAMIRPUR', 'Date of Birth' => '30-11-2016', 'Father Name' => 'PRAKASH CHANDRA SAROJ', 'Mother Name' => 'SHIV KUMARI SAROJ', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_103123.jpg'),
                91 => array('Serial No.' => '140', 'AppID' => '5346', 'Name' => 'RITIK GOND -II', 'Admno.' => '202300347', 'Mobile' => '7379255833', 'Address' => 'K.V. SUBCENTRAL PAUTHIYA', 'Date of Birth' => '31-08-2017', 'Father Name' => 'RAM NARAYAN', 'Mother Name' => 'POONAM DEVI', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_103151.jpg'),
                92 => array('Serial No.' => '141', 'AppID' => '5348', 'Name' => 'SHANVI SRISTHI', 'Admno.' => '202300349', 'Mobile' => '9140827197', 'Address' => 'BANGALI MUHAL RAMEDI HAMIRPUR', 'Date of Birth' => '17-07-2018', 'Father Name' => 'ANIL KUMAR SRIVAS', 'Mother Name' => 'REENA SHRIVAS', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_103224.jpg'),
                93 => array('Serial No.' => '142', 'AppID' => '5357', 'Name' => 'SHIVANSH SINGH', 'Admno.' => '202300358', 'Mobile' => '8707315120', 'Address' => 'POLICE', 'Date of Birth' => '27-06-2019', 'Father Name' => 'RAVI KUMAR', 'Mother Name' => 'LALI DEVI', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_103004.jpg'),
                94 => array('Serial No.' => '143', 'AppID' => '5370', 'Name' => 'SHIVANYA', 'Admno.' => '202300371', 'Mobile' => '9029187376', 'Address' => 'NALKUP COLONY GAYTRI MANDIR HAMIRPUR', 'Date of Birth' => '11-10-2018', 'Father Name' => 'RAKESH KUMAR', 'Mother Name' => 'ANSHU DEVI', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_103311.jpg'),
                95 => array('Serial No.' => '144', 'AppID' => '5359', 'Name' => 'SHREYANSH SAHU', 'Admno.' => '202300360', 'Mobile' => '9795108085', 'Address' => 'GAURA DEVI HAMIRPUR', 'Date of Birth' => '06-01-2018', 'Father Name' => 'MANISH SAHU', 'Mother Name' => 'ARCHNA SAHU', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_103325.jpg'),
                96 => array('Serial No.' => '145', 'AppID' => '5371', 'Name' => 'SRASHTI SINGH', 'Admno.' => '202300372', 'Mobile' => '9129484087', 'Address' => 'EIDGAH KALPI CHAIRAHA HAMIRPUR', 'Date of Birth' => '23-03-2018', 'Father Name' => 'SUMIT PRATAP SINGH', 'Mother Name' => 'SHEELU', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_103234.jpg'),
                97 => array('Serial No.' => '146', 'AppID' => '5363', 'Name' => 'TANVI POTTER', 'Admno.' => '202300364', 'Mobile' => '9649901577', 'Address' => 'NUPUL TOWNSHIP GHATAMPUR', 'Date of Birth' => '24-02-2017', 'Father Name' => 'KANAHAYLAL POTTER', 'Mother Name' => 'BHARTI PRAJAPATI', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_103408.jpg'),
                98 => array('Serial No.' => '147', 'AppID' => '5361', 'Name' => 'VAISHNAVI TRIPATHI', 'Admno.' => '202300362', 'Mobile' => '6394229034', 'Address' => 'DEVADAS RAMEDI HAMIRPUR', 'Date of Birth' => '25-08-2018', 'Father Name' => 'RAHUL TRIPATHI', 'Mother Name' => 'KANCHAN TRIPATHI', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_103428.jpg'),
                99 => array('Serial No.' => '148', 'AppID' => '5349', 'Name' => 'VANSH PRATAP', 'Admno.' => '202300350', 'Mobile' => '638819034', 'Address' => 'RAMEDI TARAUS HAMIRPUR', 'Date of Birth' => '00-01-1900', 'Father Name' => 'SANJAY SINGH', 'Mother Name' => 'NEHA SINGH', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_103637.jpg'),
                100 => array('Serial No.' => '149', 'AppID' => '5373', 'Name' => 'VANSH SONI', 'Admno.' => '202300374', 'Mobile' => '8924950022', 'Address' => 'RAMEDI GAUSHALA HAMIRPUR', 'Date of Birth' => '16-03-2022', 'Father Name' => 'DHARMENDRA SONI', 'Mother Name' => 'SHOBHA SONI', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_103608.jpg'),
                101 => array('Serial No.' => '150', 'AppID' => '5362', 'Name' => 'YASHIKA SONI', 'Admno.' => '202300363', 'Mobile' => '9198331100', 'Address' => 'BANGALI MUHAL RAMEDI HAMIRPUR', 'Date of Birth' => '24-12-2019', 'Father Name' => 'DEVESH KUMAR SONI', 'Mother Name' => 'NEELAM SONI', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_103621.jpg'),
                102 => array('Serial No.' => '151', 'AppID' => '5374', 'Name' => 'YUVANI MAURYA', 'Admno.' => '202300375', 'Mobile' => '7905661674', 'Address' => 'PURANI TAHSEEL COLONY HAMIRPUR', 'Date of Birth' => '15-10-2017', 'Father Name' => 'ANOOP KUMAR MAURYA', 'Mother Name' => 'REKHA MAURYA', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_103551.jpg'),
                103 => array('Serial No.' => '152', 'AppID' => '5375', 'Name' => 'ZAHIRA', 'Admno.' => '202300376', 'Mobile' => '9335394042', 'Address' => 'KHALEPURA HAMIRPUR', 'Date of Birth' => '13-09-2016', 'Father Name' => 'PARIK', 'Mother Name' => 'SIMA', 'Class' => 'I', 'Section' => 'A', 'Photo' => 'IMG_20231220_103441.jpg'),
                104 => array('Serial No.' => '161', 'AppID' => '5001', 'Name' => 'ABHI PRATAP SINGH', 'Admno.' => '202300002', 'Mobile' => '', 'Address' => 'VIVEK NAGAR HAMIRPUR', 'Date of Birth' => '12-01-2016', 'Father Name' => 'SARDAR SINGH', 'Mother Name' => 'GUDIYA SINGH', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_102400.jpg'),
                105 => array('Serial No.' => '162', 'AppID' => '5002', 'Name' => 'AHANA', 'Admno.' => '202300003', 'Mobile' => '9999797033', 'Address' => 'MANJHKHOR HAMIRPUR', 'Date of Birth' => '13-12-2016', 'Father Name' => 'PRASHANT KUMAR', 'Mother Name' => 'KHUSHBOO', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_102413.jpg'),
                106 => array('Serial No.' => '163', 'AppID' => '5003', 'Name' => 'AMAYRA KHAN', 'Admno.' => '202300004', 'Mobile' => '9794236117', 'Address' => 'SUJIGANJ BAZAR, HAMIRPUR', 'Date of Birth' => '22-09-2013', 'Father Name' => 'SHEIKH RAJU', 'Mother Name' => 'MUSKAN', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_102428.jpg'),
                107 => array('Serial No.' => '164', 'AppID' => '5005', 'Name' => 'ANSH KEVAT', 'Admno.' => '202300006', 'Mobile' => '', 'Address' => 'PHULARANI RAMEDI TARAUS HAMIRPUR', 'Date of Birth' => '07-02-2015', 'Father Name' => 'MUKESH KUMAR KEVAT', 'Mother Name' => 'RUBI KEVAT', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_102633.jpg'),
                108 => array('Serial No.' => '165', 'AppID' => '5006', 'Name' => 'ANUBHUTI KUSHWAHA', 'Admno.' => '202300007', 'Mobile' => '8009520123', 'Address' => 'VIVEK NAGAR HAMIRPUR', 'Date of Birth' => '04-01-2017', 'Father Name' => 'ARVIND KUMAR KUSHWAHA', 'Mother Name' => 'PREELI KUSHWAHA', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_102753.jpg'),
                109 => array('Serial No.' => '166', 'AppID' => '5008', 'Name' => 'ARADHYA SINGH', 'Admno.' => '202300009', 'Mobile' => '7683023953', 'Address' => 'LAXMIBAI PARK BEHIND VIMAL NURSING HOME', 'Date of Birth' => '27-12-2014', 'Father Name' => 'ANURAG SINGH', 'Mother Name' => 'ANTIMA SINGH', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_102847.jpg'),
                110 => array('Serial No.' => '167', 'AppID' => '5009', 'Name' => 'ARSHALA', 'Admno.' => '202300010', 'Mobile' => '', 'Address' => 'SUBHASH BAZAR HAMIRPUR', 'Date of Birth' => '29-03-2018', 'Father Name' => 'SHAREEF AHAMAD', 'Mother Name' => 'AAFRIN', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_102900.jpg'),
                111 => array('Serial No.' => '168', 'AppID' => '5010', 'Name' => 'ARYAN', 'Admno.' => '202300011', 'Mobile' => '7651842291', 'Address' => 'C 128 PARED COLONY POLICE LINE HAMIRPUR', 'Date of Birth' => '02-04-2019', 'Father Name' => 'SURAJ VERMA', 'Mother Name' => 'KALPANA', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_102914.jpg'),
                112 => array('Serial No.' => '169', 'AppID' => '5011', 'Name' => 'ATHARV MISHRA', 'Admno.' => '202300012', 'Mobile' => '9935045144', 'Address' => 'NALKOOP COLONY YAGSHALA HAMIRPUR', 'Date of Birth' => '10-09-2018', 'Father Name' => 'VIRENDRA KUMAR MISHRA', 'Mother Name' => 'PREETI MISHRA', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_102950.jpg'),
                113 => array('Serial No.' => '170', 'AppID' => '5014', 'Name' => 'AYUSHMANI', 'Admno.' => '202300015', 'Mobile' => '', 'Address' => 'RAMEDI TARAUS HAMIRPUR', 'Date of Birth' => '23-08-2016', 'Father Name' => 'KRISHNA KUMAR', 'Mother Name' => 'RAJESHVARI', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_103035.jpg'),
                114 => array('Serial No.' => '171', 'AppID' => '5015', 'Name' => 'BHAVIKA AWASTHI', 'Admno.' => '202300016', 'Mobile' => '8423624817', 'Address' => 'PURNIMA MILK DAIRY HAMIRPUR', 'Date of Birth' => '07-06-2016', 'Father Name' => 'PUNEET AWASTHI', 'Mother Name' => 'RAMA AWASTHI', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_103049.jpg'),
                115 => array('Serial No.' => '172', 'AppID' => '5017', 'Name' => 'DEVANSHU ANURAGEE', 'Admno.' => '202300018', 'Mobile' => '8400536493', 'Address' => 'PAUTHIYA HAMIRPUR', 'Date of Birth' => '11-11-2016', 'Father Name' => 'NIRVENDRA KUMAR', 'Mother Name' => 'KUSHUM KUMARI', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_103206.jpg'),
                116 => array('Serial No.' => '173', 'AppID' => '5018', 'Name' => 'GAURAV', 'Admno.' => '202300019', 'Mobile' => '7607627156', 'Address' => 'RAMEDI TARAUS HAMIRPUR', 'Date of Birth' => '29-12-2015', 'Father Name' => 'GAJRAJ', 'Mother Name' => 'MAMTA', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_103257.jpg'),
                117 => array('Serial No.' => '174', 'AppID' => '5019', 'Name' => 'HARSH AWASTHI', 'Admno.' => '202300020', 'Mobile' => '9838040343', 'Address' => 'RAMEDI TARAUS HAMIRPUR', 'Date of Birth' => '11-01-2015', 'Father Name' => 'JITESH KUMAR AWASTHI', 'Mother Name' => 'RAMAKANTI', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_103313.jpg'),
                118 => array('Serial No.' => '175', 'AppID' => '5020', 'Name' => 'JEEVA SINGH', 'Admno.' => '202300021', 'Mobile' => '8423723211', 'Address' => 'POLICE LINE', 'Date of Birth' => '07-05-2018', 'Father Name' => 'JITENDRA SINGH', 'Mother Name' => 'POONAM SINGH', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_103347.jpg'),
                119 => array('Serial No.' => '176', 'AppID' => '5022', 'Name' => 'RAJ MATHUR', 'Admno.' => '202300023', 'Mobile' => '9560105048', 'Address' => 'DIGGI HAMIRPUR', 'Date of Birth' => '14-10-2015', 'Father Name' => 'JAYPRAKASH', 'Mother Name' => 'REENU DEVI', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_103515.jpg'),
                120 => array('Serial No.' => '177', 'AppID' => '5023', 'Name' => 'RIYA', 'Admno.' => '202300024', 'Mobile' => '8810843294', 'Address' => 'RAMEDI TARAUS HAMIRPUR', 'Date of Birth' => '10-08-2015', 'Father Name' => 'DHARMRNDRA SINGH', 'Mother Name' => 'KAVITA', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_103435.jpg'),
                121 => array('Serial No.' => '178', 'AppID' => '5024', 'Name' => 'RUDRA KUSHWAHA', 'Admno.' => '202300025', 'Mobile' => '8318535310', 'Address' => 'RAMEDI HAMIRPUR', 'Date of Birth' => '07-02-2017', 'Father Name' => 'MANOJ KUMAR', 'Mother Name' => 'PUJA KUSHWAHA', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_103623.jpg'),
                122 => array('Serial No.' => '179', 'AppID' => '5025', 'Name' => 'SAKSHI', 'Admno.' => '202300026', 'Mobile' => '9135422260', 'Address' => 'TEHSIL COLONY HAMIRPUR', 'Date of Birth' => '08-05-2017', 'Father Name' => 'MANOJ KUMAR', 'Mother Name' => 'ANAMIKA KUMARI', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_103604.jpg'),
                123 => array('Serial No.' => '180', 'AppID' => '5026', 'Name' => 'SHRADDHA', 'Admno.' => '202300027', 'Mobile' => '', 'Address' => 'RAMEDI NIRANKARI GALI', 'Date of Birth' => '16-05-2016', 'Father Name' => 'SUNIL KUAMAR', 'Mother Name' => 'ROSHANI', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_103637.jpg'),
                124 => array('Serial No.' => '181', 'AppID' => '5027', 'Name' => 'SHWETA', 'Admno.' => '202300028', 'Mobile' => '9696738731', 'Address' => 'JAGDESH DIVEDBAG VALI GALI RAMEDI HAMIRPUR', 'Date of Birth' => '21-02-2017', 'Father Name' => 'PRAVEEN KUMAR', 'Mother Name' => 'SUNEETA', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_103650.jpg'),
                125 => array('Serial No.' => '182', 'AppID' => '5028', 'Name' => 'SURYANSH KUMAR', 'Admno.' => '202300029', 'Mobile' => '', 'Address' => '', 'Date of Birth' => '25-08-2017', 'Father Name' => 'RITNESH KUMAR', 'Mother Name' => 'MANISHA DEVI', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_102721.jpg'),
                126 => array('Serial No.' => '183', 'AppID' => '5029', 'Name' => 'TEJASVI', 'Admno.' => '202300030', 'Mobile' => '8299368812', 'Address' => 'E- 24 GOVERNMENT COLONY AKHIL TIRAHA NEAR TREOSURY OFFICE', 'Date of Birth' => '18-04-2017', 'Father Name' => 'DEVENDRA PRATAP', 'Mother Name' => 'VIJAYLAKSHMI', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_103705.jpg'),
                127 => array('Serial No.' => '184', 'AppID' => '5030', 'Name' => 'VIJAY PRATAP SINGH', 'Admno.' => '202300031', 'Mobile' => '9628322218', 'Address' => 'RAMEDI TARAUS HAMIRPUR', 'Date of Birth' => '04-03-2019', 'Father Name' => 'ALOK KUMAR SAVITA', 'Mother Name' => 'ANITA SAVITA', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_103756.jpg'),
                128 => array('Serial No.' => '185', 'AppID' => '5031', 'Name' => 'VIRAT YADAV', 'Admno.' => '202300032', 'Mobile' => '9936229970', 'Address' => 'DAMODAR SHRINAGAR TAHSIL GHATAMPUR KANPUR', 'Date of Birth' => '04-07-2018', 'Father Name' => 'BRIJBHAN SINGH', 'Mother Name' => 'RASHMI DEVI', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_103813.jpg'),
                129 => array('Serial No.' => '186', 'AppID' => '5034', 'Name' => 'YUVANSH', 'Admno.' => '202300035', 'Mobile' => '6387828106', 'Address' => 'RAMEDI HAMIRPUR', 'Date of Birth' => '28-07-2015', 'Father Name' => 'ANIL KUMAR', 'Mother Name' => 'BINITA', 'Class' => 'II', 'Section' => 'A', 'Photo' => 'IMG_20231222_103928.jpg'),
                130 => array('Serial No.' => '201', 'AppID' => '5036', 'Name' => 'AASHI CHAURASIYA', 'Admno.' => '202300037', 'Mobile' => '', 'Address' => '', 'Date of Birth' => '12-11-2015', 'Father Name' => 'DHEERENDRE KUMAR', 'Mother Name' => 'SONAM', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_111207.jpg'),
                131 => array('Serial No.' => '202', 'AppID' => '5037', 'Name' => 'ABHINENDRA SINGH', 'Admno.' => '202300038', 'Mobile' => '9264778737', 'Address' => 'POLICE LINE HAMIRPUR', 'Date of Birth' => '20-12-2016', 'Father Name' => 'PANKAJ SINGH', 'Mother Name' => 'PRIYANKA', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_104700.jpg'),
                132 => array('Serial No.' => '203', 'AppID' => '5038', 'Name' => 'ADHIRA SINGH', 'Admno.' => '202300039', 'Mobile' => '8009812018', 'Address' => 'C/O RAJKUMAR SACHAN VIVEK NAGAR', 'Date of Birth' => '17-04-2016', 'Father Name' => 'AMBUJ PRATAP SINGH', 'Mother Name' => 'ASHA DEVI', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_104423.jpg'),
                133 => array('Serial No.' => '204', 'AppID' => '5039', 'Name' => 'AKRITI', 'Admno.' => '202300040', 'Mobile' => '6394957296', 'Address' => 'LAXMI BAI PARK HAMIRPUR', 'Date of Birth' => '07-11-2016', 'Father Name' => 'BALKRISHNA', 'Mother Name' => 'SUNEETA', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_104439.jpg'),
                134 => array('Serial No.' => '205', 'AppID' => '5040', 'Name' => 'AKSHAT DWIVEDI', 'Admno.' => '202300041', 'Mobile' => '', 'Address' => '', 'Date of Birth' => '29-05-2015', 'Father Name' => 'GUANENDRA DWIVEDI', 'Mother Name' => 'MEENU', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_111227.jpg'),
                135 => array('Serial No.' => '206', 'AppID' => '5041', 'Name' => 'ANISHK SAKHWAR', 'Admno.' => '202300042', 'Mobile' => '9455681381', 'Address' => 'POLICE LINE HAMIRPUR', 'Date of Birth' => '02-06-2017', 'Father Name' => 'MR KULDDEEP', 'Mother Name' => 'SMT MONIKA', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_104642.jpg'),
                136 => array('Serial No.' => '207', 'AppID' => '5042', 'Name' => 'ANJAL', 'Admno.' => '202300043', 'Mobile' => '9125230514', 'Address' => 'RAMEDI CHAWRAHA HAMIRPUR', 'Date of Birth' => '07-02-2016', 'Father Name' => 'TULSIRAM', 'Mother Name' => 'RUBI DEVI', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_104457.jpg'),
                137 => array('Serial No.' => '208', 'AppID' => '5043', 'Name' => 'ANSH KUMAR DHURYA', 'Admno.' => '202300044', 'Mobile' => '9621577364', 'Address' => 'HOSPITAL COLONY RAMEDI HAMIRPUR', 'Date of Birth' => '03-04-2017', 'Father Name' => 'MR AMIT', 'Mother Name' => 'SMT SHAYNA', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_104729.jpg'),
                138 => array('Serial No.' => '209', 'AppID' => '5044', 'Name' => 'ANSH PAL', 'Admno.' => '202300045', 'Mobile' => '7887027060', 'Address' => 'RAURA DEVI NAI BASTI HAMIRPUR', 'Date of Birth' => '07-07-2015', 'Father Name' => 'MR KAMAL', 'Mother Name' => 'SMT ANITA', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_104801.jpg'),
                139 => array('Serial No.' => '210', 'AppID' => '5045', 'Name' => 'ARAV PALIWAL', 'Admno.' => '202300046', 'Mobile' => '8840051881', 'Address' => 'PAUTHIYA HAMIRPUR', 'Date of Birth' => '09-09-2016', 'Father Name' => 'MR AKHILESH', 'Mother Name' => 'SMT ANJLI', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_105754.jpg'),
                140 => array('Serial No.' => '211', 'AppID' => '5046', 'Name' => 'ATHARV SACHAN', 'Admno.' => '202300047', 'Mobile' => '9140957892', 'Address' => '10/25 CHAURA DEVI NAGAR MAHINDRA TRACTOR AGENCY', 'Date of Birth' => '18-12-2016', 'Father Name' => 'MR AJAY', 'Mother Name' => 'SMT DEEPA', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_104818.jpg'),
                141 => array('Serial No.' => '212', 'AppID' => '5047', 'Name' => 'ATIKSH SAINI', 'Admno.' => '202300048', 'Mobile' => '9140247227', 'Address' => 'KAJIYANA JAMINA GHAL HAMIRPUR', 'Date of Birth' => '25-04-2017', 'Father Name' => 'DHARMENDRA SAINI', 'Mother Name' => 'ARCHANA', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_104400.jpg'),
                142 => array('Serial No.' => '213', 'AppID' => '5048', 'Name' => 'AYUSHI YADAV', 'Admno.' => '202300049', 'Mobile' => '8533011002', 'Address' => 'H. NO. 100 TEHSEEL COLONY POLICE LINE HAMIRPUR', 'Date of Birth' => '02-10-2017', 'Father Name' => 'PUSHPENDRA KUMAR', 'Mother Name' => 'SHASHI PRABHA', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_104832.jpg'),
                143 => array('Serial No.' => '214', 'AppID' => '5049', 'Name' => 'BHAVYA TRIPATHI', 'Admno.' => '202300050', 'Mobile' => '9455162595', 'Address' => 'KETKI VIDYA MANDIR SCHOOL HAMIRPUR', 'Date of Birth' => '16-01-2016', 'Father Name' => 'NIRDESH', 'Mother Name' => 'PRATIBHA', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_104847.jpg'),
                144 => array('Serial No.' => '215', 'AppID' => '5050', 'Name' => 'DAKSH RAJPUT', 'Admno.' => '202300051', 'Mobile' => '8756969464', 'Address' => 'BEGH DEV DAYAL RAMEDI HAMIRPUR', 'Date of Birth' => '02-05-2016', 'Father Name' => 'RAHUL SINGH', 'Mother Name' => 'SAPNA SINGH', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_104924.jpg'),
                145 => array('Serial No.' => '216', 'AppID' => '5052', 'Name' => 'DIVYANSH KUMAR', 'Admno.' => '202300053', 'Mobile' => '9838747314', 'Address' => 'AMAN SHAHEED DURGA MANDIR HAMIRPUR', 'Date of Birth' => '01-11-2016', 'Father Name' => 'SANDEEEP', 'Mother Name' => 'RACHANA', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_104945.jpg'),
                146 => array('Serial No.' => '217', 'AppID' => '5053', 'Name' => 'DIVYANSH SINGH', 'Admno.' => '202300054', 'Mobile' => '9682558210', 'Address' => 'RAMEDI', 'Date of Birth' => '20-08-2017', 'Father Name' => 'RAVI', 'Mother Name' => 'SUNITA', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_105015.jpg'),
                147 => array('Serial No.' => '218', 'AppID' => '5054', 'Name' => 'GUNJAN', 'Admno.' => '202300055', 'Mobile' => '8736043873', 'Address' => 'RAMEDI HAMIRPUR', 'Date of Birth' => '22-10-2015', 'Father Name' => 'AVADHESH KUMAR', 'Mother Name' => 'ARADHNA', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_105145.jpg'),
                148 => array('Serial No.' => '219', 'AppID' => '5055', 'Name' => 'ISHAAN SRIVASAV', 'Admno.' => '202300056', 'Mobile' => '7985875284', 'Address' => 'C/O SHRI KEMROJ SRIVASTAV H.N. 20/42 MANJHKAR RAMEDI HAMIRPUR', 'Date of Birth' => '13-06-2018', 'Father Name' => 'ASHISH', 'Mother Name' => 'AKANSHA', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_105211.jpg'),
                149 => array('Serial No.' => '220', 'AppID' => '5056', 'Name' => 'JIJIVISHA', 'Admno.' => '202300057', 'Mobile' => '9918045913', 'Address' => 'VIVEK NAGAR HAMIRPUR', 'Date of Birth' => '18-12-2015', 'Father Name' => 'VIR BAHADUR SINGH', 'Mother Name' => 'SAROJ SINGH', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_105225.jpg'),
                150 => array('Serial No.' => '221', 'AppID' => '5057', 'Name' => 'KANCHAN', 'Admno.' => '202300058', 'Mobile' => '8922889960', 'Address' => 'RAMEDI HAMIRPUR', 'Date of Birth' => '15-07-2015', 'Father Name' => 'RAJENDRA KUMAR', 'Mother Name' => 'SHYAMA DEVI', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_105244.jpg'),
                151 => array('Serial No.' => '222', 'AppID' => '5058', 'Name' => 'KRISHNA SAINI', 'Admno.' => '202300059', 'Mobile' => '8318743455', 'Address' => 'SHARDA PRASAD O TAHSEEDAR COLONY HAMIRPUR', 'Date of Birth' => '21-07-2016', 'Father Name' => 'SHARDA PRASAD', 'Mother Name' => 'POONAM', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_105301.jpg'),
                152 => array('Serial No.' => '223', 'AppID' => '5059', 'Name' => 'LAVANYA SINGH', 'Admno.' => '202300060', 'Mobile' => '9453019002', 'Address' => 'RAMEDI HAMIRPUR', 'Date of Birth' => '13-06-2018', 'Father Name' => 'RAVENDRA SINGH', 'Mother Name' => 'SHARIKA', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_110001.jpg'),
                153 => array('Serial No.' => '224', 'AppID' => '5060', 'Name' => 'MANAV SAHU', 'Admno.' => '202300061', 'Mobile' => '8851992590', 'Address' => 'BANGALI MAHAL MANJHKHAR RAMEDI HAMIRPUR', 'Date of Birth' => '29-12-2015', 'Father Name' => 'MANISH SAHU', 'Mother Name' => 'SEEMA SAHU', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_110032.jpg'),
                154 => array('Serial No.' => '225', 'AppID' => '5061', 'Name' => 'PARTH DHURIYA', 'Admno.' => '202300062', 'Mobile' => '7651880309', 'Address' => 'IN FRONT OF MAHILA THANA HAMIRPUR', 'Date of Birth' => '04-04-2017', 'Father Name' => 'KAMLESH KUMAR', 'Mother Name' => 'MADHURI', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_110201.jpg'),
                155 => array('Serial No.' => '226', 'AppID' => '5062', 'Name' => 'PRACHITA SHRIVAS', 'Admno.' => '202300063', 'Mobile' => '9936545249', 'Address' => '11/72 AMBEDKAR NAGAR NAUBASTA HAMIRPUR', 'Date of Birth' => '09-11-2016', 'Father Name' => 'PRAMOD KUMAR', 'Mother Name' => 'REKHA', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_110221.jpg'),
                156 => array('Serial No.' => '227', 'AppID' => '5063', 'Name' => 'PRASTUTI KHARE', 'Admno.' => '202300064', 'Mobile' => '9415453787', 'Address' => 'C-25 TEHSIL COLONY HAMIRPUR', 'Date of Birth' => '06-11-2016', 'Father Name' => 'SACHIN KUMAR', 'Mother Name' => 'ANJANA DEVI', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_105413.jpg'),
                157 => array('Serial No.' => '228', 'AppID' => '5064', 'Name' => 'PRINCE YADAV', 'Admno.' => '202300065', 'Mobile' => '', 'Address' => '', 'Date of Birth' => '18-06-2017', 'Father Name' => 'JAYRAM', 'Mother Name' => 'NISHA', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_105911.jpg'),
                158 => array('Serial No.' => '229', 'AppID' => '5065', 'Name' => 'RAKHI SACHAN', 'Admno.' => '202300066', 'Mobile' => '94510212688', 'Address' => 'PAUTHIYA HAMIRPUR', 'Date of Birth' => '31-08-2015', 'Father Name' => 'SWAPNIL SACHAN', 'Mother Name' => 'REETU SACHAN', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_105506.jpg'),
                159 => array('Serial No.' => '230', 'AppID' => '5066', 'Name' => 'RIDAM SACHAN', 'Admno.' => '202300067', 'Mobile' => '8840915560', 'Address' => 'PAUTHIYA HAMIRPUR', 'Date of Birth' => '31-05-2017', 'Father Name' => 'ALOK SACHAN', 'Mother Name' => 'DEEPIKA SACHAN', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_105520.jpg'),
                160 => array('Serial No.' => '231', 'AppID' => '5067', 'Name' => 'SAKET', 'Admno.' => '202300068', 'Mobile' => '8923001685', 'Address' => 'C-46 PARED COLONY POLICE LINE HAMIRPUR', 'Date of Birth' => '08-01-2017', 'Father Name' => 'SATENDRA PAL', 'Mother Name' => 'KAVITA PAL', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_105552.jpg'),
                161 => array('Serial No.' => '232', 'AppID' => '5068', 'Name' => 'SHAURYA PRTAP SINGH', 'Admno.' => '202300069', 'Mobile' => '7985791970', 'Address' => 'POLICE PARED COLONY HAMIRPUR', 'Date of Birth' => '06-06-2017', 'Father Name' => 'RAVI KUMAR', 'Mother Name' => 'LALI DEVI', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_105623.jpg'),
                162 => array('Serial No.' => '233', 'AppID' => '5069', 'Name' => 'SHRASTI SINGH', 'Admno.' => '202300070', 'Mobile' => '6392872941', 'Address' => 'MANJHKHAR RAMEDI HAMIRPUR', 'Date of Birth' => '20-10-2016', 'Father Name' => 'PRAVEEN KUMAR BHADURIYA', 'Mother Name' => 'ARAHANAA', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_105640.jpg'),
                163 => array('Serial No.' => '234', 'AppID' => '5070', 'Name' => 'SHREYASH PANDEY', 'Admno.' => '202300071', 'Mobile' => '9794304264', 'Address' => 'RAMEDI HAMIRPUR', 'Date of Birth' => '18-12-2016', 'Father Name' => 'VINAY KUMAR', 'Mother Name' => 'DIPIKA PANDEY', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_105725.jpg'),
                164 => array('Serial No.' => '235', 'AppID' => '5071', 'Name' => 'SHRIJA VERMA', 'Admno.' => '202300072', 'Mobile' => '9990090302', 'Address' => 'C/O RAMESH SHUKLA CHAURA DEVI HAMIRPUR', 'Date of Birth' => '03-08-2017', 'Father Name' => 'MUKESH KUMAR', 'Mother Name' => 'SHEWTA', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_105657.jpg'),
                165 => array('Serial No.' => '236', 'AppID' => '5072', 'Name' => 'TEJASVANI DEIVEDI', 'Admno.' => '202300073', 'Mobile' => '9140259155', 'Address' => 'KASHIRAM COLONY HAMIRPUR', 'Date of Birth' => '09-09-2011', 'Father Name' => 'KULDEEP', 'Mother Name' => 'KUMKUM', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_105736.jpg'),
                166 => array('Serial No.' => '237', 'AppID' => '5074', 'Name' => 'VED PRATAP SINGH', 'Admno.' => '202300075', 'Mobile' => '888919470', 'Address' => 'GAUTAM VIVASH NAGAR SHARASHWATI SISHU MANDIR VIVEK NAGAR HAMIRPUR', 'Date of Birth' => '05-01-2015', 'Father Name' => 'PUSHPENDRA KUMAR SINGH', 'Mother Name' => 'VANDNA', 'Class' => 'III', 'Section' => 'A', 'Photo' => 'IMG_20231222_105924.jpg'),
                167 => array('Serial No.' => '241', 'AppID' => '5077', 'Name' => 'ANIKET', 'Admno.' => '202300078', 'Mobile' => '', 'Address' => '', 'Date of Birth' => '09-04-2014', 'Father Name' => 'ANURAG', 'Mother Name' => 'KIRAN SONKAR', 'Class' => 'IV', 'Section' => 'A', 'Photo' => 'IMG_20231222_114153.jpg'),
                168 => array('Serial No.' => '242', 'AppID' => '5078', 'Name' => 'ANSH YADAV', 'Admno.' => '202300079', 'Mobile' => '', 'Address' => '', 'Date of Birth' => '02-04-2016', 'Father Name' => 'JAY RAM', 'Mother Name' => 'NISHA', 'Class' => 'IV', 'Section' => 'A', 'Photo' => 'IMG_20231222_111310.jpg'),
                169 => array('Serial No.' => '243', 'AppID' => '5079', 'Name' => 'ANSHIKA', 'Admno.' => '202300080', 'Mobile' => '7887027060', 'Address' => 'GAURA DEVI NAI BASTI HAMIRPUR', 'Date of Birth' => '14-12-2013', 'Father Name' => 'KAMAL KUMAR', 'Mother Name' => 'ANITA PAL', 'Class' => 'IV', 'Section' => 'A', 'Photo' => 'IMG_20231222_111330.jpg'),
                170 => array('Serial No.' => '244', 'AppID' => '5080', 'Name' => 'ANUBHAV CHANDRO', 'Admno.' => '202300081', 'Mobile' => '9954739263', 'Address' => 'VIDYA MANDIR ROAD TAHSHIL -DAR COLONY HAMIRPUR', 'Date of Birth' => '31-07-2015', 'Father Name' => 'KISHOR KUMAR', 'Mother Name' => 'ARCHANA DEVI', 'Class' => 'IV', 'Section' => 'A', 'Photo' => 'IMG_20231222_111720.jpg'),
                171 => array('Serial No.' => '245', 'AppID' => '5081', 'Name' => 'APRAJITA AWASTHI', 'Admno.' => '202300082', 'Mobile' => '', 'Address' => 'RAMEDI TARAUS PURANI GAIS GODAN', 'Date of Birth' => '21-02-2013', 'Father Name' => 'JITESH KUMAR AWASTHI', 'Mother Name' => 'RAMAKANTI', 'Class' => 'IV', 'Section' => 'A', 'Photo' => 'IMG_20231222_111737.jpg'),
                172 => array('Serial No.' => '246', 'AppID' => '5083', 'Name' => 'ARYA TRIPATHI', 'Admno.' => '202300084', 'Mobile' => '6394229039', 'Address' => 'MAJHKHARA RAMEDI HAMIRPUR', 'Date of Birth' => '07-07-2016', 'Father Name' => 'RAHUL TRIPATHI', 'Mother Name' => 'KANCHAN TRIPATHI', 'Class' => 'IV', 'Section' => 'A', 'Photo' => 'IMG_20231222_111814.jpg'),
                173 => array('Serial No.' => '247', 'AppID' => '5084', 'Name' => 'AYUSH SINGH', 'Admno.' => '202300085', 'Mobile' => '8381881387', 'Address' => 'GAURA DEVI NAI BASTI HAMIRPUR', 'Date of Birth' => '20-01-2014', 'Father Name' => 'SUMIT PRATAP SINGH', 'Mother Name' => 'SHEELU SINGH', 'Class' => 'IV', 'Section' => 'A', 'Photo' => 'IMG_20231222_111847.jpg'),
                174 => array('Serial No.' => '248', 'AppID' => '5086', 'Name' => 'JANNAT NISHAD', 'Admno.' => '202300087', 'Mobile' => '9628353532', 'Address' => 'VIVEK NAGAR HAMIRPUR', 'Date of Birth' => '20-09-2014', 'Father Name' => 'RAMKHILAWAN', 'Mother Name' => 'SUSHILA', 'Class' => 'IV', 'Section' => 'A', 'Photo' => 'IMG_20231222_112605.jpg'),
                175 => array('Serial No.' => '249', 'AppID' => '5089', 'Name' => 'KUNJ KRANTI', 'Admno.' => '202300090', 'Mobile' => '8009520123', 'Address' => 'VIVEK NAGAR HAMIRPUR', 'Date of Birth' => '13-09-2015', 'Father Name' => 'DEVKARAN', 'Mother Name' => 'ANITA', 'Class' => 'IV', 'Section' => 'A', 'Photo' => 'IMG_20231222_112645.jpg'),
                176 => array('Serial No.' => '250', 'AppID' => '5091', 'Name' => 'MINAKSHI', 'Admno.' => '202300092', 'Mobile' => '7330924734', 'Address' => 'RAMEDI TARAUS HAMIRPUR', 'Date of Birth' => '27-06-2015', 'Father Name' => 'DHEERENDRA KUMAR', 'Mother Name' => 'JYOTI', 'Class' => 'IV', 'Section' => 'A', 'Photo' => 'IMG_20231222_114110.jpg'),
                177 => array('Serial No.' => '251', 'AppID' => '5093', 'Name' => 'PRAJJWAL', 'Admno.' => '202300094', 'Mobile' => '9140879571', 'Address' => 'POLICE LINE HAMIRPUR', 'Date of Birth' => '14-10-2014', 'Father Name' => 'RAMBABU KATARYA', 'Mother Name' => 'SEEMA', 'Class' => 'IV', 'Section' => 'A', 'Photo' => 'IMG_20231222_113050.jpg'),
                178 => array('Serial No.' => '252', 'AppID' => '5094', 'Name' => 'PRASHANT SINGH', 'Admno.' => '202300095', 'Mobile' => '8127800677', 'Address' => 'LAXMI BAI PARK BEHIND RAJPOOT METERS HAMIRPUR', 'Date of Birth' => '15-12-2014', 'Father Name' => 'RAJESH KUMAR', 'Mother Name' => 'SADHANA', 'Class' => 'IV', 'Section' => 'A', 'Photo' => 'IMG_20231222_112808.jpg'),
                179 => array('Serial No.' => '253', 'AppID' => '5095', 'Name' => 'PRIYA', 'Admno.' => '202300096', 'Mobile' => '', 'Address' => '', 'Date of Birth' => '01-01-2013', 'Father Name' => 'MANISH KUMAR', 'Mother Name' => 'SAPNA', 'Class' => 'IV', 'Section' => 'A', 'Photo' => 'IMG_20231222_113146.jpg'),
                180 => array('Serial No.' => '254', 'AppID' => '5096', 'Name' => 'PURVI', 'Admno.' => '202300097', 'Mobile' => '7080101039', 'Address' => 'SAJETI KANPUR NAGAR', 'Date of Birth' => '09-12-2014', 'Father Name' => 'MOHIT KUMAR', 'Mother Name' => 'MEENA', 'Class' => 'IV', 'Section' => 'A', 'Photo' => 'IMG_20231222_113157.jpg'),
                181 => array('Serial No.' => '255', 'AppID' => '5097', 'Name' => 'RADHIKA', 'Admno.' => '202300098', 'Mobile' => '9554835063', 'Address' => 'RAMEDI TARAUS HAMIRPUR', 'Date of Birth' => '01-01-2015', 'Father Name' => 'RAM MILAN', 'Mother Name' => 'REKHA DEVI', 'Class' => 'IV', 'Section' => 'A', 'Photo' => 'IMG_20231222_113209.jpg'),
                182 => array('Serial No.' => '256', 'AppID' => '5100', 'Name' => 'RUPESH KUMAR', 'Admno.' => '202300101', 'Mobile' => '6393164625', 'Address' => 'KESARIYA KA DARA HAMIRPUR', 'Date of Birth' => '20-01-2014', 'Father Name' => 'VIRENDRA KUMAR', 'Mother Name' => 'GENDA RANI', 'Class' => 'IV', 'Section' => 'A', 'Photo' => 'IMG_20231222_113953.jpg'),
                183 => array('Serial No.' => '257', 'AppID' => '5104', 'Name' => 'SURYANSH GUPTA', 'Admno.' => '202300105', 'Mobile' => '9919526065', 'Address' => 'VIVEK GANAR HAMIRPUR', 'Date of Birth' => '18-08-2015', 'Father Name' => 'AMIT KUMAR GUPTA', 'Mother Name' => 'SADHANA', 'Class' => 'IV', 'Section' => 'A', 'Photo' => 'IMG_20231222_114038.jpg'),
                184 => array('Serial No.' => '258', 'AppID' => '5107', 'Name' => 'YUVRAJ KUMAR', 'Admno.' => '202300108', 'Mobile' => '9029187376', 'Address' => 'NALKUP COLONY GAYATRI MANADIR HAMIRPUR', 'Date of Birth' => '30-08-2015', 'Father Name' => 'RAKESH KUMAR', 'Mother Name' => 'ANSHU NISHAD', 'Class' => 'IV', 'Section' => 'A', 'Photo' => 'IMG_20231222_114024.jpg'),
                185 => array('Serial No.' => '281', 'AppID' => '5109', 'Name' => 'AARAB MANSOORI', 'Admno.' => '202300110', 'Mobile' => '9794326117', 'Address' => 'SUFI GANJ BAZAR HAMIRPUR', 'Date of Birth' => '30-09-2011', 'Father Name' => 'SHEKH RAJU', 'Mother Name' => 'MUSKAN KHATOON', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_115351.jpg'),
                186 => array('Serial No.' => '282', 'AppID' => '5110', 'Name' => 'ABHIMANYU', 'Admno.' => '202300111', 'Mobile' => '9792628993', 'Address' => 'RAMEDI TARAUS HAMIRPUR', 'Date of Birth' => '30-08-2013', 'Father Name' => 'RAJ PRATAP SINGH', 'Mother Name' => 'SONAM DEVI', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_115424.jpg'),
                187 => array('Serial No.' => '283', 'AppID' => '5111', 'Name' => 'ADARSH', 'Admno.' => '202300112', 'Mobile' => '9838343380', 'Address' => 'GAUSHALA RAMEDI HAMIRPUR', 'Date of Birth' => '24-12-2013', 'Father Name' => 'SANTOSH KUMAR', 'Mother Name' => 'PRITI', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_115526.jpg'),
                188 => array('Serial No.' => '284', 'AppID' => '5112', 'Name' => 'ADITI', 'Admno.' => '202300113', 'Mobile' => '7007221968', 'Address' => 'NEAR SARDAR PATEL TEACHER COLONY HAMIRPUR', 'Date of Birth' => '25-08-2015', 'Father Name' => 'DESHRAJ PATEL', 'Mother Name' => 'SUMAN PATEL', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_115820.jpg'),
                189 => array('Serial No.' => '285', 'AppID' => '5113', 'Name' => 'ANMOL SINGH', 'Admno.' => '202300114', 'Mobile' => '8957928639', 'Address' => 'VIVEK NAGAR HAMIRPUR', 'Date of Birth' => '25-06-2013', 'Father Name' => 'NAGENDRA SINGH', 'Mother Name' => 'SHIVLOCHANA SINGH', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_115848.jpg'),
                190 => array('Serial No.' => '286', 'AppID' => '5114', 'Name' => 'ANSHIKA NISHAD', 'Admno.' => '202300115', 'Mobile' => '9559054917', 'Address' => 'FULARANI RAMEDI HAMIRAPUR', 'Date of Birth' => '18-08-2014', 'Father Name' => 'KAMLESH KUMAR', 'Mother Name' => 'PUSHPA DEVI', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_120024.jpg'),
                191 => array('Serial No.' => '287', 'AppID' => '5115', 'Name' => 'ANSHIKA SHARMA', 'Admno.' => '202300116', 'Mobile' => '9889650370', 'Address' => 'NEAR NIRANKAR BHAVAN RAMEDI HAMIRPUR', 'Date of Birth' => '07-01-2016', 'Father Name' => 'VIVESH KUMAR', 'Mother Name' => 'ARCHANA', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_120030.jpg'),
                192 => array('Serial No.' => '288', 'AppID' => '5116', 'Name' => 'ARADHYA YADAV', 'Admno.' => '202300117', 'Mobile' => '6386879825', 'Address' => 'BAGALI MAHAL HAMIRPUR', 'Date of Birth' => '17-01-2014', 'Father Name' => 'RAMBALAK YADAV', 'Mother Name' => 'UMA', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_120045.jpg'),
                193 => array('Serial No.' => '289', 'AppID' => '5117', 'Name' => 'ARNAV KUSHWAHA', 'Admno.' => '202300118', 'Mobile' => '8840378482', 'Address' => 'RAMEDI TARAUS HAMIRPUR', 'Date of Birth' => '17-05-2015', 'Father Name' => 'BABU NARAYAN', 'Mother Name' => 'MAMTA', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_120057.jpg'),
                194 => array('Serial No.' => '290', 'AppID' => '5118', 'Name' => 'ARSHAN AHAMAD', 'Admno.' => '202300119', 'Mobile' => '8423826425', 'Address' => 'SUBHASH MARKET HAMIRPUR', 'Date of Birth' => '27-07-2015', 'Father Name' => 'SHARIF AHAMAD', 'Mother Name' => 'AAFREEN', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_120110.jpg'),
                195 => array('Serial No.' => '291', 'AppID' => '5119', 'Name' => 'ARYAN SINGH', 'Admno.' => '202300120', 'Mobile' => '8931933502', 'Address' => 'NEAR OLD GAS GODAM RAMEDI HAMIRPUR', 'Date of Birth' => '21-01-2014', 'Father Name' => 'JANAK SINGH', 'Mother Name' => 'LAXMI SINGH', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_120219.jpg'),
                196 => array('Serial No.' => '292', 'AppID' => '5120', 'Name' => 'AYUSH KUMAR MAURYA', 'Admno.' => '202300121', 'Mobile' => '9129946327', 'Address' => 'POLICE LINE HAMIRPUR', 'Date of Birth' => '01-09-2014', 'Father Name' => 'RAMESH KUMAR MAURYA', 'Mother Name' => 'RAJKUMARI', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_120246.jpg'),
                197 => array('Serial No.' => '293', 'AppID' => '5121', 'Name' => 'AYUSHI RAJPOOT', 'Admno.' => '202300122', 'Mobile' => '9026208250', 'Address' => 'BAGH DAU DAYAL RAMEDI HAMIRPUR', 'Date of Birth' => '17-04-2014', 'Father Name' => 'ROHIT SINGH', 'Mother Name' => 'KIRAN', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_120257.jpg'),
                198 => array('Serial No.' => '294', 'AppID' => '5122', 'Name' => 'DURGESH KUMAR', 'Admno.' => '202300123', 'Mobile' => '9696594873', 'Address' => 'RAMEDI CHAURAHA HAMIRPUR', 'Date of Birth' => '22-10-2012', 'Father Name' => 'UMESH KUMAR DIXIT', 'Mother Name' => 'NEELAM', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_120549.jpg'),
                199 => array('Serial No.' => '295', 'AppID' => '5123', 'Name' => 'OJAS KUTAR', 'Admno.' => '202300124', 'Mobile' => '7355547031', 'Address' => 'JAIL TALAB RAMEDI CHAURAHA HAMIRPUR', 'Date of Birth' => '24-02-2013', 'Father Name' => 'RAJENDRA KUMAR', 'Mother Name' => 'SADHNA', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_120910.jpg'),
                200 => array('Serial No.' => '296', 'AppID' => '5124', 'Name' => 'PALAK KOTARYA', 'Admno.' => '202300125', 'Mobile' => '9140879571', 'Address' => 'POLICE LINE HAMIRPUR', 'Date of Birth' => '29-07-2013', 'Father Name' => 'RAMBABU KOTARYA', 'Mother Name' => 'SEEMA', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_120509.jpg'),
                201 => array('Serial No.' => '297', 'AppID' => '5125', 'Name' => 'PALAK YADAV', 'Admno.' => '202300126', 'Mobile' => '9415406031', 'Address' => 'POLICE LINE HAMIRPUR', 'Date of Birth' => '14-08-2013', 'Father Name' => 'SHAILENDRA', 'Mother Name' => 'SHOBHNA', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_120530.jpg'),
                202 => array('Serial No.' => '298', 'AppID' => '5126', 'Name' => 'PRAGYA', 'Admno.' => '202300127', 'Mobile' => '9452584344', 'Address' => '08/1007 RAMEDI DANDA HAMIRPUR', 'Date of Birth' => '29-12-2013', 'Father Name' => 'PRAKASH CHAND SAROJ', 'Mother Name' => 'SHIV KUMARI', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_120602.jpg'),
                203 => array('Serial No.' => '299', 'AppID' => '5127', 'Name' => 'RAMJI DIXIT', 'Admno.' => '202300128', 'Mobile' => '7752843422', 'Address' => 'UJNEDI HAMIRPUR', 'Date of Birth' => '30-06-2014', 'Father Name' => 'BRAJESH KUMAR', 'Mother Name' => 'MAINA', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_120620.jpg'),
                204 => array('Serial No.' => '300', 'AppID' => '5128', 'Name' => 'SHIVANGI MISHRA', 'Admno.' => '202300129', 'Mobile' => '9935045144', 'Address' => 'NALKOOP COLONY YOGASHALA HAMIRPUR', 'Date of Birth' => '27-02-2015', 'Father Name' => 'VEERENDRA', 'Mother Name' => 'PREETA', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_120644.jpg'),
                205 => array('Serial No.' => '301', 'AppID' => '5129', 'Name' => 'SHREYA SINGH', 'Admno.' => '202300130', 'Mobile' => '9653048613', 'Address' => 'NEAR GYAN GARDEN RAMEDI HAMIRPUR', 'Date of Birth' => '08-03-2015', 'Father Name' => 'BRAJESH KUMAR SINGH', 'Mother Name' => 'NEHA SINGH', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_120711.jpg'),
                206 => array('Serial No.' => '302', 'AppID' => '5131', 'Name' => 'SHUBHAM', 'Admno.' => '202300132', 'Mobile' => '9118697587', 'Address' => 'E21, COLONY TREASURY OFFICE AKIL TIRAHA HAMIRPUR', 'Date of Birth' => '17-01-2015', 'Father Name' => 'DHARMENDRA', 'Mother Name' => 'SUNITA', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_120804.jpg'),
                207 => array('Serial No.' => '303', 'AppID' => '5132', 'Name' => 'SNEHA', 'Admno.' => '202300133', 'Mobile' => '9170141433', 'Address' => 'NEAR CHAURA DEVI HAMIRPUR', 'Date of Birth' => '01-03-2016', 'Father Name' => 'MANJOOL', 'Mother Name' => 'JANKI', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_120817.jpg'),
                208 => array('Serial No.' => '304', 'AppID' => '5133', 'Name' => 'UTKARSH CHATURVEDI', 'Admno.' => '202300134', 'Mobile' => '9354237263', 'Address' => 'GAUSHALA RAMEDI HAMIRPUR', 'Date of Birth' => '13-07-2015', 'Father Name' => 'KAMLESH', 'Mother Name' => 'PREETI', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_120920.jpg'),
                209 => array('Serial No.' => '305', 'AppID' => '5134', 'Name' => 'VEDANG', 'Admno.' => '202300135', 'Mobile' => '7080143877', 'Address' => 'RAMEDI HAMIRPUR', 'Date of Birth' => '13-10-2016', 'Father Name' => 'PRADEEP KUMAR', 'Mother Name' => 'REENA', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_120953.jpg'),
                210 => array('Serial No.' => '306', 'AppID' => '5135', 'Name' => 'YASH KUMAR SINGH', 'Admno.' => '202300136', 'Mobile' => '9795697618', 'Address' => 'RAMEDI HAMIRPUR', 'Date of Birth' => '30-07-2014', 'Father Name' => 'RAJESH KUMAR', 'Mother Name' => 'BHAGUNTI', 'Class' => 'V', 'Section' => 'A', 'Photo' => 'IMG_20231221_121008.jpg'),
                211 => array('Serial No.' => '321', 'AppID' => '5137', 'Name' => 'ADITYA GAUTAM', 'Admno.' => '202300138', 'Mobile' => '', 'Address' => 'YAGYASHALA HAMIRPUR', 'Date of Birth' => '24-11-2011', 'Father Name' => 'SHASHIKANT', 'Mother Name' => 'KALINDRI', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_102944.jpg'),
                212 => array('Serial No.' => '322', 'AppID' => '5139', 'Name' => 'ANIKET WASTHI', 'Admno.' => '202300140', 'Mobile' => '', 'Address' => 'NEAR DEVADAS TEMPLE RAMEDI HAMIRPUR', 'Date of Birth' => '21-02-2013', 'Father Name' => 'ANURAG AWASTHI', 'Mother Name' => 'RASHMI AWASTHI', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_103119.jpg'),
                213 => array('Serial No.' => '323', 'AppID' => '5140', 'Name' => 'ANMOL YADAV', 'Admno.' => '202300141', 'Mobile' => '', 'Address' => 'SHERPUR BASGARE BASREHAR ETAWAH', 'Date of Birth' => '15-01-2015', 'Father Name' => 'MANOJ KUMAR', 'Mother Name' => 'SAPNA', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_103339.jpg'),
                214 => array('Serial No.' => '324', 'AppID' => '5141', 'Name' => 'ANSH AWASTHI', 'Admno.' => '202300142', 'Mobile' => '', 'Address' => 'RAMEDI TARAUS HAMIRPUR', 'Date of Birth' => '09-11-2013', 'Father Name' => 'ALOK AWASTHI', 'Mother Name' => 'KOMAL AWASTHI', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_103502.jpg'),
                215 => array('Serial No.' => '325', 'AppID' => '5142', 'Name' => 'ARADHYA DAKSH', 'Admno.' => '202300143', 'Mobile' => '', 'Address' => 'V+P LODIPUR NIWADA HAMIRPUR', 'Date of Birth' => '29-05-2013', 'Father Name' => 'ABHISHEK KUMAR', 'Mother Name' => 'SAMPAT', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_103801.jpg'),
                216 => array('Serial No.' => '326', 'AppID' => '5144', 'Name' => 'ARADHYA SINGH', 'Admno.' => '202300145', 'Mobile' => '', 'Address' => 'GAUSHALA RAMEDI HAMIRPUR', 'Date of Birth' => '17-12-2012', 'Father Name' => 'ARVIND', 'Mother Name' => 'RASHMI', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_103626.jpg'),
                217 => array('Serial No.' => '327', 'AppID' => '5145', 'Name' => 'ARISH', 'Admno.' => '202300146', 'Mobile' => '', 'Address' => 'KHALEPURA HAMIRPUR', 'Date of Birth' => '18-11-2012', 'Father Name' => 'AZAD', 'Mother Name' => 'SHABANA', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_104455.jpg'),
                218 => array('Serial No.' => '328', 'AppID' => '5146', 'Name' => 'ARJU', 'Admno.' => '202300147', 'Mobile' => '', 'Address' => 'CHAURA DEVI HAMIRPUR', 'Date of Birth' => '04-01-2014', 'Father Name' => 'CHARAN SINGH', 'Mother Name' => 'JAMINI DEVI', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_104625.jpg'),
                219 => array('Serial No.' => '329', 'AppID' => '5148', 'Name' => 'ARNI CHAURASIYA', 'Admno.' => '202300149', 'Mobile' => '', 'Address' => 'BHILAWA HAMIRPUR', 'Date of Birth' => '19-11-2013', 'Father Name' => 'ARVIND', 'Mother Name' => 'RACHANA', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_104343.jpg'),
                220 => array('Serial No.' => '330', 'AppID' => '5149', 'Name' => 'ARUSH GUPTA', 'Admno.' => '202300150', 'Mobile' => '', 'Address' => 'BENGALI MAHAL RAMEDI HAMIRPUR', 'Date of Birth' => '12-11-2013', 'Father Name' => 'RAKESH KUMAR', 'Mother Name' => 'MEGHA GUPTA', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_105017.jpg'),
                221 => array('Serial No.' => '331', 'AppID' => '5152', 'Name' => 'HASNAIN', 'Admno.' => '202300153', 'Mobile' => '', 'Address' => 'KHALEPURA HAMIRPUR', 'Date of Birth' => '04-05-2012', 'Father Name' => 'MOHD JAMAL', 'Mother Name' => 'RAISA', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_105953.jpg'),
                222 => array('Serial No.' => '332', 'AppID' => '5153', 'Name' => 'JAY CHAURASIYA', 'Admno.' => '202300154', 'Mobile' => '', 'Address' => 'KHALEPURA HAMIRPUR', 'Date of Birth' => '19-08-2011', 'Father Name' => 'RAJESH', 'Mother Name' => 'NEHA', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_110214.jpg'),
                223 => array('Serial No.' => '333', 'AppID' => '5155', 'Name' => 'KARTIK SINGH PARMAR', 'Admno.' => '202300156', 'Mobile' => '', 'Address' => 'BAPPA JI KI GALI RAMEDI HAMIRPUR', 'Date of Birth' => '01-06-2012', 'Father Name' => 'DEVENDRA SINGH', 'Mother Name' => 'GEETA', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_110454.jpg'),
                224 => array('Serial No.' => '334', 'AppID' => '5156', 'Name' => 'KAUSHLENDRA BRAMH SINGH', 'Admno.' => '202300157', 'Mobile' => '', 'Address' => 'RAMEDI TARAUS HAMIRPUR', 'Date of Birth' => '07-06-2014', 'Father Name' => 'BIRENDRA BRAMH SINGH', 'Mother Name' => 'VIVEK MANI', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_110629.jpg'),
                225 => array('Serial No.' => '335', 'AppID' => '5157', 'Name' => 'MAHAVEER PRATAP SINGH', 'Admno.' => '202300158', 'Mobile' => '', 'Address' => 'VIVEK NAGAR HAMIRPUR', 'Date of Birth' => '07-09-2013', 'Father Name' => 'ABHAY PRATAP SINGH', 'Mother Name' => 'POOJA SINGH', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_110810.jpg'),
                226 => array('Serial No.' => '336', 'AppID' => '5158', 'Name' => 'NAVYA PAL', 'Admno.' => '202300159', 'Mobile' => '', 'Address' => 'GAURA DEVI GANDHI NAGAR HAMIRPUR', 'Date of Birth' => '07-01-2012', 'Father Name' => 'DHANIRAM', 'Mother Name' => 'ANJU', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_110942.jpg'),
                227 => array('Serial No.' => '337', 'AppID' => '5160', 'Name' => 'PRATISHTHA YADAV', 'Admno.' => '202300161', 'Mobile' => '', 'Address' => 'RAMEDI TARAUS HAMIRPUR', 'Date of Birth' => '25-03-2013', 'Father Name' => 'BALVIR', 'Mother Name' => 'NILAM', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_111210.jpg'),
                228 => array('Serial No.' => '338', 'AppID' => '5161', 'Name' => 'PRINCE KUMAR', 'Admno.' => '202300162', 'Mobile' => '', 'Address' => 'RAMEDI TARAUS HAMIRPUR', 'Date of Birth' => '12-05-2012', 'Father Name' => 'ANAND KUMAR', 'Mother Name' => 'RITU', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_111318.jpg'),
                229 => array('Serial No.' => '339', 'AppID' => '5162', 'Name' => 'RISHI DIXIT', 'Admno.' => '202300163', 'Mobile' => '', 'Address' => 'RAMEDI HAMIRPUR', 'Date of Birth' => '11-12-2012', 'Father Name' => 'AVDHESH KUMAR', 'Mother Name' => 'ARADHANA', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_114725.jpg'),
                230 => array('Serial No.' => '340', 'AppID' => '5163', 'Name' => 'RITI SINGH', 'Admno.' => '202300164', 'Mobile' => '', 'Address' => 'DEVADAS MANDIR HAMIRPUR', 'Date of Birth' => '02-04-2015', 'Father Name' => 'ROHIT SINGH', 'Mother Name' => 'REKHA', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_114736.jpg'),
                231 => array('Serial No.' => '341', 'AppID' => '5164', 'Name' => 'RITIKA AWASTHI', 'Admno.' => '202300165', 'Mobile' => '', 'Address' => 'DEVADAS MANDIR HAMIRPUR', 'Date of Birth' => '20-02-2013', 'Father Name' => 'SUDHANSHU', 'Mother Name' => 'ALKA AWASTHI', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_114753.jpg'),
                232 => array('Serial No.' => '342', 'AppID' => '5165', 'Name' => 'RUCHI', 'Admno.' => '202300166', 'Mobile' => '', 'Address' => 'KESHARIYA KA DERA RAMEDI HAMIRPUR', 'Date of Birth' => '01-01-2013', 'Father Name' => 'VIRENDRA KUMAR', 'Mother Name' => 'GENDA RANI', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_114806.jpg'),
                233 => array('Serial No.' => '343', 'AppID' => '5166', 'Name' => 'SAMAR SINGH', 'Admno.' => '202300167', 'Mobile' => '', 'Address' => 'RAMEDI TARAUS HAMIRPUR', 'Date of Birth' => '28-07-2014', 'Father Name' => 'GYAN SINGH', 'Mother Name' => 'POOJA SINGH', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_114839.jpg'),
                234 => array('Serial No.' => '344', 'AppID' => '5167', 'Name' => 'SAMRIDDHI SAHU', 'Admno.' => '202300168', 'Mobile' => '', 'Address' => '18/2 B SUBHASH BAZAR HAMIRPUR', 'Date of Birth' => '01-10-2013', 'Father Name' => 'RAJENDRA PRASAD SAHU', 'Mother Name' => 'RUCHI SAHU', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_114853.jpg'),
                235 => array('Serial No.' => '345', 'AppID' => '5168', 'Name' => 'SARAS SAHU', 'Admno.' => '202300169', 'Mobile' => '', 'Address' => 'B-6, MAUDHAHA BANDH COLONY HAMIRPUR', 'Date of Birth' => '30-08-2014', 'Father Name' => 'AWADHESH KUMAR SAHU', 'Mother Name' => 'RAJKUMARI SAHU', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_114907.jpg'),
                236 => array('Serial No.' => '346', 'AppID' => '5169', 'Name' => 'SAUYA PARMAR', 'Admno.' => '202300170', 'Mobile' => '', 'Address' => 'BENGALI MAHAL HAMIRPUR', 'Date of Birth' => '08-08-2012', 'Father Name' => 'RAVI KIRAN SINGH', 'Mother Name' => 'RITU SINGH', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_114925.jpg'),
                237 => array('Serial No.' => '347', 'AppID' => '5170', 'Name' => 'SHALINI YADAV', 'Admno.' => '202300171', 'Mobile' => '', 'Address' => 'BENGALI MAHAL HAMIRPUR', 'Date of Birth' => '18-09-2013', 'Father Name' => 'SUNIL KUMAR YADAV', 'Mother Name' => 'SAVITRI YADAV', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_114822.jpg'),
                238 => array('Serial No.' => '348', 'AppID' => '5172', 'Name' => 'SWASTIK', 'Admno.' => '202300173', 'Mobile' => '', 'Address' => 'BENGALI MAHAL VIDYA MANDIR RAOD HAMIRPUR', 'Date of Birth' => '27-07-2017', 'Father Name' => 'SAROJ KUMAR', 'Mother Name' => 'ARTI DEVI', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_115000.jpg'),
                239 => array('Serial No.' => '349', 'AppID' => '5173', 'Name' => 'TANISHQ KUSHWAHA', 'Admno.' => '202300174', 'Mobile' => '', 'Address' => 'PHOOLARANI NEW BASTI RAMEDI HAMIRPUR', 'Date of Birth' => '07-10-2014', 'Father Name' => 'UDIT NARAYAN', 'Mother Name' => 'PREETI KUSHWAHA', 'Class' => 'VI', 'Section' => 'A', 'Photo' => 'IMG_20231221_115011.jpg'),
                240 => array('Serial No.' => '361', 'AppID' => '5178', 'Name' => 'ADYA SINGH', 'Admno.' => '202300179', 'Mobile' => '8052360300', 'Address' => 'VIVEK NAGAR HAMIRPUR', 'Date of Birth' => '20-12-2013', 'Father Name' => 'RAGHVENDRA SINGH', 'Mother Name' => 'JYOTI SINGH', 'Class' => 'VII', 'Section' => 'A', 'Photo' => 'IMG_20231221_115313.jpg'),
                241 => array('Serial No.' => '362', 'AppID' => '5179', 'Name' => 'ANSHIKA SHAH', 'Admno.' => '202300180', 'Mobile' => '6387481259', 'Address' => 'AMAR SAHEED ROAD HAMIRPUR', 'Date of Birth' => '08-01-2013', 'Father Name' => 'ASHWANI KUMAR', 'Mother Name' => 'PRIYANKA SHAH', 'Class' => 'VII', 'Section' => 'A', 'Photo' => 'IMG_20231220_113847.jpg'),
                242 => array('Serial No.' => '363', 'AppID' => '5180', 'Name' => 'ARADHYA GUPTA', 'Admno.' => '202300181', 'Mobile' => '8090086551', 'Address' => 'BEHIND VIMAL NURSING HOME HAMIRPUR', 'Date of Birth' => '16-12-2012', 'Father Name' => 'DEEP PRAKASH GUPTA', 'Mother Name' => 'CHITRA GUPTA', 'Class' => 'VII', 'Section' => 'A', 'Photo' => 'IMG_20231220_113940.jpg'),
                243 => array('Serial No.' => '364', 'AppID' => '5183', 'Name' => 'DIPANSHI YADAV', 'Admno.' => '202300184', 'Mobile' => '9651875716', 'Address' => 'LAXMIBAI RAJPUT MOTERS HAMIRPUR', 'Date of Birth' => '01-01-2012', 'Father Name' => 'ARIMARDAN SINGH', 'Mother Name' => 'RADHA YADAV', 'Class' => 'VII', 'Section' => 'A', 'Photo' => 'IMG_20231220_114514.jpg'),
                244 => array('Serial No.' => '365', 'AppID' => '5184', 'Name' => 'DIVYANSHI', 'Admno.' => '202300185', 'Mobile' => '9984739265', 'Address' => 'VIDYA MANDIR ROAD TAHSILDAR COLONY HAMIRPUR', 'Date of Birth' => '18-09-2010', 'Father Name' => 'KISHOR KUMAR', 'Mother Name' => 'ARCHANA', 'Class' => 'VII', 'Section' => 'A', 'Photo' => 'IMG_20231220_114553.jpg'),
                245 => array('Serial No.' => '366', 'AppID' => '5185', 'Name' => 'EKANSH SHARMA', 'Admno.' => '202300186', 'Mobile' => '8572975848', 'Address' => 'BENGALI MAHAL RAMEDI HAMIRPUR', 'Date of Birth' => '26-05-2013', 'Father Name' => 'ANUJ KUMAR SHARMA', 'Mother Name' => 'SIMA', 'Class' => 'VII', 'Section' => 'A', 'Photo' => 'IMG_20231220_114700.jpg'),
                246 => array('Serial No.' => '367', 'AppID' => '5186', 'Name' => 'GYANENDRA PRATAP SINGH', 'Admno.' => '202300187', 'Mobile' => '9026536745', 'Address' => 'VIVEK NAGAR HAMIRPUR', 'Date of Birth' => '21-02-2013', 'Father Name' => 'TEJ PRATAP SINGH', 'Mother Name' => 'ANKITA SINGH', 'Class' => 'VII', 'Section' => 'A', 'Photo' => 'IMG_20231220_114713.jpg'),
                247 => array('Serial No.' => '368', 'AppID' => '5187', 'Name' => 'JAISLEEN WILLIAM', 'Admno.' => '202300188', 'Mobile' => '9005371777', 'Address' => '192, BENGALI MAHAL HAMIRPUR', 'Date of Birth' => '28-05-2011', 'Father Name' => 'VINAY KUMAR WILLIAM', 'Mother Name' => 'SMITA WILLIAM', 'Class' => 'VII', 'Section' => 'A', 'Photo' => 'IMG_20231220_114817.jpg'),
                248 => array('Serial No.' => '369', 'AppID' => '5188', 'Name' => 'KARAN GUPTA', 'Admno.' => '202300189', 'Mobile' => '8874097010', 'Address' => 'NEAR AT ITI GALI FOURTH NO. HOUSE HAMIRPUR', 'Date of Birth' => '15-08-2010', 'Father Name' => 'VINOD KUMAR GUPTA', 'Mother Name' => 'KALA DEVI', 'Class' => 'VII', 'Section' => 'A', 'Photo' => 'IMG_20231220_114835.jpg'),
                249 => array('Serial No.' => '370', 'AppID' => '5189', 'Name' => 'MAHAK PAL', 'Admno.' => '202300190', 'Mobile' => '7497957043', 'Address' => 'GAURA DEVI GANDHINAGAR HAMIRPUR', 'Date of Birth' => '22-01-2011', 'Father Name' => 'DHANIRAM PAL', 'Mother Name' => 'ANJU PAL', 'Class' => 'VII', 'Section' => 'A', 'Photo' => 'IMG_20231220_115044.jpg'),
                250 => array('Serial No.' => '371', 'AppID' => '5190', 'Name' => 'MAHIMA PATEL', 'Admno.' => '202300191', 'Mobile' => '7007221968', 'Address' => 'TEACHERS COLONY HAMIRPUR', 'Date of Birth' => '25-07-2014', 'Father Name' => 'DESHRAJ PATEL', 'Mother Name' => 'SUMAN PATEL', 'Class' => 'VII', 'Section' => 'A', 'Photo' => 'IMG_20231220_115058.jpg'),
                251 => array('Serial No.' => '372', 'AppID' => '5191', 'Name' => 'MUSKAN PARVEEN', 'Admno.' => '202300192', 'Mobile' => '9005492770', 'Address' => 'RAMEDI HAMIIRPUR', 'Date of Birth' => '04-06-2013', 'Father Name' => 'ILIYAS AHAMAD', 'Mother Name' => 'HAMISA', 'Class' => 'VII', 'Section' => 'A', 'Photo' => 'IMG_20231220_115126.jpg'),
                252 => array('Serial No.' => '373', 'AppID' => '5193', 'Name' => 'RIDDHIMA SINGH', 'Admno.' => '202300194', 'Mobile' => '9455292954', 'Address' => 'RAHURIYA DHARM SHALA HAMIRPUR', 'Date of Birth' => '22-08-2012', 'Father Name' => 'AKHILESH KUMAR SINGH', 'Mother Name' => 'REENA SINGH', 'Class' => 'VII', 'Section' => 'A', 'Photo' => 'IMG_20231220_115327.jpg'),
                253 => array('Serial No.' => '374', 'AppID' => '5194', 'Name' => 'SADGI', 'Admno.' => '202300195', 'Mobile' => '8127259262', 'Address' => 'CHAURA DEVI HAMIRPUR', 'Date of Birth' => '01-01-2013', 'Father Name' => 'CHARAN SINGH', 'Mother Name' => 'JAMNI DEVI', 'Class' => 'VII', 'Section' => 'A', 'Photo' => 'IMG_20231220_115341.jpg'),
                254 => array('Serial No.' => '375', 'AppID' => '5197', 'Name' => 'SURBHI POTER', 'Admno.' => '202300198', 'Mobile' => '9828599307', 'Address' => 'BLOCK NO-23 ROOM NO. 304 NUPPL TOWER HAMIRPUR', 'Date of Birth' => '17-04-2012', 'Father Name' => 'KANHAYA LAL POTER', 'Mother Name' => 'BHARTI PRAJAPATI', 'Class' => 'VII', 'Section' => 'A', 'Photo' => 'IMG_20231220_115457.jpg'),
                255 => array('Serial No.' => '401', 'AppID' => '5202', 'Name' => 'ANSH SONI', 'Admno.' => '202300203', 'Mobile' => '8400744174', 'Address' => 'PAUTHIYA HAMIRPUR', 'Date of Birth' => '13-08-2012', 'Father Name' => 'NEELKAMAL SONI', 'Mother Name' => 'NANDANI SONI', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_114728-01.jpeg'),
                256 => array('Serial No.' => '402', 'AppID' => '5201', 'Name' => 'ANSHOO SACHAN', 'Admno.' => '202300202', 'Mobile' => '7652008110', 'Address' => 'PAUTHIYA HAMIRPUR', 'Date of Birth' => '10-06-2012', 'Father Name' => 'SHYAM BAHADUR SACHAN', 'Mother Name' => 'KAMINI SACHAN', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_114739-01.jpeg'),
                257 => array('Serial No.' => '403', 'AppID' => '5203', 'Name' => 'ANUBHAVI YADAV', 'Admno.' => '202300204', 'Mobile' => '7355117840', 'Address' => 'RANI LAXMI BAI PARK NEAR RAJPUT MOTERS HAMIRPUR', 'Date of Birth' => '26-04-2012', 'Father Name' => 'ALOK KUMAR', 'Mother Name' => 'SARAL KUMARI', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_114815-01.jpeg'),
                258 => array('Serial No.' => '404', 'AppID' => '5204', 'Name' => 'APOORV SACHAN', 'Admno.' => '202300205', 'Mobile' => '9455243094', 'Address' => 'PAUTHIYA HAMIRPUR', 'Date of Birth' => '11-04-2012', 'Father Name' => 'RAJESH KUMAR SACHAN', 'Mother Name' => 'ARUNA SACHAN', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_114830-01.jpeg'),
                259 => array('Serial No.' => '405', 'AppID' => '5205', 'Name' => 'ARJUN SINGH', 'Admno.' => '202300206', 'Mobile' => '8423118909', 'Address' => 'ADARSH NAGAR HAMIRPUR', 'Date of Birth' => '17-10-2012', 'Father Name' => 'DHARMENDRA SINGH', 'Mother Name' => 'RAGNI SINGH', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_114848-01.jpeg'),
                260 => array('Serial No.' => '406', 'AppID' => '5206', 'Name' => 'ARNI SINGH', 'Admno.' => '202300207', 'Mobile' => '9119979815', 'Address' => 'CHAURA DEVI HAMIRPUR', 'Date of Birth' => '06-11-2012', 'Father Name' => 'MAHENDRA SINGH', 'Mother Name' => 'GEETA SAHU', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_114916-01.jpeg'),
                261 => array('Serial No.' => '407', 'AppID' => '5207', 'Name' => 'ARYAN SACHAN', 'Admno.' => '202300208', 'Mobile' => '8604456125', 'Address' => 'KING ROAD HAMIRPUR', 'Date of Birth' => '03-10-2011', 'Father Name' => 'RANVIJAY SACHAN', 'Mother Name' => 'NEELAM SACHAN', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_114936-01.jpeg'),
                262 => array('Serial No.' => '408', 'AppID' => '5208', 'Name' => 'ASTHA DAKSH', 'Admno.' => '202300209', 'Mobile' => '8004057386', 'Address' => 'RAMEDI TARAUS BAPPA JI GALI HAMIRPUR', 'Date of Birth' => '08-08-2011', 'Father Name' => 'VIJAY KUMAR', 'Mother Name' => 'GOMTI DEVI', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_115001-01.jpeg'),
                263 => array('Serial No.' => '409', 'AppID' => '5209', 'Name' => 'ASTHA PARMAR', 'Admno.' => '202300210', 'Mobile' => '9120259753', 'Address' => 'BANGALI MAHAL HAMIRPUR', 'Date of Birth' => '26-05-2010', 'Father Name' => 'RAVI KIRAN SINGH', 'Mother Name' => 'RITU SINGH', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_115026-01.jpeg'),
                264 => array('Serial No.' => '410', 'AppID' => '5211', 'Name' => 'DEVANSH VYAS', 'Admno.' => '202300212', 'Mobile' => '9760083322', 'Address' => 'CHAURA DEVI HAMIRPUR', 'Date of Birth' => '03-07-2012', 'Father Name' => 'AMITY VYAS', 'Mother Name' => 'MAMTA RANI', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_115043-01.jpeg'),
                265 => array('Serial No.' => '411', 'AppID' => '5212', 'Name' => 'DIVYANSHI SINGH', 'Admno.' => '202300213', 'Mobile' => '9119979803', 'Address' => 'MANJHKHAR RAMEDI HAMIRPUR', 'Date of Birth' => '07-04-2012', 'Father Name' => 'RANJAN SINGH CHAUHAN', 'Mother Name' => 'RITU SINGH', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_115059-01.jpeg'),
                266 => array('Serial No.' => '412', 'AppID' => '5213', 'Name' => 'FUJAIL', 'Admno.' => '202300214', 'Mobile' => '9956531827', 'Address' => 'KHELE PURA HAMIRPUR', 'Date of Birth' => '10-10-2008', 'Father Name' => 'NAUSHAD', 'Mother Name' => 'TABASSUM', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_115125-01.jpeg'),
                267 => array('Serial No.' => '413', 'AppID' => '5214', 'Name' => 'GEET SINGH', 'Admno.' => '202300215', 'Mobile' => '7905195413', 'Address' => 'RAMEDI DANDA HAMIRPUR', 'Date of Birth' => '08-06-2009', 'Father Name' => 'MANISH SINGH', 'Mother Name' => 'ANJU VERMA', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_115156-01.jpeg'),
                268 => array('Serial No.' => '414', 'AppID' => '5215', 'Name' => 'INSHA', 'Admno.' => '202300216', 'Mobile' => '9889871307', 'Address' => 'SUBHASH BAZAR HAMIRPUR', 'Date of Birth' => '19-01-2014', 'Father Name' => 'AMJAD KHAN', 'Mother Name' => 'YASMEEN', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_115229-01.jpeg'),
                269 => array('Serial No.' => '415', 'AppID' => '5216', 'Name' => 'JANVI SAINI', 'Admno.' => '202300217', 'Mobile' => '8175011359', 'Address' => 'PAUTHIYA HAMIRPUR', 'Date of Birth' => '01-05-2013', 'Father Name' => 'SUSHEEL KUMAR', 'Mother Name' => 'MANJUSHA SAINI', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_115248-01.jpeg'),
                270 => array('Serial No.' => '416', 'AppID' => '5217', 'Name' => 'KAJAL', 'Admno.' => '202300218', 'Mobile' => '7897476825', 'Address' => 'NEAR RANI LAXMI BAI PARK HAMIRPUR', 'Date of Birth' => '15-10-2011', 'Father Name' => 'BAL KRISHNA', 'Mother Name' => 'SUNEETA', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_115307-01.jpeg'),
                271 => array('Serial No.' => '417', 'AppID' => '5218', 'Name' => 'KAZIM', 'Admno.' => '202300219', 'Mobile' => '9450273784', 'Address' => 'KHELE PURA HAMIRPUR', 'Date of Birth' => '12-12-2010', 'Father Name' => 'MOHAMMAD JAVED', 'Mother Name' => 'MUMTAJ', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_115319-01.jpeg'),
                272 => array('Serial No.' => '418', 'AppID' => '5219', 'Name' => 'MOHAMMAD FAIZAN', 'Admno.' => '202300220', 'Mobile' => '', 'Address' => 'KHALEPURA HAMIRPUR', 'Date of Birth' => '06-07-2008', 'Father Name' => 'MOHMMAD NASEEM', 'Mother Name' => 'AMANA', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_115111-01.jpeg'),
                273 => array('Serial No.' => '419', 'AppID' => '5220', 'Name' => 'NANDITA BHARTI', 'Admno.' => '202300221', 'Mobile' => '', 'Address' => 'GAUSHALA RAMEDI HAMIRPUR', 'Date of Birth' => '15-11-2011', 'Father Name' => 'NIRMAL KUMAR', 'Mother Name' => 'RENU', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_115351-01.jpeg'),
                274 => array('Serial No.' => '420', 'AppID' => '5221', 'Name' => 'NANDNI DWIVEDI', 'Admno.' => '202300222', 'Mobile' => '', 'Address' => 'ADARSH NAGAR BANGALI MAHAL HAMIRPUR', 'Date of Birth' => '13-08-2012', 'Father Name' => 'JEEVESH RANJAN DWIVEDI', 'Mother Name' => 'NIDHI DWIVEDI', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_115335-01.jpeg'),
                275 => array('Serial No.' => '421', 'AppID' => '5222', 'Name' => 'PAVANI YADAV', 'Admno.' => '202300223', 'Mobile' => '', 'Address' => 'BANGALI MAHAL HAMIRPUR', 'Date of Birth' => '25-10-2011', 'Father Name' => 'RAM BALAK YADAV', 'Mother Name' => 'UMA YADAV', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_115442-01.jpeg'),
                276 => array('Serial No.' => '422', 'AppID' => '5223', 'Name' => 'SANSKAR MISHRA', 'Admno.' => '202300224', 'Mobile' => '', 'Address' => 'DEVADAS TEMPLE HAMIRPUR', 'Date of Birth' => '14-04-2013', 'Father Name' => 'SANJAY MISHRA', 'Mother Name' => 'BANDANA MISHRA', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_115514-01.jpeg'),
                277 => array('Serial No.' => '423', 'AppID' => '5224', 'Name' => 'SHAMBHAVI PATHAK', 'Admno.' => '202300225', 'Mobile' => '', 'Address' => 'NEAR GAURA DEVI MANDIR HAMIRPUR', 'Date of Birth' => '01-01-2012', 'Father Name' => 'SANJEEV KUMAR', 'Mother Name' => 'RINA DEVI', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_115533-01.jpeg'),
                278 => array('Serial No.' => '424', 'AppID' => '5225', 'Name' => 'SHAURYA DWIVEDI', 'Admno.' => '202300226', 'Mobile' => '', 'Address' => 'MANJHKHAR RAMEDI HAMIRPUR', 'Date of Birth' => '22-07-2012', 'Father Name' => 'DEEPAK DWIVEDI', 'Mother Name' => 'KALPANA DWIVEDIO', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_115600-01.jpeg'),
                279 => array('Serial No.' => '425', 'AppID' => '5226', 'Name' => 'SHRADDHA', 'Admno.' => '202300227', 'Mobile' => '9198949486', 'Address' => 'DEVADAS TEMPLE HAMIRPUR', 'Date of Birth' => '10-05-2012', 'Father Name' => 'RAJEEV KUMAR GUPTA', 'Mother Name' => 'REKHA', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_115614-01.jpeg'),
                280 => array('Serial No.' => '426', 'AppID' => '5227', 'Name' => 'SHRADDHA SINGH', 'Admno.' => '202300228', 'Mobile' => '6392872941', 'Address' => 'MANJHKHAR RAMEDI HAMIRPUR', 'Date of Birth' => '05-11-2013', 'Father Name' => 'PRAVEEN KUMAR SINGH', 'Mother Name' => 'ARADHANA SINGH', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_115630-01.jpeg'),
                281 => array('Serial No.' => '427', 'AppID' => '5228', 'Name' => 'VANSH KUMAR', 'Admno.' => '202300229', 'Mobile' => '9651929636', 'Address' => 'NEAR JAIL TALAB RAMEDI HAMIRPUR', 'Date of Birth' => '30-10-2011', 'Father Name' => 'RAJU KUMAR', 'Mother Name' => 'ARCHANA DEVI', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_115642-01.jpeg'),
                282 => array('Serial No.' => '428', 'AppID' => '5229', 'Name' => 'VIDHI GUPTA', 'Admno.' => '202300230', 'Mobile' => '8299688513', 'Address' => 'SANT NIRANKARI BHAVAN RAMEDI TARAUS HAMIRPUR', 'Date of Birth' => '21-03-2011', 'Father Name' => 'SUNIL KUMAR GUPTA', 'Mother Name' => 'JYOTI GUPTA', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_115654-01.jpeg'),
                283 => array('Serial No.' => '429', 'AppID' => '5230', 'Name' => 'YASHI YADAV', 'Admno.' => '202300231', 'Mobile' => '9198072072', 'Address' => 'SEWA ASRAM NARAYAN NAGAR HAMIRPUR', 'Date of Birth' => '14-08-2011', 'Father Name' => 'GYAN PRAKASH YADAV', 'Mother Name' => 'VANDANA YADAV', 'Class' => 'VIII', 'Section' => 'A', 'Photo' => 'IMG_20231219_115708-01.jpeg'),
            );
            $key = $request->key;
            
            // for($i=$key;$i<=283;$i++){
            //     // $name = $e['Name'];
            //     $e = $excelRecord[$i];
            //     $username = $e['AppID'];
            //     $param = "CardNoList[0]=$username";
            //     // Delete Card Record
            //     $client = new Client();
            //     $url = 'http://192.168.177.196';
            //     $response = $client->request(
            //         'GET',
            //         $url.'/cgi-bin/AccessCard.cgi?action=removeMulti&'.$param, [
            //             'verify' => false,
            //             'auth' => ['admin', 'tipl9910', 'digest'],
            //     ]);
            // }
            // return $param;

            // echo $key;
            // die;
            // for($i=$key;$i<=283;$i++){
                // [15, ]
            $tempKey = [28, 29, 36, 41, 45, 49, 52, 53, 55, 59, 67, 70, 86, 92, 95, 99, 102, 103, 106, 109, 111, 113, 115, 116, 118, 126, 136, 141, 143, 148, 149, 151, 153, 155, 166, 169, 171, 172, 173, 175, 183, 193, 195, 196, 201, 205, 208, 215, 249, 250, 252, 257, 262, 265, 269, 270, 274, 276, 280];
            // /*
            foreach($tempKey as $t){
                // $e = $excelRecord[$i];
                $e = $excelRecord[$t];
                $imagePath = $photoDirectory . DIRECTORY_SEPARATOR . $e['Photo'];
                if (!file_exists($imagePath)) {
                    abort(404, 'Image not found');
                }
                

                $imageData = file_get_contents($imagePath);

                $base64Image = base64_encode($imageData);
                echo $e['AppID']."<br>";
                // echo $t.' - ,'.$e['AppID'].' - ,'.$e['Name'],' - ,'.$e['Photo'].' , - '.$base64Image."<br>";
                // return;
                // $client = new Client();
                // $name = $e['Name'];
                // $username = $e['AppID'];
                // $param = "&CardName=$name&CardNo=$username&UserID=$username";
                // $url = 'http://192.168.177.196';
                // $response = $client->request(
                //     'GET',
                //     $url.'/cgi-bin/recordUpdater.cgi?action=insert&name=AccessControlCard&CardStatus=0'.$param, [
                //         'verify' => false,
                //         'auth' => ['admin', 'tipl9910', 'digest'],
                // ]);
                // Check response status code
                // if ($response->getStatusCode() == 200) {
                    // $jayParsedAry = [
                    //     "UserID" => $username, 
                    //     "Info" => [
                    //         "PhotoData" => [$base64Image],
                    //     ] 
                    // ]; 
                    // $response = $client->post($url.'/cgi-bin/FaceInfoManager.cgi?action=add', [
                    //     'verify' => false,
                    //     'auth' => ['admin', 'tipl9910', 'digest'],
                    //     'headers' => [
                    //         'Content-Type' => 'application/json', // Set the content type to JSON
                    //     ],
                    //     'body' => json_encode($jayParsedAry), // JSON data as the request body
                    // ]);
                    // return $response->getBody();
                // } else {
                //     // Handle non-200 response (e.g., error handling)
                //     echo 'Request failed with status code: ' . $response->getStatusCode();
                // }
            }
            // */
            /*
            $PhotoArray = [];
            for($i=0;$i<=283;$i++){
                $e = $excelRecord[$i];
                $imagePath = $photoDirectory . DIRECTORY_SEPARATOR . $e['Photo'];
                if (!file_exists($imagePath)) {
                    abort(404, 'Image not found');
                }
                $name = $e['Name'];
                $username = $e['AppID'];
                $param = "&CardName=$name&CardNo=$username&UserID=$username";

                $imageData = file_get_contents($imagePath);

                $base64Image = base64_encode($imageData);
                $PhotoArray[] = [
                    "UserID" => $username,
                    "PhotoData" => [$base64Image],
                ]; 
            }
            
            $jayParsedAry["FaceList"]=$PhotoArray;
            // return $PhotoArray;
            $client = new Client();
            $url = 'http://192.168.177.196';

            $response = $client->post($url.'/cgi-bin/AccessFace.cgi?action=insertMulti', [
                'verify' => false,
                'auth' => ['admin', 'tipl9910', 'digest'],
                'headers' => [
                    'Content-Type' => 'application/json', // Set the content type to JSON
                ],
                'body' => json_encode($jayParsedAry), // JSON data as the request body
            ]);*/
        } catch (\Exception $e) {
            // Handle exceptions (e.g., connection error, timeout)
            echo 'Request failed: ' . $e->getMessage();
        }
    }

    public function uploadtoTimy(){
        try{
            // Establish a temporary MySQL connection
            config::set(['database.connections.mysql' => [
                'driver'    => 'mysql',
                'host'      => "localhost",
                'database'  => 'realtime',
                'username'  => 'root',
                'password'  => '',
                'charset'   => 'utf8',
                'collation' => 'utf8_unicode_ci',
                'prefix'    => '',
                'strict'    => false,
            ]]);


            $record = array(
                0 => array('AppID' => '5037', 'StudentName' => 'SUMEDH UPADHYAY', 'DoB' => '01-05-2020', 'StudentPhoto' => 'PG (10).jpeg'),
                1 => array('AppID' => '5002', 'StudentName' => 'AAYUSH RAJPUT', 'DoB' => '03-05-2019', 'StudentPhoto' => 'PG (33).jpeg'),
                2 => array('AppID' => '5004', 'StudentName' => 'ABHIJEET AGRAWAL', 'DoB' => '30-11-2019', 'StudentPhoto' => 'PG (21).jpeg'),
                3 => array('AppID' => '5007', 'StudentName' => 'ANAYA KOSTHA', 'DoB' => '05-01-2020', 'StudentPhoto' => 'PG (31).jpeg'),
                4 => array('AppID' => '5008', 'StudentName' => 'ANSH PRATAP SINGH', 'DoB' => '24-05-2020', 'StudentPhoto' => 'PG (30).jpeg'),
                5 => array('AppID' => '5012', 'StudentName' => 'DEEPANSH RAJPUT', 'DoB' => '25-02-2020', 'StudentPhoto' => 'PG (19).jpeg'),
                6 => array('AppID' => '5014', 'StudentName' => 'DEVANSHI', 'DoB' => '12-08-2019', 'StudentPhoto' => 'PG (29).jpeg'),
                7 => array('AppID' => '5016', 'StudentName' => 'HARDIK YADAV', 'DoB' => '15-07-2020', 'StudentPhoto' => 'PG (5).jpeg'),
                8 => array('AppID' => '5022', 'StudentName' => 'KARTIK RAJPUT', 'DoB' => '01-11-2018', 'StudentPhoto' => 'PG (34).jpeg'),
                9 => array('AppID' => '5024', 'StudentName' => 'MU AHIL KHAN', 'DoB' => '05-11-2018', 'StudentPhoto' => 'PG (14).jpeg'),
                10 => array('AppID' => '5026', 'StudentName' => 'NAVYA GAUTAM', 'DoB' => '19-03-2019', 'StudentPhoto' => 'PG (16).jpeg'),
                11 => array('AppID' => '5028', 'StudentName' => 'RISHABH', 'DoB' => '28-06-2019', 'StudentPhoto' => 'PG (24).jpeg'),
                12 => array('AppID' => '5034', 'StudentName' => 'SHIVANI', 'DoB' => '26-05-2019', 'StudentPhoto' => 'PG (13).jpeg'),
                13 => array('AppID' => '5036', 'StudentName' => 'SHREYANSH RAJPOOT', 'DoB' => '24-06-2019', 'StudentPhoto' => 'PG (20).jpeg'),
                14 => array('AppID' => '5001', 'StudentName' => 'AAYRA', 'DoB' => '02-02-2020', 'StudentPhoto' => 'PG (1).jpeg'),
                15 => array('AppID' => '5003', 'StudentName' => 'ABHI RAJPOOT', 'DoB' => '12-10-2019', 'StudentPhoto' => 'PG (35).jpeg'),
                16 => array('AppID' => '5005', 'StudentName' => 'AKSHAJ SAHU', 'DoB' => '15-10-2019', 'StudentPhoto' => 'PG (28).jpeg'),
                17 => array('AppID' => '5006', 'StudentName' => 'ANAYA CHAUDHARY', 'DoB' => '03-10-2019', 'StudentPhoto' => 'PG (9).jpeg'),
                18 => array('AppID' => '5009', 'StudentName' => 'ARYANS', 'DoB' => '26-08-2018', 'StudentPhoto' => 'PG (32).jpeg'),
                19 => array('AppID' => '5017', 'StudentName' => 'HARSHITA SAHU', 'DoB' => '18-11-2019', 'StudentPhoto' => 'PG (38).jpeg'),
                20 => array('AppID' => '5019', 'StudentName' => 'JANHVI', 'DoB' => '24-10-2020', 'StudentPhoto' => 'PG (4).jpeg'),
                21 => array('AppID' => '5025', 'StudentName' => 'MUDDASSIR ALI', 'DoB' => '01-11-2019', 'StudentPhoto' => 'PG (15).jpeg'),
                22 => array('AppID' => '5027', 'StudentName' => 'PARI RAJPUT', 'DoB' => '25-10-2019', 'StudentPhoto' => 'PG (26).jpeg'),
                23 => array('AppID' => '5029', 'StudentName' => 'SAKSHI SINGH', 'DoB' => '27-07-2020', 'StudentPhoto' => 'PG (27).jpeg'),
                24 => array('AppID' => '5031', 'StudentName' => 'SANGAM', 'DoB' => '13-07-2019', 'StudentPhoto' => 'PG (7).jpeg'),
                25 => array('AppID' => '5033', 'StudentName' => 'SHIRASHTI', 'DoB' => '01-12-2019', 'StudentPhoto' => 'PG (23).jpeg'),
                26 => array('AppID' => '5035', 'StudentName' => 'SHREYANSH CHAUDHARY', 'DoB' => '17-01-2021', 'StudentPhoto' => 'PG (8).jpeg'),
                27 => array('AppID' => '5039', 'StudentName' => 'VAISHNAVI', 'DoB' => '14-12-2018', 'StudentPhoto' => 'PG (25).jpeg'),
                28 => array('AppID' => '5041', 'StudentName' => 'YASHRAJ', 'DoB' => '17-03-2020', 'StudentPhoto' => 'PG (11).jpeg'),
                29 => array('AppID' => '5042', 'StudentName' => 'AHIL KHAN', 'DoB' => '27-09-2018', 'StudentPhoto' => 'LKG-A1 (4).jpeg'),
                30 => array('AppID' => '5043', 'StudentName' => 'ANANYA', 'DoB' => '03-12-2018', 'StudentPhoto' => 'LKG-A1 (20).jpeg'),
                31 => array('AppID' => '5044', 'StudentName' => 'ANGEL RAJPOOT', 'DoB' => '30-06-2019', 'StudentPhoto' => 'LKG-A1 (10).jpeg'),
                32 => array('AppID' => '5045', 'StudentName' => 'ANSH', 'DoB' => '19-09-2020', 'StudentPhoto' => 'LKG-A1 (8).jpeg'),
                33 => array('AppID' => '5046', 'StudentName' => 'ANSH PRATAP SINGH', 'DoB' => '30-04-2019', 'StudentPhoto' => 'LKG-A1 (5).jpeg'),
                34 => array('AppID' => '5047', 'StudentName' => 'ANSHIKA YADAV', 'DoB' => '11-11-2018', 'StudentPhoto' => 'LKG-A1 (6).jpeg'),
                35 => array('AppID' => '5049', 'StudentName' => 'ARTH', 'DoB' => '14-09-2018', 'StudentPhoto' => 'LKG-A1 (11).jpeg'),
                36 => array('AppID' => '5050', 'StudentName' => 'ARYAN SINGH', 'DoB' => '23-10-2019', 'StudentPhoto' => 'LKG-A1 (24).jpeg'),
                37 => array('AppID' => '5051', 'StudentName' => 'ASTHA', 'DoB' => '10-04-2018', 'StudentPhoto' => 'LKG-A1 (12).jpeg'),
                38 => array('AppID' => '5052', 'StudentName' => 'BHUMIKA', 'DoB' => '28-03-2019', 'StudentPhoto' => 'LKG-A1 (34).jpeg'),
                39 => array('AppID' => '5053', 'StudentName' => 'DEEPNA', 'DoB' => '22-10-2018', 'StudentPhoto' => 'LKG-A1 (21).jpeg'),
                40 => array('AppID' => '5054', 'StudentName' => 'DHAIRYA PRATAP SINGH', 'DoB' => '01-11-2019', 'StudentPhoto' => 'LKG-A1 (28).jpeg'),
                41 => array('AppID' => '5055', 'StudentName' => 'DHAIRYA RAJPUT', 'DoB' => '06-01-2019', 'StudentPhoto' => 'LKG-A1 (13).jpeg'),
                42 => array('AppID' => '5056', 'StudentName' => 'DHRUV SHUKLA', 'DoB' => '21-08-2018', 'StudentPhoto' => 'LKG-A1 (35).jpeg'),
                43 => array('AppID' => '5057', 'StudentName' => 'DHRUVI RAJPUT', 'DoB' => '06-01-2019', 'StudentPhoto' => 'LKG-A1 (15).jpeg'),
                44 => array('AppID' => '5058', 'StudentName' => 'DIVYANSHI RAJPUT', 'DoB' => '16-01-2019', 'StudentPhoto' => 'LKG-A1 (1).jpeg'),
                45 => array('AppID' => '5059', 'StudentName' => 'HARSH RAJPUT', 'DoB' => '30-10-2019', 'StudentPhoto' => 'LKG-A1 (2).jpeg'),
                46 => array('AppID' => '5060', 'StudentName' => 'IKRA JAHAN', 'DoB' => '27-07-2018', 'StudentPhoto' => 'LKG-A1 (38).jpeg'),
                47 => array('AppID' => '5061', 'StudentName' => 'JAGRATI SINGH RAJPOOT', 'DoB' => '13-05-2019', 'StudentPhoto' => 'LKG-A1 (33).jpeg'),
                48 => array('AppID' => '5062', 'StudentName' => 'KARTIK YADAV', 'DoB' => '02-10-2018', 'StudentPhoto' => 'LKG-A1 (30).jpeg'),
                49 => array('AppID' => '5063', 'StudentName' => 'KHUSHI', 'DoB' => '08-07-2018', 'StudentPhoto' => 'LKG-A1 (18).jpeg'),
                50 => array('AppID' => '5064', 'StudentName' => 'KRISHNA KUMAR', 'DoB' => '05-09-2018', 'StudentPhoto' => 'LKG-A1 (9).jpeg'),
                51 => array('AppID' => '5065', 'StudentName' => 'KRITYA', 'DoB' => '09-05-2019', 'StudentPhoto' => 'LKG-A1 (23).jpeg'),
                52 => array('AppID' => '5023', 'StudentName' => 'LUCKY SINGH', 'DoB' => '04-05-2020', 'StudentPhoto' => 'PG (18).jpeg'),
                53 => array('AppID' => '5067', 'StudentName' => 'MATEENA KHATOON', 'DoB' => '26-08-2018', 'StudentPhoto' => 'LKG-A1 (19).jpeg'),
                54 => array('AppID' => '5068', 'StudentName' => 'MAYANK SINGH', 'DoB' => '22-08-2019', 'StudentPhoto' => 'LKG-A1 (31).jpeg'),
                55 => array('AppID' => '5069', 'StudentName' => 'MOHAMMAD ARFEEN', 'DoB' => '15-07-2018', 'StudentPhoto' => 'LKG-A1 (36).jpeg'),
                56 => array('AppID' => '5070', 'StudentName' => 'MOHAMMAD FAIJ', 'DoB' => '17-02-2018', 'StudentPhoto' => 'LKG-A1 (25).jpeg'),
                57 => array('AppID' => '5071', 'StudentName' => 'MOHAMMAD MUZAMMIL', 'DoB' => '11-08-2019', 'StudentPhoto' => 'LKG-A1 (27).jpeg'),
                58 => array('AppID' => '5072', 'StudentName' => 'MOHAMMAD RAYAN', 'DoB' => '27-07-2019', 'StudentPhoto' => 'LKG-A1 (3).jpeg'),
                59 => array('AppID' => '5074', 'StudentName' => 'PRAJJVAL', 'DoB' => '14-08-2018', 'StudentPhoto' => 'LKG-A1 (22).jpeg'),
                60 => array('AppID' => '5075', 'StudentName' => 'PRATYAKSH YADAV', 'DoB' => '12-12-2018', 'StudentPhoto' => 'LKG-A1 (32).jpeg'),
                61 => array('AppID' => '5030', 'StudentName' => 'SAMAR RAJPUT', 'DoB' => '15-05-2020', 'StudentPhoto' => 'PG (2).jpeg'),
                62 => array('AppID' => '5077', 'StudentName' => 'SURYANSH', 'DoB' => '05-11-2017', 'StudentPhoto' => 'LKG-A1 (29).jpeg'),
                63 => array('AppID' => '5078', 'StudentName' => 'UJJWAL', 'DoB' => '18-04-2018', 'StudentPhoto' => 'LKG-A1 (7).jpeg'),
                64 => array('AppID' => '5079', 'StudentName' => 'VEER TAMRKAR', 'DoB' => '12-12-2017', 'StudentPhoto' => 'LKG-A1 (37).jpeg'),
                65 => array('AppID' => '5080', 'StudentName' => 'YASHIKA RAJPUT', 'DoB' => '24-07-2018', 'StudentPhoto' => 'LKG-A1 (17).jpeg'),
                66 => array('AppID' => '5081', 'StudentName' => 'YATHARTH RAJPUT', 'DoB' => '18-06-2019', 'StudentPhoto' => 'LKG-A1 (14).jpeg'),
                67 => array('AppID' => '5082', 'StudentName' => 'AASHVI', 'DoB' => '03-01-2020', 'StudentPhoto' => 'LKG-A2 (14).jpeg'),
                68 => array('AppID' => '5083', 'StudentName' => 'ABHIMANYU', 'DoB' => '02-12-2017', 'StudentPhoto' => 'LKG-A2 (30).jpeg'),
                69 => array('AppID' => '5084', 'StudentName' => 'ABHINAV RAJPUT', 'DoB' => '15-05-2018', 'StudentPhoto' => 'LKG-A2 (27).jpeg'),
                70 => array('AppID' => '5086', 'StudentName' => 'ADYA', 'DoB' => '07-07-2020', 'StudentPhoto' => 'LKG-A2 (11).jpeg'),
                71 => array('AppID' => '5087', 'StudentName' => 'AHIL', 'DoB' => '27-09-2017', 'StudentPhoto' => 'LKG-A2 (28).jpeg'),
                72 => array('AppID' => '5088', 'StudentName' => 'ANURAG', 'DoB' => '14-10-2019', 'StudentPhoto' => 'LKG-A2 (8).jpeg'),
                73 => array('AppID' => '5089', 'StudentName' => 'ANVIYA AHMAD', 'DoB' => '06-08-2019', 'StudentPhoto' => 'LKG-A2 (24).jpeg'),
                74 => array('AppID' => '5090', 'StudentName' => 'ARAN KHAN', 'DoB' => '28-02-2017', 'StudentPhoto' => 'LKG-A2 (34).jpeg'),
                75 => array('AppID' => '5092', 'StudentName' => 'ARMAN HASAN', 'DoB' => '16-09-2018', 'StudentPhoto' => 'LKG-A2 (10).jpeg'),
                76 => array('AppID' => '5093', 'StudentName' => 'ARYANSH', 'DoB' => '02-09-2020', 'StudentPhoto' => 'LKG-A2 (1).jpeg'),
                77 => array('AppID' => '5094', 'StudentName' => 'ATAL', 'DoB' => '08-12-2014', 'StudentPhoto' => 'LKG-A2 (33).jpeg'),
                78 => array('AppID' => '5095', 'StudentName' => 'DAKSH VARDHAN SINGH', 'DoB' => '28-08-2018', 'StudentPhoto' => 'LKG-A2 (21).jpeg'),
                79 => array('AppID' => '5096', 'StudentName' => 'DEVANSH YADAV', 'DoB' => '11-08-2018', 'StudentPhoto' => 'LKG-A2 (31).jpeg'),
                80 => array('AppID' => '5097', 'StudentName' => 'DIVYANSH SONI', 'DoB' => '29-03-2019', 'StudentPhoto' => 'LKG-A2 (5).jpeg'),
                81 => array('AppID' => '5099', 'StudentName' => 'DURANJAY SINGH YADAV', 'DoB' => '11-05-2018', 'StudentPhoto' => 'LKG-A2 (29).jpeg'),
                82 => array('AppID' => '5100', 'StudentName' => 'GAJENDRA SINGH', 'DoB' => '01-10-2017', 'StudentPhoto' => 'LKG-A2 (17).jpeg'),
                83 => array('AppID' => '5101', 'StudentName' => 'HANZLA HASAN', 'DoB' => '01-03-2019', 'StudentPhoto' => 'LKG-A2 (7).jpeg'),
                84 => array('AppID' => '5102', 'StudentName' => 'HARSH VERMA', 'DoB' => '14-07-2018', 'StudentPhoto' => 'LKG-A2 (2).jpeg'),
                85 => array('AppID' => '5103', 'StudentName' => 'INAYA FATIMA', 'DoB' => '16-03-2018', 'StudentPhoto' => 'LKG-A2 (18).jpeg'),
                86 => array('AppID' => '5020', 'StudentName' => 'JATIN', 'DoB' => '02-06-2018', 'StudentPhoto' => 'PG (12).jpeg'),
                87 => array('AppID' => '5104', 'StudentName' => 'KRATGYA PRAJAPATI', 'DoB' => '08-08-2020', 'StudentPhoto' => 'LKG-A2 (20).jpeg'),
                88 => array('AppID' => '5106', 'StudentName' => 'MOHAMMAD NAWAZ UDDIN USMANI', 'DoB' => '30-05-2018', 'StudentPhoto' => 'LKG-A2 (9).jpeg'),
                89 => array('AppID' => '5109', 'StudentName' => 'SHAN MUHAMMAD', 'DoB' => '23-01-2018', 'StudentPhoto' => 'LKG-A2 (25).jpeg'),
                90 => array('AppID' => '5110', 'StudentName' => 'SRASHATI', 'DoB' => '08-02-2019', 'StudentPhoto' => 'LKG-A2 (15).jpeg'),
                91 => array('AppID' => '5111', 'StudentName' => 'SUBH RAY', 'DoB' => '26-05-2019', 'StudentPhoto' => 'LKG-A2 (6).jpeg'),
                92 => array('AppID' => '5112', 'StudentName' => 'UMANG', 'DoB' => '15-08-2017', 'StudentPhoto' => 'LKG-A2 (26).jpeg'),
                93 => array('AppID' => '5113', 'StudentName' => 'VANI', 'DoB' => '22-04-2018', 'StudentPhoto' => 'LKG-A2 (4).jpeg'),
                94 => array('AppID' => '5114', 'StudentName' => 'VARTIKA SINGH', 'DoB' => '04-09-2019', 'StudentPhoto' => 'LKG-A2 (16).jpeg'),
                95 => array('AppID' => '5115', 'StudentName' => 'VEDANT', 'DoB' => '22-08-2018', 'StudentPhoto' => 'LKG-A2 (23).jpeg'),
                96 => array('AppID' => '5116', 'StudentName' => 'VIPUL PRATAP SINGH', 'DoB' => '20-04-2018', 'StudentPhoto' => 'LKG-A2 (12).jpeg'),
                97 => array('AppID' => '5117', 'StudentName' => 'YATHARTH JADON', 'DoB' => '21-09-2019', 'StudentPhoto' => 'LKG-A2 (3).jpeg'),
                98 => array('AppID' => '5118', 'StudentName' => 'AAYUSH AGRAWAL', 'DoB' => '10-10-2018', 'StudentPhoto' => 'UKG-A1 (2).jpeg'),
                99 => array('AppID' => '5120', 'StudentName' => 'AMAR BHATNAGAR', 'DoB' => '25-06-2017', 'StudentPhoto' => 'UKG-A1 (29).jpeg'),
                100 => array('AppID' => '5126', 'StudentName' => 'ARUSH CHAURASIYA', 'DoB' => '16-04-2018', 'StudentPhoto' => 'UKG-A1 (12).jpeg'),
                101 => array('AppID' => '5128', 'StudentName' => 'AYSHA', 'DoB' => '20-07-2017', 'StudentPhoto' => 'UKG-A1 (23).jpeg'),
                102 => array('AppID' => '5130', 'StudentName' => 'BHAVISHYA', 'DoB' => '15-05-2017', 'StudentPhoto' => 'UKG-A1 (26).jpeg'),
                103 => array('AppID' => '5132', 'StudentName' => 'DEV KHARE', 'DoB' => '11-08-2017', 'StudentPhoto' => 'UKG-A1 (40).jpeg'),
                104 => array('AppID' => '5136', 'StudentName' => 'JAYDEEP MISHRA', 'DoB' => '07-08-2019', 'StudentPhoto' => 'UKG-A1 (16).jpeg'),
                105 => array('AppID' => '5138', 'StudentName' => 'MANJIL RAJPOOT', 'DoB' => '20-12-2018', 'StudentPhoto' => 'UKG-A1 (19).jpeg'),
                106 => array('AppID' => '5140', 'StudentName' => 'MOHAMMAD ARSHIL', 'DoB' => '25-02-2018', 'StudentPhoto' => 'UKG-A1 (31).jpeg'),
                107 => array('AppID' => '5142', 'StudentName' => 'MOHAMMAD NAVAJISH HUSAIN', 'DoB' => '19-08-2017', 'StudentPhoto' => 'UKG-A1 (39).jpeg'),
                108 => array('AppID' => '5144', 'StudentName' => 'MONIT KHARE', 'DoB' => '02-07-2017', 'StudentPhoto' => 'UKG-A1 (3).jpeg'),
                109 => array('AppID' => '5146', 'StudentName' => 'NISHANT SINGH', 'DoB' => '29-08-2017', 'StudentPhoto' => 'UKG-A1 (37).jpeg'),
                110 => array('AppID' => '5148', 'StudentName' => 'PRINCE VARDHAN', 'DoB' => '23-10-2019', 'StudentPhoto' => 'UKG-A1 (7).jpeg'),
                111 => array('AppID' => '5150', 'StudentName' => 'RAJVANSH', 'DoB' => '12-10-2016', 'StudentPhoto' => 'UKG-A1 (9).jpeg'),
                112 => array('AppID' => '5152', 'StudentName' => 'SHANVI', 'DoB' => '27-05-2018', 'StudentPhoto' => 'UKG-A1 (6).jpeg'),
                113 => array('AppID' => '5154', 'StudentName' => 'TEJASH', 'DoB' => '12-07-2018', 'StudentPhoto' => 'UKG-A1 (36).jpeg'),
                114 => array('AppID' => '5156', 'StudentName' => 'VIRAT SINGH', 'DoB' => '11-01-2018', 'StudentPhoto' => 'UKG-A1 (18).jpeg'),
                115 => array('AppID' => '5158', 'StudentName' => 'YUVRAJ', 'DoB' => '12-05-2016', 'StudentPhoto' => 'UKG-A1 (34).jpeg'),
                116 => array('AppID' => '5085', 'StudentName' => 'ADARSH', 'DoB' => '01-01-2019', 'StudentPhoto' => 'LKG-A2 (32).jpeg'),
                117 => array('AppID' => '5119', 'StudentName' => 'ADRIKA', 'DoB' => '03-08-2019', 'StudentPhoto' => 'UKG-A1 (22).jpeg'),
                118 => array('AppID' => '5121', 'StudentName' => 'ANAYA YADAV', 'DoB' => '22-09-2018', 'StudentPhoto' => 'UKG-A1 (25).jpeg'),
                119 => array('AppID' => '5091', 'StudentName' => 'ARHAM KHAN', 'DoB' => '13-11-2018', 'StudentPhoto' => 'LKG-A2 (19).jpeg'),
                120 => array('AppID' => '5125', 'StudentName' => 'ARNAV RAJPUT', 'DoB' => '02-12-2017', 'StudentPhoto' => 'UKG-A1 (17).jpeg'),
                121 => array('AppID' => '5127', 'StudentName' => 'AYANSH RAJPUT', 'DoB' => '24-06-2018', 'StudentPhoto' => 'UKG-A1 (4).jpeg'),
                122 => array('AppID' => '5129', 'StudentName' => 'AYUSHI', 'DoB' => '11-05-2013', 'StudentPhoto' => 'UKG-A1 (15).jpeg'),
                123 => array('AppID' => '5131', 'StudentName' => 'DEEPAK KUMAR', 'DoB' => '15-03-2017', 'StudentPhoto' => 'UKG-A1 (33).jpeg'),
                124 => array('AppID' => '5133', 'StudentName' => 'DEVANSH', 'DoB' => '16-02-2019', 'StudentPhoto' => 'UKG-A1 (30).jpeg'),
                125 => array('AppID' => '5135', 'StudentName' => 'HIMANCHAL', 'DoB' => '20-06-2017', 'StudentPhoto' => 'UKG-A1 (35).jpeg'),
                126 => array('AppID' => '5137', 'StudentName' => 'KALYANI GUPTA', 'DoB' => '09-03-2018', 'StudentPhoto' => 'UKG-A1 (5).jpeg'),
                127 => array('AppID' => '5139', 'StudentName' => 'MANYA', 'DoB' => '11-08-2018', 'StudentPhoto' => 'UKG-A1 (14).jpeg'),
                128 => array('AppID' => '5141', 'StudentName' => 'MOHAMMAD HUSSAIN KHAN', 'DoB' => '30-06-2018', 'StudentPhoto' => 'UKG-A1 (1).jpeg'),
                129 => array('AppID' => '5143', 'StudentName' => 'MOHD SAIF KHAN', 'DoB' => '31-12-2017', 'StudentPhoto' => 'UKG-A1 (27).jpeg'),
                130 => array('AppID' => '5145', 'StudentName' => 'NIKHIL', 'DoB' => '04-02-2016', 'StudentPhoto' => 'UKG-A1 (13).jpeg'),
                131 => array('AppID' => '5147', 'StudentName' => 'PADAMJEET SINGH', 'DoB' => '27-07-2017', 'StudentPhoto' => 'UKG-A1 (10).jpeg'),
                132 => array('AppID' => '5149', 'StudentName' => 'RADHA', 'DoB' => '03-09-2017', 'StudentPhoto' => 'UKG-A1 (11).jpeg'),
                133 => array('AppID' => '5107', 'StudentName' => 'RIYA SAXENA', 'DoB' => '12-11-2018', 'StudentPhoto' => 'LKG-A2 (13).jpeg'),
                134 => array('AppID' => '5108', 'StudentName' => 'SANKSKAR SAXENA', 'DoB' => '12-11-2018', 'StudentPhoto' => 'LKG-A2 (22).jpeg'),
                135 => array('AppID' => '5153', 'StudentName' => 'SMITA SONI', 'DoB' => '04-07-2018', 'StudentPhoto' => 'UKG-A1 (8).jpeg'),
                136 => array('AppID' => '5155', 'StudentName' => 'VANSH RAJPUT', 'DoB' => '11-09-2017', 'StudentPhoto' => 'UKG-A1 (32).jpeg'),
                137 => array('AppID' => '5157', 'StudentName' => 'YUG PRATAP SINGH', 'DoB' => '15-04-2017', 'StudentPhoto' => 'UKG-A1 (28).jpeg'),
                138 => array('AppID' => '5159', 'StudentName' => 'AAROHI SINGH', 'DoB' => '09-08-2018', 'StudentPhoto' => 'UKG-A2 (13).jpeg'),
                139 => array('AppID' => '5161', 'StudentName' => 'AGAM YADAV', 'DoB' => '28-02-2017', 'StudentPhoto' => 'UKG-A2 (10).jpeg'),
                140 => array('AppID' => '5166', 'StudentName' => 'ARHAN KHAN', 'DoB' => '05-04-2017', 'StudentPhoto' => 'UKG-A2 (4).jpeg'),
                141 => array('AppID' => '5172', 'StudentName' => 'DAKSHITA KUSHWAHA', 'DoB' => '18-10-2017', 'StudentPhoto' => 'UKG-A2 (2).jpeg'),
                142 => array('AppID' => '5174', 'StudentName' => 'ERANSHI PURWAR', 'DoB' => '14-04-2018', 'StudentPhoto' => 'UKG-A2 (36).jpeg'),
                143 => array('AppID' => '5176', 'StudentName' => 'JAYESH VERMA', 'DoB' => '24-08-2018', 'StudentPhoto' => 'UKG-A2 (37).jpeg'),
                144 => array('AppID' => '5178', 'StudentName' => 'KRISHNA', 'DoB' => '03-09-2018', 'StudentPhoto' => 'UKG-A2 (34).jpeg'),
                145 => array('AppID' => '5180', 'StudentName' => 'MOHD HAMMAD', 'DoB' => '21-06-2019', 'StudentPhoto' => 'UKG-A2 (30).jpeg'),
                146 => array('AppID' => '5182', 'StudentName' => 'MUBRASEIR', 'DoB' => '01-06-2018', 'StudentPhoto' => 'UKG-A2 (29).jpeg'),
                147 => array('AppID' => '5184', 'StudentName' => 'NAMAN GUPTA', 'DoB' => '05-07-2018', 'StudentPhoto' => 'UKG-A2 (27).jpeg'),
                148 => array('AppID' => '5186', 'StudentName' => 'PRAKRATI', 'DoB' => '30-07-2017', 'StudentPhoto' => 'UKG-A2 (24).jpeg'),
                149 => array('AppID' => '5188', 'StudentName' => 'RAJ RAJPUT', 'DoB' => '16-12-2017', 'StudentPhoto' => 'UKG-A2 (23).jpeg'),
                150 => array('AppID' => '5190', 'StudentName' => 'SAMARTH SAHU', 'DoB' => '02-10-2017', 'StudentPhoto' => 'UKG-A2 (20).jpeg'),
                151 => array('AppID' => '5192', 'StudentName' => 'SHLOK', 'DoB' => '20-05-2017', 'StudentPhoto' => 'UKG-A2 (18).jpeg'),
                152 => array('AppID' => '5194', 'StudentName' => 'TWINKLE', 'DoB' => '02-09-2018', 'StudentPhoto' => 'UKG-A2 (17).jpeg'),
                153 => array('AppID' => '5196', 'StudentName' => 'VANSH', 'DoB' => '19-09-2020', 'StudentPhoto' => 'UKG-A2 (15).jpeg'),
                154 => array('AppID' => '5198', 'StudentName' => 'YASH RAJPUT', 'DoB' => '15-04-2020', 'StudentPhoto' => 'UKG-A2 (11).jpeg'),
                155 => array('AppID' => '5160', 'StudentName' => 'ADNAN KHAN', 'DoB' => '20-04-2017', 'StudentPhoto' => 'UKG-A2 (9).jpeg'),
                156 => array('AppID' => '5162', 'StudentName' => 'AKSHIT SEN', 'DoB' => '04-07-2017', 'StudentPhoto' => 'UKG-A2 (7).jpeg'),
                157 => array('AppID' => '5165', 'StudentName' => 'ANUSHKA', 'DoB' => '30-06-2018', 'StudentPhoto' => 'UKG-A2 (8).jpeg'),
                158 => array('AppID' => '5167', 'StudentName' => 'ARPITA', 'DoB' => '19-03-2019', 'StudentPhoto' => 'UKG-A2 (3).jpeg'),
                159 => array('AppID' => '5169', 'StudentName' => 'ASHI SAHU', 'DoB' => '20-03-2018', 'StudentPhoto' => 'UKG-A2 (5).jpeg'),
                160 => array('AppID' => '5171', 'StudentName' => 'AYEZA FATIMA', 'DoB' => '23-06-2018', 'StudentPhoto' => 'UKG-A2 (12).jpeg'),
                161 => array('AppID' => '5173', 'StudentName' => 'DEV RAJPUT', 'DoB' => '11-09-2016', 'StudentPhoto' => 'UKG-A2 (1).jpeg'),
                162 => array('AppID' => '5175', 'StudentName' => 'GAURAV KUMAR', 'DoB' => '07-05-2018', 'StudentPhoto' => 'UKG-A2 (35).jpeg'),
                163 => array('AppID' => '5177', 'StudentName' => 'KRISH', 'DoB' => '15-08-2018', 'StudentPhoto' => 'UKG-A2 (33).jpeg'),
                164 => array('AppID' => '5179', 'StudentName' => 'MOHAMMAD ZAID HUSAIN', 'DoB' => '08-03-2017', 'StudentPhoto' => 'UKG-A2 (32).jpeg'),
                165 => array('AppID' => '5181', 'StudentName' => 'MOHSIN AHMAD', 'DoB' => '21-07-2019', 'StudentPhoto' => 'UKG-A2 (31).jpeg'),
                166 => array('AppID' => '5183', 'StudentName' => 'MUHAMMAD ATA', 'DoB' => '22-04-2018', 'StudentPhoto' => 'UKG-A2 (28).jpeg'),
                167 => array('AppID' => '5185', 'StudentName' => 'NIDA KHATOON', 'DoB' => '03-04-2017', 'StudentPhoto' => 'UKG-A2 (25).jpeg'),
                168 => array('AppID' => '5187', 'StudentName' => 'RADHA', 'DoB' => '07-03-2018', 'StudentPhoto' => 'UKG-A2 (26).jpeg'),
                169 => array('AppID' => '5189', 'StudentName' => 'RUDRA PRATAP', 'DoB' => '31-12-2019', 'StudentPhoto' => 'UKG-A2 (22).jpeg'),
                170 => array('AppID' => '5191', 'StudentName' => 'SHAURY', 'DoB' => '12-05-2018', 'StudentPhoto' => 'UKG-A2 (21).jpeg'),
                171 => array('AppID' => '5193', 'StudentName' => 'TAIBA', 'DoB' => '27-12-2017', 'StudentPhoto' => 'UKG-A2 (19).jpeg'),
                172 => array('AppID' => '5195', 'StudentName' => 'UJJWAL', 'DoB' => '08-01-2017', 'StudentPhoto' => 'UKG-A2 (16).jpeg'),
                173 => array('AppID' => '5197', 'StudentName' => 'VEDANT GUPTA', 'DoB' => '30-09-2018', 'StudentPhoto' => 'UKG-A2 (14).jpeg'),
                174 => array('AppID' => '5199', 'StudentName' => 'ADARSH', 'DoB' => '05-03-2018', 'StudentPhoto' => '1a2 (14).jpeg'),
                175 => array('AppID' => '5200', 'StudentName' => 'AMAN SHARMA', 'DoB' => '11-10-2016', 'StudentPhoto' => '1a2 (15).jpeg'),
                176 => array('AppID' => '5201', 'StudentName' => 'AMRITA', 'DoB' => '06-01-2017', 'StudentPhoto' => '1a2 (13).jpeg'),
                177 => array('AppID' => '5202', 'StudentName' => 'ANSHU SINGH', 'DoB' => '15-03-2014', 'StudentPhoto' => '1a2 (11).jpeg'),
                178 => array('AppID' => '5203', 'StudentName' => 'ARUSH DUBEY', 'DoB' => '22-10-2017', 'StudentPhoto' => '1a2 (10).jpeg'),
                179 => array('AppID' => '5204', 'StudentName' => 'ARYAN', 'DoB' => '15-08-2017', 'StudentPhoto' => '1a2 (12).jpeg'),
                180 => array('AppID' => '5205', 'StudentName' => 'AVYA SAINI', 'DoB' => '12-04-2018', 'StudentPhoto' => '1a2 (8).jpeg'),
                181 => array('AppID' => '5207', 'StudentName' => 'HARSH', 'DoB' => '11-09-2015', 'StudentPhoto' => '1a2 (6).jpeg'),
                182 => array('AppID' => '5208', 'StudentName' => 'MANHA', 'DoB' => '08-09-2018', 'StudentPhoto' => '1a2 (7).jpeg'),
                183 => array('AppID' => '5209', 'StudentName' => 'MAYANK', 'DoB' => '20-12-2016', 'StudentPhoto' => '1a2 (4).jpeg'),
                184 => array('AppID' => '5210', 'StudentName' => 'MOHAMMAD SOHRAB', 'DoB' => '03-07-2017', 'StudentPhoto' => '1a2 (3).jpeg'),
                185 => array('AppID' => '5211', 'StudentName' => 'NANDNI', 'DoB' => '06-01-2016', 'StudentPhoto' => '1a2 (5).jpeg'),
                186 => array('AppID' => '5212', 'StudentName' => 'PRAGYANSH RAJPUT', 'DoB' => '16-08-2017', 'StudentPhoto' => '1a2 (1).jpeg'),
                187 => array('AppID' => '5213', 'StudentName' => 'PRANJAL', 'DoB' => '25-11-2016', 'StudentPhoto' => '1a2 (2).jpeg'),
                188 => array('AppID' => '5151', 'StudentName' => 'REYANSH', 'DoB' => '23-05-2016', 'StudentPhoto' => 'UKG-A1 (38).jpeg'),
                189 => array('AppID' => '5214', 'StudentName' => 'RIJA KHATOON', 'DoB' => '19-11-2014', 'StudentPhoto' => '1a2 (21).jpeg'),
                190 => array('AppID' => '5215', 'StudentName' => 'RITYANSH GOKHALE', 'DoB' => '25-11-2016', 'StudentPhoto' => '1a2 (22).jpeg'),
                191 => array('AppID' => '5216', 'StudentName' => 'SALIHA NAFIS', 'DoB' => '13-08-2017', 'StudentPhoto' => '1a2 (19).jpeg'),
                192 => array('AppID' => '5217', 'StudentName' => 'SANIDHYA', 'DoB' => '21-11-2018', 'StudentPhoto' => '1a2 (20).jpeg'),
                193 => array('AppID' => '5218', 'StudentName' => 'SHREYANSH', 'DoB' => '03-12-2017', 'StudentPhoto' => '1a2 (18).jpeg'),
                194 => array('AppID' => '5219', 'StudentName' => 'TASMIYA KHAN', 'DoB' => '02-04-2018', 'StudentPhoto' => '1a2 (16).jpeg'),
                195 => array('AppID' => '5220', 'StudentName' => 'UMANG TIWARI', 'DoB' => '19-01-2017', 'StudentPhoto' => '1a2 (17).jpeg'),
                196 => array('AppID' => '5241', 'StudentName' => 'AAROHI', 'DoB' => '02-10-2018', 'StudentPhoto' => '1a1 (7).jpeg'),
                197 => array('AppID' => '5221', 'StudentName' => 'ANAYA YADAV', 'DoB' => '25-12-2017', 'StudentPhoto' => '1a1 (6).jpeg'),
                198 => array('AppID' => '5222', 'StudentName' => 'ARNAV KUMAR GANGWAR', 'DoB' => '23-08-2018', 'StudentPhoto' => '1a1 (4).jpeg'),
                199 => array('AppID' => '5223', 'StudentName' => 'ATHAR PATHAK', 'DoB' => '19-05-2017', 'StudentPhoto' => '1a1 (2).jpeg'),
                200 => array('AppID' => '5242', 'StudentName' => 'BHAVISHY KUMAR', 'DoB' => '28-01-2017', 'StudentPhoto' => '1a1 (5).jpeg'),
                201 => array('AppID' => '5225', 'StudentName' => 'HRIDYANSH MISHRA', 'DoB' => '19-11-2016', 'StudentPhoto' => '1a1 (21).jpeg'),
                202 => array('AppID' => '5226', 'StudentName' => 'KRATIKA', 'DoB' => '26-11-2017', 'StudentPhoto' => '1a1 (3).jpeg'),
                203 => array('AppID' => '5227', 'StudentName' => 'MAYANK KUMAR', 'DoB' => '24-06-2015', 'StudentPhoto' => '1a1 (22).jpeg'),
                204 => array('AppID' => '5228', 'StudentName' => 'MEHER PREM', 'DoB' => '04-12-2017', 'StudentPhoto' => '1a1 (19).jpeg'),
                205 => array('AppID' => '5230', 'StudentName' => 'NISHCHAYA SAHU', 'DoB' => '04-07-2017', 'StudentPhoto' => '1a1 (17).jpeg'),
                206 => array('AppID' => '5231', 'StudentName' => 'PALLAVI', 'DoB' => '04-05-2016', 'StudentPhoto' => '1a1 (18).jpeg'),
                207 => array('AppID' => '5224', 'StudentName' => 'PARNIKA RAJPOOT', 'DoB' => '25-08-2017', 'StudentPhoto' => '1a1 (1).jpeg'),
                208 => array('AppID' => '5232', 'StudentName' => 'PRATYANSH', 'DoB' => '25-12-2017', 'StudentPhoto' => '1a1 (15).jpeg'),
                209 => array('AppID' => '5233', 'StudentName' => 'SAHIL', 'DoB' => '07-09-2017', 'StudentPhoto' => '1a1 (16).jpeg'),
                210 => array('AppID' => '5234', 'StudentName' => 'SAMAR MISHRA', 'DoB' => '03-10-2017', 'StudentPhoto' => '1a1 (13).jpeg'),
                211 => array('AppID' => '5235', 'StudentName' => 'SHIVANG SINGH', 'DoB' => '08-05-2017', 'StudentPhoto' => '1a1 (14).jpeg'),
                212 => array('AppID' => '5236', 'StudentName' => 'SIDDHARTH SAINI', 'DoB' => '22-04-2017', 'StudentPhoto' => '1a1 (11).jpeg'),
                213 => array('AppID' => '5238', 'StudentName' => 'TAKSH PRATAP', 'DoB' => '02-04-2018', 'StudentPhoto' => '1a1 (9).jpeg'),
                214 => array('AppID' => '5239', 'StudentName' => 'TEJAS', 'DoB' => '30-05-2018', 'StudentPhoto' => '1a1 (8).jpeg'),
                215 => array('AppID' => '5240', 'StudentName' => 'VEDANT', 'DoB' => '09-09-2016', 'StudentPhoto' => '1a1 (10).jpeg'),
                216 => array('AppID' => '5244', 'StudentName' => 'AHIL KHAN', 'DoB' => '25-06-2016', 'StudentPhoto' => '1a3 (19).jpeg'),
                217 => array('AppID' => '5263', 'StudentName' => 'AMRENDRA DEV SINGH', 'DoB' => '10-09-2017', 'StudentPhoto' => '1a3 (3).jpeg'),
                218 => array('AppID' => '5243', 'StudentName' => 'ANANYA RAJPUT', 'DoB' => '28-10-2017', 'StudentPhoto' => '1a3 (20).jpeg'),
                219 => array('AppID' => '5245', 'StudentName' => 'ARPIT SINGH', 'DoB' => '11-09-2017', 'StudentPhoto' => '1a3 (21).jpeg'),
                220 => array('AppID' => '5246', 'StudentName' => 'ARYAN PURWAR', 'DoB' => '29-07-2016', 'StudentPhoto' => '1a3 (17).jpeg'),
                221 => array('AppID' => '5247', 'StudentName' => 'AYEZA HASHMI', 'DoB' => '03-07-2016', 'StudentPhoto' => '1a3 (18).jpeg'),
                222 => array('AppID' => '5248', 'StudentName' => 'DEVANGANA PARIHAR', 'DoB' => '20-06-2017', 'StudentPhoto' => '1a3 (15).jpeg'),
                223 => array('AppID' => '5249', 'StudentName' => 'HARSHIT RAJPOOT', 'DoB' => '02-02-2017', 'StudentPhoto' => '1a3 (14).jpeg'),
                224 => array('AppID' => '5250', 'StudentName' => 'HITENDRA SINGH', 'DoB' => '19-06-2017', 'StudentPhoto' => '1a3 (16).jpeg'),
                225 => array('AppID' => '5251', 'StudentName' => 'ISHU', 'DoB' => '06-11-2017', 'StudentPhoto' => '1a3 (12).jpeg'),
                226 => array('AppID' => '5252', 'StudentName' => 'KANISHK', 'DoB' => '16-01-2016', 'StudentPhoto' => '1a3 (13).jpeg'),
                227 => array('AppID' => '5253', 'StudentName' => 'KAVYA', 'DoB' => '20-10-2016', 'StudentPhoto' => '1a3 (10).jpeg'),
                228 => array('AppID' => '5254', 'StudentName' => 'MAYANK', 'DoB' => '31-08-2016', 'StudentPhoto' => '1a3 (11).jpeg'),
                229 => array('AppID' => '5255', 'StudentName' => 'NISHESH RAJPOOT', 'DoB' => '14-07-2016', 'StudentPhoto' => '1a3 (9).jpeg'),
                230 => array('AppID' => '5256', 'StudentName' => 'PRAVEER RAJPUT', 'DoB' => '23-03-2018', 'StudentPhoto' => '1a3 (8).jpeg'),
                231 => array('AppID' => '5257', 'StudentName' => 'RAJDEEP SINGH', 'DoB' => '12-11-2015', 'StudentPhoto' => '1a3 (7).jpeg'),
                232 => array('AppID' => '5258', 'StudentName' => 'RICHA', 'DoB' => '22-09-2017', 'StudentPhoto' => '1a3 (6).jpeg'),
                233 => array('AppID' => '5259', 'StudentName' => 'RISHABH KUMAR', 'DoB' => '19-09-2016', 'StudentPhoto' => '1a3 (5).jpeg'),
                234 => array('AppID' => '5260', 'StudentName' => 'TAYBA FATIMA', 'DoB' => '27-01-2017', 'StudentPhoto' => '1a3 (4).jpeg'),
                235 => array('AppID' => '5261', 'StudentName' => 'TEJASVI', 'DoB' => '24-09-2017', 'StudentPhoto' => '1a3 (2).jpeg'),
                236 => array('AppID' => '5262', 'StudentName' => 'VED SINGH', 'DoB' => '23-06-2018', 'StudentPhoto' => '1a3 (1).jpeg'),
                237 => array('AppID' => '5284', 'StudentName' => 'ADARSH RAJPUT', 'DoB' => '02-06-2016', 'StudentPhoto' => '1a4 (1).jpeg'),
                238 => array('AppID' => '5163', 'StudentName' => 'ANSH KUMAR', 'DoB' => '13-03-2017', 'StudentPhoto' => 'UKG-A2 (6).jpeg'),
                239 => array('AppID' => '5264', 'StudentName' => 'ANSHUL YADAV', 'DoB' => '19-07-2016', 'StudentPhoto' => '1a4 (20).jpeg'),
                240 => array('AppID' => '5283', 'StudentName' => 'ARES', 'DoB' => '05-09-2018', 'StudentPhoto' => '1a4 (3).jpeg'),
                241 => array('AppID' => '5265', 'StudentName' => 'ARIKET', 'DoB' => '14-12-2016', 'StudentPhoto' => '1a4 (19).jpeg'),
                242 => array('AppID' => '5266', 'StudentName' => 'ATHRV', 'DoB' => '27-01-2017', 'StudentPhoto' => '1a4 (21).jpeg'),
                243 => array('AppID' => '5267', 'StudentName' => 'DIMPAL', 'DoB' => '13-10-2017', 'StudentPhoto' => '1a4 (17).jpeg'),
                244 => array('AppID' => '5268', 'StudentName' => 'INIYA', 'DoB' => '20-01-2015', 'StudentPhoto' => '1a4 (16).jpeg'),
                245 => array('AppID' => '5269', 'StudentName' => 'KARTIK RAJPUT', 'DoB' => '21-01-2016', 'StudentPhoto' => '1a4 (18).jpeg'),
                246 => array('AppID' => '5270', 'StudentName' => 'KASHIFA FATIMA', 'DoB' => '06-09-2017', 'StudentPhoto' => '1a4 (15).jpeg'),
                247 => array('AppID' => '5271', 'StudentName' => 'MAHI', 'DoB' => '06-12-2017', 'StudentPhoto' => '1a4 (14).jpeg'),
                248 => array('AppID' => '5272', 'StudentName' => 'MAZHAR ALI', 'DoB' => '04-02-2017', 'StudentPhoto' => '1a4 (12).jpeg'),
                249 => array('AppID' => '5273', 'StudentName' => 'MOHAMMAD ANAS', 'DoB' => '08-12-2016', 'StudentPhoto' => '1a4 (13).jpeg'),
                250 => array('AppID' => '5274', 'StudentName' => 'PARTH', 'DoB' => '17-01-2017', 'StudentPhoto' => '1a4 (11).jpeg'),
                251 => array('AppID' => '5275', 'StudentName' => 'PRANJUL', 'DoB' => '02-07-2016', 'StudentPhoto' => '1a4 (9).jpeg'),
                252 => array('AppID' => '5276', 'StudentName' => 'RISHI PRAJAPATI', 'DoB' => '14-07-2018', 'StudentPhoto' => '1a4 (10).jpeg'),
                253 => array('AppID' => '5277', 'StudentName' => 'SHASHANK', 'DoB' => '06-03-2018', 'StudentPhoto' => '1a4 (7).jpeg'),
                254 => array('AppID' => '5278', 'StudentName' => 'SHREYANSH SINGH RAJPOOT', 'DoB' => '19-03-2019', 'StudentPhoto' => '1a4 (8).jpeg'),
                255 => array('AppID' => '5279', 'StudentName' => 'SOM PRATAP SINGH', 'DoB' => '31-07-2018', 'StudentPhoto' => '1a4 (6).jpeg'),
                256 => array('AppID' => '5280', 'StudentName' => 'SURYANSH', 'DoB' => '29-08-2017', 'StudentPhoto' => '1a4 (4).jpeg'),
                257 => array('AppID' => '5281', 'StudentName' => 'SWARNIMA PASTOR', 'DoB' => '19-07-2017', 'StudentPhoto' => '1a4 (5).jpeg'),
                258 => array('AppID' => '5282', 'StudentName' => 'TANYA', 'DoB' => '08-05-2017', 'StudentPhoto' => '1a4 (2).jpeg'),
                259 => array('AppID' => '5285', 'StudentName' => 'AADVIK AGRAWAL', 'DoB' => '14-01-2017', 'StudentPhoto' => '2a1 (29).jpeg'),
                260 => array('AppID' => '5286', 'StudentName' => 'AAFIYA FATIMA', 'DoB' => '03-09-2016', 'StudentPhoto' => '2a1 (30).jpeg'),
                261 => array('AppID' => '5287', 'StudentName' => 'ABHAY', 'DoB' => '21-06-2017', 'StudentPhoto' => '2a1 (27).jpeg'),
                262 => array('AppID' => '5288', 'StudentName' => 'ANAS KHAN', 'DoB' => '21-09-2014', 'StudentPhoto' => '2a1 (26).jpeg'),
                263 => array('AppID' => '5301', 'StudentName' => 'ANAYA', 'DoB' => '30-12-2016', 'StudentPhoto' => '2a1 (15).jpeg'),
                264 => array('AppID' => '5289', 'StudentName' => 'ANIKET', 'DoB' => '30-01-2016', 'StudentPhoto' => '2a1 (28).jpeg'),
                265 => array('AppID' => '5290', 'StudentName' => 'ARADHYA', 'DoB' => '01-02-2016', 'StudentPhoto' => '2a1 (24).jpeg'),
                266 => array('AppID' => '5291', 'StudentName' => 'ARYASH', 'DoB' => '01-01-2015', 'StudentPhoto' => '2a1 (23).jpeg'),
                267 => array('AppID' => '5292', 'StudentName' => 'AYUSH KUMAR', 'DoB' => '05-08-2016', 'StudentPhoto' => '2a1 (25).jpeg'),
                268 => array('AppID' => '5293', 'StudentName' => 'DHARMVEER', 'DoB' => '26-05-2015', 'StudentPhoto' => '2a1 (21).jpeg'),
                269 => array('AppID' => '5294', 'StudentName' => 'GAURI RAJPOOT', 'DoB' => '19-11-2017', 'StudentPhoto' => '2a1 (22).jpeg'),
                270 => array('AppID' => '5295', 'StudentName' => 'HAMEEDA KHAN', 'DoB' => '16-08-2014', 'StudentPhoto' => '2a1 (19).jpeg'),
                271 => array('AppID' => '5296', 'StudentName' => 'IMMANUEL ABRAHAM', 'DoB' => '31-01-2016', 'StudentPhoto' => '2a1 (18).jpeg'),
                272 => array('AppID' => '5297', 'StudentName' => 'KASHISH RAJPOOT', 'DoB' => '11-06-2016', 'StudentPhoto' => '2a1 (20).jpeg'),
                273 => array('AppID' => '5298', 'StudentName' => 'KAUTILYA RAJPUT', 'DoB' => '28-11-2016', 'StudentPhoto' => '2a1 (16).jpeg'),
                274 => array('AppID' => '5299', 'StudentName' => 'LAKSHY RAJPOOT', 'DoB' => '17-05-2017', 'StudentPhoto' => '2a1 (17).jpeg'),
                275 => array('AppID' => '5300', 'StudentName' => 'LAVI YADAV', 'DoB' => '05-06-2017', 'StudentPhoto' => '2a1 (14).jpeg'),
                276 => array('AppID' => '5314', 'StudentName' => 'MADHAVI MAHI', 'DoB' => '31-05-2017', 'StudentPhoto' => '2a1 (2).jpeg'),
                277 => array('AppID' => '5302', 'StudentName' => 'NAMRATA RAJPOOT', 'DoB' => '27-10-2014', 'StudentPhoto' => '2a1 (12).jpeg'),
                278 => array('AppID' => '5303', 'StudentName' => 'PRATYAKSHA KUMAR', 'DoB' => '27-08-2015', 'StudentPhoto' => '2a1 (13).jpeg'),
                279 => array('AppID' => '5304', 'StudentName' => 'PRERANA ARYA', 'DoB' => '02-12-2017', 'StudentPhoto' => '2a1 (10).jpeg'),
                280 => array('AppID' => '5305', 'StudentName' => 'RAM JI SONI', 'DoB' => '28-01-2015', 'StudentPhoto' => '2a1 (9).jpeg'),
                281 => array('AppID' => '5306', 'StudentName' => 'RASHI', 'DoB' => '13-07-2016', 'StudentPhoto' => '2a1 (11).jpeg'),
                282 => array('AppID' => '5307', 'StudentName' => 'RISHABH', 'DoB' => '06-01-2015', 'StudentPhoto' => '2a1 (8).jpeg'),
                283 => array('AppID' => '5308', 'StudentName' => 'RUDRA PRATAP', 'DoB' => '07-12-2015', 'StudentPhoto' => '2a1 (7).jpeg'),
                284 => array('AppID' => '5309', 'StudentName' => 'SANSKAR SINGH', 'DoB' => '26-01-2016', 'StudentPhoto' => '2a1 (5).jpeg'),
                285 => array('AppID' => '5310', 'StudentName' => 'SHREYASH PANDEY', 'DoB' => '15-09-2017', 'StudentPhoto' => '2a1 (6).jpeg'),
                286 => array('AppID' => '5311', 'StudentName' => 'SIDDHIKSHA RAJPUT', 'DoB' => '26-02-2017', 'StudentPhoto' => '2a1 (4).jpeg'),
                287 => array('AppID' => '5312', 'StudentName' => 'VEDANSH SONI', 'DoB' => '20-01-2017', 'StudentPhoto' => '2a1 (3).jpeg'),
                288 => array('AppID' => '5313', 'StudentName' => 'YASH RAJ RAJPOOT', 'DoB' => '14-12-2017', 'StudentPhoto' => '2a1 (1).jpeg'),
                289 => array('AppID' => '5315', 'StudentName' => 'AKSHAT YADAV', 'DoB' => '01-07-2017', 'StudentPhoto' => '2a2 (10).jpeg'),
                290 => array('AppID' => '5316', 'StudentName' => 'ANKUR (ABHAY)', 'DoB' => '30-10-2014', 'StudentPhoto' => '2a2 (7).jpeg'),
                291 => array('AppID' => '5317', 'StudentName' => 'ARADHYA SHRIVASTAV', 'DoB' => '17-11-2016', 'StudentPhoto' => '2a2 (8).jpeg'),
                292 => array('AppID' => '5318', 'StudentName' => 'ARPAN SONI', 'DoB' => '13-05-2016', 'StudentPhoto' => '2a2 (5).jpeg'),
                293 => array('AppID' => '5319', 'StudentName' => 'ARYAN', 'DoB' => '01-05-2017', 'StudentPhoto' => '2a2 (4).jpeg'),
                294 => array('AppID' => '5320', 'StudentName' => 'ASEEM AHMAD KHAN', 'DoB' => '14-07-2017', 'StudentPhoto' => '2a2 (6).jpeg'),
                295 => array('AppID' => '5321', 'StudentName' => 'AYAT', 'DoB' => '04-09-2014', 'StudentPhoto' => '2a2 (3).jpeg'),
                296 => array('AppID' => '5322', 'StudentName' => 'AZAN', 'DoB' => '15-09-2016', 'StudentPhoto' => '2a2 (2).jpeg'),
                297 => array('AppID' => '5323', 'StudentName' => 'BHARGAV SINGH', 'DoB' => '18-08-2017', 'StudentPhoto' => '2a2 (1).jpeg'),
                298 => array('AppID' => '5324', 'StudentName' => 'DAKSH RAJPUT', 'DoB' => '20-08-2016', 'StudentPhoto' => '2a2 (30).jpeg'),
                299 => array('AppID' => '5325', 'StudentName' => 'HARSH KUMARI', 'DoB' => '14-05-2016', 'StudentPhoto' => '2a2 (28).jpeg'),
                300 => array('AppID' => '5326', 'StudentName' => 'HARSH RAJ', 'DoB' => '23-05-2017', 'StudentPhoto' => '2a2 (29).jpeg'),
                301 => array('AppID' => '5327', 'StudentName' => 'JAGRATI', 'DoB' => '05-09-2016', 'StudentPhoto' => '2a2 (26).jpeg'),
                302 => array('AppID' => '5328', 'StudentName' => 'KARTIK', 'DoB' => '11-11-2015', 'StudentPhoto' => '2a2 (27).jpeg'),
                303 => array('AppID' => '5329', 'StudentName' => 'KAVYA', 'DoB' => '04-07-2016', 'StudentPhoto' => '2a2 (24).jpeg'),
                304 => array('AppID' => '5330', 'StudentName' => 'MAHAK GUPTA', 'DoB' => '26-12-2014', 'StudentPhoto' => '2a2 (25).jpeg'),
                305 => array('AppID' => '5331', 'StudentName' => 'MAYANK', 'DoB' => '25-07-2016', 'StudentPhoto' => '2a2 (22).jpeg'),
                306 => array('AppID' => '5332', 'StudentName' => 'MOHAMMAD ALKAIF', 'DoB' => '26-08-2015', 'StudentPhoto' => '2a2 (23).jpeg'),
                307 => array('AppID' => '5333', 'StudentName' => 'NAMAN RAJPOOT', 'DoB' => '28-07-2015', 'StudentPhoto' => '2a2 (21).jpeg'),
                308 => array('AppID' => '5334', 'StudentName' => 'NIYAN', 'DoB' => '12-12-2014', 'StudentPhoto' => '2a2 (20).jpeg'),
                309 => array('AppID' => '5335', 'StudentName' => 'PRATHAM', 'DoB' => '01-10-2017', 'StudentPhoto' => '2a2 (18).jpeg'),
                310 => array('AppID' => '5336', 'StudentName' => 'RAJAT RAJPOOT', 'DoB' => '31-10-2017', 'StudentPhoto' => '2a2 (19).jpeg'),
                311 => array('AppID' => '5337', 'StudentName' => 'RAJDEEP', 'DoB' => '25-10-2016', 'StudentPhoto' => '2a2 (16).jpeg'),
                312 => array('AppID' => '5338', 'StudentName' => 'RAMYA', 'DoB' => '01-09-2015', 'StudentPhoto' => '2a2 (17).jpeg'),
                313 => array('AppID' => '5339', 'StudentName' => 'RISHABH', 'DoB' => '15-10-2015', 'StudentPhoto' => '2a2 (14).jpeg'),
                314 => array('AppID' => '5340', 'StudentName' => 'RUZAINA SIDDIQUI', 'DoB' => '11-02-2018', 'StudentPhoto' => '2a2 (13).jpeg'),
                315 => array('AppID' => '5341', 'StudentName' => 'SAURABH RAJPOOT', 'DoB' => '18-09-2013', 'StudentPhoto' => '2a2 (15).jpeg'),
                316 => array('AppID' => '5344', 'StudentName' => 'YUVRAJ SINGH', 'DoB' => '02-03-2016', 'StudentPhoto' => '2a2 (9).jpeg'),
                317 => array('AppID' => '5345', 'StudentName' => 'AADYA SINGH', 'DoB' => '01-12-2014', 'StudentPhoto' => '2a3 (29).jpeg'),
                318 => array('AppID' => '5346', 'StudentName' => 'AARADHYA TIWARI', 'DoB' => '29-10-2016', 'StudentPhoto' => '2a3 (27).jpeg'),
                319 => array('AppID' => '5347', 'StudentName' => 'ABHI RAJPUT', 'DoB' => '19-07-2016', 'StudentPhoto' => '2a3 (26).jpeg'),
                320 => array('AppID' => '5348', 'StudentName' => 'ANANYA SINGH', 'DoB' => '24-06-2017', 'StudentPhoto' => '2a3 (25).jpeg'),
                321 => array('AppID' => '5349', 'StudentName' => 'ANSH', 'DoB' => '12-12-2015', 'StudentPhoto' => '2a3 (28).jpeg'),
                322 => array('AppID' => '5350', 'StudentName' => 'ANURUDDH YADAV', 'DoB' => '21-08-2016', 'StudentPhoto' => '2a3 (24).jpeg'),
                323 => array('AppID' => '5351', 'StudentName' => 'AROHI RAJPUT', 'DoB' => '01-07-2015', 'StudentPhoto' => '2a3 (23).jpeg'),
                324 => array('AppID' => '5352', 'StudentName' => 'ARSYAN ALI', 'DoB' => '01-10-2016', 'StudentPhoto' => '2a3 (22).jpeg'),
                325 => array('AppID' => '5353', 'StudentName' => 'ARUSHI SHRIVAS', 'DoB' => '03-09-2015', 'StudentPhoto' => '2a3 (20).jpeg'),
                326 => array('AppID' => '5354', 'StudentName' => 'DAKSH', 'DoB' => '05-12-2016', 'StudentPhoto' => '2a3 (21).jpeg'),
                327 => array('AppID' => '5355', 'StudentName' => 'DEEPANSHU', 'DoB' => '07-04-2016', 'StudentPhoto' => '2a3 (19).jpeg'),
                328 => array('AppID' => '5356', 'StudentName' => 'GARIMA RAJPUT', 'DoB' => '26-12-2014', 'StudentPhoto' => '2a3 (18).jpeg'),
                329 => array('AppID' => '5357', 'StudentName' => 'HRITIK', 'DoB' => '30-06-2016', 'StudentPhoto' => '2a3 (16).jpeg'),
                330 => array('AppID' => '5358', 'StudentName' => 'IMRA FATMA', 'DoB' => '16-04-2016', 'StudentPhoto' => '2a3 (17).jpeg'),
                331 => array('AppID' => '5359', 'StudentName' => 'KARTIK SINGH', 'DoB' => '02-12-2015', 'StudentPhoto' => '2a3 (14).jpeg'),
                332 => array('AppID' => '5360', 'StudentName' => 'KAYANAT KHAN', 'DoB' => '14-11-2016', 'StudentPhoto' => '2a3 (15).jpeg'),
                333 => array('AppID' => '5362', 'StudentName' => 'MAYANK SINGH', 'DoB' => '22-04-2014', 'StudentPhoto' => '2a3 (12).jpeg'),
                334 => array('AppID' => '5363', 'StudentName' => 'MISTI', 'DoB' => '22-02-2016', 'StudentPhoto' => '2a3 (13).jpeg'),
                335 => array('AppID' => '5364', 'StudentName' => 'MOHD AHAD KHAN', 'DoB' => '25-12-2017', 'StudentPhoto' => '2a3 (11).jpeg'),
                336 => array('AppID' => '5365', 'StudentName' => 'MUHAMMAD UBAID KHAN', 'DoB' => '06-04-2015', 'StudentPhoto' => '2a3 (9).jpeg'),
                337 => array('AppID' => '5366', 'StudentName' => 'RAMAN RAJPUT', 'DoB' => '01-07-2017', 'StudentPhoto' => '2a3 (10).jpeg'),
                338 => array('AppID' => '5367', 'StudentName' => 'RISAB SINGH', 'DoB' => '01-08-2015', 'StudentPhoto' => '2a3 (8).jpeg'),
                339 => array('AppID' => '5368', 'StudentName' => 'RUCHI SHRIVAS', 'DoB' => '03-09-2015', 'StudentPhoto' => '2a3 (7).jpeg'),
                340 => array('AppID' => '5369', 'StudentName' => 'RUDRAPRATAP', 'DoB' => '01-11-2016', 'StudentPhoto' => '2a3 (5).jpeg'),
                341 => array('AppID' => '5370', 'StudentName' => 'SHIVANG', 'DoB' => '19-04-2015', 'StudentPhoto' => '2a3 (6).jpeg'),
                342 => array('AppID' => '5371', 'StudentName' => 'SHREYA', 'DoB' => '22-01-2015', 'StudentPhoto' => '2a3 (4).jpeg'),
                343 => array('AppID' => '5372', 'StudentName' => 'SHUBH', 'DoB' => '31-07-2015', 'StudentPhoto' => '2a3 (2).jpeg'),
                344 => array('AppID' => '5373', 'StudentName' => 'SRISHTI GAUTAM', 'DoB' => '06-06-2017', 'StudentPhoto' => '2a3 (3).jpeg'),
                345 => array('AppID' => '5374', 'StudentName' => 'VAISHNAVI GUPTA', 'DoB' => '15-10-2016', 'StudentPhoto' => '2a3 (1).jpeg'),
                346 => array('AppID' => '5376', 'StudentName' => 'AARADHYA SINGH', 'DoB' => '03-04-2015', 'StudentPhoto' => '2a4 (30).jpeg'),
                347 => array('AppID' => '5377', 'StudentName' => 'AFFAN', 'DoB' => '14-02-2015', 'StudentPhoto' => '2a4 (28).jpeg'),
                348 => array('AppID' => '5378', 'StudentName' => 'ALSHIFA FATIMA', 'DoB' => '18-07-2014', 'StudentPhoto' => '2a4 (27).jpeg'),
                349 => array('AppID' => '5379', 'StudentName' => 'ANSH PRATAP SINGH', 'DoB' => '13-02-2016', 'StudentPhoto' => '2a4 (29).jpeg'),
                350 => array('AppID' => '5380', 'StudentName' => 'ANVESHA TAMRAKAR', 'DoB' => '20-08-2016', 'StudentPhoto' => '2a4 (25).jpeg'),
                351 => array('AppID' => '5381', 'StudentName' => 'ARADHYA', 'DoB' => '07-07-2015', 'StudentPhoto' => '2a4 (24).jpeg'),
                352 => array('AppID' => '5382', 'StudentName' => 'BUSRA', 'DoB' => '10-07-2015', 'StudentPhoto' => '2a4 (26).jpeg'),
                353 => array('AppID' => '5383', 'StudentName' => 'DAKSH RAJPOOT', 'DoB' => '05-11-2016', 'StudentPhoto' => '2a4 (23).jpeg'),
                354 => array('AppID' => '5384', 'StudentName' => 'HARSHVARDHAN', 'DoB' => '07-01-2014', 'StudentPhoto' => '2a4 (22).jpeg'),
                355 => array('AppID' => '5385', 'StudentName' => 'ISHIKA', 'DoB' => '17-01-2016', 'StudentPhoto' => '2a4 (20).jpeg'),
                356 => array('AppID' => '5386', 'StudentName' => 'JANHAVI SINGH', 'DoB' => '12-08-2016', 'StudentPhoto' => '2a4 (21).jpeg'),
                357 => array('AppID' => '5387', 'StudentName' => 'KARTIK', 'DoB' => '17-12-2015', 'StudentPhoto' => '2a4 (19).jpeg'),
                358 => array('AppID' => '5388', 'StudentName' => 'KAVY', 'DoB' => '04-05-2014', 'StudentPhoto' => '2a4 (17).jpeg'),
                359 => array('AppID' => '5389', 'StudentName' => 'MARUTI NANDAN', 'DoB' => '29-09-2016', 'StudentPhoto' => '2a4 (18).jpeg'),
                360 => array('AppID' => '5390', 'StudentName' => 'MAYANK', 'DoB' => '22-12-2013', 'StudentPhoto' => '2a4 (15).jpeg'),
                361 => array('AppID' => '5391', 'StudentName' => 'MOHAMMAD ALI', 'DoB' => '08-11-2014', 'StudentPhoto' => '2a4 (16).jpeg'),
                362 => array('AppID' => '5392', 'StudentName' => 'MRITUNJAY RAJPOOT', 'DoB' => '21-10-2017', 'StudentPhoto' => '2a4 (2).jpeg'),
                363 => array('AppID' => '5393', 'StudentName' => 'PRANAV KUMAR', 'DoB' => '12-07-2016', 'StudentPhoto' => '2a4 (13).jpeg'),
                364 => array('AppID' => '5394', 'StudentName' => 'RAJVEER SINGH', 'DoB' => '13-05-2017', 'StudentPhoto' => '2a4 (14).jpeg'),
                365 => array('AppID' => '5395', 'StudentName' => 'RAMJI', 'DoB' => '05-09-2016', 'StudentPhoto' => '2a4 (11).jpeg'),
                366 => array('AppID' => '5396', 'StudentName' => 'RISABH', 'DoB' => '02-03-2015', 'StudentPhoto' => '2a4 (12).jpeg'),
                367 => array('AppID' => '5397', 'StudentName' => 'SHIVANSH', 'DoB' => '07-03-2016', 'StudentPhoto' => '2a4 (10).jpeg'),
                368 => array('AppID' => '5398', 'StudentName' => 'SHYAM JI SUROTHIYA', 'DoB' => '06-08-2015', 'StudentPhoto' => '2a4 (8).jpeg'),
                369 => array('AppID' => '5399', 'StudentName' => 'SONIYA', 'DoB' => '11-06-2016', 'StudentPhoto' => '2a4 (7).jpeg'),
                370 => array('AppID' => '5400', 'StudentName' => 'UNNATI VERMA', 'DoB' => '19-08-2015', 'StudentPhoto' => '2a4 (9).jpeg'),
                371 => array('AppID' => '5401', 'StudentName' => 'VED', 'DoB' => '04-08-2015', 'StudentPhoto' => '2a4 (6).jpeg'),
                372 => array('AppID' => '5402', 'StudentName' => 'YAGYA PRASAD', 'DoB' => '09-12-2014', 'StudentPhoto' => '2a4 (5).jpeg'),
                373 => array('AppID' => '5403', 'StudentName' => 'YASH', 'DoB' => '17-12-2015', 'StudentPhoto' => '2a4 (3).jpeg'),
                374 => array('AppID' => '5404', 'StudentName' => 'ZAINAB FATIMA', 'DoB' => '19-08-2015', 'StudentPhoto' => '2a4 (4).jpeg'),
                375 => array('AppID' => '5405', 'StudentName' => 'AAYSHA HAYAT', 'DoB' => '02-04-2015', 'StudentPhoto' => '3a1 (27).jpeg'),
                376 => array('AppID' => '5406', 'StudentName' => 'ABHAY', 'DoB' => '22-09-2013', 'StudentPhoto' => '3a1 (26).jpeg'),
                377 => array('AppID' => '5407', 'StudentName' => 'ABHAY PRATAP SINGH', 'DoB' => '11-09-2014', 'StudentPhoto' => '3a1 (28).jpeg'),
                378 => array('AppID' => '5408', 'StudentName' => 'ABIDA KHATOON', 'DoB' => '01-04-2014', 'StudentPhoto' => '3a1 (24).jpeg'),
                379 => array('AppID' => '5409', 'StudentName' => 'ANKUSH YADAV', 'DoB' => '10-09-2013', 'StudentPhoto' => '3a1 (23).jpeg'),
                380 => array('AppID' => '5410', 'StudentName' => 'ANSH', 'DoB' => '05-10-2015', 'StudentPhoto' => '3a1 (25).jpeg'),
                381 => array('AppID' => '5411', 'StudentName' => 'ARADHYA KASHYAP', 'DoB' => '13-11-2014', 'StudentPhoto' => '3a1 (21).jpeg'),
                382 => array('AppID' => '5412', 'StudentName' => 'ARHAN AHMAD', 'DoB' => '24-06-2015', 'StudentPhoto' => '3a1 (22).jpeg'),
                383 => array('AppID' => '5413', 'StudentName' => 'AVNI', 'DoB' => '13-07-2015', 'StudentPhoto' => '3a1 (19).jpeg'),
                384 => array('AppID' => '5414', 'StudentName' => 'AYUSH RAJPOOT', 'DoB' => '25-01-2015', 'StudentPhoto' => '3a1 (18).jpeg'),
                385 => array('AppID' => '5415', 'StudentName' => 'DEEPENDRA', 'DoB' => '10-04-2012', 'StudentPhoto' => '3a1 (20).jpeg'),
                386 => array('AppID' => '5416', 'StudentName' => 'DEVANK RAJPUT', 'DoB' => '16-11-2015', 'StudentPhoto' => '3a1 (17).jpeg'),
                387 => array('AppID' => '5417', 'StudentName' => 'HARDIK', 'DoB' => '02-06-2014', 'StudentPhoto' => '3a1 (16).jpeg'),
                388 => array('AppID' => '5418', 'StudentName' => 'JAHNAVI SINGH', 'DoB' => '06-09-2015', 'StudentPhoto' => '3a1 (15).jpeg'),
                389 => array('AppID' => '5419', 'StudentName' => 'KAPIL KUMAR', 'DoB' => '22-01-2013', 'StudentPhoto' => '3a1 (14).jpeg'),
                390 => array('AppID' => '5420', 'StudentName' => 'LAKSHYA RAJPUT', 'DoB' => '22-01-2015', 'StudentPhoto' => '3a1 (12).jpeg'),
                391 => array('AppID' => '5421', 'StudentName' => 'MANYA SAHU', 'DoB' => '06-12-2014', 'StudentPhoto' => '3a1 (11).jpeg'),
                392 => array('AppID' => '5422', 'StudentName' => 'MUHAMMAD ARSH', 'DoB' => '13-03-2014', 'StudentPhoto' => '3a1 (13).jpeg'),
                393 => array('AppID' => '5431', 'StudentName' => 'MUHAMMAD UMM KHAN', 'DoB' => '12-03-2014', 'StudentPhoto' => '3a1 (1).jpeg'),
                394 => array('AppID' => '5423', 'StudentName' => 'PRAJJAWALA TIWARI', 'DoB' => '', 'StudentPhoto' => '3a1 (9).jpeg'),
                395 => array('AppID' => '5424', 'StudentName' => 'RISHAB RAJPUT', 'DoB' => '01-05-2015', 'StudentPhoto' => '3a1 (8).jpeg'),
                396 => array('AppID' => '5425', 'StudentName' => 'RIYA', 'DoB' => '20-05-2014', 'StudentPhoto' => '3a1 (10).jpeg'),
                397 => array('AppID' => '5426', 'StudentName' => 'SHREYA GUPTA', 'DoB' => '22-08-2015', 'StudentPhoto' => '3a1 (7).jpeg'),
                398 => array('AppID' => '5427', 'StudentName' => 'TANISHKA GUPTA', 'DoB' => '03-04-2015', 'StudentPhoto' => '3a1 (5).jpeg'),
                399 => array('AppID' => '5428', 'StudentName' => 'VAIBHAV PRATAP SINGH', 'DoB' => '25-02-2015', 'StudentPhoto' => '3a1 (6).jpeg'),
                400 => array('AppID' => '5429', 'StudentName' => 'YASHVARDHAN RAJPUT', 'DoB' => '23-11-2016', 'StudentPhoto' => '3a1 (4).jpeg'),
                401 => array('AppID' => '5430', 'StudentName' => 'YATHARTH RAJPUT', 'DoB' => '20-04-2014', 'StudentPhoto' => '3a1 (2).jpeg'),
                402 => array('AppID' => '5433', 'StudentName' => 'AKSHANSH', 'DoB' => '10-09-2015', 'StudentPhoto' => '3a2 (28).jpeg'),
                403 => array('AppID' => '5434', 'StudentName' => 'ANIKET', 'DoB' => '10-07-2014', 'StudentPhoto' => '3a2 (29).jpeg'),
                404 => array('AppID' => '5435', 'StudentName' => 'ANOKHI PURWAR', 'DoB' => '02-02-2015', 'StudentPhoto' => '3a2 (26).jpeg'),
                405 => array('AppID' => '5436', 'StudentName' => 'ARUSHI', 'DoB' => '19-06-2013', 'StudentPhoto' => '3a2 (25).jpeg'),
                406 => array('AppID' => '5437', 'StudentName' => 'ARYAN', 'DoB' => '26-02-2016', 'StudentPhoto' => '3a2 (27).jpeg'),
                407 => array('AppID' => '5438', 'StudentName' => 'AYUSHI RAJPUT', 'DoB' => '08-05-2015', 'StudentPhoto' => '3a2 (23).jpeg'),
                408 => array('AppID' => '5439', 'StudentName' => 'BHAWNA YADAV', 'DoB' => '02-07-2015', 'StudentPhoto' => '3a2 (22).jpeg'),
                409 => array('AppID' => '5440', 'StudentName' => 'DEEPANSH RAJPUT', 'DoB' => '25-08-2014', 'StudentPhoto' => '3a2 (24).jpeg'),
                410 => array('AppID' => '5441', 'StudentName' => 'DEV PRATAP SINGH', 'DoB' => '04-03-2014', 'StudentPhoto' => '3a2 (21).jpeg'),
                411 => array('AppID' => '5442', 'StudentName' => 'HARSH RAJPOOT', 'DoB' => '03-03-2015', 'StudentPhoto' => '3a2 (19).jpeg'),
                412 => array('AppID' => '5443', 'StudentName' => 'IKCHA', 'DoB' => '11-08-2014', 'StudentPhoto' => '3a2 (20).jpeg'),
                413 => array('AppID' => '5444', 'StudentName' => 'IKHLAS ALI', 'DoB' => '15-12-2014', 'StudentPhoto' => '3a2 (17).jpeg'),
                414 => array('AppID' => '5445', 'StudentName' => 'ISHANI', 'DoB' => '29-01-2015', 'StudentPhoto' => '3a2 (18).jpeg'),
                415 => array('AppID' => '5446', 'StudentName' => 'KRISHNA SINGH PARIHAR', 'DoB' => '09-06-2014', 'StudentPhoto' => '3a2 (16).jpeg'),
                416 => array('AppID' => '5447', 'StudentName' => 'MO ABDULLA KHAN', 'DoB' => '28-04-2014', 'StudentPhoto' => '3a2 (14).jpeg'),
                417 => array('AppID' => '5448', 'StudentName' => 'PARI', 'DoB' => '04-12-2013', 'StudentPhoto' => '3a2 (15).jpeg'),
                418 => array('AppID' => '5460', 'StudentName' => 'PARTH', 'DoB' => '21-10-2014', 'StudentPhoto' => '3a2 (1).jpeg'),
                419 => array('AppID' => '5449', 'StudentName' => 'PIHU GUPTA', 'DoB' => '06-09-2015', 'StudentPhoto' => '3a2 (13).jpeg'),
                420 => array('AppID' => '5450', 'StudentName' => 'PRAGYA', 'DoB' => '30-05-2014', 'StudentPhoto' => '3a2 (11).jpeg'),
                421 => array('AppID' => '5451', 'StudentName' => 'RAM SHASHWAT DWIVEDI', 'DoB' => '18-12-2015', 'StudentPhoto' => '3a2 (10).jpeg'),
                422 => array('AppID' => '5452', 'StudentName' => 'RISHIKA', 'DoB' => '17-09-2015', 'StudentPhoto' => '3a2 (12).jpeg'),
                423 => array('AppID' => '5453', 'StudentName' => 'SAMAR', 'DoB' => '14-04-2014', 'StudentPhoto' => '3a2 (9).jpeg'),
                424 => array('AppID' => '5454', 'StudentName' => 'SHAURYA SINGH CHAUHAN', 'DoB' => '30-09-2015', 'StudentPhoto' => '3a2 (7).jpeg'),
                425 => array('AppID' => '5459', 'StudentName' => 'SUCHIT SINGH', 'DoB' => '07-07-2015', 'StudentPhoto' => '3a2 (4).jpeg'),
                426 => array('AppID' => '5455', 'StudentName' => 'SWEKSHA', 'DoB' => '11-06-2014', 'StudentPhoto' => '3a2 (8).jpeg'),
                427 => array('AppID' => '5456', 'StudentName' => 'TANISHK SONI', 'DoB' => '05-10-2016', 'StudentPhoto' => '3a2 (6).jpeg'),
                428 => array('AppID' => '5457', 'StudentName' => 'UMESH KUMAR', 'DoB' => '29-11-2016', 'StudentPhoto' => '3a2 (5).jpeg'),
                429 => array('AppID' => '5458', 'StudentName' => 'YASH', 'DoB' => '03-09-2014', 'StudentPhoto' => '3a2 (3).jpeg'),
                430 => array('AppID' => '5461', 'StudentName' => 'ABHINAV SINGH', 'DoB' => '30-12-2015', 'StudentPhoto' => '3a3 (25).jpeg'),
                431 => array('AppID' => '5462', 'StudentName' => 'ADITI SINGH', 'DoB' => '18-11-2014', 'StudentPhoto' => '3a3 (24).jpeg'),
                432 => array('AppID' => '5463', 'StudentName' => 'AKSHANSH', 'DoB' => '23-03-2014', 'StudentPhoto' => '3a3 (26).jpeg'),
                433 => array('AppID' => '5464', 'StudentName' => 'ANYA JADIYA', 'DoB' => '06-09-2015', 'StudentPhoto' => '3a3 (22).jpeg'),
                434 => array('AppID' => '5465', 'StudentName' => 'ATHARV RAJPOOT', 'DoB' => '04-06-2015', 'StudentPhoto' => '3a3 (21).jpeg'),
                435 => array('AppID' => '5466', 'StudentName' => 'CHIRAG', 'DoB' => '10-09-2014', 'StudentPhoto' => '3a3 (20).jpeg'),
                436 => array('AppID' => '5467', 'StudentName' => 'DEEPANJALI', 'DoB' => '30-06-2016', 'StudentPhoto' => '3a3 (23).jpeg'),
                437 => array('AppID' => '5468', 'StudentName' => 'DIPANSHU', 'DoB' => '01-07-2015', 'StudentPhoto' => '3a3 (18).jpeg'),
                438 => array('AppID' => '5469', 'StudentName' => 'DIVYANSH SINGH', 'DoB' => '26-06-2015', 'StudentPhoto' => '3a3 (17).jpeg'),
                439 => array('AppID' => '5470', 'StudentName' => 'HARDIK SAHU', 'DoB' => '30-03-2015', 'StudentPhoto' => '3a3 (19).jpeg'),
                440 => array('AppID' => '5471', 'StudentName' => 'KRISHNA RAJPUT', 'DoB' => '18-08-2015', 'StudentPhoto' => '3a3 (15).jpeg'),
                441 => array('AppID' => '5472', 'StudentName' => 'LALIT', 'DoB' => '20-07-2014', 'StudentPhoto' => '3a3 (16).jpeg'),
                442 => array('AppID' => '5473', 'StudentName' => 'MAHAK', 'DoB' => '01-01-2016', 'StudentPhoto' => '3a3 (13).jpeg'),
                443 => array('AppID' => '5474', 'StudentName' => 'MAYANK', 'DoB' => '13-04-2014', 'StudentPhoto' => '3a3 (12).jpeg'),
                444 => array('AppID' => '5475', 'StudentName' => 'MOHAMMAD ALTAMAS KHAN', 'DoB' => '05-10-2014', 'StudentPhoto' => '3a3 (14).jpeg'),
                445 => array('AppID' => '5476', 'StudentName' => 'NAMAN', 'DoB' => '11-09-2016', 'StudentPhoto' => '3a3 (10).jpeg'),
                446 => array('AppID' => '5477', 'StudentName' => 'PALAK', 'DoB' => '01-11-2014', 'StudentPhoto' => '3a3 (9).jpeg'),
                447 => array('AppID' => '5479', 'StudentName' => 'RADHIKA', 'DoB' => '15-07-2013', 'StudentPhoto' => '3a3 (11).jpeg'),
                448 => array('AppID' => '5480', 'StudentName' => 'RAJ', 'DoB' => '13-12-2015', 'StudentPhoto' => '3a3 (8).jpeg'),
                449 => array('AppID' => '5481', 'StudentName' => 'SHAURYA', 'DoB' => '15-10-2015', 'StudentPhoto' => '3a3 (7).jpeg'),
                450 => array('AppID' => '5482', 'StudentName' => 'SHIVANS', 'DoB' => '03-07-2015', 'StudentPhoto' => '3a3 (5).jpeg'),
                451 => array('AppID' => '5483', 'StudentName' => 'SHUBH', 'DoB' => '22-03-2015', 'StudentPhoto' => '3a3 (6).jpeg'),
                452 => array('AppID' => '5484', 'StudentName' => 'SIMRAN', 'DoB' => '28-03-2016', 'StudentPhoto' => '3a3 (3).jpeg'),
                453 => array('AppID' => '5485', 'StudentName' => 'TOOBA NAAZ', 'DoB' => '17-01-2015', 'StudentPhoto' => '3a3 (2).jpeg'),
                454 => array('AppID' => '5486', 'StudentName' => 'UTKARSH', 'DoB' => '01-12-2014', 'StudentPhoto' => '3a3 (4).jpeg'),
                455 => array('AppID' => '5487', 'StudentName' => 'YOG', 'DoB' => '01-03-2015', 'StudentPhoto' => '3a3 (1).jpeg'),
                456 => array('AppID' => '5488', 'StudentName' => 'ADARSH', 'DoB' => '03-10-2017', 'StudentPhoto' => '3a4 (28).jpeg'),
                457 => array('AppID' => '5489', 'StudentName' => 'ADEEBA', 'DoB' => '17-04-2015', 'StudentPhoto' => '3a4 (27).jpeg'),
                458 => array('AppID' => '5490', 'StudentName' => 'AGRIM', 'DoB' => '11-03-2015', 'StudentPhoto' => '3a4 (29).jpeg'),
                459 => array('AppID' => '5491', 'StudentName' => 'ANSH RAJPUT', 'DoB' => '01-07-2015', 'StudentPhoto' => '3a4 (25).jpeg'),
                460 => array('AppID' => '5492', 'StudentName' => 'ARYAN', 'DoB' => '10-02-2014', 'StudentPhoto' => '3a4 (26).jpeg'),
                461 => array('AppID' => '5493', 'StudentName' => 'ARYANS', 'DoB' => '17-07-2016', 'StudentPhoto' => '3a4 (24).jpeg'),
                462 => array('AppID' => '5494', 'StudentName' => 'ATHARV SAXENA', 'DoB' => '29-07-2014', 'StudentPhoto' => '3a4 (22).jpeg'),
                463 => array('AppID' => '5495', 'StudentName' => 'BHAVYA GUPTA', 'DoB' => '04-12-2015', 'StudentPhoto' => '3a4 (21).jpeg'),
                464 => array('AppID' => '5496', 'StudentName' => 'DHAIRYA RAJPOOT', 'DoB' => '01-07-2016', 'StudentPhoto' => '3a4 (23).jpeg'),
                465 => array('AppID' => '5497', 'StudentName' => 'DHANI RAJPUT', 'DoB' => '26-09-2015', 'StudentPhoto' => '3a4 (19).jpeg'),
                466 => array('AppID' => '5498', 'StudentName' => 'GYAN SAHU', 'DoB' => '14-09-2016', 'StudentPhoto' => '3a4 (20).jpeg'),
                467 => array('AppID' => '5499', 'StudentName' => 'HARDIK', 'DoB' => '01-06-2015', 'StudentPhoto' => '3a4 (17).jpeg'),
                468 => array('AppID' => '5500', 'StudentName' => 'HARSH RAJPOOT', 'DoB' => '15-08-2012', 'StudentPhoto' => '3a4 (16).jpeg'),
                469 => array('AppID' => '5501', 'StudentName' => 'KASHIF MANSOORI', 'DoB' => '05-08-2015', 'StudentPhoto' => '3a4 (18).jpeg'),
                470 => array('AppID' => '5502', 'StudentName' => 'KRITIKA', 'DoB' => '15-03-2016', 'StudentPhoto' => '3a4 (14).jpeg'),
                471 => array('AppID' => '5503', 'StudentName' => 'LAVKUSH GUPTA', 'DoB' => '12-12-2013', 'StudentPhoto' => '3a4 (15).jpeg'),
                472 => array('AppID' => '5504', 'StudentName' => 'MANU YADAV', 'DoB' => '12-12-2014', 'StudentPhoto' => '3a4 (12).jpeg'),
                473 => array('AppID' => '5505', 'StudentName' => 'MOHAMMAD ARHAN', 'DoB' => '07-09-2013', 'StudentPhoto' => '3a4 (11).jpeg'),
                474 => array('AppID' => '5506', 'StudentName' => 'MOHD RAZA', 'DoB' => '26-01-2011', 'StudentPhoto' => '3a4 (13).jpeg'),
                475 => array('AppID' => '5507', 'StudentName' => 'NAVYA', 'DoB' => '16-05-2016', 'StudentPhoto' => '3a4 (10).jpeg'),
                476 => array('AppID' => '5508', 'StudentName' => 'NIKET RAJPUT', 'DoB' => '11-12-2015', 'StudentPhoto' => '3a4 (8).jpeg'),
                477 => array('AppID' => '5509', 'StudentName' => 'PRAVEEN', 'DoB' => '12-10-2015', 'StudentPhoto' => '3a4 (9).jpeg'),
                478 => array('AppID' => '5510', 'StudentName' => 'SHRESHTH KUMAR', 'DoB' => '22-08-2014', 'StudentPhoto' => '3a4 (6).jpeg'),
                479 => array('AppID' => '5511', 'StudentName' => 'SHUBHI RAJPOOT', 'DoB' => '25-11-2014', 'StudentPhoto' => '3a4 (7).jpeg'),
                480 => array('AppID' => '5512', 'StudentName' => 'SIFTEN', 'DoB' => '22-09-2012', 'StudentPhoto' => '3a4 (4).jpeg'),
                481 => array('AppID' => '5513', 'StudentName' => 'TANVI', 'DoB' => '01-06-2014', 'StudentPhoto' => '3a4 (3).jpeg'),
                482 => array('AppID' => '5514', 'StudentName' => 'TANYA', 'DoB' => '16-04-2014', 'StudentPhoto' => '3a4 (5).jpeg'),
                483 => array('AppID' => '5515', 'StudentName' => 'UMANG RAJPUT', 'DoB' => '24-03-2015', 'StudentPhoto' => '3a4 (2).jpeg'),
                484 => array('AppID' => '5516', 'StudentName' => 'YOGENDRA KUMAR', 'DoB' => '20-04-2014', 'StudentPhoto' => '3a4 (1).jpeg'),
                485 => array('AppID' => '5517', 'StudentName' => 'AADI RAJPUT', 'DoB' => '28-10-2014', 'StudentPhoto' => '4a1 (27).jpeg'),
                486 => array('AppID' => '5518', 'StudentName' => 'AKSHARA', 'DoB' => '24-09-2014', 'StudentPhoto' => '4a1 (25).jpeg'),
                487 => array('AppID' => '5519', 'StudentName' => 'ALI HAMZA', 'DoB' => '20-12-2014', 'StudentPhoto' => '4a1 (24).jpeg'),
                488 => array('AppID' => '5520', 'StudentName' => 'ANJALI RAJPUT', 'DoB' => '23-12-2014', 'StudentPhoto' => '4a1 (26).jpeg'),
                489 => array('AppID' => '5521', 'StudentName' => 'ANSHIKA TAMRAKAR', 'DoB' => '07-12-2013', 'StudentPhoto' => '4a1 (22).jpeg'),
                490 => array('AppID' => '5522', 'StudentName' => 'ANSHU', 'DoB' => '17-10-2013', 'StudentPhoto' => '4a1 (21).jpeg'),
                491 => array('AppID' => '5571', 'StudentName' => 'ARADHYA GUPTA', 'DoB' => '09-01-2014', 'StudentPhoto' => '4a3 (26).jpeg'),
                492 => array('AppID' => '5523', 'StudentName' => 'AROHI AGRAWAL', 'DoB' => '15-06-2014', 'StudentPhoto' => '4a1 (23).jpeg'),
                493 => array('AppID' => '5524', 'StudentName' => 'ARPIT RAJPOOT', 'DoB' => '09-02-2013', 'StudentPhoto' => '4a1 (19).jpeg'),
                494 => array('AppID' => '5525', 'StudentName' => 'AVNI RAJPOOT', 'DoB' => '26-07-2015', 'StudentPhoto' => '4a1 (20).jpeg'),
                495 => array('AppID' => '5526', 'StudentName' => 'DEEKSHA', 'DoB' => '01-01-2014', 'StudentPhoto' => '4a1 (17).jpeg'),
                496 => array('AppID' => '5527', 'StudentName' => 'HAMEEDA KHATOON', 'DoB' => '15-06-2013', 'StudentPhoto' => '4a1 (18).jpeg'),
                497 => array('AppID' => '5528', 'StudentName' => 'HARSH GUPTA', 'DoB' => '24-08-2014', 'StudentPhoto' => '4a1 (16).jpeg'),
                498 => array('AppID' => '5529', 'StudentName' => 'HARSHITA', 'DoB' => '10-07-2014', 'StudentPhoto' => '4a1 (14).jpeg'),
                499 => array('AppID' => '5530', 'StudentName' => 'ILMA KHAN', 'DoB' => '18-02-2013', 'StudentPhoto' => '4a1 (13).jpeg'),
                500 => array('AppID' => '5532', 'StudentName' => 'KAVYA GAUTAM', 'DoB' => '09-12-2013', 'StudentPhoto' => '4a1 (11).jpeg'),
                501 => array('AppID' => '5542', 'StudentName' => 'MAYANK', 'DoB' => '26-03-2014', 'StudentPhoto' => '4a1 (1).jpeg'),
                502 => array('AppID' => '5541', 'StudentName' => 'MOHAMMAD ARMAN', 'DoB' => '02-12-2014', 'StudentPhoto' => '4a1 (3).jpeg'),
                503 => array('AppID' => '5533', 'StudentName' => 'MOHAMMAD SHARIF', 'DoB' => '25-03-2015', 'StudentPhoto' => '4a1 (12).jpeg'),
                504 => array('AppID' => '5534', 'StudentName' => 'NAITIK NAGAYACH', 'DoB' => '22-03-2014', 'StudentPhoto' => '4a1 (9).jpeg'),
                505 => array('AppID' => '5535', 'StudentName' => 'PRAGUN GUPTA', 'DoB' => '21-03-2014', 'StudentPhoto' => '4a1 (10).jpeg'),
                506 => array('AppID' => '5536', 'StudentName' => 'PRANAV DWIVEDI', 'DoB' => '28-11-2014', 'StudentPhoto' => '4a1 (7).jpeg'),
                507 => array('AppID' => '5537', 'StudentName' => 'RITURAJ', 'DoB' => '02-11-2013', 'StudentPhoto' => '4a1 (6).jpeg'),
                508 => array('AppID' => '5538', 'StudentName' => 'RIYA RAJPUT', 'DoB' => '26-07-2014', 'StudentPhoto' => '4a1 (8).jpeg'),
                509 => array('AppID' => '5539', 'StudentName' => 'SHUBH DWIVEDI', 'DoB' => '13-09-2013', 'StudentPhoto' => '4a1 (4).jpeg'),
                510 => array('AppID' => '5543', 'StudentName' => 'SUMIT SINGH YADAV', 'DoB' => '23-02-2013', 'StudentPhoto' => '4a1 (2).jpeg'),
                511 => array('AppID' => '5540', 'StudentName' => 'VED SUROTHIYA', 'DoB' => '13-01-2015', 'StudentPhoto' => '4a1 (5).jpeg'),
                512 => array('AppID' => '5544', 'StudentName' => 'ADITYA RAJ BUDHAULIYA', 'DoB' => '16-03-2014', 'StudentPhoto' => '4a2 (25).jpeg'),
                513 => array('AppID' => '5568', 'StudentName' => 'AMAN SINGH', 'DoB' => '28-08-2014', 'StudentPhoto' => '4a2 (1).jpeg'),
                514 => array('AppID' => '5545', 'StudentName' => 'ANSH GUPTA', 'DoB' => '29-07-2016', 'StudentPhoto' => '4a2 (26).jpeg'),
                515 => array('AppID' => '5546', 'StudentName' => 'ANSH RAJPOOT', 'DoB' => '11-04-2015', 'StudentPhoto' => '4a2 (23).jpeg'),
                516 => array('AppID' => '5547', 'StudentName' => 'ANSHIKA', 'DoB' => '07-04-2014', 'StudentPhoto' => '4a2 (22).jpeg'),
                517 => array('AppID' => '5548', 'StudentName' => 'ARADHYA', 'DoB' => '30-06-2014', 'StudentPhoto' => '4a2 (24).jpeg'),
                518 => array('AppID' => '5549', 'StudentName' => 'DAKSH SWARNKAR', 'DoB' => '13-01-2014', 'StudentPhoto' => '4a2 (21).jpeg'),
                519 => array('AppID' => '5550', 'StudentName' => 'DIVYA PRATAP SINGH', 'DoB' => '05-12-2014', 'StudentPhoto' => '4a2 (19).jpeg'),
                520 => array('AppID' => '5569', 'StudentName' => 'GRESHY NISHAD', 'DoB' => '17-09-2015', 'StudentPhoto' => '4a2 (2).jpeg'),
                521 => array('AppID' => '5551', 'StudentName' => 'HARSH SHAKYA', 'DoB' => '12-03-2015', 'StudentPhoto' => '4a2 (20).jpeg'),
                522 => array('AppID' => '5552', 'StudentName' => 'HASNAIN', 'DoB' => '18-12-2013', 'StudentPhoto' => '4a2 (17).jpeg'),
                523 => array('AppID' => '5553', 'StudentName' => 'ILMA', 'DoB' => '27-11-2014', 'StudentPhoto' => '4a2 (18).jpeg'),
                524 => array('AppID' => '5554', 'StudentName' => 'KAVYA GUPTA', 'DoB' => '29-03-2014', 'StudentPhoto' => '4a2 (16).jpeg'),
                525 => array('AppID' => '5555', 'StudentName' => 'KRISH', 'DoB' => '01-09-2013', 'StudentPhoto' => '4a2 (15).jpeg'),
                526 => array('AppID' => '5556', 'StudentName' => 'MANAN MAHESHWARI', 'DoB' => '03-12-2013', 'StudentPhoto' => '4a2 (13).jpeg'),
                527 => array('AppID' => '5557', 'StudentName' => 'MOHAMMAD FARHAN KHAN', 'DoB' => '11-06-2013', 'StudentPhoto' => '4a2 (12).jpeg'),
                528 => array('AppID' => '5558', 'StudentName' => 'MOHD ASIF', 'DoB' => '31-03-2013', 'StudentPhoto' => '4a2 (14).jpeg'),
                529 => array('AppID' => '5559', 'StudentName' => 'PARTH RAJPOOT', 'DoB' => '14-10-2014', 'StudentPhoto' => '4a2 (10).jpeg'),
                530 => array('AppID' => '5560', 'StudentName' => 'RAGANI SINGH', 'DoB' => '06-04-2015', 'StudentPhoto' => '4a2 (9).jpeg'),
                531 => array('AppID' => '5561', 'StudentName' => 'RAGHVENDRA', 'DoB' => '24-05-2014', 'StudentPhoto' => '4a2 (11).jpeg'),
                532 => array('AppID' => '5562', 'StudentName' => 'REBECCA', 'DoB' => '15-06-2015', 'StudentPhoto' => '4a2 (8).jpeg'),
                533 => array('AppID' => '5563', 'StudentName' => 'ROBBY', 'DoB' => '04-07-2013', 'StudentPhoto' => '4a2 (6).jpeg'),
                534 => array('AppID' => '5564', 'StudentName' => 'RUDRA SINGH', 'DoB' => '28-05-2014', 'StudentPhoto' => '4a2 (7).jpeg'),
                535 => array('AppID' => '5565', 'StudentName' => 'SHRESH KUMAR', 'DoB' => '31-10-2014', 'StudentPhoto' => '4a2 (5).jpeg'),
                536 => array('AppID' => '5566', 'StudentName' => 'UJJVAL YADAV', 'DoB' => '01-10-2014', 'StudentPhoto' => '4a2 (3).jpeg'),
                537 => array('AppID' => '5567', 'StudentName' => 'VANSH SAHU', 'DoB' => '25-06-2014', 'StudentPhoto' => '4a2 (4).jpeg'),
                538 => array('AppID' => '5574', 'StudentName' => 'AATIF ALI', 'DoB' => '08-08-2012', 'StudentPhoto' => '4a3 (23).jpeg'),
                539 => array('AppID' => '5570', 'StudentName' => 'AKSHITA RAJPOOT', 'DoB' => '16-07-2014', 'StudentPhoto' => '4a3 (27).jpeg'),
                540 => array('AppID' => '5572', 'StudentName' => 'ARPIT RAJPUT', 'DoB' => '15-04-2013', 'StudentPhoto' => '4a3 (28).jpeg'),
                541 => array('AppID' => '5573', 'StudentName' => 'ASHUTOSH', 'DoB' => '10-11-2013', 'StudentPhoto' => '4a3 (24).jpeg'),
                542 => array('AppID' => '5575', 'StudentName' => 'CRYSTAL SINGH', 'DoB' => '17-12-2014', 'StudentPhoto' => '4a3 (25).jpeg'),
                543 => array('AppID' => '5576', 'StudentName' => 'DAKSH RAJPOOT', 'DoB' => '09-06-2015', 'StudentPhoto' => '4a3 (21).jpeg'),
                544 => array('AppID' => '5577', 'StudentName' => 'EKAGRA', 'DoB' => '20-07-2016', 'StudentPhoto' => '4a3 (20).jpeg'),
                545 => array('AppID' => '5578', 'StudentName' => 'HARSH WARDHAN', 'DoB' => '07-07-2015', 'StudentPhoto' => '4a3 (22).jpeg'),
                546 => array('AppID' => '5579', 'StudentName' => 'HIMANSHI', 'DoB' => '18-04-2014', 'StudentPhoto' => '4a3 (19).jpeg'),
                547 => array('AppID' => '5580', 'StudentName' => 'KARTIK PATHAK', 'DoB' => '30-09-2015', 'StudentPhoto' => '4a3 (18).jpeg'),
                548 => array('AppID' => '5581', 'StudentName' => 'KRISHNA RAJPOOT', 'DoB' => '18-08-2015', 'StudentPhoto' => '4a3 (16).jpeg'),
                549 => array('AppID' => '5583', 'StudentName' => 'MEENAKSHI SAINI', 'DoB' => '02-10-2013', 'StudentPhoto' => '4a3 (17).jpeg'),
                550 => array('AppID' => '5584', 'StudentName' => 'MOHAMMAD ALIM SIDDIQUE', 'DoB' => '28-06-2015', 'StudentPhoto' => '4a3 (14).jpeg'),
                551 => array('AppID' => '5585', 'StudentName' => 'MOHAMMAD MUEENUDDIN HASHMI', 'DoB' => '08-07-2014', 'StudentPhoto' => '4a3 (13).jpeg'),
                552 => array('AppID' => '5586', 'StudentName' => 'NIKHIL KUMAR', 'DoB' => '17-08-2014', 'StudentPhoto' => '4a3 (11).jpeg'),
                553 => array('AppID' => '5587', 'StudentName' => 'PALAK', 'DoB' => '16-07-2013', 'StudentPhoto' => '4a3 (12).jpeg'),
                554 => array('AppID' => '5588', 'StudentName' => 'PARVEZ MANSOORY', 'DoB' => '14-04-2014', 'StudentPhoto' => '4a3 (9).jpeg'),
                555 => array('AppID' => '5589', 'StudentName' => 'PRADYUMM RAJPUT', 'DoB' => '24-12-2013', 'StudentPhoto' => '4a3 (10).jpeg'),
                556 => array('AppID' => '5590', 'StudentName' => 'PRATHA', 'DoB' => '10-08-2014', 'StudentPhoto' => '4a3 (7).jpeg'),
                557 => array('AppID' => '5591', 'StudentName' => 'RIDIT SINGH', 'DoB' => '06-10-2013', 'StudentPhoto' => '4a3 (6).jpeg'),
                558 => array('AppID' => '5592', 'StudentName' => 'RUBA ARIF', 'DoB' => '21-04-2015', 'StudentPhoto' => '4a3 (8).jpeg'),
                559 => array('AppID' => '5593', 'StudentName' => 'SATAKSHI PURWAR', 'DoB' => '10-10-2013', 'StudentPhoto' => '4a3 (4).jpeg'),
                560 => array('AppID' => '5594', 'StudentName' => 'SUNAINA', 'DoB' => '31-01-2015', 'StudentPhoto' => '4a3 (3).jpeg'),
                561 => array('AppID' => '5595', 'StudentName' => 'TEJASV SAHU', 'DoB' => '09-07-2014', 'StudentPhoto' => '4a3 (5).jpeg'),
                562 => array('AppID' => '5596', 'StudentName' => 'UTKARSH', 'DoB' => '08-11-2013', 'StudentPhoto' => '4a3 (1).jpeg'),
                563 => array('AppID' => '5597', 'StudentName' => 'YASH RAJPOOT', 'DoB' => '02-08-2014', 'StudentPhoto' => '4a3 (2).jpeg'),
                564 => array('AppID' => '5598', 'StudentName' => 'ABHINAV KUMAR', 'DoB' => '26-03-2014', 'StudentPhoto' => '4a4 (26).jpeg'),
                565 => array('AppID' => '5599', 'StudentName' => 'ALEENA HASHMI', 'DoB' => '04-02-2013', 'StudentPhoto' => '4a4 (25).jpeg'),
                566 => array('AppID' => '5600', 'StudentName' => 'ANSHIKA', 'DoB' => '25-02-2013', 'StudentPhoto' => '4a4 (27).jpeg'),
                567 => array('AppID' => '5601', 'StudentName' => 'AYUSH SINGH', 'DoB' => '22-02-2013', 'StudentPhoto' => '4a4 (23).jpeg'),
                568 => array('AppID' => '5602', 'StudentName' => 'BHAVNA', 'DoB' => '28-01-2014', 'StudentPhoto' => '4a4 (24).jpeg'),
                569 => array('AppID' => '5603', 'StudentName' => 'DEV RAJPUT', 'DoB' => '23-02-2015', 'StudentPhoto' => '4a4 (21).jpeg'),
                570 => array('AppID' => '5604', 'StudentName' => 'DEVANSH', 'DoB' => '07-09-2013', 'StudentPhoto' => '4a4 (22).jpeg'),
                571 => array('AppID' => '5605', 'StudentName' => 'DIVYANSH SINGH RAJPUT', 'DoB' => '03-06-2013', 'StudentPhoto' => '4a4 (20).jpeg'),
                572 => array('AppID' => '5606', 'StudentName' => 'GAUTAM RAJPOOT', 'DoB' => '03-05-2013', 'StudentPhoto' => '4a4 (19).jpeg'),
                573 => array('AppID' => '5607', 'StudentName' => 'HARSH', 'DoB' => '24-05-2016', 'StudentPhoto' => '4a4 (18).jpeg'),
                574 => array('AppID' => '5608', 'StudentName' => 'HARSHITA', 'DoB' => '09-01-2012', 'StudentPhoto' => '4a4 (16).jpeg'),
                575 => array('AppID' => '5609', 'StudentName' => 'HIMANSHU', 'DoB' => '02-04-2012', 'StudentPhoto' => '4a4 (17).jpeg'),
                576 => array('AppID' => '5610', 'StudentName' => 'JAY PRATAP SINGH', 'DoB' => '09-07-2015', 'StudentPhoto' => '4a4 (14).jpeg'),
                577 => array('AppID' => '5611', 'StudentName' => 'KANAK SAGAR', 'DoB' => '15-01-2014', 'StudentPhoto' => '4a4 (15).jpeg'),
                578 => array('AppID' => '5582', 'StudentName' => 'MAUSAM', 'DoB' => '28-09-2013', 'StudentPhoto' => '4a3 (15).jpeg'),
                579 => array('AppID' => '5613', 'StudentName' => 'MOHAMMAD HASNAIN ANSARI', 'DoB' => '06-11-2013', 'StudentPhoto' => '4a4 (12).jpeg'),
                580 => array('AppID' => '5614', 'StudentName' => 'MOHD ARIS KHAN', 'DoB' => '13-01-2015', 'StudentPhoto' => '4a4 (13).jpeg'),
                581 => array('AppID' => '5615', 'StudentName' => 'NANDANI', 'DoB' => '19-08-2011', 'StudentPhoto' => '4a4 (10).jpeg'),
                582 => array('AppID' => '5616', 'StudentName' => 'PAL VIVEK KUMAR', 'DoB' => '30-08-2014', 'StudentPhoto' => '4a4 (11).jpeg'),
                583 => array('AppID' => '5617', 'StudentName' => 'PARTH SAHU', 'DoB' => '25-12-2013', 'StudentPhoto' => '4a4 (9).jpeg'),
                584 => array('AppID' => '5619', 'StudentName' => 'PRATEEK', 'DoB' => '10-11-2013', 'StudentPhoto' => '4a4 (8).jpeg'),
                585 => array('AppID' => '5620', 'StudentName' => 'RISHABH', 'DoB' => '24-01-2014', 'StudentPhoto' => '4a4 (6).jpeg'),
                586 => array('AppID' => '5621', 'StudentName' => 'SHREYA', 'DoB' => '02-04-2014', 'StudentPhoto' => '4a4 (4).jpeg'),
                587 => array('AppID' => '5622', 'StudentName' => 'TANYA', 'DoB' => '11-06-2014', 'StudentPhoto' => '4a4 (5).jpeg'),
                588 => array('AppID' => '5623', 'StudentName' => 'UMMUL FATIMA', 'DoB' => '15-03-2013', 'StudentPhoto' => '4a4 (3).jpeg'),
                589 => array('AppID' => '5624', 'StudentName' => 'VIVEK SINGH', 'DoB' => '19-02-2013', 'StudentPhoto' => '4a4 (1).jpeg'),
                590 => array('AppID' => '5625', 'StudentName' => 'YASH VARDHAN', 'DoB' => '25-05-2014', 'StudentPhoto' => '4a4 (2).jpeg'),
                591 => array('AppID' => '5626', 'StudentName' => 'AAISHA FATIMA', 'DoB' => '05-06-2013', 'StudentPhoto' => '5a1 (32).jpeg'),
                592 => array('AppID' => '5627', 'StudentName' => 'ABHISHEK KUMAR', 'DoB' => '01-01-2011', 'StudentPhoto' => '5a1 (34).jpeg'),
                593 => array('AppID' => '5628', 'StudentName' => 'AHAD RAZA', 'DoB' => '11-06-2013', 'StudentPhoto' => '5a1 (30).jpeg'),
                594 => array('AppID' => '5659', 'StudentName' => 'ANANYA TIWARI', 'DoB' => '27-01-2013', 'StudentPhoto' => '5a1 (2).jpeg'),
                595 => array('AppID' => '5629', 'StudentName' => 'ANSH', 'DoB' => '25-08-2015', 'StudentPhoto' => '5a1 (31).jpeg'),
                596 => array('AppID' => '5630', 'StudentName' => 'ARHAMA SIDDIQUE', 'DoB' => '01-07-2012', 'StudentPhoto' => '5a1 (29).jpeg'),
                597 => array('AppID' => '5631', 'StudentName' => 'ARYAN TIWARI', 'DoB' => '23-01-2015', 'StudentPhoto' => '5a1 (27).jpeg'),
                598 => array('AppID' => '5632', 'StudentName' => 'ASMITA SHARMA', 'DoB' => '26-03-2012', 'StudentPhoto' => '5a1 (26).jpeg'),
                599 => array('AppID' => '5633', 'StudentName' => 'AYUSH SINGH', 'DoB' => '23-12-2012', 'StudentPhoto' => '5a1 (25).jpeg'),
                600 => array('AppID' => '5634', 'StudentName' => 'BHAGAT SINGH', 'DoB' => '01-01-2015', 'StudentPhoto' => '5a1 (28).jpeg'),
                601 => array('AppID' => '5635', 'StudentName' => 'DARSHIT SINGH', 'DoB' => '23-11-2013', 'StudentPhoto' => '5a1 (23).jpeg'),
                602 => array('AppID' => '5636', 'StudentName' => 'DEVANSH RAJPUT', 'DoB' => '28-10-2014', 'StudentPhoto' => '5a1 (24).jpeg'),
                603 => array('AppID' => '5637', 'StudentName' => 'DIYA RAJPUT', 'DoB' => '07-06-2012', 'StudentPhoto' => '5a1 (21).jpeg'),
                604 => array('AppID' => '5638', 'StudentName' => 'EFFAT FATIMA', 'DoB' => '30-08-2012', 'StudentPhoto' => '5a1 (22).jpeg'),
                605 => array('AppID' => '5639', 'StudentName' => 'KANISHK SINGH RAJPUT', 'DoB' => '14-08-2013', 'StudentPhoto' => '5a1 (19).jpeg'),
                606 => array('AppID' => '5640', 'StudentName' => 'KOMAL', 'DoB' => '26-01-2013', 'StudentPhoto' => '5a1 (20).jpeg'),
                607 => array('AppID' => '5641', 'StudentName' => 'KRISHNA', 'DoB' => '26-01-2014', 'StudentPhoto' => '5a1 (18).jpeg'),
                608 => array('AppID' => '5642', 'StudentName' => 'LAKSHYA PRATAP SINGH', 'DoB' => '05-09-2012', 'StudentPhoto' => '5a1 (17).jpeg'),
                609 => array('AppID' => '5643', 'StudentName' => 'LOCHANA RAJPUT', 'DoB' => '11-05-2013', 'StudentPhoto' => '5a1 (16).jpeg'),
                610 => array('AppID' => '5645', 'StudentName' => 'MANYA GUPTA', 'DoB' => '12-11-2012', 'StudentPhoto' => '5a1 (14).jpeg'),
                611 => array('AppID' => '5646', 'StudentName' => 'MANYA GUPTA', 'DoB' => '18-05-2014', 'StudentPhoto' => '5a1 (15).jpeg'),
                612 => array('AppID' => '5647', 'StudentName' => 'MOHAMMAD AYAZ', 'DoB' => '11-03-2012', 'StudentPhoto' => '5a1 (13).jpeg'),
                613 => array('AppID' => '5648', 'StudentName' => 'MOHAMMAD HAMZA NASEEM', 'DoB' => '23-09-2013', 'StudentPhoto' => '5a1 (12).jpeg'),
                614 => array('AppID' => '5649', 'StudentName' => 'NANCY RAJPUT', 'DoB' => '07-05-2013', 'StudentPhoto' => '5a1 (11).jpeg'),
                615 => array('AppID' => '5650', 'StudentName' => 'PRATIGYA', 'DoB' => '03-11-2013', 'StudentPhoto' => '5a1 (9).jpeg'),
                616 => array('AppID' => '5651', 'StudentName' => 'RAJ VISWAKARMA', 'DoB' => '16-04-2015', 'StudentPhoto' => '5a1 (8).jpeg'),
                617 => array('AppID' => '5652', 'StudentName' => 'SHASHIKANT AHIRWAR', 'DoB' => '23-09-2012', 'StudentPhoto' => '5a1 (10).jpeg'),
                618 => array('AppID' => '5653', 'StudentName' => 'SHIVANYA AGRAWAL', 'DoB' => '02-10-2012', 'StudentPhoto' => '5a1 (6).jpeg'),
                619 => array('AppID' => '5654', 'StudentName' => 'SHREYAS DWIVEDEI', 'DoB' => '02-05-2014', 'StudentPhoto' => '5a1 (7).jpeg'),
                620 => array('AppID' => '5655', 'StudentName' => 'UDIKA SAINI', 'DoB' => '09-07-2012', 'StudentPhoto' => '5a1 (4).jpeg'),
                621 => array('AppID' => '5656', 'StudentName' => 'UTKARSH', 'DoB' => '22-07-2013', 'StudentPhoto' => '5a1 (3).jpeg'),
                622 => array('AppID' => '5657', 'StudentName' => 'VEDANT YADAV', 'DoB' => '30-11-2012', 'StudentPhoto' => '5a1 (5).jpeg'),
                623 => array('AppID' => '5658', 'StudentName' => 'YASH YADAV', 'DoB' => '03-12-2013', 'StudentPhoto' => '5a1 (1).jpeg'),
                624 => array('AppID' => '5660', 'StudentName' => 'ADITYA', 'DoB' => '18-03-2012', 'StudentPhoto' => '5a2 (33).jpeg'),
                625 => array('AppID' => '5661', 'StudentName' => 'ADITYA SHARMA', 'DoB' => '11-05-2013', 'StudentPhoto' => '5a2 (34).jpeg'),
                626 => array('AppID' => '5662', 'StudentName' => 'AJAY SINGH', 'DoB' => '09-08-2012', 'StudentPhoto' => '5a2 (31).jpeg'),
                627 => array('AppID' => '5663', 'StudentName' => 'ANANYA SAINI', 'DoB' => '07-11-2014', 'StudentPhoto' => '5a2 (30).jpeg'),
                628 => array('AppID' => '5664', 'StudentName' => 'ARTIKA', 'DoB' => '20-10-2012', 'StudentPhoto' => '5a2 (32).jpeg'),
                629 => array('AppID' => '5665', 'StudentName' => 'ARYAN GUPTA', 'DoB' => '23-11-2012', 'StudentPhoto' => '5a2 (28).jpeg'),
                630 => array('AppID' => '5666', 'StudentName' => 'ASHUTOSH SHARMA', 'DoB' => '26-03-2012', 'StudentPhoto' => '5a2 (29).jpeg'),
                631 => array('AppID' => '5667', 'StudentName' => 'BHARAT KUMAR', 'DoB' => '13-04-2014', 'StudentPhoto' => '5a2 (27).jpeg'),
                632 => array('AppID' => '5668', 'StudentName' => 'BHAVISHYA PRATAP SINGH', 'DoB' => '24-07-2013', 'StudentPhoto' => '5a2 (25).jpeg'),
                633 => array('AppID' => '5669', 'StudentName' => 'DIVYANSHI JADON', 'DoB' => '04-08-2013', 'StudentPhoto' => '5a2 (26).jpeg'),
                634 => array('AppID' => '5670', 'StudentName' => 'DRISHTI', 'DoB' => '13-12-2013', 'StudentPhoto' => '5a2 (24).jpeg'),
                635 => array('AppID' => '5671', 'StudentName' => 'GAURI SINGH', 'DoB' => '27-02-2014', 'StudentPhoto' => '5a2 (22).jpeg'),
                636 => array('AppID' => '5672', 'StudentName' => 'HARSHIT KUMAR', 'DoB' => '30-11-2012', 'StudentPhoto' => '5a2 (21).jpeg'),
                637 => array('AppID' => '5673', 'StudentName' => 'JIGYASA', 'DoB' => '05-07-2012', 'StudentPhoto' => '5a2 (23).jpeg'),
                638 => array('AppID' => '5674', 'StudentName' => 'KARTIK', 'DoB' => '01-07-2012', 'StudentPhoto' => '5a2 (19).jpeg'),
                639 => array('AppID' => '5675', 'StudentName' => 'KASHIFA SIDDIQUI', 'DoB' => '02-02-2014', 'StudentPhoto' => '5a2 (20).jpeg'),
                640 => array('AppID' => '5676', 'StudentName' => 'KOMAL', 'DoB' => '13-01-2013', 'StudentPhoto' => '5a2 (18).jpeg'),
                641 => array('AppID' => '5677', 'StudentName' => 'KRISH RAJPUT', 'DoB' => '01-01-2012', 'StudentPhoto' => '5a2 (17).jpeg'),
                642 => array('AppID' => '5678', 'StudentName' => 'LAKSHYA', 'DoB' => '13-07-2012', 'StudentPhoto' => '5a2 (15).jpeg'),
                643 => array('AppID' => '5679', 'StudentName' => 'MAHI RAJPUT', 'DoB' => '25-03-2013', 'StudentPhoto' => '5a2 (16).jpeg'),
                644 => array('AppID' => '5680', 'StudentName' => 'MISTI SINHA', 'DoB' => '21-12-2011', 'StudentPhoto' => '5a2 (14).jpeg'),
                645 => array('AppID' => '5693', 'StudentName' => 'MUHAMMAD ARSHAN', 'DoB' => '10-01-2014', 'StudentPhoto' => '5a2 (1).jpeg'),
                646 => array('AppID' => '5681', 'StudentName' => 'POORVA', 'DoB' => '13-10-2012', 'StudentPhoto' => '5a2 (12).jpeg'),
                647 => array('AppID' => '5682', 'StudentName' => 'PRAVEEN', 'DoB' => '14-10-2012', 'StudentPhoto' => '5a2 (11).jpeg'),
                648 => array('AppID' => '5683', 'StudentName' => 'PURNIMA', 'DoB' => '27-03-2013', 'StudentPhoto' => '5a2 (13).jpeg'),
                649 => array('AppID' => '5684', 'StudentName' => 'RAGHVENDRA SINGH', 'DoB' => '15-10-2012', 'StudentPhoto' => '5a2 (9).jpeg'),
                650 => array('AppID' => '5685', 'StudentName' => 'RAJVEER', 'DoB' => '12-03-2013', 'StudentPhoto' => '5a2 (10).jpeg'),
                651 => array('AppID' => '5686', 'StudentName' => 'ROHINI', 'DoB' => '04-02-2011', 'StudentPhoto' => '5a2 (7).jpeg'),
                652 => array('AppID' => '5687', 'StudentName' => 'RUWAID', 'DoB' => '02-11-2013', 'StudentPhoto' => '5a2 (8).jpeg'),
                653 => array('AppID' => '5688', 'StudentName' => 'SAMEER KHAN', 'DoB' => '01-01-2012', 'StudentPhoto' => '5a2 (5).jpeg'),
                654 => array('AppID' => '5689', 'StudentName' => 'SIDRA FATIMA', 'DoB' => '24-11-2013', 'StudentPhoto' => '5a2 (6).jpeg'),
                655 => array('AppID' => '5690', 'StudentName' => 'TEJASVA GUPTA', 'DoB' => '18-07-2013', 'StudentPhoto' => '5a2 (4).jpeg'),
                656 => array('AppID' => '5691', 'StudentName' => 'VAIBHAV PRATAP SINGH', 'DoB' => '15-04-2014', 'StudentPhoto' => '5a2 (2).jpeg'),
                657 => array('AppID' => '5692', 'StudentName' => 'VISHESH DWIVEDI', 'DoB' => '18-02-2014', 'StudentPhoto' => '5a2 (3).jpeg'),
                658 => array('AppID' => '5694', 'StudentName' => 'ADITI YADAV', 'DoB' => '11-06-2013', 'StudentPhoto' => '5a3 (32).jpeg'),
                659 => array('AppID' => '5695', 'StudentName' => 'ANANYA SHARMA', 'DoB' => '18-05-2013', 'StudentPhoto' => '5a3 (31).jpeg'),
                660 => array('AppID' => '5696', 'StudentName' => 'ANSH RAJPOOT', 'DoB' => '01-10-2013', 'StudentPhoto' => '5a3 (33).jpeg'),
                661 => array('AppID' => '5697', 'StudentName' => 'ANSHIKA ARYA', 'DoB' => '06-10-2013', 'StudentPhoto' => '5a3 (29).jpeg'),
                662 => array('AppID' => '5698', 'StudentName' => 'ANUJ', 'DoB' => '05-07-2014', 'StudentPhoto' => '5a3 (30).jpeg'),
                663 => array('AppID' => '5699', 'StudentName' => 'ANYA', 'DoB' => '13-03-2013', 'StudentPhoto' => '5a3 (27).jpeg'),
                664 => array('AppID' => '5700', 'StudentName' => 'APARNA RAJPUT', 'DoB' => '27-05-2013', 'StudentPhoto' => '5a3 (28).jpeg'),
                665 => array('AppID' => '5701', 'StudentName' => 'ARIBA FATIMA', 'DoB' => '27-02-2013', 'StudentPhoto' => '5a3 (25).jpeg'),
                666 => array('AppID' => '5702', 'StudentName' => 'ARYAN SINGH', 'DoB' => '13-07-2014', 'StudentPhoto' => '5a3 (26).jpeg'),
                667 => array('AppID' => '5703', 'StudentName' => 'AYUSH RAJPUT', 'DoB' => '04-04-2013', 'StudentPhoto' => '5a3 (24).jpeg'),
                668 => array('AppID' => '5704', 'StudentName' => 'DAKSH MAHESHWARI', 'DoB' => '13-02-2014', 'StudentPhoto' => '5a3 (23).jpeg'),
                669 => array('AppID' => '5705', 'StudentName' => 'DEEPRAJ SINGH RAJAW', 'DoB' => '01-01-2012', 'StudentPhoto' => '5a3 (22).jpeg'),
                670 => array('AppID' => '5706', 'StudentName' => 'DHARMESH RAJPUT', 'DoB' => '05-07-2013', 'StudentPhoto' => '5a3 (20).jpeg'),
                671 => array('AppID' => '5707', 'StudentName' => 'GAUREE RAJPOOT', 'DoB' => '01-09-2012', 'StudentPhoto' => '5a3 (21).jpeg'),
                672 => array('AppID' => '5708', 'StudentName' => 'GRANTH PATKAR', 'DoB' => '22-05-2013', 'StudentPhoto' => '5a3 (18).jpeg'),
                673 => array('AppID' => '5709', 'StudentName' => 'HARSHIT VERMA', 'DoB' => '21-06-2013', 'StudentPhoto' => '5a3 (17).jpeg'),
                674 => array('AppID' => '5710', 'StudentName' => 'ILMA KHATOON', 'DoB' => '21-10-2012', 'StudentPhoto' => '5a3 (19).jpeg'),
                675 => array('AppID' => '5711', 'StudentName' => 'KHUSHI', 'DoB' => '14-08-2013', 'StudentPhoto' => '5a3 (15).jpeg'),
                676 => array('AppID' => '5712', 'StudentName' => 'MAHAK RAJPOOT', 'DoB' => '07-06-2012', 'StudentPhoto' => '5a3 (16).jpeg'),
                677 => array('AppID' => '5713', 'StudentName' => 'MOHAMMAD ALTAMAS KHAN', 'DoB' => '25-10-2013', 'StudentPhoto' => '5a3 (14).jpeg'),
                678 => array('AppID' => '5714', 'StudentName' => 'OMKAR SONWANI', 'DoB' => '15-05-2014', 'StudentPhoto' => '5a3 (12).jpeg'),
                679 => array('AppID' => '5715', 'StudentName' => 'PRATHVI SINGH', 'DoB' => '15-01-2013', 'StudentPhoto' => '5a3 (13).jpeg'),
                680 => array('AppID' => '5716', 'StudentName' => 'PRIYANJANA', 'DoB' => '06-04-2013', 'StudentPhoto' => '5a3 (10).jpeg'),
                681 => array('AppID' => '5717', 'StudentName' => 'RUDRADEV', 'DoB' => '22-01-2014', 'StudentPhoto' => '5a3 (9).jpeg'),
                682 => array('AppID' => '5718', 'StudentName' => 'SAKSHI', 'DoB' => '16-09-2012', 'StudentPhoto' => '5a3 (11).jpeg'),
                683 => array('AppID' => '5719', 'StudentName' => 'SAMAR PRATAP SINGH', 'DoB' => '15-02-2013', 'StudentPhoto' => '5a3 (7).jpeg'),
                684 => array('AppID' => '5720', 'StudentName' => 'SANTOSHI RAJPUT', 'DoB' => '16-11-2010', 'StudentPhoto' => '5a3 (8).jpeg'),
                685 => array('AppID' => '5721', 'StudentName' => 'SAVINAY', 'DoB' => '02-03-2014', 'StudentPhoto' => '5a3 (5).jpeg'),
                686 => array('AppID' => '5722', 'StudentName' => 'SHREEYANSH', 'DoB' => '13-04-2013', 'StudentPhoto' => '5a3 (6).jpeg'),
                687 => array('AppID' => '5723', 'StudentName' => 'SOYASH', 'DoB' => '12-04-2013', 'StudentPhoto' => '5a3 (3).jpeg'),
                688 => array('AppID' => '5725', 'StudentName' => 'VIRAT GUPTA', 'DoB' => '14-03-2014', 'StudentPhoto' => '5a3 (1).jpeg'),
                689 => array('AppID' => '5726', 'StudentName' => 'YASHI RAJPUT', 'DoB' => '26-12-2014', 'StudentPhoto' => '5a3 (2).jpeg'),
                690 => array('AppID' => '5727', 'StudentName' => 'ABHISHEK KUMAR', 'DoB' => '10-06-2013', 'StudentPhoto' => '5a4 (32).jpeg'),
                691 => array('AppID' => '5728', 'StudentName' => 'ANANYA', 'DoB' => '29-07-2013', 'StudentPhoto' => '5a4 (33).jpeg'),
                692 => array('AppID' => '5729', 'StudentName' => 'ANJALI', 'DoB' => '12-02-2013', 'StudentPhoto' => '5a4 (30).jpeg'),
                693 => array('AppID' => '5730', 'StudentName' => 'ANSHU SINGH', 'DoB' => '01-01-2012', 'StudentPhoto' => '5a4 (29).jpeg'),
                694 => array('AppID' => '5731', 'StudentName' => 'ANUJA', 'DoB' => '17-10-2013', 'StudentPhoto' => '5a4 (31).jpeg'),
                695 => array('AppID' => '5732', 'StudentName' => 'ARAV GUPTA', 'DoB' => '06-10-2013', 'StudentPhoto' => '5a4 (27).jpeg'),
                696 => array('AppID' => '5733', 'StudentName' => 'ARBIYA', 'DoB' => '09-08-2012', 'StudentPhoto' => '5a4 (26).jpeg'),
                697 => array('AppID' => '5734', 'StudentName' => 'ARUSHI', 'DoB' => '02-02-2014', 'StudentPhoto' => '5a4 (28).jpeg'),
                698 => array('AppID' => '5735', 'StudentName' => 'AVIRAL VERMA', 'DoB' => '21-11-2013', 'StudentPhoto' => '5a4 (25).jpeg'),
                699 => array('AppID' => '5736', 'StudentName' => 'DARSH NIRANJAN', 'DoB' => '27-06-2014', 'StudentPhoto' => '5a4 (23).jpeg'),
                700 => array('AppID' => '5737', 'StudentName' => 'DEVANSH', 'DoB' => '01-06-2013', 'StudentPhoto' => '5a4 (24).jpeg'),
                701 => array('AppID' => '5738', 'StudentName' => 'DHRUV KUMAR', 'DoB' => '01-01-2012', 'StudentPhoto' => '5a4 (22).jpeg'),
                702 => array('AppID' => '5739', 'StudentName' => 'HARSH VARDHAN SINGH', 'DoB' => '26-01-2014', 'StudentPhoto' => '5a4 (20).jpeg'),
                703 => array('AppID' => '5740', 'StudentName' => 'HIMANCHAL', 'DoB' => '31-12-2013', 'StudentPhoto' => '5a4 (21).jpeg'),
                704 => array('AppID' => '5741', 'StudentName' => 'ISHMA', 'DoB' => '12-10-2012', 'StudentPhoto' => '5a4 (18).jpeg'),
                705 => array('AppID' => '5742', 'StudentName' => 'JAYKRISHN RAJPOOT', 'DoB' => '25-09-2012', 'StudentPhoto' => '5a4 (19).jpeg'),
                706 => array('AppID' => '5743', 'StudentName' => 'KRITIKA SINGH', 'DoB' => '05-12-2011', 'StudentPhoto' => '5a4 (17).jpeg'),
                707 => array('AppID' => '5744', 'StudentName' => 'LAVIZA KHAN', 'DoB' => '17-02-2014', 'StudentPhoto' => '5a4 (15).jpeg'),
                708 => array('AppID' => '5745', 'StudentName' => 'MOHAMMAD ARSH', 'DoB' => '15-07-2014', 'StudentPhoto' => '5a4 (16).jpeg'),
                709 => array('AppID' => '5746', 'StudentName' => 'NAINSEE', 'DoB' => '19-07-2013', 'StudentPhoto' => '5a4 (14).jpeg'),
                710 => array('AppID' => '5747', 'StudentName' => 'NAMAN PRATAP SINGH', 'DoB' => '24-05-2013', 'StudentPhoto' => '5a4 (13).jpeg'),
                711 => array('AppID' => '5748', 'StudentName' => 'PRANSHU GUPTA', 'DoB' => '01-01-2012', 'StudentPhoto' => '5a4 (11).jpeg'),
                712 => array('AppID' => '5749', 'StudentName' => 'RADHIKA', 'DoB' => '24-05-2012', 'StudentPhoto' => '5a4 (12).jpeg'),
                713 => array('AppID' => '5750', 'StudentName' => 'RAJ', 'DoB' => '01-07-2012', 'StudentPhoto' => '5a4 (9).jpeg'),
                714 => array('AppID' => '5751', 'StudentName' => 'SAGAR', 'DoB' => '05-01-2012', 'StudentPhoto' => '5a4 (10).jpeg'),
                715 => array('AppID' => '5752', 'StudentName' => 'SAKSHAM SONI', 'DoB' => '12-12-2011', 'StudentPhoto' => '5a4 (8).jpeg'),
                716 => array('AppID' => '5753', 'StudentName' => 'SAMIKSHA', 'DoB' => '03-08-2013', 'StudentPhoto' => '5a4 (6).jpeg'),
                717 => array('AppID' => '5754', 'StudentName' => 'SATAKSHI', 'DoB' => '10-07-2013', 'StudentPhoto' => '5a4 (7).jpeg'),
                718 => array('AppID' => '5755', 'StudentName' => 'SHAKTI TRIPATHI', 'DoB' => '06-03-2013', 'StudentPhoto' => '5a4 (5).jpeg'),
                719 => array('AppID' => '5756', 'StudentName' => 'SHUBH RAJPUT', 'DoB' => '15-12-2013', 'StudentPhoto' => '5a4 (4).jpeg'),
                720 => array('AppID' => '5757', 'StudentName' => 'VARTIKA', 'DoB' => '12-06-2014', 'StudentPhoto' => '5a4 (2).jpeg'),
                721 => array('AppID' => '5759', 'StudentName' => 'YASHIKA', 'DoB' => '19-08-2013', 'StudentPhoto' => '5a4 (1).jpeg'),
                722 => array('AppID' => '5774', 'StudentName' => 'ABDUL AHAD HUSAIN', 'DoB' => '06-02-2013', 'StudentPhoto' => '6a1 (1).jpeg'),
                723 => array('AppID' => '5775', 'StudentName' => 'ADARSH RAJPOOT', 'DoB' => '10-11-2011', 'StudentPhoto' => '6a1 (34).jpeg'),
                724 => array('AppID' => '5760', 'StudentName' => 'ADARSHINI', 'DoB' => '03-11-2012', 'StudentPhoto' => '6a1 (14).jpeg'),
                725 => array('AppID' => '5776', 'StudentName' => 'ADITYA YADAV', 'DoB' => '19-07-2013', 'StudentPhoto' => '6a1 (33).jpeg'),
                726 => array('AppID' => '5761', 'StudentName' => 'ANSHIKA', 'DoB' => '28-04-2012', 'StudentPhoto' => '6a1 (15).jpeg'),
                727 => array('AppID' => '5777', 'StudentName' => 'ANSHUDEEP', 'DoB' => '27-11-2013', 'StudentPhoto' => '6a1 (32).jpeg'),
                728 => array('AppID' => '5778', 'StudentName' => 'ARPIT YADAV', 'DoB' => '10-08-2012', 'StudentPhoto' => '6a1 (31).jpeg'),
                729 => array('AppID' => '5779', 'StudentName' => 'ARYAN RAJPUT', 'DoB' => '07-07-2012', 'StudentPhoto' => '6a1 (30).jpeg'),
                730 => array('AppID' => '5762', 'StudentName' => 'ATIKA PARVEEN', 'DoB' => '01-01-2012', 'StudentPhoto' => '6a1 (13).jpeg'),
                731 => array('AppID' => '5780', 'StudentName' => 'AYUSH', 'DoB' => '09-08-2012', 'StudentPhoto' => '6a1 (29).jpeg'),
                732 => array('AppID' => '5781', 'StudentName' => 'DEEPAK RAJPOOT', 'DoB' => '26-10-2013', 'StudentPhoto' => '6a1 (28).jpeg'),
                733 => array('AppID' => '5782', 'StudentName' => 'DIVYANSH KUMAR', 'DoB' => '01-01-2011', 'StudentPhoto' => '6a1 (27).jpeg'),
                734 => array('AppID' => '5783', 'StudentName' => 'DIVYANSHU TRIPATHI', 'DoB' => '23-02-2013', 'StudentPhoto' => '6a1 (26).jpeg'),
                735 => array('AppID' => '5763', 'StudentName' => 'GRASI RAJPOOT', 'DoB' => '16-04-2014', 'StudentPhoto' => '6a1 (12).jpeg'),
                736 => array('AppID' => '5784', 'StudentName' => 'HARSH PRATAP', 'DoB' => '08-10-2012', 'StudentPhoto' => '6a1 (25).jpeg'),
                737 => array('AppID' => '5785', 'StudentName' => 'HIMANSHU KOSTHA', 'DoB' => '06-03-2012', 'StudentPhoto' => '6a1 (24).jpeg'),
                738 => array('AppID' => '5786', 'StudentName' => 'KARTIK SAHU', 'DoB' => '08-01-2012', 'StudentPhoto' => '6a1 (23).jpeg'),
                739 => array('AppID' => '5764', 'StudentName' => 'MADIHA KHATOON', 'DoB' => '24-10-2012', 'StudentPhoto' => '6a1 (11).jpeg'),
                740 => array('AppID' => '5787', 'StudentName' => 'MANEESH KUMAR', 'DoB' => '29-08-2013', 'StudentPhoto' => '6a1 (22).jpeg'),
                741 => array('AppID' => '5788', 'StudentName' => 'MRITUNJAY RAJPUT', 'DoB' => '12-05-2013', 'StudentPhoto' => '6a1 (20).jpeg'),
                742 => array('AppID' => '5765', 'StudentName' => 'NAINCY GUPTA', 'DoB' => '27-07-2011', 'StudentPhoto' => '6a1 (10).jpeg'),
                743 => array('AppID' => '5766', 'StudentName' => 'NEETANSHI', 'DoB' => '08-05-2011', 'StudentPhoto' => '6a1 (9).jpeg'),
                744 => array('AppID' => '5789', 'StudentName' => 'OM GUPTA', 'DoB' => '09-03-2012', 'StudentPhoto' => '6a1 (21).jpeg'),
                745 => array('AppID' => '5767', 'StudentName' => 'PALAK RAJPOOT', 'DoB' => '30-05-2012', 'StudentPhoto' => '6a1 (8).jpeg'),
                746 => array('AppID' => '5790', 'StudentName' => 'PARV JHA', 'DoB' => '11-04-2012', 'StudentPhoto' => '6a1 (19).jpeg'),
                747 => array('AppID' => '5768', 'StudentName' => 'PRASHANSA', 'DoB' => '13-03-2012', 'StudentPhoto' => '6a1 (6).jpeg'),
                748 => array('AppID' => '5791', 'StudentName' => 'PRATEEK SINGH YADAV', 'DoB' => '27-06-2012', 'StudentPhoto' => '6a1 (18).jpeg'),
                749 => array('AppID' => '5769', 'StudentName' => 'SADHNA', 'DoB' => '01-01-2014', 'StudentPhoto' => '6a1 (7).jpeg'),
                750 => array('AppID' => '5770', 'StudentName' => 'SARISA YADAV', 'DoB' => '01-05-2013', 'StudentPhoto' => '6a1 (4).jpeg'),
                751 => array('AppID' => '5792', 'StudentName' => 'SHAURYA SAHU', 'DoB' => '01-10-2013', 'StudentPhoto' => '6a1 (16).jpeg'),
                752 => array('AppID' => '5771', 'StudentName' => 'SHREYA RAJPUT', 'DoB' => '28-12-2012', 'StudentPhoto' => '6a1 (5).jpeg'),
                753 => array('AppID' => '5772', 'StudentName' => 'SHRISHTI', 'DoB' => '14-11-2011', 'StudentPhoto' => '6a1 (3).jpeg'),
                754 => array('AppID' => '5793', 'StudentName' => 'SYED FARAZ UDDIN', 'DoB' => '25-04-2012', 'StudentPhoto' => '6a1 (17).jpeg'),
                755 => array('AppID' => '5773', 'StudentName' => 'VAISHNAVI', 'DoB' => '02-10-2011', 'StudentPhoto' => '6a1 (2).jpeg'),
                756 => array('AppID' => '5808', 'StudentName' => 'ABHISHEK THAMAS', 'DoB' => '21-06-2011', 'StudentPhoto' => '6a2 (20).jpeg'),
                757 => array('AppID' => '5809', 'StudentName' => 'ADARSH KUMAR', 'DoB' => '13-04-2012', 'StudentPhoto' => '6a2 (18).jpeg'),
                758 => array('AppID' => '5810', 'StudentName' => 'ADITYA RAJPOOT', 'DoB' => '01-07-2013', 'StudentPhoto' => '6a2 (16).jpeg'),
                759 => array('AppID' => '5794', 'StudentName' => 'ALINA SIDDIQUE', 'DoB' => '28-12-2012', 'StudentPhoto' => '6a2 (32).jpeg'),
                760 => array('AppID' => '5795', 'StudentName' => 'ANANYA GUPTA', 'DoB' => '02-03-2014', 'StudentPhoto' => '6a2 (33).jpeg'),
                761 => array('AppID' => '5796', 'StudentName' => 'ANJIL RAJPOOT', 'DoB' => '17-07-2015', 'StudentPhoto' => '6a2 (31).jpeg'),
                762 => array('AppID' => '5811', 'StudentName' => 'ANSH RAJPOOT', 'DoB' => '15-10-2012', 'StudentPhoto' => '6a2 (17).jpeg'),
                763 => array('AppID' => '5812', 'StudentName' => 'ANSHUL SINGH', 'DoB' => '10-11-2011', 'StudentPhoto' => '6a2 (15).jpeg'),
                764 => array('AppID' => '5813', 'StudentName' => 'ARADHYA KOHLI', 'DoB' => '14-09-2012', 'StudentPhoto' => '6a2 (14).jpeg'),
                765 => array('AppID' => '5814', 'StudentName' => 'ARAV GUPTA', 'DoB' => '01-01-2013', 'StudentPhoto' => '6a2 (12).jpeg'),
                766 => array('AppID' => '5797', 'StudentName' => 'ARYA AGRAWAL', 'DoB' => '08-04-2012', 'StudentPhoto' => '6a2 (29).jpeg'),
                767 => array('AppID' => '5815', 'StudentName' => 'ASHWINI KUMAR GANGWAR', 'DoB' => '30-01-2013', 'StudentPhoto' => '6a2 (13).jpeg'),
                768 => array('AppID' => '5798', 'StudentName' => 'AYUSHI GUPTA', 'DoB' => '08-10-2012', 'StudentPhoto' => '6a2 (30).jpeg'),
                769 => array('AppID' => '5816', 'StudentName' => 'DAKSH KUMAR', 'DoB' => '04-06-2012', 'StudentPhoto' => '6a2 (11).jpeg'),
                770 => array('AppID' => '5817', 'StudentName' => 'DHANANJAY', 'DoB' => '12-07-2012', 'StudentPhoto' => '6a2 (10).jpeg'),
                771 => array('AppID' => '5818', 'StudentName' => 'DIVYANSH', 'DoB' => '12-07-2012', 'StudentPhoto' => '6a2 (8).jpeg'),
                772 => array('AppID' => '5819', 'StudentName' => 'HIMANSHU', 'DoB' => '11-02-2012', 'StudentPhoto' => '6a2 (9).jpeg'),
                773 => array('AppID' => '5820', 'StudentName' => 'HRADYANSH SAXENA', 'DoB' => '03-07-2012', 'StudentPhoto' => '6a2 (6).jpeg'),
                774 => array('AppID' => '5799', 'StudentName' => 'KHUSHI SRIVAS', 'DoB' => '20-04-2012', 'StudentPhoto' => '6a2 (28).jpeg'),
                775 => array('AppID' => '5800', 'StudentName' => 'MARIYAM FATIMA', 'DoB' => '28-05-2013', 'StudentPhoto' => '6a2 (27).jpeg'),
                776 => array('AppID' => '5821', 'StudentName' => 'MOHSIN AHMAD', 'DoB' => '25-09-2012', 'StudentPhoto' => '6a2 (7).jpeg'),
                777 => array('AppID' => '5801', 'StudentName' => 'NAVEELA KHATOON', 'DoB' => '30-04-2012', 'StudentPhoto' => '6a2 (26).jpeg'),
                778 => array('AppID' => '5802', 'StudentName' => 'NIHARIKA RAI', 'DoB' => '12-12-2012', 'StudentPhoto' => '6a2 (24).jpeg'),
                779 => array('AppID' => '5803', 'StudentName' => 'PIHOO', 'DoB' => '04-11-2013', 'StudentPhoto' => '6a2 (23).jpeg'),
                780 => array('AppID' => '5822', 'StudentName' => 'PRAJWAL GUPTA', 'DoB' => '19-06-2012', 'StudentPhoto' => '6a2 (5).jpeg'),
                781 => array('AppID' => '5804', 'StudentName' => 'REHAM', 'DoB' => '18-08-2013', 'StudentPhoto' => '6a2 (25).jpeg'),
                782 => array('AppID' => '5823', 'StudentName' => 'RUDRA PRATAP', 'DoB' => '08-07-2011', 'StudentPhoto' => '6a2 (3).jpeg'),
                783 => array('AppID' => '5805', 'StudentName' => 'SARA', 'DoB' => '07-09-2013', 'StudentPhoto' => '6a2 (22).jpeg'),
                784 => array('AppID' => '5824', 'StudentName' => 'SAYED ATIF HASHMI', 'DoB' => '11-01-2013', 'StudentPhoto' => '6a2 (4).jpeg'),
                785 => array('AppID' => '5806', 'StudentName' => 'SHRISHTI MISHRA', 'DoB' => '01-01-2013', 'StudentPhoto' => '6a2 (21).jpeg'),
                786 => array('AppID' => '5825', 'StudentName' => 'SHUBH TIWARI', 'DoB' => '28-04-2013', 'StudentPhoto' => '6a2 (2).jpeg'),
                787 => array('AppID' => '5807', 'StudentName' => 'TANISHKA', 'DoB' => '19-10-2011', 'StudentPhoto' => '6a2 (19).jpeg'),
                788 => array('AppID' => '5827', 'StudentName' => 'YASH GUPTA', 'DoB' => '08-01-2014', 'StudentPhoto' => '6a2 (1).jpeg'),
                789 => array('AppID' => '5828', 'StudentName' => 'AADILA PARVEEN', 'DoB' => '15-06-2011', 'StudentPhoto' => '6a3 (11).jpeg'),
                790 => array('AppID' => '5842', 'StudentName' => 'ABHAY SHUKLA', 'DoB' => '14-04-2013', 'StudentPhoto' => '6a3 (28).jpeg'),
                791 => array('AppID' => '5843', 'StudentName' => 'ADITYA PRATAP SINGH', 'DoB' => '20-03-2013', 'StudentPhoto' => '6a3 (29).jpeg'),
                792 => array('AppID' => '5844', 'StudentName' => 'AKASH RAJPOOT', 'DoB' => '12-05-2012', 'StudentPhoto' => '6a3 (27).jpeg'),
                793 => array('AppID' => '5829', 'StudentName' => 'ANANYA', 'DoB' => '12-04-2012', 'StudentPhoto' => '6a3 (8).jpeg'),
                794 => array('AppID' => '5830', 'StudentName' => 'ARADHYA', 'DoB' => '04-04-2013', 'StudentPhoto' => '6a3 (6).jpeg'),
                795 => array('AppID' => '5845', 'StudentName' => 'ARYAN SINGH', 'DoB' => '27-06-2013', 'StudentPhoto' => '6a3 (25).jpeg'),
                796 => array('AppID' => '5846', 'StudentName' => 'AYUSH RAJPUT', 'DoB' => '10-12-2013', 'StudentPhoto' => '6a3 (24).jpeg'),
                797 => array('AppID' => '5847', 'StudentName' => 'DHAIRY KUMAR', 'DoB' => '08-11-2012', 'StudentPhoto' => '6a3 (26).jpeg'),
                798 => array('AppID' => '5848', 'StudentName' => 'GAGAN', 'DoB' => '15-03-2011', 'StudentPhoto' => '6a3 (23).jpeg'),
                799 => array('AppID' => '5849', 'StudentName' => 'GARV SAINI', 'DoB' => '11-09-2012', 'StudentPhoto' => '6a3 (22).jpeg'),
                800 => array('AppID' => '5831', 'StudentName' => 'KEERTIKA', 'DoB' => '25-05-2012', 'StudentPhoto' => '6a3 (7).jpeg'),
                801 => array('AppID' => '5851', 'StudentName' => 'MO YASEEN KHAN', 'DoB' => '20-08-2013', 'StudentPhoto' => '6a3 (20).jpeg'),
                802 => array('AppID' => '5852', 'StudentName' => 'MOHD HASAN', 'DoB' => '26-11-2011', 'StudentPhoto' => '6a3 (19).jpeg'),
                803 => array('AppID' => '5832', 'StudentName' => 'NAJEEB KHANAM', 'DoB' => '08-09-2011', 'StudentPhoto' => '6a3 (4).jpeg'),
                804 => array('AppID' => '5853', 'StudentName' => 'NAYAN', 'DoB' => '01-01-2010', 'StudentPhoto' => '6a3 (21).jpeg'),
                805 => array('AppID' => '5833', 'StudentName' => 'NIVEDITA SINGH', 'DoB' => '03-10-2013', 'StudentPhoto' => '6a3 (5).jpeg'),
                806 => array('AppID' => '5854', 'StudentName' => 'PRANJUL KUMAR', 'DoB' => '02-11-2012', 'StudentPhoto' => '6a3 (18).jpeg'),
                807 => array('AppID' => '5834', 'StudentName' => 'RAUNAK', 'DoB' => '23-02-2012', 'StudentPhoto' => '6a3 (2).jpeg'),
                808 => array('AppID' => '5855', 'StudentName' => 'RINKU', 'DoB' => '15-07-2011', 'StudentPhoto' => '6a3 (16).jpeg'),
                809 => array('AppID' => '5835', 'StudentName' => 'RIYA', 'DoB' => '02-05-2013', 'StudentPhoto' => '6a3 (3).jpeg'),
                810 => array('AppID' => '5856', 'StudentName' => 'RUDRA KUMAR', 'DoB' => '16-10-2011', 'StudentPhoto' => '6a3 (17).jpeg'),
                811 => array('AppID' => '5836', 'StudentName' => 'SAINOOR KHATOON', 'DoB' => '01-01-2011', 'StudentPhoto' => '6a3 (1).jpeg'),
                812 => array('AppID' => '5857', 'StudentName' => 'SANKALP', 'DoB' => '03-12-2013', 'StudentPhoto' => '6a3 (14).jpeg'),
                813 => array('AppID' => '5837', 'StudentName' => 'SHAILJA SINGH', 'DoB' => '23-03-2012', 'StudentPhoto' => '6a3 (33).jpeg'),
                814 => array('AppID' => '5838', 'StudentName' => 'SHALINEE', 'DoB' => '16-06-2012', 'StudentPhoto' => '6a3 (31).jpeg'),
                815 => array('AppID' => '5858', 'StudentName' => 'SHLOK RAJPOOT', 'DoB' => '22-05-2009', 'StudentPhoto' => '6a3 (15).jpeg'),
                816 => array('AppID' => '5859', 'StudentName' => 'SRAYANSH', 'DoB' => '26-09-2011', 'StudentPhoto' => '6a3 (13).jpeg'),
                817 => array('AppID' => '5839', 'StudentName' => 'SUCHITRA', 'DoB' => '01-01-2012', 'StudentPhoto' => '6a3 (30).jpeg'),
                818 => array('AppID' => '5860', 'StudentName' => 'VAIBHAV KUMAR', 'DoB' => '29-07-2011', 'StudentPhoto' => '6a3 (12).jpeg'),
                819 => array('AppID' => '5861', 'StudentName' => 'YUVRAJ SINGH', 'DoB' => '24-09-2011', 'StudentPhoto' => '6a3 (10).jpeg'),
                820 => array('AppID' => '5841', 'StudentName' => 'ZIKRA FATIMA', 'DoB' => '28-03-2013', 'StudentPhoto' => '6a3 (32).jpeg'),
                821 => array('AppID' => '5862', 'StudentName' => 'AALIYA', 'DoB' => '19-11-2011', 'StudentPhoto' => '6a4 (32).jpeg'),
                822 => array('AppID' => '5863', 'StudentName' => 'ANANYA SONI', 'DoB' => '07-09-2012', 'StudentPhoto' => '6a4 (31).jpeg'),
                823 => array('AppID' => '5864', 'StudentName' => 'APARNA RAJPUT', 'DoB' => '01-06-2012', 'StudentPhoto' => '6a4 (33).jpeg'),
                824 => array('AppID' => '5879', 'StudentName' => 'ARYAN SINGH', 'DoB' => '05-04-2013', 'StudentPhoto' => '6a4 (16).jpeg'),
                825 => array('AppID' => '5865', 'StudentName' => 'ASHI RAJPUT', 'DoB' => '10-06-2012', 'StudentPhoto' => '6a4 (30).jpeg'),
                826 => array('AppID' => '5866', 'StudentName' => 'AYUSHI', 'DoB' => '05-10-2012', 'StudentPhoto' => '6a4 (29).jpeg'),
                827 => array('AppID' => '5880', 'StudentName' => 'DEVANSH RAJPUT', 'DoB' => '18-10-2011', 'StudentPhoto' => '6a4 (15).jpeg'),
                828 => array('AppID' => '5867', 'StudentName' => 'DIVYANSHI', 'DoB' => '26-10-2012', 'StudentPhoto' => '6a4 (27).jpeg'),
                829 => array('AppID' => '5881', 'StudentName' => 'DIVYANSHU RAJPUT', 'DoB' => '06-12-2012', 'StudentPhoto' => '6a4 (13).jpeg'),
                830 => array('AppID' => '5868', 'StudentName' => 'DUAA KHAN', 'DoB' => '29-11-2010', 'StudentPhoto' => '6a4 (28).jpeg'),
                831 => array('AppID' => '5882', 'StudentName' => 'GAGAN RAJ', 'DoB' => '26-08-2012', 'StudentPhoto' => '6a4 (10).jpeg'),
                832 => array('AppID' => '5869', 'StudentName' => 'GARGI BUDHAULIYA', 'DoB' => '01-12-2011', 'StudentPhoto' => '6a4 (26).jpeg'),
                833 => array('AppID' => '5883', 'StudentName' => 'KRITAGYA KHARE', 'DoB' => '03-08-2012', 'StudentPhoto' => '6a4 (11).jpeg'),
                834 => array('AppID' => '5870', 'StudentName' => 'MAHI RAJPOOT', 'DoB' => '04-11-2012', 'StudentPhoto' => '6a4 (24).jpeg'),
                835 => array('AppID' => '5884', 'StudentName' => 'MOHAMMAD AKSAM', 'DoB' => '27-02-2011', 'StudentPhoto' => '6a4 (9).jpeg'),
                836 => array('AppID' => '5871', 'StudentName' => 'NABHYA RAJPOOT', 'DoB' => '01-10-2012', 'StudentPhoto' => '6a4 (25).jpeg'),
                837 => array('AppID' => '5885', 'StudentName' => 'NAITIK RAJPOOT', 'DoB' => '15-06-2011', 'StudentPhoto' => '6a4 (7).jpeg'),
                838 => array('AppID' => '5886', 'StudentName' => 'NARSINGH', 'DoB' => '04-09-2012', 'StudentPhoto' => '6a4 (14).jpeg'),
                839 => array('AppID' => '5872', 'StudentName' => 'NAVYA PALIWAL', 'DoB' => '02-08-2013', 'StudentPhoto' => '6a4 (22).jpeg'),
                840 => array('AppID' => '5873', 'StudentName' => 'PRIYANJALI', 'DoB' => '15-03-2012', 'StudentPhoto' => '6a4 (23).jpeg'),
                841 => array('AppID' => '5887', 'StudentName' => 'RAGHUVANSH', 'DoB' => '09-03-2014', 'StudentPhoto' => '6a4 (8).jpeg'),
                842 => array('AppID' => '5888', 'StudentName' => 'RISHI KUMAR', 'DoB' => '02-10-2012', 'StudentPhoto' => '6a4 (12).jpeg'),
                843 => array('AppID' => '5889', 'StudentName' => 'RUDRA PRATAP', 'DoB' => '19-06-2012', 'StudentPhoto' => '6a4 (5).jpeg'),
                844 => array('AppID' => '5890', 'StudentName' => 'SANSKAR', 'DoB' => '26-09-2013', 'StudentPhoto' => '6a4 (6).jpeg'),
                845 => array('AppID' => '5891', 'StudentName' => 'SARTHAK', 'DoB' => '17-06-2012', 'StudentPhoto' => '6a4 (3).jpeg'),
                846 => array('AppID' => '5892', 'StudentName' => 'SHIVANSH', 'DoB' => '05-12-2013', 'StudentPhoto' => '6a4 (4).jpeg'),
                847 => array('AppID' => '5874', 'StudentName' => 'SMRITI RAJPOOT', 'DoB' => '06-10-2011', 'StudentPhoto' => '6a4 (21).jpeg'),
                848 => array('AppID' => '5875', 'StudentName' => 'UNNATI', 'DoB' => '10-02-2012', 'StudentPhoto' => '6a4 (20).jpeg'),
                849 => array('AppID' => '5893', 'StudentName' => 'VAIBHAV', 'DoB' => '14-09-2011', 'StudentPhoto' => '6a4 (2).jpeg'),
                850 => array('AppID' => '5894', 'StudentName' => 'VANSH KUMAR', 'DoB' => '03-07-2012', 'StudentPhoto' => '6a4 (1).jpeg'),
                851 => array('AppID' => '5907', 'StudentName' => 'AGNESH KUMAR DWIVEDEE', 'DoB' => '12-04-2011', 'StudentPhoto' => '7a1 (21).jpeg'),
                852 => array('AppID' => '5908', 'StudentName' => 'ALOK', 'DoB' => '01-07-2011', 'StudentPhoto' => '7a1 (22).jpeg'),
                853 => array('AppID' => '5909', 'StudentName' => 'AMBAR SAXENA', 'DoB' => '16-09-2013', 'StudentPhoto' => '7a1 (20).jpeg'),
                854 => array('AppID' => '5896', 'StudentName' => 'ANANYA SONI', 'DoB' => '27-02-2012', 'StudentPhoto' => '7a1 (4).jpeg'),
                855 => array('AppID' => '5897', 'StudentName' => 'ANSHIKA RAJPOOT', 'DoB' => '05-05-2011', 'StudentPhoto' => '7a1 (2).jpeg'),
                856 => array('AppID' => '5910', 'StudentName' => 'ANSHUL RAJPUT', 'DoB' => '07-11-2010', 'StudentPhoto' => '7a1 (18).jpeg'),
                857 => array('AppID' => '5898', 'StudentName' => 'AVANTIKA', 'DoB' => '06-07-2012', 'StudentPhoto' => '7a1 (1).jpeg'),
                858 => array('AppID' => '5911', 'StudentName' => 'BOBEE VISHWAKARMA', 'DoB' => '10-07-2012', 'StudentPhoto' => '7a1 (19).jpeg'),
                859 => array('AppID' => '5912', 'StudentName' => 'DEEPANSHU TRIPATHI', 'DoB' => '15-12-2012', 'StudentPhoto' => '7a1 (17).jpeg'),
                860 => array('AppID' => '5913', 'StudentName' => 'GAURAV SINGH PARIHAR', 'DoB' => '01-07-2010', 'StudentPhoto' => '7a1 (15).jpeg'),
                861 => array('AppID' => '5914', 'StudentName' => 'HARSH PRAJAPATI', 'DoB' => '01-01-2012', 'StudentPhoto' => '7a1 (16).jpeg'),
                862 => array('AppID' => '5899', 'StudentName' => 'HARSHITA RAJPOOT', 'DoB' => '20-12-2010', 'StudentPhoto' => '7a1 (30).jpeg'),
                863 => array('AppID' => '5900', 'StudentName' => 'ISHIKA', 'DoB' => '28-06-2010', 'StudentPhoto' => '7a1 (29).jpeg'),
                864 => array('AppID' => '5901', 'StudentName' => 'JANE JOFINA', 'DoB' => '16-10-2011', 'StudentPhoto' => '7a1 (25).jpeg'),
                865 => array('AppID' => '5902', 'StudentName' => 'JANVHI BUDHAULIYA', 'DoB' => '23-08-2011', 'StudentPhoto' => '7a1 (26).jpeg'),
                866 => array('AppID' => '5915', 'StudentName' => 'JEETENDRA', 'DoB' => '08-04-2011', 'StudentPhoto' => '7a1 (14).jpeg'),
                867 => array('AppID' => '5903', 'StudentName' => 'KHUSHI RAJPOOT', 'DoB' => '01-07-2013', 'StudentPhoto' => '7a1 (24).jpeg'),
                868 => array('AppID' => '6031', 'StudentName' => 'MOHD TALHA', 'DoB' => '30-01-2010', 'StudentPhoto' => '8a1 (16).jpeg'),
                869 => array('AppID' => '5904', 'StudentName' => 'PRACHI YADAV', 'DoB' => '13-11-2011', 'StudentPhoto' => '7a1 (27).jpeg'),
                870 => array('AppID' => '5917', 'StudentName' => 'PRINCE PRATAP SINGH', 'DoB' => '24-05-2010', 'StudentPhoto' => '7a1 (11).jpeg'),
                871 => array('AppID' => '5918', 'StudentName' => 'SAMAR MANSURI', 'DoB' => '14-03-2012', 'StudentPhoto' => '7a1 (12).jpeg'),
                872 => array('AppID' => '5905', 'StudentName' => 'SAUMYA', 'DoB' => '26-09-2010', 'StudentPhoto' => '7a1 (28).jpeg'),
                873 => array('AppID' => '5919', 'StudentName' => 'SHIVA', 'DoB' => '01-01-2012', 'StudentPhoto' => '7a1 (10).jpeg'),
                874 => array('AppID' => '5920', 'StudentName' => 'SHIVANSH TIWARI', 'DoB' => '03-12-2012', 'StudentPhoto' => '7a1 (8).jpeg'),
                875 => array('AppID' => '5921', 'StudentName' => 'SHUBH DWIVEDI', 'DoB' => '12-09-2011', 'StudentPhoto' => '7a1 (9).jpeg'),
                876 => array('AppID' => '5906', 'StudentName' => 'TANISHKA', 'DoB' => '03-09-2011', 'StudentPhoto' => '7a1 (23).jpeg'),
                877 => array('AppID' => '5922', 'StudentName' => 'TEJ PRATAP YADAV', 'DoB' => '28-10-2012', 'StudentPhoto' => '7a1 (7).jpeg'),
                878 => array('AppID' => '5923', 'StudentName' => 'VANSH RAJ', 'DoB' => '01-02-2012', 'StudentPhoto' => '7a1 (6).jpeg'),
                879 => array('AppID' => '5924', 'StudentName' => 'VIVEK SINGH', 'DoB' => '06-08-2010', 'StudentPhoto' => '7a1 (5).jpeg'),
                880 => array('AppID' => '5925', 'StudentName' => 'YASH', 'DoB' => '15-06-2013', 'StudentPhoto' => '7a1 (3).jpeg'),
                881 => array('AppID' => '5934', 'StudentName' => 'ADITYA SINGH', 'DoB' => '06-02-2013', 'StudentPhoto' => '7a2 (22).jpeg'),
                882 => array('AppID' => '5935', 'StudentName' => 'AKASH', 'DoB' => '12-07-2009', 'StudentPhoto' => '7a2 (20).jpeg'),
                883 => array('AppID' => '5926', 'StudentName' => 'ALFIYA ALTAF SHAIKH', 'DoB' => '10-10-2009', 'StudentPhoto' => '7a2 (29).jpeg'),
                884 => array('AppID' => '5936', 'StudentName' => 'ALI KHAN', 'DoB' => '25-02-2011', 'StudentPhoto' => '7a2 (18).jpeg'),
                885 => array('AppID' => '5927', 'StudentName' => 'ANJALI VERMA', 'DoB' => '07-03-2012', 'StudentPhoto' => '7a2 (27).jpeg'),
                886 => array('AppID' => '5928', 'StudentName' => 'ARADHYA SINGH RAI', 'DoB' => '25-09-2011', 'StudentPhoto' => '7a2 (28).jpeg'),
                887 => array('AppID' => '5937', 'StudentName' => 'ASHISH KUMAR', 'DoB' => '02-01-2010', 'StudentPhoto' => '7a2 (19).jpeg'),
                888 => array('AppID' => '5938', 'StudentName' => 'ASHVANI KUMAR', 'DoB' => '26-05-2011', 'StudentPhoto' => '7a2 (17).jpeg'),
                889 => array('AppID' => '5939', 'StudentName' => 'DEEPENDRA SINGH', 'DoB' => '08-06-2010', 'StudentPhoto' => '7a2 (15).jpeg'),
                890 => array('AppID' => '5940', 'StudentName' => 'GARV RAJPUT', 'DoB' => '26-01-2012', 'StudentPhoto' => '7a2 (16).jpeg'),
                891 => array('AppID' => '5929', 'StudentName' => 'HARSHITA', 'DoB' => '23-04-2012', 'StudentPhoto' => '7a2 (26).jpeg'),
                892 => array('AppID' => '5941', 'StudentName' => 'HIMANSHU RAJPOOT', 'DoB' => '01-01-2012', 'StudentPhoto' => '7a2 (13).jpeg'),
                893 => array('AppID' => '5930', 'StudentName' => 'ICHHA KUMARI', 'DoB' => '20-03-2012', 'StudentPhoto' => '7a2 (25).jpeg'),
                894 => array('AppID' => '5931', 'StudentName' => 'ISHITA RAJPUT', 'DoB' => '09-08-2011', 'StudentPhoto' => '7a2 (23).jpeg'),
                895 => array('AppID' => '5942', 'StudentName' => 'KAMESH RAJPOOT', 'DoB' => '18-01-2012', 'StudentPhoto' => '7a2 (14).jpeg'),
                896 => array('AppID' => '5943', 'StudentName' => 'KAVY RAJPOOT', 'DoB' => '01-07-2012', 'StudentPhoto' => '7a2 (12).jpeg'),
                897 => array('AppID' => '5932', 'StudentName' => 'MAHIMA GUPTA', 'DoB' => '22-06-2011', 'StudentPhoto' => '7a2 (24).jpeg'),
                898 => array('AppID' => '5954', 'StudentName' => 'MD FARAZ', 'DoB' => '06-08-2011', 'StudentPhoto' => '7a2 (1).jpeg'),
                899 => array('AppID' => '5944', 'StudentName' => 'MRITYUNJAY', 'DoB' => '06-06-2011', 'StudentPhoto' => '7a2 (11).jpeg'),
                900 => array('AppID' => '5945', 'StudentName' => 'OMESH RAJPUT', 'DoB' => '17-09-2010', 'StudentPhoto' => '7a2 (9).jpeg'),
                901 => array('AppID' => '5946', 'StudentName' => 'PRATYAKSH SINGH RAJPOOT', 'DoB' => '10-09-2011', 'StudentPhoto' => '7a2 (10).jpeg'),
                902 => array('AppID' => '5947', 'StudentName' => 'SAGAR', 'DoB' => '01-01-2010', 'StudentPhoto' => '7a2 (8).jpeg'),
                903 => array('AppID' => '5948', 'StudentName' => 'SANIDHYA SHRIVASTAV', 'DoB' => '21-07-2012', 'StudentPhoto' => '7a2 (6).jpeg'),
                904 => array('AppID' => '5949', 'StudentName' => 'SHIVAM RAJPUT', 'DoB' => '13-03-2011', 'StudentPhoto' => '7a2 (7).jpeg'),
                905 => array('AppID' => '5950', 'StudentName' => 'TARUNRAJ', 'DoB' => '23-02-2011', 'StudentPhoto' => '7a2 (4).jpeg'),
                906 => array('AppID' => '5951', 'StudentName' => 'VAIBHAV PRATAP SINGH', 'DoB' => '15-06-2012', 'StudentPhoto' => '7a2 (5).jpeg'),
                907 => array('AppID' => '5952', 'StudentName' => 'VIVEK RAJPOOT', 'DoB' => '01-07-2010', 'StudentPhoto' => '7a2 (2).jpeg'),
                908 => array('AppID' => '5933', 'StudentName' => 'YASHSAVI SAHU', 'DoB' => '30-04-2010', 'StudentPhoto' => '7a2 (21).jpeg'),
                909 => array('AppID' => '5953', 'StudentName' => 'YATHARTH', 'DoB' => '20-10-2013', 'StudentPhoto' => '7a2 (3).jpeg'),
                910 => array('AppID' => '5966', 'StudentName' => 'ABHINAV GUPTA', 'DoB' => '02-12-2010', 'StudentPhoto' => '7a3 (18).jpeg'),
                911 => array('AppID' => '5967', 'StudentName' => 'ADARSH', 'DoB' => '07-02-2010', 'StudentPhoto' => '7a3 (19).jpeg'),
                912 => array('AppID' => '5969', 'StudentName' => 'ALOK SINGH', 'DoB' => '13-05-2012', 'StudentPhoto' => '7a3 (17).jpeg'),
                913 => array('AppID' => '5955', 'StudentName' => 'ANSHIKA SAXENA', 'DoB' => '19-11-2012', 'StudentPhoto' => '7a3 (2).jpeg'),
                914 => array('AppID' => '5970', 'StudentName' => 'CHAHAT', 'DoB' => '01-01-2012', 'StudentPhoto' => '7a3 (14).jpeg'),
                915 => array('AppID' => '5956', 'StudentName' => 'GARIMA', 'DoB' => '07-05-2010', 'StudentPhoto' => '7a3 (28).jpeg'),
                916 => array('AppID' => '5957', 'StudentName' => 'HARSHITA', 'DoB' => '15-09-2011', 'StudentPhoto' => '7a3 (29).jpeg'),
                917 => array('AppID' => '5971', 'StudentName' => 'HIRDYANSH GOKHALE', 'DoB' => '06-09-2010', 'StudentPhoto' => '7a3 (15).jpeg'),
                918 => array('AppID' => '6093', 'StudentName' => 'JIHAN AHMAD', 'DoB' => '18-11-2009', 'StudentPhoto' => '8a3 (14).jpeg'),
                919 => array('AppID' => '5958', 'StudentName' => 'KANCHAN ARYA', 'DoB' => '11-04-2012', 'StudentPhoto' => '7a3 (26).jpeg'),
                920 => array('AppID' => '5972', 'StudentName' => 'KAUSHLENDRA', 'DoB' => '10-07-2011', 'StudentPhoto' => '7a3 (13).jpeg'),
                921 => array('AppID' => '5916', 'StudentName' => 'KRISH KUMAR SONI', 'DoB' => '08-06-2012', 'StudentPhoto' => '7a1 (13).jpeg'),
                922 => array('AppID' => '5959', 'StudentName' => 'KYANA HAYAT FAROOQUE', 'DoB' => '24-10-2012', 'StudentPhoto' => '7a3 (25).jpeg'),
                923 => array('AppID' => '5960', 'StudentName' => 'MANYA MAHESHWARI', 'DoB' => '07-06-2011', 'StudentPhoto' => '7a3 (27).jpeg'),
                924 => array('AppID' => '5974', 'StudentName' => 'MOHD SHAYAN', 'DoB' => '03-11-2011', 'StudentPhoto' => '7a3 (10).jpeg'),
                925 => array('AppID' => '5961', 'StudentName' => 'PRATIGYA', 'DoB' => '03-07-2012', 'StudentPhoto' => '7a3 (24).jpeg'),
                926 => array('AppID' => '5962', 'StudentName' => 'RIDA FATIMA', 'DoB' => '02-01-2011', 'StudentPhoto' => '7a3 (23).jpeg'),
                927 => array('AppID' => '5978', 'StudentName' => 'ROHIT RAJPOOT', 'DoB' => '24-12-2010', 'StudentPhoto' => '7a3 (8).jpeg'),
                928 => array('AppID' => '5979', 'StudentName' => 'SANIDHYA TIWARI', 'DoB' => '09-02-2012', 'StudentPhoto' => '7a3 (9).jpeg'),
                929 => array('AppID' => '5980', 'StudentName' => 'SARTHIK', 'DoB' => '09-02-2012', 'StudentPhoto' => '7a3 (6).jpeg'),
                930 => array('AppID' => '5981', 'StudentName' => 'SHIVA', 'DoB' => '15-01-2012', 'StudentPhoto' => '7a3 (7).jpeg'),
                931 => array('AppID' => '5963', 'StudentName' => 'SHRADDHA GOKHALE', 'DoB' => '06-06-2011', 'StudentPhoto' => '7a3 (21).jpeg'),
                932 => array('AppID' => '5982', 'StudentName' => 'SURYANSH YADAV', 'DoB' => '01-07-2012', 'StudentPhoto' => '7a3 (4).jpeg'),
                933 => array('AppID' => '5983', 'StudentName' => 'UBED HASHMI', 'DoB' => '20-03-2013', 'StudentPhoto' => '7a3 (3).jpeg'),
                934 => array('AppID' => '5964', 'StudentName' => 'VAISHNAVI VERMA', 'DoB' => '28-08-2011', 'StudentPhoto' => '7a3 (20).jpeg'),
                935 => array('AppID' => '5965', 'StudentName' => 'VIBHA RAJPUT', 'DoB' => '16-01-2011', 'StudentPhoto' => '7a3 (22).jpeg'),
                936 => array('AppID' => '5995', 'StudentName' => 'ABDUL AYAN', 'DoB' => '02-04-2011', 'StudentPhoto' => '7a4 (19).jpeg'),
                937 => array('AppID' => '5996', 'StudentName' => 'ABHISHEK YADAV', 'DoB' => '01-06-2012', 'StudentPhoto' => '7a4 (21).jpeg'),
                938 => array('AppID' => '5997', 'StudentName' => 'ADITYA RAJPUT', 'DoB' => '01-07-2010', 'StudentPhoto' => '7a4 (17).jpeg'),
                939 => array('AppID' => '5998', 'StudentName' => 'ANKIT', 'DoB' => '15-07-2010', 'StudentPhoto' => '7a4 (18).jpeg'),
                940 => array('AppID' => '5986', 'StudentName' => 'ANSHIKA SINGH', 'DoB' => '30-10-2013', 'StudentPhoto' => '7a4 (28).jpeg'),
                941 => array('AppID' => '5987', 'StudentName' => 'AROHI GUPTA', 'DoB' => '29-10-2011', 'StudentPhoto' => '7a4 (29).jpeg'),
                942 => array('AppID' => '5999', 'StudentName' => 'ARPIT TRIPATHI', 'DoB' => '27-01-2011', 'StudentPhoto' => '7a4 (16).jpeg'),
                943 => array('AppID' => '6000', 'StudentName' => 'DIVYANSH', 'DoB' => '07-06-2011', 'StudentPhoto' => '7a4 (14).jpeg'),
                944 => array('AppID' => '5988', 'StudentName' => 'GARIMA VERMA', 'DoB' => '22-01-2011', 'StudentPhoto' => '7a4 (26).jpeg'),
                945 => array('AppID' => '6001', 'StudentName' => 'GAURAV', 'DoB' => '17-10-2011', 'StudentPhoto' => '7a4 (13).jpeg'),
                946 => array('AppID' => '5989', 'StudentName' => 'HARSHITA', 'DoB' => '02-05-2010', 'StudentPhoto' => '7a4 (27).jpeg'),
                947 => array('AppID' => '5990', 'StudentName' => 'JOHANAZ', 'DoB' => '07-01-2011', 'StudentPhoto' => '7a4 (24).jpeg'),
                948 => array('AppID' => '5991', 'StudentName' => 'KANISHKA', 'DoB' => '12-11-2011', 'StudentPhoto' => '7a4 (25).jpeg'),
                949 => array('AppID' => '6003', 'StudentName' => 'KRISHNAM UDENIYA', 'DoB' => '16-06-2011', 'StudentPhoto' => '7a4 (12).jpeg'),
                950 => array('AppID' => '6004', 'StudentName' => 'LOVEKESH', 'DoB' => '13-05-2013', 'StudentPhoto' => '7a4 (11).jpeg'),
                951 => array('AppID' => '6005', 'StudentName' => 'MOHAMMAD ZYAUL', 'DoB' => '01-02-2012', 'StudentPhoto' => '7a4 (9).jpeg'),
                952 => array('AppID' => '6006', 'StudentName' => 'NAVNEET RAJPUT', 'DoB' => '12-04-2012', 'StudentPhoto' => '7a4 (8).jpeg'),
                953 => array('AppID' => '5992', 'StudentName' => 'POOJA SAINI', 'DoB' => '02-03-2010', 'StudentPhoto' => '7a4 (23).jpeg'),
                954 => array('AppID' => '6007', 'StudentName' => 'PRAJJWAL SINGH', 'DoB' => '03-11-2011', 'StudentPhoto' => '7a4 (10).jpeg'),
                955 => array('AppID' => '6008', 'StudentName' => 'PRIYANSHU', 'DoB' => '07-03-2011', 'StudentPhoto' => '7a4 (6).jpeg'),
                956 => array('AppID' => '6009', 'StudentName' => 'PURVANSH GUPTA', 'DoB' => '15-01-2012', 'StudentPhoto' => '7a4 (7).jpeg'),
                957 => array('AppID' => '6010', 'StudentName' => 'RAN VIJAY', 'DoB' => '01-01-2011', 'StudentPhoto' => '7a4 (5).jpeg'),
                958 => array('AppID' => '6011', 'StudentName' => 'SAYYAD SANIB ALI', 'DoB' => '16-01-2009', 'StudentPhoto' => '7a4 (4).jpeg'),
                959 => array('AppID' => '6012', 'StudentName' => 'SHUBH RAJPOOT', 'DoB' => '04-04-2012', 'StudentPhoto' => '7a4 (2).jpeg'),
                960 => array('AppID' => '5993', 'StudentName' => 'SNEHA', 'DoB' => '06-07-2010', 'StudentPhoto' => '7a4 (22).jpeg'),
                961 => array('AppID' => '6013', 'StudentName' => 'TANISH SINGH', 'DoB' => '08-11-2011', 'StudentPhoto' => '7a4 (3).jpeg'),
                962 => array('AppID' => '5994', 'StudentName' => 'TRAPTI DWIVEDI', 'DoB' => '31-12-2012', 'StudentPhoto' => '7a4 (20).jpeg'),
                963 => array('AppID' => '6014', 'StudentName' => 'VIVEK RAJPOOT', 'DoB' => '16-03-2011', 'StudentPhoto' => '7a4 (1).jpeg'),
                964 => array('AppID' => '6015', 'StudentName' => 'AADYA AGRAWAL', 'DoB' => '20-12-2010', 'StudentPhoto' => '8a1 (4).jpeg'),
                965 => array('AppID' => '6023', 'StudentName' => 'ABHISHEK RAJPUT', 'DoB' => '05-07-2010', 'StudentPhoto' => '8a1 (26).jpeg'),
                966 => array('AppID' => '6024', 'StudentName' => 'ADITYA SINGH', 'DoB' => '10-10-2009', 'StudentPhoto' => '8a1 (24).jpeg'),
                967 => array('AppID' => '6025', 'StudentName' => 'AKSHAY PRATAP SINGH', 'DoB' => '01-07-2010', 'StudentPhoto' => '8a1 (22).jpeg'),
                968 => array('AppID' => '6016', 'StudentName' => 'ARADHYA', 'DoB' => '08-09-2011', 'StudentPhoto' => '8a1 (2).jpeg'),
                969 => array('AppID' => '6017', 'StudentName' => 'DEEPANJALI SHRIVASTAVA', 'DoB' => '06-06-2011', 'StudentPhoto' => '8a1 (1).jpeg'),
                970 => array('AppID' => '6026', 'StudentName' => 'DEEPENDRA KUMAR', 'DoB' => '21-11-2010', 'StudentPhoto' => '8a1 (23).jpeg'),
                971 => array('AppID' => '6027', 'StudentName' => 'DEVANSH', 'DoB' => '15-05-2010', 'StudentPhoto' => '8a1 (20).jpeg'),
                972 => array('AppID' => '6028', 'StudentName' => 'DHANANJAY PRATAP SINGH', 'DoB' => '06-07-2012', 'StudentPhoto' => '8a1 (21).jpeg'),
                973 => array('AppID' => '6018', 'StudentName' => 'DIVYANSHI RAJPUT', 'DoB' => '03-12-2010', 'StudentPhoto' => '8a1 (30).jpeg'),
                974 => array('AppID' => '6029', 'StudentName' => 'KARTIK YADAV', 'DoB' => '01-10-2011', 'StudentPhoto' => '8a1 (19).jpeg'),
                975 => array('AppID' => '6019', 'StudentName' => 'KASHISH GAUTAM', 'DoB' => '17-02-2009', 'StudentPhoto' => '8a1 (28).jpeg'),
                976 => array('AppID' => '6030', 'StudentName' => 'MOHAMMAD MANNAN KHAN', 'DoB' => '07-07-2011', 'StudentPhoto' => '8a1 (18).jpeg'),
                977 => array('AppID' => '6032', 'StudentName' => 'MUHAMMAD KASHIF KHAN', 'DoB' => '27-03-2010', 'StudentPhoto' => '8a1 (17).jpeg'),
                978 => array('AppID' => '6033', 'StudentName' => 'NIMAY RAJPOOT', 'DoB' => '16-11-2009', 'StudentPhoto' => '8a1 (15).jpeg'),
                979 => array('AppID' => '6034', 'StudentName' => 'PIYUSH KUMAR', 'DoB' => '10-07-2011', 'StudentPhoto' => '8a1 (13).jpeg'),
                980 => array('AppID' => '6035', 'StudentName' => 'PRAYASH SINGH RAJPUT', 'DoB' => '20-11-2011', 'StudentPhoto' => '8a1 (14).jpeg'),
                981 => array('AppID' => '6036', 'StudentName' => 'RAM GUPTA', 'DoB' => '20-04-2010', 'StudentPhoto' => '8a1 (11).jpeg'),
                982 => array('AppID' => '6020', 'StudentName' => 'RIMI RAJPOOT', 'DoB' => '08-07-2012', 'StudentPhoto' => '8a1 (27).jpeg'),
                983 => array('AppID' => '6037', 'StudentName' => 'RISHABH', 'DoB' => '05-01-2011', 'StudentPhoto' => '8a1 (10).jpeg'),
                984 => array('AppID' => '6038', 'StudentName' => 'SARTHAK PURWAR', 'DoB' => '14-03-2010', 'StudentPhoto' => '8a1 (12).jpeg'),
                985 => array('AppID' => '6039', 'StudentName' => 'SHANTANU SINGH', 'DoB' => '19-08-2010', 'StudentPhoto' => '8a1 (8).jpeg'),
                986 => array('AppID' => '6040', 'StudentName' => 'SHIVA AWASTHI', 'DoB' => '06-09-2009', 'StudentPhoto' => '8a1 (9).jpeg'),
                987 => array('AppID' => '6021', 'StudentName' => 'SHRIYANSHI DWIVEDI', 'DoB' => '01-02-2012', 'StudentPhoto' => '8a1 (29).jpeg'),
                988 => array('AppID' => '6041', 'StudentName' => 'SUYASH NAGAYACH', 'DoB' => '15-08-2013', 'StudentPhoto' => '8a1 (6).jpeg'),
                989 => array('AppID' => '6022', 'StudentName' => 'TALVIYA VARSI', 'DoB' => '06-03-2010', 'StudentPhoto' => '8a1 (25).jpeg'),
                990 => array('AppID' => '6042', 'StudentName' => 'UTSAH GUPTA', 'DoB' => '23-11-2010', 'StudentPhoto' => '8a1 (7).jpeg'),
                991 => array('AppID' => '6043', 'StudentName' => 'VAIBHAV RAJPUT', 'DoB' => '22-11-2010', 'StudentPhoto' => '8a1 (5).jpeg'),
                992 => array('AppID' => '6044', 'StudentName' => 'YASH GUPTA', 'DoB' => '11-05-2011', 'StudentPhoto' => '8a1 (3).jpeg'),
                993 => array('AppID' => '6045', 'StudentName' => 'AASRA', 'DoB' => '15-08-2010', 'StudentPhoto' => '8a2 (29).jpeg'),
                994 => array('AppID' => '6053', 'StudentName' => 'ABHISHEK', 'DoB' => '12-08-2009', 'StudentPhoto' => '8a2 (21).jpeg'),
                995 => array('AppID' => '6054', 'StudentName' => 'ADARSH MAHAN', 'DoB' => '12-12-2010', 'StudentPhoto' => '8a2 (22).jpeg'),
                996 => array('AppID' => '6055', 'StudentName' => 'ADITYA KUMAR', 'DoB' => '29-09-2010', 'StudentPhoto' => '8a2 (20).jpeg'),
                997 => array('AppID' => '6056', 'StudentName' => 'ANKIT VERMA', 'DoB' => '22-07-2010', 'StudentPhoto' => '8a2 (18).jpeg'),
                998 => array('AppID' => '6057', 'StudentName' => 'ANSH RAJPUT', 'DoB' => '12-08-2010', 'StudentPhoto' => '8a2 (19).jpeg'),
                999 => array('AppID' => '6058', 'StudentName' => 'ANSH SHUKLA', 'DoB' => '26-10-2010', 'StudentPhoto' => '8a2 (16).jpeg'),
                1000 => array('AppID' => '6046', 'StudentName' => 'ANUSHKA', 'DoB' => '09-01-2009', 'StudentPhoto' => '8a2 (30).jpeg'),
                1001 => array('AppID' => '6047', 'StudentName' => 'ASTHA', 'DoB' => '30-04-2010', 'StudentPhoto' => '8a2 (27).jpeg'),
                1002 => array('AppID' => '6059', 'StudentName' => 'AYUSH', 'DoB' => '20-06-2010', 'StudentPhoto' => '8a2 (17).jpeg'),
                1003 => array('AppID' => '6060', 'StudentName' => 'BHAVISHYA RAJPUT', 'DoB' => '30-10-2011', 'StudentPhoto' => '8a2 (15).jpeg'),
                1004 => array('AppID' => '6061', 'StudentName' => 'DEV MISHRA', 'DoB' => '01-08-2011', 'StudentPhoto' => '8a2 (13).jpeg'),
                1005 => array('AppID' => '6062', 'StudentName' => 'DIVYANSHU RAJPUT', 'DoB' => '29-03-2010', 'StudentPhoto' => '8a2 (12).jpeg'),
                1006 => array('AppID' => '6048', 'StudentName' => 'LIPI', 'DoB' => '13-10-2011', 'StudentPhoto' => '8a2 (28).jpeg'),
                1007 => array('AppID' => '6049', 'StudentName' => 'MADEEHA FATIMA', 'DoB' => '13-01-2011', 'StudentPhoto' => '8a2 (26).jpeg'),
                1008 => array('AppID' => '6063', 'StudentName' => 'MANEESH KUMAR', 'DoB' => '09-06-2010', 'StudentPhoto' => '8a2 (14).jpeg'),
                1009 => array('AppID' => '6064', 'StudentName' => 'MOHAMMAD RAZA KHAN', 'DoB' => '23-02-2010', 'StudentPhoto' => '8a2 (11).jpeg'),
                1010 => array('AppID' => '6065', 'StudentName' => 'MOHD ARSH RAYEEN', 'DoB' => '25-09-2011', 'StudentPhoto' => '8a2 (9).jpeg'),
                1011 => array('AppID' => '6066', 'StudentName' => 'MUDASSIR ANSARI', 'DoB' => '09-09-2009', 'StudentPhoto' => '8a2 (10).jpeg'),
                1012 => array('AppID' => '6067', 'StudentName' => 'RAJDEEP', 'DoB' => '04-02-2010', 'StudentPhoto' => '8a2 (7).jpeg'),
                1013 => array('AppID' => '6068', 'StudentName' => 'RANVEER SINGH RAJPOOT', 'DoB' => '14-10-2011', 'StudentPhoto' => '8a2 (8).jpeg'),
                1014 => array('AppID' => '6069', 'StudentName' => 'RIYANSH', 'DoB' => '06-08-2011', 'StudentPhoto' => '8a2 (6).jpeg'),
                1015 => array('AppID' => '6050', 'StudentName' => 'SANIYA KHAN', 'DoB' => '05-08-2010', 'StudentPhoto' => '8a2 (25).jpeg'),
                1016 => array('AppID' => '6070', 'StudentName' => 'SARTHAK GUPTA', 'DoB' => '10-11-2010', 'StudentPhoto' => '8a2 (4).jpeg'),
                1017 => array('AppID' => '6071', 'StudentName' => 'SHIVA RAJPOOT', 'DoB' => '18-08-2010', 'StudentPhoto' => '8a2 (5).jpeg'),
                1018 => array('AppID' => '6051', 'StudentName' => 'SHREYA GUPTA', 'DoB' => '26-04-2011', 'StudentPhoto' => '8a2 (23).jpeg'),
                1019 => array('AppID' => '6072', 'StudentName' => 'SIDDHANT MAHAN', 'DoB' => '29-07-2011', 'StudentPhoto' => '8a2 (2).jpeg'),
                1020 => array('AppID' => '6073', 'StudentName' => 'SOM GARG', 'DoB' => '23-06-2010', 'StudentPhoto' => '8a2 (3).jpeg'),
                1021 => array('AppID' => '6074', 'StudentName' => 'SWASTIK KASHYAP', 'DoB' => '12-01-2010', 'StudentPhoto' => '8a2 (1).jpeg'),
                1022 => array('AppID' => '6052', 'StudentName' => 'VAISHNAVI GUPTA', 'DoB' => '10-03-2010', 'StudentPhoto' => '8a2 (24).jpeg'),
                1023 => array('AppID' => '6082', 'StudentName' => 'ABDUL TALIB', 'DoB' => '25-01-2011', 'StudentPhoto' => '8a3 (25).jpeg'),
                1024 => array('AppID' => '6083', 'StudentName' => 'ABHINESH', 'DoB' => '01-01-2010', 'StudentPhoto' => '8a3 (26).jpeg'),
                1025 => array('AppID' => '6084', 'StudentName' => 'AKHIL KUMAR', 'DoB' => '30-07-2011', 'StudentPhoto' => '8a3 (23).jpeg'),
                1026 => array('AppID' => '6085', 'StudentName' => 'AMIT KUMAR', 'DoB' => '03-01-2011', 'StudentPhoto' => '8a3 (24).jpeg'),
                1027 => array('AppID' => '6086', 'StudentName' => 'ANSH RAJPUT', 'DoB' => '10-10-2010', 'StudentPhoto' => '8a3 (21).jpeg'),
                1028 => array('AppID' => '6075', 'StudentName' => 'APEKSHA KHARE', 'DoB' => '04-02-2011', 'StudentPhoto' => '8a3 (3).jpeg'),
                1029 => array('AppID' => '6087', 'StudentName' => 'ARNAV SINGH', 'DoB' => '27-10-2011', 'StudentPhoto' => '8a3 (22).jpeg'),
                1030 => array('AppID' => '6088', 'StudentName' => 'AYUSH RAJ', 'DoB' => '16-09-2010', 'StudentPhoto' => '8a3 (19).jpeg'),
                1031 => array('AppID' => '6076', 'StudentName' => 'AYUSHI SINGH', 'DoB' => '10-03-2011', 'StudentPhoto' => '8a3 (2).jpeg'),
                1032 => array('AppID' => '6089', 'StudentName' => 'CHANDRAMOHAN', 'DoB' => '25-07-2011', 'StudentPhoto' => '8a3 (18).jpeg'),
                1033 => array('AppID' => '6090', 'StudentName' => 'DEEPANSHU RAJPUT', 'DoB' => '16-02-2011', 'StudentPhoto' => '8a3 (20).jpeg'),
                1034 => array('AppID' => '6091', 'StudentName' => 'FARHAN BEG', 'DoB' => '01-01-2011', 'StudentPhoto' => '8a3 (17).jpeg'),
                1035 => array('AppID' => '6092', 'StudentName' => 'GAURAV NAYAK', 'DoB' => '27-08-2009', 'StudentPhoto' => '8a3 (15).jpeg'),
                1036 => array('AppID' => '6077', 'StudentName' => 'INSHA', 'DoB' => '11-04-2010', 'StudentPhoto' => '8a3 (1).jpeg'),
                1037 => array('AppID' => '6094', 'StudentName' => 'KAUTILYA', 'DoB' => '05-03-2010', 'StudentPhoto' => '8a3 (16).jpeg'),
                1038 => array('AppID' => '6104', 'StudentName' => 'LAKSHYA NISHAD', 'DoB' => '21-07-2011', 'StudentPhoto' => '8a3 (5).jpeg'),
                1039 => array('AppID' => '6078', 'StudentName' => 'MANVI', 'DoB' => '29-08-2011', 'StudentPhoto' => '8a3 (30).jpeg'),
                1040 => array('AppID' => '6095', 'StudentName' => 'MOHD AHKAM KHAN', 'DoB' => '14-03-2011', 'StudentPhoto' => '8a3 (12).jpeg'),
                1041 => array('AppID' => '6079', 'StudentName' => 'MOHINI GUPTA', 'DoB' => '01-08-2010', 'StudentPhoto' => '8a3 (27).jpeg'),
                1042 => array('AppID' => '6080', 'StudentName' => 'NEELAM', 'DoB' => '17-11-2010', 'StudentPhoto' => '8a3 (29).jpeg'),
                1043 => array('AppID' => '6096', 'StudentName' => 'PRAVEEN', 'DoB' => '29-07-2007', 'StudentPhoto' => '8a3 (13).jpeg'),
                1044 => array('AppID' => '6097', 'StudentName' => 'PRAVESH KUMAR', 'DoB' => '18-04-2010', 'StudentPhoto' => '8a3 (10).jpeg'),
                1045 => array('AppID' => '6098', 'StudentName' => 'RAJ RAJPOOT', 'DoB' => '22-12-2011', 'StudentPhoto' => '8a3 (11).jpeg'),
                1046 => array('AppID' => '6099', 'StudentName' => 'RISHABH', 'DoB' => '16-08-2012', 'StudentPhoto' => '8a3 (9).jpeg'),
                1047 => array('AppID' => '6100', 'StudentName' => 'RISHABH GAUTAM', 'DoB' => '18-07-2010', 'StudentPhoto' => '8a3 (8).jpeg'),
                1048 => array('AppID' => '6101', 'StudentName' => 'SHEIKH ALQAWI', 'DoB' => '24-04-2010', 'StudentPhoto' => '8a3 (6).jpeg'),
                1049 => array('AppID' => '6102', 'StudentName' => 'UMANG KUMAR', 'DoB' => '01-04-2009', 'StudentPhoto' => '8a3 (7).jpeg'),
                1050 => array('AppID' => '6081', 'StudentName' => 'UMMATUL FATIMA', 'DoB' => '30-07-2010', 'StudentPhoto' => '8a3 (28).jpeg'),
                1051 => array('AppID' => '6103', 'StudentName' => 'YOGESH SINGH', 'DoB' => '24-09-2010', 'StudentPhoto' => '8a3 (4).jpeg'),
                1052 => array('AppID' => '6115', 'StudentName' => 'AADITYA SINGH', 'DoB' => '30-07-2011', 'StudentPhoto' => '8a4 (22).jpeg'),
                1053 => array('AppID' => '6116', 'StudentName' => 'ADITYA KUMAR', 'DoB' => '30-06-2011', 'StudentPhoto' => '8a4 (19).jpeg'),
                1054 => array('AppID' => '6117', 'StudentName' => 'AJINKYA KHARE', 'DoB' => '07-05-2011', 'StudentPhoto' => '8a4 (17).jpeg'),
                1055 => array('AppID' => '6118', 'StudentName' => 'ANMOL SONI', 'DoB' => '26-11-2010', 'StudentPhoto' => '8a4 (16).jpeg'),
                1056 => array('AppID' => '6119', 'StudentName' => 'ANURAG SINGH', 'DoB' => '28-02-2010', 'StudentPhoto' => '8a4 (18).jpeg'),
                1057 => array('AppID' => '6120', 'StudentName' => 'ARUSH', 'DoB' => '05-06-2010', 'StudentPhoto' => '8a4 (14).jpeg'),
                1058 => array('AppID' => '6105', 'StudentName' => 'ARUSHI AHIRWAR', 'DoB' => '24-07-2011', 'StudentPhoto' => '8a4 (29).jpeg'),
                1059 => array('AppID' => '6121', 'StudentName' => 'AYUSH', 'DoB' => '01-10-2010', 'StudentPhoto' => '8a4 (15).jpeg'),
                1060 => array('AppID' => '6106', 'StudentName' => 'CHANCHAL', 'DoB' => '11-05-2011', 'StudentPhoto' => '8a4 (30).jpeg'),
                1061 => array('AppID' => '6107', 'StudentName' => 'DEEKSHA RAJPUT', 'DoB' => '05-04-2011', 'StudentPhoto' => '8a4 (27).jpeg'),
                1062 => array('AppID' => '6122', 'StudentName' => 'HAPPY RAJPUT', 'DoB' => '24-11-2011', 'StudentPhoto' => '8a4 (12).jpeg'),
                1063 => array('AppID' => '6123', 'StudentName' => 'HARSH KUMAR', 'DoB' => '09-04-2010', 'StudentPhoto' => '8a4 (13).jpeg'),
                1064 => array('AppID' => '6124', 'StudentName' => 'HARSH SINGH PARIHAR', 'DoB' => '14-05-2010', 'StudentPhoto' => '8a4 (11).jpeg'),
                1065 => array('AppID' => '6125', 'StudentName' => 'HARSHIT RAJPUT', 'DoB' => '12-06-2011', 'StudentPhoto' => '8a4 (9).jpeg'),
                1066 => array('AppID' => '6126', 'StudentName' => 'KAPTAN SINGH', 'DoB' => '17-05-2011', 'StudentPhoto' => '8a4 (10).jpeg'),
                1067 => array('AppID' => '6108', 'StudentName' => 'MITANSHI SINHA', 'DoB' => '06-01-2010', 'StudentPhoto' => '8a4 (26).jpeg'),
                1068 => array('AppID' => '6134', 'StudentName' => 'MOHMMAD SAIF', 'DoB' => '10-07-2009', 'StudentPhoto' => '8a4 (1).jpeg'),
                1069 => array('AppID' => '6127', 'StudentName' => 'NEELESH', 'DoB' => '06-06-2011', 'StudentPhoto' => '8a4 (7).jpeg'),
                1070 => array('AppID' => '6109', 'StudentName' => 'PRASHA TRIPATHI', 'DoB' => '17-11-2011', 'StudentPhoto' => '8a4 (28).jpeg'),
                1071 => array('AppID' => '6128', 'StudentName' => 'RAJ RAJPUT', 'DoB' => '01-01-2010', 'StudentPhoto' => '8a4 (8).jpeg'),
                1072 => array('AppID' => '6129', 'StudentName' => 'RAMJI GUPTA', 'DoB' => '15-01-2010', 'StudentPhoto' => '8a4 (5).jpeg'),
                1073 => array('AppID' => '6130', 'StudentName' => 'RISHI VERMA', 'DoB' => '25-08-2010', 'StudentPhoto' => '8a4 (6).jpeg'),
                1074 => array('AppID' => '6131', 'StudentName' => 'RISHIKANT RAJPOOT', 'DoB' => '01-01-2011', 'StudentPhoto' => '8a4 (4).jpeg'),
                1075 => array('AppID' => '6132', 'StudentName' => 'SAKSHAM SINGH', 'DoB' => '04-03-2010', 'StudentPhoto' => '8a4 (2).jpeg'),
                1076 => array('AppID' => '6133', 'StudentName' => 'SAMEER VERMA', 'DoB' => '30-03-2010', 'StudentPhoto' => '8a4 (3).jpeg'),
                1077 => array('AppID' => '6110', 'StudentName' => 'SHANIYA', 'DoB' => '16-02-2009', 'StudentPhoto' => '8a4 (24).jpeg'),
                1078 => array('AppID' => '6111', 'StudentName' => 'SHIVI', 'DoB' => '01-07-2009', 'StudentPhoto' => '8a4 (23).jpeg'),
                1079 => array('AppID' => '6112', 'StudentName' => 'SIHVI', 'DoB' => '04-01-2010', 'StudentPhoto' => '8a4 (25).jpeg'),
                1080 => array('AppID' => '6113', 'StudentName' => 'SUHANI', 'DoB' => '22-11-2010', 'StudentPhoto' => '8a4 (21).jpeg'),
                1081 => array('AppID' => '6114', 'StudentName' => 'YASHASVINI', 'DoB' => '02-05-2010', 'StudentPhoto' => '8a4 (20).jpeg'),
                1082 => array('AppID' => '6150', 'StudentName' => 'ABHAY PRATAP SINGH', 'DoB' => '11-10-2011', 'StudentPhoto' => '9a1 (21).jpeg'),
                1083 => array('AppID' => '6151', 'StudentName' => 'ABHISHEK', 'DoB' => '16-12-2008', 'StudentPhoto' => '9a1 (20).jpeg'),
                1084 => array('AppID' => '6135', 'StudentName' => 'AKSHARA GUPTA', 'DoB' => '01-10-2009', 'StudentPhoto' => '9a1 (3).jpeg'),
                1085 => array('AppID' => '6136', 'StudentName' => 'ANAM MANSOORI', 'DoB' => '13-11-2010', 'StudentPhoto' => '9a1 (1).jpeg'),
                1086 => array('AppID' => '6152', 'StudentName' => 'ANIKET DWIVEDI', 'DoB' => '04-10-2008', 'StudentPhoto' => '9a1 (18).jpeg'),
                1087 => array('AppID' => '6153', 'StudentName' => 'ANSH SINGH', 'DoB' => '08-12-2008', 'StudentPhoto' => '9a1 (19).jpeg'),
                1088 => array('AppID' => '6137', 'StudentName' => 'ANSHIKA YADAV', 'DoB' => '31-03-2009', 'StudentPhoto' => '9a1 (2).jpeg'),
                1089 => array('AppID' => '6138', 'StudentName' => 'ARBIYA FATIMA', 'DoB' => '12-09-2009', 'StudentPhoto' => '9a1 (31).jpeg'),
                1090 => array('AppID' => '6139', 'StudentName' => 'ASTHA', 'DoB' => '03-10-2010', 'StudentPhoto' => '9a1 (32).jpeg'),
                1091 => array('AppID' => '6154', 'StudentName' => 'AVYANSH SINGH PARIHAR', 'DoB' => '02-12-2009', 'StudentPhoto' => '9a1 (16).jpeg'),
                1092 => array('AppID' => '6140', 'StudentName' => 'BHOOMIKA SINGH', 'DoB' => '18-06-2010', 'StudentPhoto' => '9a1 (29).jpeg'),
                1093 => array('AppID' => '6155', 'StudentName' => 'DEVANSH DWIVEDI', 'DoB' => '06-04-2010', 'StudentPhoto' => '9a1 (17).jpeg'),
                1094 => array('AppID' => '6141', 'StudentName' => 'DIVYANSHI VERMA', 'DoB' => '07-11-2009', 'StudentPhoto' => '9a1 (30).jpeg'),
                1095 => array('AppID' => '6156', 'StudentName' => 'ESHANT RAJPOOT', 'DoB' => '07-08-2009', 'StudentPhoto' => '9a1 (15).jpeg'),
                1096 => array('AppID' => '6157', 'StudentName' => 'GAURAV NAGAYACH', 'DoB' => '13-09-2010', 'StudentPhoto' => '9a1 (13).jpeg'),
                1097 => array('AppID' => '6142', 'StudentName' => 'HIMANSHI GUPTA', 'DoB' => '06-07-2008', 'StudentPhoto' => '9a1 (28).jpeg'),
                1098 => array('AppID' => '6143', 'StudentName' => 'INSHA SUBHAN', 'DoB' => '29-01-2010', 'StudentPhoto' => '9a1 (27).jpeg'),
                1099 => array('AppID' => '6144', 'StudentName' => 'KAVYA VERMA', 'DoB' => '05-06-2009', 'StudentPhoto' => '9a1 (26).jpeg'),
                1100 => array('AppID' => '6158', 'StudentName' => 'LAKSHYA SONI', 'DoB' => '21-10-2010', 'StudentPhoto' => '9a1 (14).jpeg'),
                1101 => array('AppID' => '6145', 'StudentName' => 'MAHI MEHER', 'DoB' => '09-02-2008', 'StudentPhoto' => '9a1 (24).jpeg'),
                1102 => array('AppID' => '6159', 'StudentName' => 'MOHAMMAD FAIZ', 'DoB' => '19-07-2009', 'StudentPhoto' => '9a1 (12).jpeg'),
                1103 => array('AppID' => '6146', 'StudentName' => 'NANDANI SONI', 'DoB' => '16-08-2010', 'StudentPhoto' => '9a1 (23).jpeg'),
                1104 => array('AppID' => '6160', 'StudentName' => 'PRASHANT RAJPOOT', 'DoB' => '17-03-2009', 'StudentPhoto' => '9a1 (11).jpeg'),
                1105 => array('AppID' => '6161', 'StudentName' => 'PRYANSH KUMAR RAJPOOT', 'DoB' => '04-02-2010', 'StudentPhoto' => '9a1 (9).jpeg'),
                1106 => array('AppID' => '6147', 'StudentName' => 'RITIKA', 'DoB' => '03-07-2010', 'StudentPhoto' => '9a1 (25).jpeg'),
                1107 => array('AppID' => '6162', 'StudentName' => 'RUDRAKSHA PURWAR', 'DoB' => '25-10-2010', 'StudentPhoto' => '9a1 (10).jpeg'),
                1108 => array('AppID' => '6163', 'StudentName' => 'SATYAM', 'DoB' => '14-06-2009', 'StudentPhoto' => '9a1 (8).jpeg'),
                1109 => array('AppID' => '6149', 'StudentName' => 'SAUMYA', 'DoB' => '29-03-2010', 'StudentPhoto' => '9a1 (22).jpeg'),
                1110 => array('AppID' => '6164', 'StudentName' => 'SHUBHANSHIT SINGH', 'DoB' => '26-12-2009', 'StudentPhoto' => '9a1 (6).jpeg'),
                1111 => array('AppID' => '6165', 'StudentName' => 'UMAR ULLA KHAN', 'DoB' => '09-09-2008', 'StudentPhoto' => '9a1 (7).jpeg'),
                1112 => array('AppID' => '6166', 'StudentName' => 'VEDANSH', 'DoB' => '07-05-2008', 'StudentPhoto' => '9a1 (4).jpeg'),
                1113 => array('AppID' => '6167', 'StudentName' => 'YASHWARDHAN SINGH', 'DoB' => '11-06-2010', 'StudentPhoto' => '9a1 (5).jpeg'),
                1114 => array('AppID' => '6168', 'StudentName' => 'AARNA SINGH', 'DoB' => '04-08-2009', 'StudentPhoto' => '9a2 (32).jpeg'),
                1115 => array('AppID' => '6180', 'StudentName' => 'ABHISHEK RAJPUT', 'DoB' => '18-03-2009', 'StudentPhoto' => '9a2 (20).jpeg'),
                1116 => array('AppID' => '6169', 'StudentName' => 'ANGEL', 'DoB' => '10-04-2010', 'StudentPhoto' => '9a2 (33).jpeg'),
                1117 => array('AppID' => '6181', 'StudentName' => 'ANIKET', 'DoB' => '25-08-2011', 'StudentPhoto' => '9a2 (21).jpeg'),
                1118 => array('AppID' => '6182', 'StudentName' => 'ANSH SINGH', 'DoB' => '17-10-2009', 'StudentPhoto' => '9a2 (19).jpeg'),
                1119 => array('AppID' => '6183', 'StudentName' => 'ANSHUMAN', 'DoB' => '08-06-2010', 'StudentPhoto' => '9a2 (17).jpeg'),
                1120 => array('AppID' => '6200', 'StudentName' => 'ANVESHA', 'DoB' => '16-05-2010', 'StudentPhoto' => '9a2 (2).jpeg'),
                1121 => array('AppID' => '6184', 'StudentName' => 'ARYAN', 'DoB' => '02-07-2009', 'StudentPhoto' => '9a2 (18).jpeg'),
                1122 => array('AppID' => '6198', 'StudentName' => 'ASHISH KUMAR', 'DoB' => '09-08-2010', 'StudentPhoto' => '9a2 (3).jpeg'),
                1123 => array('AppID' => '6185', 'StudentName' => 'BALENDRA RAJPUT', 'DoB' => '02-02-2009', 'StudentPhoto' => '9a2 (15).jpeg'),
                1124 => array('AppID' => '6170', 'StudentName' => 'BHOOMI RAJPOOT', 'DoB' => '07-09-2009', 'StudentPhoto' => '9a2 (30).jpeg'),
                1125 => array('AppID' => '6171', 'StudentName' => 'DARSHIKA RAJPOOT', 'DoB' => '20-10-2010', 'StudentPhoto' => '9a2 (31).jpeg'),
                1126 => array('AppID' => '6172', 'StudentName' => 'DIVYANSHI GUPTA', 'DoB' => '23-07-2009', 'StudentPhoto' => '9a2 (29).jpeg'),
                1127 => array('AppID' => '6186', 'StudentName' => 'HARSH RAJPOOT', 'DoB' => '15-07-2009', 'StudentPhoto' => '9a2 (16).jpeg'),
                1128 => array('AppID' => '6173', 'StudentName' => 'ILA SIDDIQUE', 'DoB' => '10-03-2009', 'StudentPhoto' => '9a2 (27).jpeg'),
                1129 => array('AppID' => '6187', 'StudentName' => 'KANISHK DWIVEDI', 'DoB' => '29-01-2010', 'StudentPhoto' => '9a2 (13).jpeg'),
                1130 => array('AppID' => '6174', 'StudentName' => 'KHUSHBU RAJPUT', 'DoB' => '10-05-2009', 'StudentPhoto' => '9a2 (28).jpeg'),
                1131 => array('AppID' => '6175', 'StudentName' => 'KRATIKA RAJPOOT', 'DoB' => '01-01-2009', 'StudentPhoto' => '9a2 (25).jpeg'),
                1132 => array('AppID' => '6199', 'StudentName' => 'KRISHNA AGRAWAL', 'DoB' => '01-11-2007', 'StudentPhoto' => '9a2 (1).jpeg'),
                1133 => array('AppID' => '6188', 'StudentName' => 'KULDEEP', 'DoB' => '21-07-2011', 'StudentPhoto' => '9a2 (14).jpeg'),
                1134 => array('AppID' => '6189', 'StudentName' => 'LOKESH RAJPUT', 'DoB' => '02-08-2010', 'StudentPhoto' => '9a2 (12).jpeg'),
                1135 => array('AppID' => '6176', 'StudentName' => 'MANSHI YADAV', 'DoB' => '05-07-2010', 'StudentPhoto' => '9a2 (26).jpeg'),
                1136 => array('AppID' => '6190', 'StudentName' => 'MD FAAIQ ANSARI', 'DoB' => '04-08-2009', 'StudentPhoto' => '9a2 (10).jpeg'),
                1137 => array('AppID' => '6177', 'StudentName' => 'NEELAM RAJPUT', 'DoB' => '09-07-2010', 'StudentPhoto' => '9a2 (24).jpeg'),
                1138 => array('AppID' => '6191', 'StudentName' => 'NIKHIL', 'DoB' => '13-04-2009', 'StudentPhoto' => '9a2 (11).jpeg'),
                1139 => array('AppID' => '6192', 'StudentName' => 'PRAKHAR UPADHYAY', 'DoB' => '06-09-2009', 'StudentPhoto' => '9a2 (9).jpeg'),
                1140 => array('AppID' => '6193', 'StudentName' => 'PRASHANT KUMAR', 'DoB' => '21-03-2010', 'StudentPhoto' => '9a2 (7).jpeg'),
                1141 => array('AppID' => '6178', 'StudentName' => 'PRIYANSHI PALIWAL', 'DoB' => '01-03-2009', 'StudentPhoto' => '9a2 (22).jpeg'),
                1142 => array('AppID' => '6195', 'StudentName' => 'RISHABH CHATURVEDI', 'DoB' => '15-08-2009', 'StudentPhoto' => '9a2 (5).jpeg'),
                1143 => array('AppID' => '6194', 'StudentName' => 'ROUNAK KUMAR', 'DoB' => '14-02-2008', 'StudentPhoto' => '9a2 (8).jpeg'),
                1144 => array('AppID' => '6196', 'StudentName' => 'SHUBH GARG', 'DoB' => '19-03-2008', 'StudentPhoto' => '9a2 (4).jpeg'),
                1145 => array('AppID' => '6179', 'StudentName' => 'TRISHA SAHU', 'DoB' => '02-06-2010', 'StudentPhoto' => '9a2 (23).jpeg'),
                1146 => array('AppID' => '6197', 'StudentName' => 'UJJWAL SINGH', 'DoB' => '04-10-2009', 'StudentPhoto' => '9a2 (6).jpeg'),
                1147 => array('AppID' => '6201', 'StudentName' => 'AKSHRA', 'DoB' => '13-11-2009', 'StudentPhoto' => '9a3 (31).jpeg'),
                1148 => array('AppID' => '6213', 'StudentName' => 'ANMOL DUTT', 'DoB' => '09-07-2010', 'StudentPhoto' => '9a3 (18).jpeg'),
                1149 => array('AppID' => '6214', 'StudentName' => 'ANSH AGRAWAL', 'DoB' => '26-04-2009', 'StudentPhoto' => '9a3 (19).jpeg'),
                1150 => array('AppID' => '6202', 'StudentName' => 'ARYA SINGH PARIHAR', 'DoB' => '12-12-2009', 'StudentPhoto' => '9a3 (29).jpeg'),
                1151 => array('AppID' => '6215', 'StudentName' => 'ARYAN DWIVEDI', 'DoB' => '27-10-2009', 'StudentPhoto' => '9a3 (17).jpeg'),
                1152 => array('AppID' => '6203', 'StudentName' => 'ASTHA GUPTA', 'DoB' => '17-02-2010', 'StudentPhoto' => '9a3 (30).jpeg'),
                1153 => array('AppID' => '6216', 'StudentName' => 'AYUSH KUMAR', 'DoB' => '21-02-2009', 'StudentPhoto' => '9a3 (16).jpeg'),
                1154 => array('AppID' => '6204', 'StudentName' => 'DIVYA DARSHNI', 'DoB' => '09-11-2009', 'StudentPhoto' => '9a3 (28).jpeg'),
                1155 => array('AppID' => '6217', 'StudentName' => 'GAURAV RAJPUT', 'DoB' => '20-07-2010', 'StudentPhoto' => '9a3 (14).jpeg'),
                1156 => array('AppID' => '6218', 'StudentName' => 'HARSH RAJPOOT', 'DoB' => '24-06-2010', 'StudentPhoto' => '9a3 (15).jpeg'),
                1157 => array('AppID' => '6219', 'StudentName' => 'HIMANSHU', 'DoB' => '18-03-2010', 'StudentPhoto' => '9a3 (13).jpeg'),
                1158 => array('AppID' => '6220', 'StudentName' => 'HIMANSHU', 'DoB' => '09-09-2009', 'StudentPhoto' => '9a3 (12).jpeg'),
                1159 => array('AppID' => '6212', 'StudentName' => 'JAIN FATIMA', 'DoB' => '05-07-2009', 'StudentPhoto' => '9a3 (21).jpeg'),
                1160 => array('AppID' => '6205', 'StudentName' => 'KANISHKA RAJ', 'DoB' => '27-08-2008', 'StudentPhoto' => '9a3 (26).jpeg'),
                1161 => array('AppID' => '6221', 'StudentName' => 'LOVEKUSH SONI', 'DoB' => '20-03-2009', 'StudentPhoto' => '9a3 (11).jpeg'),
                1162 => array('AppID' => '6222', 'StudentName' => 'MAYANK', 'DoB' => '23-03-2008', 'StudentPhoto' => '9a3 (10).jpeg'),
                1163 => array('AppID' => '6223', 'StudentName' => 'NIKHIL KUMAR TIWARI', 'DoB' => '18-06-2010', 'StudentPhoto' => '9a3 (8).jpeg'),
                1164 => array('AppID' => '6224', 'StudentName' => 'NIRMENDRA RAJPUT', 'DoB' => '16-11-2008', 'StudentPhoto' => '9a3 (9).jpeg'),
                1165 => array('AppID' => '6206', 'StudentName' => 'PRAGATI PURWAR', 'DoB' => '05-01-2009', 'StudentPhoto' => '9a3 (27).jpeg'),
                1166 => array('AppID' => '6207', 'StudentName' => 'PRATEEKSHA', 'DoB' => '03-02-2009', 'StudentPhoto' => '9a3 (25).jpeg'),
                1167 => array('AppID' => '6208', 'StudentName' => 'RIYA RAJPUT', 'DoB' => '11-07-2009', 'StudentPhoto' => '9a3 (23).jpeg'),
                1168 => array('AppID' => '6209', 'StudentName' => 'SAMIKSHA RAJPOOT', 'DoB' => '08-07-2010', 'StudentPhoto' => '9a3 (24).jpeg'),
                1169 => array('AppID' => '6225', 'StudentName' => 'SATYAM', 'DoB' => '01-01-2009', 'StudentPhoto' => '9a3 (6).jpeg'),
                1170 => array('AppID' => '6226', 'StudentName' => 'SHORYA', 'DoB' => '01-06-2009', 'StudentPhoto' => '9a3 (7).jpeg'),
                1171 => array('AppID' => '6210', 'StudentName' => 'SNEHA SHUKLA', 'DoB' => '29-11-2009', 'StudentPhoto' => '9a3 (22).jpeg'),
                1172 => array('AppID' => '6227', 'StudentName' => 'SUMIT KUMAR', 'DoB' => '06-09-2009', 'StudentPhoto' => '9a3 (5).jpeg'),
                1173 => array('AppID' => '6228', 'StudentName' => 'TUHIN SARKAR', 'DoB' => '10-04-2009', 'StudentPhoto' => '9a3 (3).jpeg'),
                1174 => array('AppID' => '6229', 'StudentName' => 'UTKARSH RAJPUT', 'DoB' => '25-01-2010', 'StudentPhoto' => '9a3 (4).jpeg'),
                1175 => array('AppID' => '6230', 'StudentName' => 'VANSH TIWARI', 'DoB' => '14-11-2010', 'StudentPhoto' => '9a3 (1).jpeg'),
                1176 => array('AppID' => '6211', 'StudentName' => 'VIDHI SINGH', 'DoB' => '02-07-2010', 'StudentPhoto' => '9a3 (20).jpeg'),
                1177 => array('AppID' => '6231', 'StudentName' => 'VIJAY KUMAR', 'DoB' => '27-09-2006', 'StudentPhoto' => '9a3 (2).jpeg'),
                1178 => array('AppID' => '6233', 'StudentName' => 'AALIMA RAHMAN', 'DoB' => '06-07-2010', 'StudentPhoto' => '9a4 (30).jpeg'),
                1179 => array('AppID' => '6234', 'StudentName' => 'ANAMIKA', 'DoB' => '22-05-2012', 'StudentPhoto' => '9a4 (31).jpeg'),
                1180 => array('AppID' => '6243', 'StudentName' => 'ANANT KUMAR', 'DoB' => '13-10-2010', 'StudentPhoto' => '9a4 (20).jpeg'),
                1181 => array('AppID' => '6244', 'StudentName' => 'ANKIT KUMAR', 'DoB' => '01-01-2007', 'StudentPhoto' => '9a4 (19).jpeg'),
                1182 => array('AppID' => '6235', 'StudentName' => 'APURVA', 'DoB' => '09-01-2010', 'StudentPhoto' => '9a4 (28).jpeg'),
                1183 => array('AppID' => '6245', 'StudentName' => 'AYAN KHAN', 'DoB' => '25-03-2010', 'StudentPhoto' => '9a4 (21).jpeg'),
                1184 => array('AppID' => '6246', 'StudentName' => 'DEEPAK KUMAR', 'DoB' => '01-01-2008', 'StudentPhoto' => '9a4 (17).jpeg'),
                1185 => array('AppID' => '6247', 'StudentName' => 'DHANANJAY', 'DoB' => '23-10-2010', 'StudentPhoto' => '9a4 (18).jpeg'),
                1186 => array('AppID' => '6248', 'StudentName' => 'DHRUV', 'DoB' => '06-04-2010', 'StudentPhoto' => '9a4 (16).jpeg'),
                1187 => array('AppID' => '6249', 'StudentName' => 'DIVYANSH BAJPAI', 'DoB' => '31-10-2009', 'StudentPhoto' => '9a4 (14).jpeg'),
                1188 => array('AppID' => '6236', 'StudentName' => 'FATIMA SAMEER', 'DoB' => '07-09-2010', 'StudentPhoto' => '9a4 (29).jpeg'),
                1189 => array('AppID' => '6250', 'StudentName' => 'LAVKESH', 'DoB' => '05-01-2010', 'StudentPhoto' => '9a4 (15).jpeg'),
                1190 => array('AppID' => '6251', 'StudentName' => 'MANISH RAJPOOT', 'DoB' => '01-01-2011', 'StudentPhoto' => '9a4 (13).jpeg'),
                1191 => array('AppID' => '6253', 'StudentName' => 'MOHAMMAD ABDULLA', 'DoB' => '02-07-2007', 'StudentPhoto' => '9a4 (10).jpeg'),
                1192 => array('AppID' => '6252', 'StudentName' => 'MOHAMMAD ATIF KHAN', 'DoB' => '27-10-2010', 'StudentPhoto' => '9a4 (12).jpeg'),
                1193 => array('AppID' => '6254', 'StudentName' => 'PRATYUSH', 'DoB' => '19-03-2009', 'StudentPhoto' => '9a4 (11).jpeg'),
                1194 => array('AppID' => '6255', 'StudentName' => 'PRIYANSHU', 'DoB' => '01-01-2008', 'StudentPhoto' => '9a4 (9).jpeg'),
                1195 => array('AppID' => '6237', 'StudentName' => 'RIZA NASEEM', 'DoB' => '03-11-2009', 'StudentPhoto' => '9a4 (26).jpeg'),
                1196 => array('AppID' => '6256', 'StudentName' => 'SAJJAN SINGH', 'DoB' => '12-04-2010', 'StudentPhoto' => '9a4 (7).jpeg'),
                1197 => array('AppID' => '6238', 'StudentName' => 'SHAILJA TRIPATHI', 'DoB' => '30-09-2009', 'StudentPhoto' => '9a4 (27).jpeg'),
                1198 => array('AppID' => '6257', 'StudentName' => 'SHIKHAR RAJ', 'DoB' => '13-05-2010', 'StudentPhoto' => '9a4 (8).jpeg'),
                1199 => array('AppID' => '6258', 'StudentName' => 'SHIVANSHU TIWARI', 'DoB' => '14-09-2009', 'StudentPhoto' => '9a4 (6).jpeg'),
                1200 => array('AppID' => '6239', 'StudentName' => 'SHRUTI', 'DoB' => '10-07-2010', 'StudentPhoto' => '9a4 (24).jpeg'),
                1201 => array('AppID' => '6259', 'StudentName' => 'SOHIL KHAN', 'DoB' => '09-08-2010', 'StudentPhoto' => '9a4 (5).jpeg'),
                1202 => array('AppID' => '6260', 'StudentName' => 'SOYASH', 'DoB' => '05-09-2010', 'StudentPhoto' => '9a4 (3).jpeg'),
                1203 => array('AppID' => '6261', 'StudentName' => 'SURYANSH RAJPOOT', 'DoB' => '25-11-2009', 'StudentPhoto' => '9a4 (4).jpeg'),
                1204 => array('AppID' => '6262', 'StudentName' => 'VAIBHAV SHUKLA', 'DoB' => '08-12-2011', 'StudentPhoto' => '9a4 (1).jpeg'),
                1205 => array('AppID' => '6240', 'StudentName' => 'VAISHNAVI RAJPOOT', 'DoB' => '15-08-2009', 'StudentPhoto' => '9a4 (25).jpeg'),
                1206 => array('AppID' => '6263', 'StudentName' => 'VIKAL KUMAR', 'DoB' => '24-01-2009', 'StudentPhoto' => '9a4 (2).jpeg'),
                1207 => array('AppID' => '6264', 'StudentName' => 'VIMAL KUMAR', 'DoB' => '23-01-2010', 'StudentPhoto' => '9a4 (32).jpeg'),
                1208 => array('AppID' => '6241', 'StudentName' => 'YASHIKA SONI', 'DoB' => '22-10-2009', 'StudentPhoto' => '9a4 (22).jpeg'),
                1209 => array('AppID' => '6242', 'StudentName' => 'ZOYA HASHMI', 'DoB' => '17-05-2011', 'StudentPhoto' => '9a4 (23).jpeg'),
                1210 => array('AppID' => '6388', 'StudentName' => 'AFREEN KHAN', 'DoB' => '16-03-2008', 'StudentPhoto' => '11a1 (28).jpeg'),
                1211 => array('AppID' => '6389', 'StudentName' => 'ANAMIKA RAJPOOT', 'DoB' => '14-01-2008', 'StudentPhoto' => '11a1 (27).jpeg'),
                1212 => array('AppID' => '6404', 'StudentName' => 'ANKIT RAJPUT', 'DoB' => '09-07-2009', 'StudentPhoto' => '11a1 (12).jpeg'),
                1213 => array('AppID' => '6405', 'StudentName' => 'ANSHIT SINGH', 'DoB' => '07-02-2007', 'StudentPhoto' => '11a1 (14).jpeg'),
                1214 => array('AppID' => '6406', 'StudentName' => 'ANSHUL RAJPOOT', 'DoB' => '27-08-2007', 'StudentPhoto' => '11a1 (11).jpeg'),
                1215 => array('AppID' => '6407', 'StudentName' => 'ATA VARISH', 'DoB' => '24-01-2008', 'StudentPhoto' => '11a1 (9).jpeg'),
                1216 => array('AppID' => '6408', 'StudentName' => 'ATHARV NIGAM', 'DoB' => '01-09-2007', 'StudentPhoto' => '11a1 (10).jpeg'),
                1217 => array('AppID' => '6390', 'StudentName' => 'DEEPIKA CHATURVEDI', 'DoB' => '10-05-2008', 'StudentPhoto' => '11a1 (29).jpeg'),
                1218 => array('AppID' => '6410', 'StudentName' => 'GAURAV RAJPUT', 'DoB' => '25-02-2006', 'StudentPhoto' => '11a1 (7).jpeg'),
                1219 => array('AppID' => '6391', 'StudentName' => 'IRAM HASHMI', 'DoB' => '13-06-2007', 'StudentPhoto' => '11a1 (25).jpeg'),
                1220 => array('AppID' => '6392', 'StudentName' => 'MAHAK RAJPUT', 'DoB' => '27-05-2007', 'StudentPhoto' => '11a1 (26).jpeg'),
                1221 => array('AppID' => '6393', 'StudentName' => 'MANSHI GUPTA', 'DoB' => '30-01-2008', 'StudentPhoto' => '11a1 (24).jpeg'),
                1222 => array('AppID' => '6412', 'StudentName' => 'MOHAMMAD FAIZAN', 'DoB' => '14-07-2007', 'StudentPhoto' => '11a1 (8).jpeg'),
                1223 => array('AppID' => '6394', 'StudentName' => 'NIYASHA', 'DoB' => '08-04-2007', 'StudentPhoto' => '11a1 (22).jpeg'),
                1224 => array('AppID' => '6395', 'StudentName' => 'PARIDHI RAJPOOT', 'DoB' => '04-11-2008', 'StudentPhoto' => '11a1 (23).jpeg'),
                1225 => array('AppID' => '6396', 'StudentName' => 'RIMSHA', 'DoB' => '13-05-2008', 'StudentPhoto' => '11a1 (20).jpeg'),
                1226 => array('AppID' => '6397', 'StudentName' => 'RITU SINGH', 'DoB' => '05-04-2009', 'StudentPhoto' => '11a1 (21).jpeg'),
                1227 => array('AppID' => '6413', 'StudentName' => 'ROHAN SINGH', 'DoB' => '15-08-2007', 'StudentPhoto' => '11a1 (6).jpeg'),
                1228 => array('AppID' => '6414', 'StudentName' => 'ROHIT KUMAR SAINI', 'DoB' => '25-02-2008', 'StudentPhoto' => '11a1 (4).jpeg'),
                1229 => array('AppID' => '6415', 'StudentName' => 'SANGAM SINGH RAJPUT', 'DoB' => '08-09-2008', 'StudentPhoto' => '11a1 (5).jpeg'),
                1230 => array('AppID' => '6398', 'StudentName' => 'SAUNAM KUMARI', 'DoB' => '11-10-2006', 'StudentPhoto' => '11a1 (19).jpeg'),
                1231 => array('AppID' => '6399', 'StudentName' => 'SHANVI GARG', 'DoB' => '10-01-2007', 'StudentPhoto' => '11a1 (18).jpeg'),
                1232 => array('AppID' => '6400', 'StudentName' => 'SHIVANI RAJPOOT', 'DoB' => '24-08-2008', 'StudentPhoto' => '11a1 (16).jpeg'),
                1233 => array('AppID' => '6419', 'StudentName' => 'SHIVENDRA KUMAR', 'DoB' => '24-06-2007', 'StudentPhoto' => '11a1 (3).jpeg'),
                1234 => array('AppID' => '6401', 'StudentName' => 'SHRDDHA', 'DoB' => '28-07-2008', 'StudentPhoto' => '11a1 (17).jpeg'),
                1235 => array('AppID' => '6402', 'StudentName' => 'SHREYA', 'DoB' => '01-05-2009', 'StudentPhoto' => '11a1 (15).jpeg'),
                1236 => array('AppID' => '6403', 'StudentName' => 'STUTI', 'DoB' => '30-06-2008', 'StudentPhoto' => '11a1 (13).jpeg'),
                1237 => array('AppID' => '6420', 'StudentName' => 'SUYASH RAJPUT', 'DoB' => '05-04-2007', 'StudentPhoto' => '11a1 (1).jpeg'),
                1238 => array('AppID' => '6421', 'StudentName' => 'VINAYAK KUMAR', 'DoB' => '18-08-2007', 'StudentPhoto' => '11a1 (2).jpeg'),
                1239 => array('AppID' => '6432', 'StudentName' => 'ABHINESH KUMAR', 'DoB' => '25-11-2008', 'StudentPhoto' => '11a2 (21).jpeg'),
                1240 => array('AppID' => '6433', 'StudentName' => 'AKASH SINGH', 'DoB' => '06-09-2008', 'StudentPhoto' => '11a2 (18).jpeg'),
                1241 => array('AppID' => '6435', 'StudentName' => 'ANURAG RAJPUT', 'DoB' => '22-03-2008', 'StudentPhoto' => '11a2 (19).jpeg'),
                1242 => array('AppID' => '6436', 'StudentName' => 'ARYAN SINGH', 'DoB' => '03-07-2009', 'StudentPhoto' => '11a2 (17).jpeg'),
                1243 => array('AppID' => '6422', 'StudentName' => 'ASHIKA CHAUHAN', 'DoB' => '15-03-2010', 'StudentPhoto' => '11a2 (32).jpeg'),
                1244 => array('AppID' => '6423', 'StudentName' => 'ASTHA GUPTA', 'DoB' => '29-12-2008', 'StudentPhoto' => '11a2 (28).jpeg'),
                1245 => array('AppID' => '6437', 'StudentName' => 'AYUSH DWIVEDI', 'DoB' => '20-07-2007', 'StudentPhoto' => '11a2 (15).jpeg'),
                1246 => array('AppID' => '6438', 'StudentName' => 'AYUSH VERMA', 'DoB' => '07-11-2006', 'StudentPhoto' => '11a2 (16).jpeg'),
                1247 => array('AppID' => '6424', 'StudentName' => 'ESHA GUPTA', 'DoB' => '11-10-2008', 'StudentPhoto' => '11a2 (27).jpeg'),
                1248 => array('AppID' => '6439', 'StudentName' => 'GOPAL JI', 'DoB' => '28-03-2009', 'StudentPhoto' => '11a2 (14).jpeg'),
                1249 => array('AppID' => '6440', 'StudentName' => 'HARSHIT YADAV', 'DoB' => '15-07-2009', 'StudentPhoto' => '11a2 (13).jpeg'),
                1250 => array('AppID' => '6441', 'StudentName' => 'HEMENDRA KUMAR', 'DoB' => '09-09-2006', 'StudentPhoto' => '11a2 (12).jpeg'),
                1251 => array('AppID' => '6443', 'StudentName' => 'JATIN SINGH', 'DoB' => '24-03-2008', 'StudentPhoto' => '11a2 (10).jpeg'),
                1252 => array('AppID' => '6444', 'StudentName' => 'JAY SAHU', 'DoB' => '05-06-2008', 'StudentPhoto' => '11a2 (9).jpeg'),
                1253 => array('AppID' => '6425', 'StudentName' => 'JUBERA SIDDIQUE', 'DoB' => '16-01-2009', 'StudentPhoto' => '11a2 (29).jpeg'),
                1254 => array('AppID' => '6426', 'StudentName' => 'KANISHKA AGRAWAL', 'DoB' => '03-01-2008', 'StudentPhoto' => '11a2 (26).jpeg'),
                1255 => array('AppID' => '6427', 'StudentName' => 'KASHISH RAJPUT', 'DoB' => '08-10-2007', 'StudentPhoto' => '11a2 (25).jpeg'),
                1256 => array('AppID' => '6445', 'StudentName' => 'KRISH KUMAR', 'DoB' => '01-07-2007', 'StudentPhoto' => '11a2 (11).jpeg'),
                1257 => array('AppID' => '6446', 'StudentName' => 'KRISH VERMA', 'DoB' => '18-04-2007', 'StudentPhoto' => '11a2 (8).jpeg'),
                1258 => array('AppID' => '6447', 'StudentName' => 'KRISHNA GUPTA', 'DoB' => '01-05-2006', 'StudentPhoto' => '11a2 (7).jpeg'),
                1259 => array('AppID' => '6448', 'StudentName' => 'LUCKY RAJPUT', 'DoB' => '15-12-2007', 'StudentPhoto' => '11a2 (5).jpeg'),
                1260 => array('AppID' => '6428', 'StudentName' => 'MAHAK RAJPUT', 'DoB' => '04-05-2007', 'StudentPhoto' => '11a2 (23).jpeg'),
                1261 => array('AppID' => '6449', 'StudentName' => 'MANVENDRA RAJPUT', 'DoB' => '20-09-2007', 'StudentPhoto' => '11a2 (6).jpeg'),
                1262 => array('AppID' => '6450', 'StudentName' => 'MOHAMMAD VASIF', 'DoB' => '24-08-2007', 'StudentPhoto' => '11a2 (3).jpeg'),
                1263 => array('AppID' => '6451', 'StudentName' => 'NAITIK GUPTA', 'DoB' => '20-11-2008', 'StudentPhoto' => '11a2 (2).jpeg'),
                1264 => array('AppID' => '6429', 'StudentName' => 'NIDHI CHANDRA', 'DoB' => '26-07-2008', 'StudentPhoto' => '11a2 (22).jpeg'),
                1265 => array('AppID' => '6452', 'StudentName' => 'PANKAJ RAJPOOT', 'DoB' => '06-06-2007', 'StudentPhoto' => '11a2 (4).jpeg'),
                1266 => array('AppID' => '6453', 'StudentName' => 'PRANSHU SINGH', 'DoB' => '15-04-2007', 'StudentPhoto' => '11a2 (1).jpeg'),
                1267 => array('AppID' => '6455', 'StudentName' => 'PRINCE YADAV', 'DoB' => '03-04-2007', 'StudentPhoto' => '11a2 (41).jpeg'),
                1268 => array('AppID' => '6456', 'StudentName' => 'PRIYANSHU GUPTA', 'DoB' => '05-09-2007', 'StudentPhoto' => '11a2 (40).jpeg'),
                1269 => array('AppID' => '6457', 'StudentName' => 'RUDRANSH SONI', 'DoB' => '05-04-2007', 'StudentPhoto' => '11a2 (42).jpeg'),
                1270 => array('AppID' => '6458', 'StudentName' => 'SACHIN SAHU', 'DoB' => '10-08-2006', 'StudentPhoto' => '11a2 (38).jpeg'),
                1271 => array('AppID' => '6459', 'StudentName' => 'SHIVA SINGH GOUTAM', 'DoB' => '18-01-2007', 'StudentPhoto' => '11a2 (37).jpeg'),
                1272 => array('AppID' => '6460', 'StudentName' => 'SIDDHARTH SEN', 'DoB' => '22-08-2007', 'StudentPhoto' => '11a2 (39).jpeg'),
                1273 => array('AppID' => '6461', 'StudentName' => 'SOHIL KHAN', 'DoB' => '10-07-2007', 'StudentPhoto' => '11a2 (36).jpeg'),
                1274 => array('AppID' => '6462', 'StudentName' => 'SUYASH TRIPATHI', 'DoB' => '18-11-2008', 'StudentPhoto' => '11a2 (34).jpeg'),
                1275 => array('AppID' => '6463', 'StudentName' => 'SWASTIK', 'DoB' => '11-02-2007', 'StudentPhoto' => '11a2 (33).jpeg'),
                1276 => array('AppID' => '6465', 'StudentName' => 'TANISHQ SAINI', 'DoB' => '17-07-2007', 'StudentPhoto' => '11a2 (35).jpeg'),
                1277 => array('AppID' => '6430', 'StudentName' => 'TANYA', 'DoB' => '23-10-2008', 'StudentPhoto' => '11a2 (24).jpeg'),
                1278 => array('AppID' => '6431', 'StudentName' => 'VANSHIKA RAJPUT', 'DoB' => '04-07-2008', 'StudentPhoto' => '11a2 (20).jpeg'),
                1279 => array('AppID' => '6466', 'StudentName' => 'VISHESH TIWARI', 'DoB' => '04-09-2007', 'StudentPhoto' => '11a2 (31).jpeg'),
                1280 => array('AppID' => '6467', 'StudentName' => 'YATENDER PARIHAR', 'DoB' => '25-12-2008', 'StudentPhoto' => '11a2 (30).jpeg')
            );
            
            $list = array(
                5043, 5051, 5054, 5057, 5062, 5067, 5068, 5115, 5116, 5118, 5126, 5130, 5132, 5146, 5156,
                5127, 5129, 5133, 5141, 5147, 5149, 5155, 5166, 5172, 5178, 5184, 5192, 5198, 5214, 5242,
                5228, 5238, 5163, 5324, 5349, 5369, 5370, 5393, 5402, 5430, 5433, 5437, 5444, 5465, 5490,
                5508, 5563, 5594, 5614, 5622, 5664, 5692, 5723, 5727, 5777, 5779, 5781, 5845, 5854, 5886,
                5946, 6056, 6117, 6124, 6144, 6218, 6257, 6404
            );
            $listalrd=array(
                    5001,5002,5003,5004,5005,5006,5007,5008,5009,5012,5014,5016,5017,5019,5020,5022,5023,5024,5025,5026,5027,5028,5029,5030,5031,5033,5034,5035,5036,5037,5039,5041,5042,5044,5045,5046,5047,5049,5050,5052,5053,5055,5056,5058,5059,5060,5061,5063,5064,5065,5069,5070,5071,5072,5074,5075,5077,5078,5079,5080,5081,5082,5083,5084,5085,5086,5087,5088,5089,5090,5091,5092,5093,5094,5095,5096,5097,5099,5100,5101,5102,5103,5104,5106,5107,5108,5109,5110,5111,5112,5113,5114,5117,5119,5120,5121,5125,5128,5131,5135,5136,5137,5138,5139,5140,5142,5143,5144,5145,5148,5150,5151,5152,5153,5154,5157,5158,5159,5160,5161,5162,5165,5167,5169,5171,5173,5174,5175,5176,5177,5179,5180,5181,5182,5183,5185,5186,5187,5188,5189,5190,5191,5193,5194,5195,5196,5197,5199,5200,5201,5202,5203,5204,5205,5207,5208,5209,5210,5211,5212,5213,5215,5216,5217,5218,5219,5220,5221,5222,5223,5224,5225,5226,5227,5230,5231,5232,5233,5234,5235,5236,5239,5240,5241,5243,5244,5245,5246,5247,5248,5249,5250,5251,5252,5253,5254,5255,5256,5257,5258,5259,5260,5261,5262,5263,5264,5265,5266,5267,5268,5269,5270,5271,5272,5273,5274,5275,5276,5277,5278,5279,5280,5281,5282,5283,5284,5285,5286,5287,5288,5289,5290,5291,5292,5293,5294,5295,5296,5297,5298,5299,5300,5301,5302,5303,5304,5305,5306,5307,5308,5309,5310,5311,5312,5313,5314,5315,5316,5317,5318,5319,5320,5321,5322,5323,5325,5326,5327,5328,5329,5330,5331,5332,5333,5334,5335,5336,5337,5338,5339,5340,5341,5344,5345,5346,5347,5348,5350,5351,5352,5353,5354,5355,5356,5357,5358,5359,5360,5362,5363,5364,5365,5366,5367,5368,5371,5372,5373,5374,5376,5377,5378,5379,5380,5381,5382,5383,5384,5385,5386,5387,5388,5389,5390,5391,5392,5394,5395,5396,5397,5398,5399,5400,5401,5403,5404,5405,5406,5407,5408,5409,5410,5411,5412,5413,5414,5415,5416,5417,5418,5419,5420,5421,5422,5423,5424,5425,5426,5427,5428,5429,5431,5434,5435,5436,5438,5439,5440,5441,5442,5443,5445,5446,5447,5448,5449,5450,5451,5452,5453,5454,5455,5456,5457,5458,5459,5460,5461,5462,5463,5464,5466,5467,5468,5469,5470,5471,5472,5473,5474,5475,5476,5477,5479,5480,5481,5482,5483,5484,5485,5486,5487,5488,5489,5491,5492,5493,5494,5495,5496,5497,5498,5499,5500,5501,5502,5503,5504,5505,5506,5507,5509,5510,5511,5512,5513,5514,5515,5516,5517,5518,5519,5520,5521,5522,5523,5524,5525,5526,5527,5528,5529,5530,5532,5533,5534,5535,5536,5537,5538,5539,5540,5541,5542,5543,5544,5545,5546,5547,5548,5549,5550,5551,5552,5553,5554,5555,5556,5557,5558,5559,5560,5561,5562,5564,5565,5566,5567,5568,5569,5570,5571,5572,5573,5574,5575,5576,5577,5578,5579,5580,5581,5582,5583,5584,5585,5586,5587,5588,5589,5590,5591,5592,5593,5595,5596,5597,5598,5599,5600,5601,5602,5603,5604,5605,5606,5607,5608,5609,5610,5611,5613,5615,5616,5617,5619,5620,5621,5623,5624,5625,5626,5627,5628,5629,5630,5631,5632,5633,5634,5635,5636,5637,5638,5639,5640,5641,5642,5643,5645,5646,5647,5648,5649,5650,5651,5652,5653,5654,5655,5656,5657,5658,5659,5660,5661,5662,5663,5665,5666,5667,5668,5669,5670,5671,5672,5673,5674,5675,5676,5677,5678,5679,5680,5681,5682,5683,5684,5685,5686,5687,5688,5689,5690,5691,5693,5694,5695,5696,5697,5698,5699,5700,5701,5702,5703,5704,5705,5706,5707,5708,5709,5710,5711,5712,5713,5714,5715,5716,5717,5718,5719,5720,5721,5722,5725,5726,5728,5729,5730,5731,5732,5733,5734,5735,5736,5737,5738,5739,5740,5741,5742,5743,5744,5745,5746,5747,5748,5749,5750,5751,5752,5753,5754,5755,5756,5757,5759,5760,5761,5762,5763,5764,5765,5766,5767,5768,5769,5770,5771,5772,5773,5774,5775,5776,5778,5780,5782,5783,5784,5785,5786,5787,5788,5789,5790,5791,5792,5793,5794,5795,5796,5797,5798,5799,5800,5801,5802,5803,5804,5805,5806,5807,5808,5809,5810,5811,5812,5813,5814,5815,5816,5817,5818,5819,5820,5821,5822,5823,5824,5825,5827,5828,5829,5830,5831,5832,5833,5834,5835,5836,5837,5838,5839,5841,5842,5843,5844,5846,5847,5848,5849,5851,5852,5853,5855,5856,5857,5858,5859,5860,5861,5862,5863,5864,5865,5866,5867,5868,5869,5870,5871,5872,5873,5874,5875,5879,5880,5881,5882,5883,5884,5885,5887,5888,5889,5890,5891,5892,5893,5894,5896,5897,5898,5899,5900,5901,5902,5903,5904,5905,5906,5907,5908,5909,5910,5911,5912,5913,5914,5915,5916,5917,5918,5919,5920,5921,5922,5923,5924,5925,5926,5927,5928,5929,5930,5931,5932,5933,5934,5935,5936,5937,5938,5939,5940,5941,5942,5943,5944,5945,5947,5948,5949,5950,5951,5952,5953,5954,5955,5956,5957,5958,5959,5960,5961,5962,5963,5964,5965,5966,5967,5969,5970,5971,5972,5974,5978,5979,5980,5981,5982,5983,5986,5987,5988,5989,5990,5991,5992,5993,5994,5995,5996,5997,5998,5999,6000,6001,6003,6004,6005,6006,6007,6008,6009,6010,6011,6012,6013,6014,6015,6016,6017,6018,6019,6020,6021,6022,6023,6024,6025,6026,6027,6028,6029,6030,6031,6032,6033,6034,6035,6036,6037,6038,6039,6040,6041,6042,6043,6044,6045,6046,6047,6048,6049,6050,6051,6052,6053,6054,6055,6057,6058,6059,6060,6061,6062,6063,6064,6065,6066,6067,6068,6069,6070,6071,6072,6073,6074,6075,6076,6077,6078,6079,6080,6081,6082,6083,6084,6085,6086,6087,6088,6089,6090,6091,6092,6093,6094,6095,6096,6097,6098,6099,6100,6101,6102,6103,6104,6105,6106,6107,6108,6109,6110,6111,6112,6113,6114,6115,6116,6118,6119,6120,6121,6122,6123,6125,6126,6127,6128,6129,6130,6131,6132,6133,6134,6135,6136,6137,6138,6139,6140,6141,6142,6143,6145,6146,6147,6149,6150,6151,6152,6153,6154,6155,6156,6157,6158,6159,6160,6161,6162,6163,6164,6165,6166,6167,6168,6169,6170,6171,6172,6173,6174,6175,6176,6177,6178,6179,6180,6181,6182,6183,6184,6185,6186,6187,6188,6189,6190,6191,6192,6193,6194,6195,6196,6197,6198,6199,6200,6201,6202,6203,6204,6205,6206,6207,6208,6209,6210,6211,6212,6213,6214,6215,6216,6217,6219,6220,6221,6222,6223,6224,6225,6226,6227,6228,6229,6230,6231,6233,6234,6235,6236,6237,6238,6239,6240,6241,6242,6243,6244,6245,6246,6247,6248,6249,6250,6251,6252,6253,6254,6255,6256,6258,6259,6260,6261,6262,6263,6264,6388,6389,6390,6391,6392,6393,6394,6395,6396,6397,6398,6399,6400,6401,6402,6403,6405,6406,6407,6408,6410,6412,6413,6414,6415,6419,6420,6421,6422,6423,6424,6425,6426,6427,6428,6429,6430,6431,6432,6433,6435,6436,6437,6438,6439,6440,6441,6443,6444,6445,6446,6447,6448,6449,6450,6451,6452,6453,6455,6456,6457,6458,6459,6460,6461,6462,6463,6465,6466,6467
                );

            set_time_limit(600);
            $photoDirectory = "F:\\SHARDA SOLUTIONS\\2024\\ccs rath\\1st-Round\\1st-round-all-student";
            $insertData = [];
            $j = 1;
            for($i=0;$i<count($record);$i++){
                $r = $record[$i];
                if (!in_array($r['AppID'], $listalrd)){
                    $imagePath = $photoDirectory . DIRECTORY_SEPARATOR . $r['StudentPhoto'];
                    if (file_exists($imagePath)) {
                        // abort(404, 'Image not found');
                        // $imageData = file_get_contents($imagePath);
                        // $base64Image = base64_encode($imageData);

                        $dob = '00-00';

                        if (!empty($r['DoB'])) {
                            $date = Carbon::createFromFormat('d-m-Y', $r['DoB']);
                            $dob = $date->format('m-d');
                        } 

                        echo $j.")-> : ->".$i." :.-- : ".$r['AppID']." -: : - ".$r['StudentName']." - : - ".$dob;
                        echo "<br>";
                        $j++;

                        

                        $cmdsetup = [
                            "cmd" => "setuserinfo",
                            "enrollid" => (int)$r['AppID'],
                            "name" => $r['StudentName'],
                            "backupnum" => 50,
                            "admin" => 0,
                            "birthday"=> $dob,
                            // "record" => $base64Image
                        ];
                        $insertData = [
                            "serial"=> "ZYRK22090931",
                            "name"=> "setuserinfo",
                            "content"=> json_encode($cmdsetup),
                            "gmt_crate"=> now(),
                            "gmt_modified"=> now(),
                        ];
                        DB::table('machine_command')->insert($insertData);
                    }
                }
            }
        }catch(\Exception $e){
            dd($e);
            return exceptionResponse($e);
        }
    }

}
