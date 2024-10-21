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
use App\Models\Registration;

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
            // dd( $classSesction);
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
            $session = getSchoolIdBySessionID($request->companyid);
            if($request->permtype=='classcontrol'){
                if($request->permvalue!='all'&&$request->permvalue!='[]'&&@count($assignclass = json_decode($request->permvalue,true))>0){
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
                User::where([['school_id',$session->school_id],['username',$request->accountid]])->update(['role'=>$permvalue]);
            }else if($request->permtype=='teacherstatus'){
                $permvalue = $request->permvalue=='Y'?1:0;
                User::where([['school_id',$session->school_id],['username',$request->accountid]])->update(['status'=>$permvalue]);
            }else{
                Faculty::where([['school_id',$session->school_id],['faculty_id',$request->accountid]])->update([$request->permtype=>$request->permvalue]);
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
            $photoDirectory = "F:/SHARDA SOLUTIONS/2024/urmila-devi/erp-data";
            
            $excelRecord = array(
                0 => array('AppID' => '6115', 'Name' => 'Aashvi', 'image' => 'IMG20240722103209.jpg'),
                1 => array('AppID' => '6121', 'Name' => 'Adarsh', 'image' => 'IMG20240718100426.jpg'),
                2 => array('AppID' => '6105', 'Name' => 'Adi Rajput', 'image' => 'IMG20240513092936.jpg'),
                3 => array('AppID' => '6093', 'Name' => 'Afsa', 'image' => 'IMG20240513093241.jpg'),
                4 => array('AppID' => '6146', 'Name' => 'Anmoal', 'image' => 'WhatsApp_Image_2024-07-27_at_9.39.29_AM.jpeg'),
                5 => array('AppID' => '6080', 'Name' => 'Ansh Kumar', 'image' => '2024-05-19-183608IMG20240516104826.jpg'),
                6 => array('AppID' => '6102', 'Name' => 'Arvi Anuragi', 'image' => 'IMG20240516104911.jpg'),
                7 => array('AppID' => '6089', 'Name' => 'Harshit Kumar', 'image' => 'IMG20240513093222.jpg'),
                8 => array('AppID' => '6104', 'Name' => 'Jaydeep Singh', 'image' => 'IMG20240513093205.jpg'),
                9 => array('AppID' => '6108', 'Name' => 'Kavya', 'image' => 'IMG20240516104927.jpg'),
                10 => array('AppID' => '6145', 'Name' => 'Kavya', 'image' => 'WhatsApp_Image_2024-07-26_at_8.29.17_AM_(1).jpeg'),
                11 => array('AppID' => '6124', 'Name' => 'Mahira', 'image' => 'IMG20240723083334.jpg'),
                12 => array('AppID' => '6125', 'Name' => 'Manjar', 'image' => 'WhatsApp_Image_2024-07-25_at_10.23.13_AM.jpeg'),
                13 => array('AppID' => '6072', 'Name' => 'Mayra', 'image' => 'DSC_0032.JPG'),
                14 => array('AppID' => '6118', 'Name' => 'Mohd Danish', 'image' => 'IMG20240723083358.jpg'),
                15 => array('AppID' => '6129', 'Name' => 'Nandni', 'image' => 'IMG20240718100856.jpg'),
                16 => array('AppID' => '6110', 'Name' => 'Om', 'image' => 'IMG20240718100604.jpg'),
                17 => array('AppID' => '6113', 'Name' => 'Pari', 'image' => 'IMG20240718100544.jpg'),
                18 => array('AppID' => '6144', 'Name' => 'Rishi Kumar', 'image' => 'WhatsApp_Image_2024-07-26_at_8.29.17_AM_(2).jpeg'),
                19 => array('AppID' => '6137', 'Name' => 'Riya', 'image' => '2024-07-24-180235IMG20240722103129.jpg'),
                20 => array('AppID' => '6111', 'Name' => 'Shiva', 'image' => 'WhatsApp_Image_2024-07-26_at_8.29.17_AM.jpeg'),
                21 => array('AppID' => '6103', 'Name' => 'Shiva Soni', 'image' => 'IMG20240718100814.jpg'),
                22 => array('AppID' => '5913', 'Name' => 'Aarsh Dev', 'image' => 'IMG20240513093928.jpg'),
                23 => array('AppID' => '5922', 'Name' => 'Abhi', 'image' => 'IMG20240513094038.jpg'),
                24 => array('AppID' => '5915', 'Name' => 'Alok Kumar', 'image' => 'IMG20240516104826.jpg'),
                25 => array('AppID' => '6076', 'Name' => 'Anabiya Ansari', 'image' => 'IMG20240718100648.jpg'),
                26 => array('AppID' => '5916', 'Name' => 'Anaya Khan', 'image' => 'IMG20240516104810.jpg'),
                27 => array('AppID' => '6131', 'Name' => 'Anubhee', 'image' => 'IMG20240722103348.jpg'),
                28 => array('AppID' => '5911', 'Name' => 'Arpit Kumar', 'image' => 'IMG20240513093917.jpg'),
                29 => array('AppID' => '5920', 'Name' => 'Arushi', 'image' => 'IMG20240513094011.jpg'),
                30 => array('AppID' => '5898', 'Name' => 'Aryanshi', 'image' => 'IMG20240718100938.jpg'),
                31 => array('AppID' => '6097', 'Name' => 'Atharav Soni', 'image' => 'IMG20240722103320.jpg'),
                32 => array('AppID' => '5899', 'Name' => 'Ayansh', 'image' => 'IMG20240516104728.jpg'),
                33 => array('AppID' => '5909', 'Name' => 'Bhumi', 'image' => 'IMG20240513093900.jpg'),
                34 => array('AppID' => '5896', 'Name' => 'Chirag Soni', 'image' => 'IMG20240513093800.jpg'),
                35 => array('AppID' => '5894', 'Name' => 'Devendra Anuragi', 'image' => '2024-05-19-184148IMG20240513093608.jpg'),
                36 => array('AppID' => '5923', 'Name' => 'Divyansh', 'image' => 'IMG20240718100922.jpg'),
                37 => array('AppID' => '5897', 'Name' => 'Harsh', 'image' => 'IMG20240513093652.jpg'),
                38 => array('AppID' => '5902', 'Name' => 'Himanshu', 'image' => 'IMG20240513093816.jpg'),
                39 => array('AppID' => '5893', 'Name' => 'Kartik Soni', 'image' => 'IMG20240513093739.jpg'),
                40 => array('AppID' => '5892', 'Name' => 'Mahak', 'image' => 'IMG20240513093553.jpg'),
                41 => array('AppID' => '5921', 'Name' => 'Maneya', 'image' => 'IMG20240513094026.jpg'),
                42 => array('AppID' => '5905', 'Name' => 'Mansingh', 'image' => 'IMG20240513093837.jpg'),
                43 => array('AppID' => '5914', 'Name' => 'Mistry Dwivedi', 'image' => 'IMG20240513093948.jpg'),
                44 => array('AppID' => '6114', 'Name' => 'Prince', 'image' => 'IMG20240718101045.jpg'),
                45 => array('AppID' => '6112', 'Name' => 'Sagar', 'image' => 'IMG20240718101106.jpg'),
                46 => array('AppID' => '5918', 'Name' => 'Sanskar', 'image' => 'IMG20240513093938.jpg'),
                47 => array('AppID' => '5889', 'Name' => 'Ankesh Kumar', 'image' => 'IMG20240513094919.jpg'),
                48 => array('AppID' => '5895', 'Name' => 'Ansh', 'image' => 'IMG20240516104427.jpg'),
                49 => array('AppID' => '5886', 'Name' => 'Aradhya', 'image' => 'IMG20240516104345.jpg'),
                50 => array('AppID' => '5862', 'Name' => 'Arush', 'image' => 'IMG20240513094211.jpg'),
                51 => array('AppID' => '5876', 'Name' => 'Bhavyansh', 'image' => 'IMG20240513094223.jpg'),
                52 => array('AppID' => '5869', 'Name' => 'Deep Verma', 'image' => 'IMG20240513094246.jpg'),
                53 => array('AppID' => '5891', 'Name' => 'Deepak Kumar', 'image' => 'IMG20240516104359.jpg'),
                54 => array('AppID' => '5885', 'Name' => 'Devika', 'image' => 'IMG20240516104412.jpg'),
                55 => array('AppID' => '5866', 'Name' => 'Harshit', 'image' => 'IMG20240513094301.jpg'),
                56 => array('AppID' => '5874', 'Name' => 'Harshita', 'image' => 'IMG20240513094139.jpg'),
                57 => array('AppID' => '5879', 'Name' => 'Harshita Soni', 'image' => 'IMG20240513094810.jpg'),
                58 => array('AppID' => '5875', 'Name' => 'Himansh Kumar', 'image' => 'IMG20240513094313.jpg'),
                59 => array('AppID' => '5878', 'Name' => 'Inaya Beg', 'image' => 'IMG20240513094744.jpg'),
                60 => array('AppID' => '5881', 'Name' => 'Jara Khatoon', 'image' => 'IMG20240513094730.jpg'),
                61 => array('AppID' => '5884', 'Name' => 'Kajal', 'image' => 'IMG20240516104321.jpg'),
                62 => array('AppID' => '5900', 'Name' => 'Kanishka', 'image' => 'IMG20240513094845.jpg'),
                63 => array('AppID' => '5867', 'Name' => 'Kartik', 'image' => 'IMG20240516104303.jpg'),
                64 => array('AppID' => '5917', 'Name' => 'Kashish', 'image' => 'IMG20240513094934.jpg'),
                65 => array('AppID' => '5887', 'Name' => 'Mayank', 'image' => 'IMG20240513094833.jpg'),
                66 => array('AppID' => '5901', 'Name' => 'Mu Eshan', 'image' => 'IMG20240513094941.jpg'),
                67 => array('AppID' => '5864', 'Name' => 'Muhammad Atif', 'image' => 'IMG20240513094401.jpg'),
                68 => array('AppID' => '5861', 'Name' => 'Muhammad Umair', 'image' => 'IMG20240513094332.jpg'),
                69 => array('AppID' => '5872', 'Name' => 'Piyush Raikwar', 'image' => 'IMG20240513094600.jpg'),
                70 => array('AppID' => '5860', 'Name' => 'Raj', 'image' => 'IMG20240513094635.jpg'),
                71 => array('AppID' => '5888', 'Name' => 'Rijban', 'image' => 'rijban.jpg'),
                72 => array('AppID' => '5948', 'Name' => 'Ritika', 'image' => 'IMG20240513094952.jpg'),
                73 => array('AppID' => '5868', 'Name' => 'Riyansh', 'image' => 'IMG20240513094645.jpg'),
                74 => array('AppID' => '5863', 'Name' => 'Rohan Singh', 'image' => 'IMG20240513094617.jpg'),
                75 => array('AppID' => '5871', 'Name' => 'Sanskar', 'image' => 'IMG20240513094652.jpg'),
                76 => array('AppID' => '5883', 'Name' => 'Sherya', 'image' => 'IMG20240513094821.jpg'),
                77 => array('AppID' => '5890', 'Name' => 'Tanvi', 'image' => 'IMG20240513094900.jpg'),
                78 => array('AppID' => '5859', 'Name' => 'Upasna', 'image' => 'IMG20240513094155.jpg'),
                79 => array('AppID' => '5873', 'Name' => 'Vansh Singh', 'image' => 'IMG20240513094544.jpg'),
                80 => array('AppID' => '5877', 'Name' => 'Vivek Kumar', 'image' => 'IMG20240513094703.jpg'),
                81 => array('AppID' => '5865', 'Name' => 'Yogyata Singh', 'image' => 'yogyta.jpg'),
                82 => array('AppID' => '6141', 'Name' => 'Aayush', 'image' => 'WhatsApp_Image_2024-07-25_at_10.23.14_AM.jpeg'),
                83 => array('AppID' => '5927', 'Name' => 'Afsana', 'image' => 'IMG20240513095101.jpg'),
                84 => array('AppID' => '5951', 'Name' => 'Akriti', 'image' => 'IMG20240513095728.jpg'),
                85 => array('AppID' => '5933', 'Name' => 'Alfiya', 'image' => 'IMG20240513095226.jpg'),
                86 => array('AppID' => '5945', 'Name' => 'Bhavishya Kushwaha', 'image' => 'IMG20240513095540.jpg'),
                87 => array('AppID' => '5926', 'Name' => 'Dhruv', 'image' => 'IMG20240513095050.jpg'),
                88 => array('AppID' => '5949', 'Name' => 'Ekra', 'image' => 'IMG20240513095628.jpg'),
                89 => array('AppID' => '5924', 'Name' => 'Harsh Kumar', 'image' => '2024-05-21-203642IMG20240513095525.jpg'),
                90 => array('AppID' => '5950', 'Name' => 'Himani', 'image' => 'himani.jpg'),
                91 => array('AppID' => '5910', 'Name' => 'Janvi', 'image' => 'IMG20240718101837.jpg'),
                92 => array('AppID' => '5944', 'Name' => 'Jeesan', 'image' => 'IMG20240513095605.jpg'),
                93 => array('AppID' => '5938', 'Name' => 'Kanak', 'image' => 'IMG20240513095322.jpg'),
                94 => array('AppID' => '6120', 'Name' => 'Krashna Ii', 'image' => 'IMG20240718101814.jpg'),
                95 => array('AppID' => '5942', 'Name' => 'Krishna I', 'image' => 'IMG20240513095502.jpg'),
                96 => array('AppID' => '5936', 'Name' => 'Mahira', 'image' => 'IMG20240513095310.jpg'),
                97 => array('AppID' => '5947', 'Name' => 'Mayank', 'image' => 'IMG20240513095622.jpg'),
                98 => array('AppID' => '5952', 'Name' => 'Munthaha Khurshid', 'image' => '2024-05-21-202523IMG20240513095739.jpg'),
                99 => array('AppID' => '5940', 'Name' => 'Nainsi', 'image' => 'nainsi_1st.jpg'),
                100 => array('AppID' => '5929', 'Name' => 'Namrata Singh', 'image' => 'IMG20240513095124.jpg'),
                101 => array('AppID' => '5939', 'Name' => 'Paridhi', 'image' => 'IMG20240513095332.jpg'),
                102 => array('AppID' => '5946', 'Name' => 'Paridhi Kumari', 'image' => 'IMG20240513095615.jpg'),
                103 => array('AppID' => '6090', 'Name' => 'Piyush', 'image' => 'IMG20240513095816.jpg'),
                104 => array('AppID' => '5937', 'Name' => 'Prashant Kumar', 'image' => 'IMG20240513095832.jpg'),
                105 => array('AppID' => '5928', 'Name' => 'Radhika', 'image' => 'IMG20240513095113.jpg'),
                106 => array('AppID' => '6130', 'Name' => 'Rashabh I', 'image' => 'IMG20240718101751.jpg'),
                107 => array('AppID' => '6136', 'Name' => 'Rishabh Ii', 'image' => '2024-07-24-182445IMG20240723084224.jpg'),
                108 => array('AppID' => '5930', 'Name' => 'Ritik', 'image' => 'IMG20240513095140.jpg'),
                109 => array('AppID' => '5925', 'Name' => 'Ritika', 'image' => 'IMG20240513095031.jpg'),
                110 => array('AppID' => '5882', 'Name' => 'Sagar', 'image' => 'sagar.jpg'),
                111 => array('AppID' => '5932', 'Name' => 'Shanavi Varma', 'image' => 'IMG20240513095211.jpg'),
                112 => array('AppID' => '5943', 'Name' => 'Shanvi Sahu', 'image' => 'IMG20240513095514.jpg'),
                113 => array('AppID' => '5941', 'Name' => 'Tanya', 'image' => 'IMG20240513095342.jpg'),
                114 => array('AppID' => '5935', 'Name' => 'Vansh', 'image' => 'IMG20240513095255.jpg'),
                115 => array('AppID' => '5706', 'Name' => 'Afsar', 'image' => '2024-05-19-195509IMG20240513095909.jpg'),
                116 => array('AppID' => '5707', 'Name' => 'Akil', 'image' => 'IMG20240513100408.jpg'),
                117 => array('AppID' => '6123', 'Name' => 'Aleena', 'image' => 'IMG20240718102020.jpg'),
                118 => array('AppID' => '5718', 'Name' => 'Ameen Veg', 'image' => 'IMG20240513100435.jpg'),
                119 => array('AppID' => '5705', 'Name' => 'Anushka', 'image' => 'IMG20240723084308.jpg'),
                120 => array('AppID' => '5713', 'Name' => 'Aradhya', 'image' => 'IMG20240513100005.jpg'),
                121 => array('AppID' => '5720', 'Name' => 'Asis', 'image' => 'IMG20240513100446.jpg'),
                122 => array('AppID' => '5695', 'Name' => 'Avanee', 'image' => 'IMG20240513100037.jpg'),
                123 => array('AppID' => '5714', 'Name' => 'Chanchal', 'image' => 'IMG20240516103656.jpg'),
                124 => array('AppID' => '5708', 'Name' => 'Diksha', 'image' => 'IMG20240513100048.jpg'),
                125 => array('AppID' => '5703', 'Name' => 'Janvi', 'image' => 'IMG20240513100058.jpg'),
                126 => array('AppID' => '5717', 'Name' => 'Jaykishan', 'image' => 'IMG20240513100308.jpg'),
                127 => array('AppID' => '5712', 'Name' => 'Kanika Verma', 'image' => 'IMG20240513100419.jpg'),
                128 => array('AppID' => '6109', 'Name' => 'Krish', 'image' => 'IMG20240718102058.jpg'),
                129 => array('AppID' => '5696', 'Name' => 'Lakshya', 'image' => 'IMG20240513100317.jpg'),
                130 => array('AppID' => '6107', 'Name' => 'Mohd Ali', 'image' => 'IMG20240516103631.jpg'),
                131 => array('AppID' => '5710', 'Name' => 'Mohd. Ayan', 'image' => 'IMG20240513100330.jpg'),
                132 => array('AppID' => '5702', 'Name' => 'Nainshi', 'image' => 'IMG20240513100105.jpg'),
                133 => array('AppID' => '5701', 'Name' => 'Naitik', 'image' => 'IMG20240513100358.jpg'),
                134 => array('AppID' => '5704', 'Name' => 'Nitya', 'image' => 'IMG20240513100116.jpg'),
                135 => array('AppID' => '5698', 'Name' => 'Poorvi', 'image' => 'IMG20240513100126.jpg'),
                136 => array('AppID' => '5700', 'Name' => 'Rishika Soni', 'image' => 'IMG20240513100141.jpg'),
                137 => array('AppID' => '5709', 'Name' => 'Ruhi I', 'image' => 'IMG20240513100227.jpg'),
                138 => array('AppID' => '5694', 'Name' => 'Saloni', 'image' => 'IMG20240513100243.jpg'),
                139 => array('AppID' => '6143', 'Name' => 'Sanskrti', 'image' => 'WhatsApp_Image_2024-07-26_at_8.29.18_AM.jpeg'),
                140 => array('AppID' => '5711', 'Name' => 'Shivanya', 'image' => 'IMG20240513100252.jpg'),
                141 => array('AppID' => '5699', 'Name' => 'Tamanna', 'image' => 'tamanna.jpg'),
                142 => array('AppID' => '5715', 'Name' => 'Yamika Verma', 'image' => '2024-05-19-210125IMG20240513100259.jpg'),
                143 => array('AppID' => '5721', 'Name' => 'Yas Vardhan', 'image' => 'yash.jpg'),
                144 => array('AppID' => '6081', 'Name' => 'Yash', 'image' => 'IMG20240513100527.jpg'),
                145 => array('AppID' => '6082', 'Name' => 'Yashika', 'image' => 'IMG20240513100515.jpg'),
                146 => array('AppID' => '6085', 'Name' => 'Aarbi', 'image' => 'IMG20240722104001.jpg'),
                147 => array('AppID' => '5731', 'Name' => 'Aarohi Anuragi', 'image' => 'IMG20240513100757.jpg'),
                148 => array('AppID' => '5724', 'Name' => 'Abhay', 'image' => 'IMG20240513101050.jpg'),
                149 => array('AppID' => '5737', 'Name' => 'Aditya', 'image' => 'IMG20240513101124.jpg'),
                150 => array('AppID' => '5734', 'Name' => 'Aditya Kumar', 'image' => 'IMG20240513101134.jpg'),
                151 => array('AppID' => '5732', 'Name' => 'Alok Rajput', 'image' => 'IMG20240513101143.jpg'),
                152 => array('AppID' => '5723', 'Name' => 'Anushka', 'image' => 'IMG20240513100814.jpg'),
                153 => array('AppID' => '5730', 'Name' => 'Arpit', 'image' => 'IMG20240513101150.jpg'),
                154 => array('AppID' => '5741', 'Name' => 'Atik', 'image' => 'IMG20240513101158.jpg'),
                155 => array('AppID' => '6092', 'Name' => 'Ayoosh', 'image' => 'IMG20240513101351.jpg'),
                156 => array('AppID' => '5746', 'Name' => 'Bharti', 'image' => 'IMG20240513101446.jpg'),
                157 => array('AppID' => '5729', 'Name' => 'Dev', 'image' => 'IMG20240513101207.jpg'),
                158 => array('AppID' => '5751', 'Name' => 'Devansh Chaursiya', 'image' => 'IMG20240723084759.jpg'),
                159 => array('AppID' => '5727', 'Name' => 'Esmita', 'image' => 'IMG20240513100824.jpg'),
                160 => array('AppID' => '5744', 'Name' => 'Hardik', 'image' => 'IMG20240513101223.jpg'),
                161 => array('AppID' => '5747', 'Name' => 'Himanshu Prajapati', 'image' => 'IMG20240513101243.jpg'),
                162 => array('AppID' => '5740', 'Name' => 'Hradesh Nandni', 'image' => 'IMG20240513100840.jpg'),
                163 => array('AppID' => '5728', 'Name' => 'Krashna Soni', 'image' => 'WhatsApp_Image_2024-07-12_at_1.47.23_PM.jpeg'),
                164 => array('AppID' => '5753', 'Name' => 'Krish Soni', 'image' => 'IMG20240513101235.jpg'),
                165 => array('AppID' => '5733', 'Name' => 'Mannat', 'image' => 'IMG20240513100847.jpg'),
                166 => array('AppID' => '5722', 'Name' => 'Manshi', 'image' => 'IMG20240513100857.jpg'),
                167 => array('AppID' => '5735', 'Name' => 'Manya', 'image' => 'IMG20240513100905.jpg'),
                168 => array('AppID' => '5749', 'Name' => 'Mohani', 'image' => 'IMG20240513101029.jpg'),
                169 => array('AppID' => '5739', 'Name' => 'Naitik', 'image' => 'IMG20240513101300.jpg'),
                170 => array('AppID' => '5745', 'Name' => 'Naseeb', 'image' => 'IMG20240513101307.jpg'),
                171 => array('AppID' => '5738', 'Name' => 'Noor Afzal', 'image' => 'IMG20240513101315.jpg'),
                172 => array('AppID' => '6079', 'Name' => 'Payal', 'image' => 'IMG20240722104009.jpg'),
                173 => array('AppID' => '5736', 'Name' => 'Radha', 'image' => 'IMG20240513101008.jpg'),
                174 => array('AppID' => '5726', 'Name' => 'Saniya', 'image' => 'IMG20240513101018.jpg'),
                175 => array('AppID' => '5725', 'Name' => 'Sharad', 'image' => 'IMG20240513101330.jpg'),
                176 => array('AppID' => '5743', 'Name' => 'Shivam', 'image' => 'IMG20240513101344.jpg'),
                177 => array('AppID' => '5752', 'Name' => 'Shivansh', 'image' => 'IMG20240513101323.jpg'),
                178 => array('AppID' => '6134', 'Name' => 'Suraj', 'image' => 'IMG20240722103944.jpg'),
                179 => array('AppID' => '5742', 'Name' => 'Vansh', 'image' => 'IMG20240513101359.jpg'),
                180 => array('AppID' => '6140', 'Name' => 'Vijay', 'image' => 'IMG20240722103929.jpg'),
                181 => array('AppID' => '5775', 'Name' => 'Aalekh Raj', 'image' => 'IMG20240513102134.jpg'),
                182 => array('AppID' => '5762', 'Name' => 'Alina', 'image' => 'IMG20240513101855.jpg'),
                183 => array('AppID' => '5777', 'Name' => 'Aman', 'image' => 'IMG20240513102140.jpg'),
                184 => array('AppID' => '5771', 'Name' => 'Ansh Gupta', 'image' => 'IMG20240513102146.jpg'),
                185 => array('AppID' => '5781', 'Name' => 'Ansh Sahu', 'image' => 'IMG20240722104038.jpg'),
                186 => array('AppID' => '5769', 'Name' => 'Ansh Soni', 'image' => 'IMG20240513102150.jpg'),
                187 => array('AppID' => '5780', 'Name' => 'Apeksha', 'image' => 'IMG20240513101905.jpg'),
                188 => array('AppID' => '5756', 'Name' => 'Avnee', 'image' => '2024-05-21-193453IMG20240513101913.jpg'),
                189 => array('AppID' => '5773', 'Name' => 'Deependra Kumar', 'image' => 'IMG20240516103516.jpg'),
                190 => array('AppID' => '5754', 'Name' => 'Dishika', 'image' => 'IMG20240513101922.jpg'),
                191 => array('AppID' => '5772', 'Name' => 'Hardik Soni', 'image' => 'IMG20240513102207.jpg'),
                192 => array('AppID' => '5767', 'Name' => 'Harsh', 'image' => 'IMG20240513102215.jpg'),
                193 => array('AppID' => '5763', 'Name' => 'Harshita', 'image' => 'IMG20240513101946.jpg'),
                194 => array('AppID' => '5786', 'Name' => 'Kapil', 'image' => 'kapil.jpg'),
                195 => array('AppID' => '6088', 'Name' => 'Kartik', 'image' => 'IMG20240513102312.jpg'),
                196 => array('AppID' => '5783', 'Name' => 'Kartik Kumar', 'image' => 'IMG20240513102300.jpg'),
                197 => array('AppID' => '5758', 'Name' => 'Mahak', 'image' => 'IMG20240516102645.jpg'),
                198 => array('AppID' => '5757', 'Name' => 'Mahi', 'image' => 'IMG20240516102716.jpg'),
                199 => array('AppID' => '5768', 'Name' => 'Mo. Asif', 'image' => 'IMG20240513102223.jpg'),
                200 => array('AppID' => '5770', 'Name' => 'Mragendra Singh', 'image' => 'IMG20240513102228.jpg'),
                201 => array('AppID' => '5778', 'Name' => 'Mu Nadeem', 'image' => 'IMG20240516103359.jpg'),
                202 => array('AppID' => '5779', 'Name' => 'Muhammad Sajid', 'image' => 'IMG20240513102236.jpg'),
                203 => array('AppID' => '6083', 'Name' => 'Nainshi', 'image' => 'IMG20240513102126.jpg'),
                204 => array('AppID' => '6126', 'Name' => 'Naveen', 'image' => 'IMG20240722104030.jpg'),
                205 => array('AppID' => '5761', 'Name' => 'Navya Sahu', 'image' => 'IMG20240513101952.jpg'),
                206 => array('AppID' => '5759', 'Name' => 'Pooja', 'image' => 'IMG20240513101959.jpg'),
                207 => array('AppID' => '5774', 'Name' => 'Pranshu', 'image' => 'IMG20240513102242.jpg'),
                208 => array('AppID' => '5787', 'Name' => 'Pratigya Anuragi', 'image' => '2024-05-21-193904IMG20240513101837.jpg'),
                209 => array('AppID' => '5760', 'Name' => 'Priyanshi', 'image' => 'IMG20240513102007.jpg'),
                210 => array('AppID' => '5755', 'Name' => 'Rashmi', 'image' => 'IMG20240513102013.jpg'),
                211 => array('AppID' => '6091', 'Name' => 'Rishu', 'image' => 'IMG20240513102318.jpg'),
                212 => array('AppID' => '5766', 'Name' => 'Ritik Yadav', 'image' => 'IMG20240513102247.jpg'),
                213 => array('AppID' => '5750', 'Name' => 'Shefu', 'image' => 'IMG20240513102337.jpg'),
                214 => array('AppID' => '6096', 'Name' => 'Shubh', 'image' => 'IMG20240513102323.jpg'),
                215 => array('AppID' => '5788', 'Name' => 'Uttra', 'image' => 'IMG20240513102020.jpg'),
                216 => array('AppID' => '5765', 'Name' => 'Vansh Kosta', 'image' => 'IMG20240513102251.jpg'),
                217 => array('AppID' => '5776', 'Name' => 'Yash', 'image' => 'IMG20240513102255.jpg'),
                218 => array('AppID' => '5813', 'Name' => 'Ansh', 'image' => 'IMG20240513102808.jpg'),
                219 => array('AppID' => '5792', 'Name' => 'Anshika', 'image' => 'IMG20240513102617.jpg'),
                220 => array('AppID' => '6084', 'Name' => 'Anshul', 'image' => 'IMG20240513102936.jpg'),
                221 => array('AppID' => '5817', 'Name' => 'Arhan Khan', 'image' => 'IMG20240513102917.jpg'),
                222 => array('AppID' => '5799', 'Name' => 'Aryan', 'image' => 'ARYAN.jpg'),
                223 => array('AppID' => '5809', 'Name' => 'Asta', 'image' => 'IMG20240513102624.jpg'),
                224 => array('AppID' => '5803', 'Name' => 'Ayush', 'image' => 'IMG20240513102817.jpg'),
                225 => array('AppID' => '5804', 'Name' => 'Chandra Sekhar', 'image' => 'IMG20240513102822.jpg'),
                226 => array('AppID' => '5807', 'Name' => 'Deepak Gupta', 'image' => 'IMG20240513102827.jpg'),
                227 => array('AppID' => '5793', 'Name' => 'Harshita', 'image' => 'IMG20240513102636.jpg'),
                228 => array('AppID' => '5814', 'Name' => 'Hina Khatun', 'image' => 'IMG20240516102201.jpg'),
                229 => array('AppID' => '5800', 'Name' => 'Jigar', 'image' => 'JIGAR.jpg'),
                230 => array('AppID' => '6075', 'Name' => 'Karan', 'image' => 'IMG20240516102414.jpg'),
                231 => array('AppID' => '5811', 'Name' => 'Krishn Kumar', 'image' => 'IMG20240513102837.jpg'),
                232 => array('AppID' => '5806', 'Name' => 'Lakshya', 'image' => 'IMG20240513102840.jpg'),
                233 => array('AppID' => '5801', 'Name' => 'Mayank', 'image' => 'IMG20240516102303.jpg'),
                234 => array('AppID' => '5802', 'Name' => 'Mayank Sahu', 'image' => 'IMG20240513102844.jpg'),
                235 => array('AppID' => '5805', 'Name' => 'Mohit Ii', 'image' => 'IMG20240513102852.jpg'),
                236 => array('AppID' => '6077', 'Name' => 'Prince', 'image' => 'IMG20240513102929.jpg'),
                237 => array('AppID' => '5798', 'Name' => 'Priyanshu', 'image' => 'IMG20240513102857.jpg'),
                238 => array('AppID' => '5794', 'Name' => 'Purvika', 'image' => 'IMG20240513102644.jpg'),
                239 => array('AppID' => '6087', 'Name' => 'Reetesh', 'image' => 'IMG20240513102923.jpg'),
                240 => array('AppID' => '5808', 'Name' => 'Ritik', 'image' => 'IMG20240513102901.jpg'),
                241 => array('AppID' => '5796', 'Name' => 'Riya', 'image' => 'IMG20240513102654.jpg'),
                242 => array('AppID' => '5795', 'Name' => 'Salonee', 'image' => 'IMG20240513102703.jpg'),
                243 => array('AppID' => '5815', 'Name' => 'Saumya', 'image' => 'IMG20240513102750.jpg'),
                244 => array('AppID' => '5789', 'Name' => 'Shiva Soni', 'image' => 'IMG20240516102546.jpg'),
                245 => array('AppID' => '5791', 'Name' => 'Suhana', 'image' => 'IMG20240513102801.jpg'),
                246 => array('AppID' => '5812', 'Name' => 'Vivek', 'image' => 'IMG20240513102905.jpg'),
                247 => array('AppID' => '5810', 'Name' => 'Yash', 'image' => 'IMG20240513102910.jpg'),
                248 => array('AppID' => '5976', 'Name' => 'Adarsh', 'image' => 'IMG20240513103259.jpg'),
                249 => array('AppID' => '5975', 'Name' => 'Aditya Soni', 'image' => 'IMG20240513103304.jpg'),
                250 => array('AppID' => '5979', 'Name' => 'Ajay Kumar Yadav', 'image' => 'IMG20240513103445.jpg'),
                251 => array('AppID' => '5953', 'Name' => 'Alshan', 'image' => 'IMG20240513103312.jpg'),
                252 => array('AppID' => '5974', 'Name' => 'Bhoomi', 'image' => 'IMG20240513103156.jpg'),
                253 => array('AppID' => '5973', 'Name' => 'Devanshu', 'image' => 'IMG20240513103318.jpg'),
                254 => array('AppID' => '5972', 'Name' => 'Geeta', 'image' => 'IMG20240513103205.jpg'),
                255 => array('AppID' => '5971', 'Name' => 'Harsh', 'image' => 'IMG20240513103323.jpg'),
                256 => array('AppID' => '5977', 'Name' => 'Hassan', 'image' => 'IMG20240513103436.jpg'),
                257 => array('AppID' => '5969', 'Name' => 'Krashana Soni', 'image' => 'IMG20240513103330.jpg'),
                258 => array('AppID' => '5968', 'Name' => 'Krishna', 'image' => 'IMG20240513103350.jpg'),
                259 => array('AppID' => '5967', 'Name' => 'Lakshya', 'image' => 'IMG20240513103357.jpg'),
                260 => array('AppID' => '6078', 'Name' => 'Manish', 'image' => 'IMG20240722104047.jpg'),
                261 => array('AppID' => '5966', 'Name' => 'Manvi', 'image' => 'IMG20240513103213.jpg'),
                262 => array('AppID' => '5965', 'Name' => 'Mohammad Sakib', 'image' => 'IMG20240513103404.jpg'),
                263 => array('AppID' => '5963', 'Name' => 'Paridhi', 'image' => 'IMG20240513103223.jpg'),
                264 => array('AppID' => '5962', 'Name' => 'Prachi Anuragi', 'image' => 'IMG20240513103233.jpg'),
                265 => array('AppID' => '5961', 'Name' => 'Pratham Soni', 'image' => 'IMG20240516102120.jpg'),
                266 => array('AppID' => '5960', 'Name' => 'Radhika', 'image' => 'IMG20240513103239.jpg'),
                267 => array('AppID' => '5959', 'Name' => 'Rajveer', 'image' => 'IMG20240513103411.jpg'),
                268 => array('AppID' => '5978', 'Name' => 'Rohit', 'image' => 'IMG20240513103451.jpg'),
                269 => array('AppID' => '6074', 'Name' => 'Sandhya', 'image' => 'WhatsApp_Image_2024-07-25_at_10.23.13_AM_(1).jpeg'),
                270 => array('AppID' => '5954', 'Name' => 'Sanjay Singh', 'image' => 'IMG20240513103420.jpg'),
                271 => array('AppID' => '5958', 'Name' => 'Shabnam Khatoon', 'image' => 'IMG20240513103244.jpg'),
                272 => array('AppID' => '5957', 'Name' => 'Tabis', 'image' => 'IMG20240722104056.jpg'),
                273 => array('AppID' => '6073', 'Name' => 'Umang', 'image' => 'IMG20240516102146.jpg'),
                274 => array('AppID' => '5956', 'Name' => 'Umesh Shriwas', 'image' => 'IMG20240513103430.jpg'),
                275 => array('AppID' => '5955', 'Name' => 'Vandana Kumari', 'image' => 'IMG20240513103253.jpg'),
                276 => array('AppID' => '6007', 'Name' => 'Aaseem', 'image' => 'IMG20240516100857.jpg'),
                277 => array('AppID' => '6006', 'Name' => 'Anshika Prajapati', 'image' => 'IMG20240513103735.jpg'),
                278 => array('AppID' => '6086', 'Name' => 'Anshul', 'image' => 'IMG20240516100918.jpg'),
                279 => array('AppID' => '6005', 'Name' => 'Arpit Yadav', 'image' => 'IMG20240513103847.jpg'),
                280 => array('AppID' => '6004', 'Name' => 'Arun', 'image' => 'arun_rajkumar.jpg'),
                281 => array('AppID' => '6003', 'Name' => 'Azad', 'image' => 'IMG20240513103916.jpg'),
                282 => array('AppID' => '6002', 'Name' => 'Bhanchandra', 'image' => 'IMG20240513103926.jpg'),
                283 => array('AppID' => '6001', 'Name' => 'Bhoopesh Kumar', 'image' => 'bhoopesh.jpg'),
                284 => array('AppID' => '6000', 'Name' => 'Himangi', 'image' => 'IMG20240513103743.jpg'),
                285 => array('AppID' => '6014', 'Name' => 'Himanshu', 'image' => 'himanshu.jpg'),
                286 => array('AppID' => '5999', 'Name' => 'Jahar Singh', 'image' => 'jahar.jpg'),
                287 => array('AppID' => '6132', 'Name' => 'Kanchan', 'image' => 'IMG20240718113137.jpg'),
                288 => array('AppID' => '5998', 'Name' => 'Krashna Anuragi', 'image' => 'IMG20240513104012.jpg'),
                289 => array('AppID' => '6009', 'Name' => 'Krishna Gautam', 'image' => 'IMG20240516100943.jpg'),
                290 => array('AppID' => '5997', 'Name' => 'Lavkush Prajapati', 'image' => 'IMG20240513104021.jpg'),
                291 => array('AppID' => '6138', 'Name' => 'Lokendra Singh Rai', 'image' => 'IMG20240718113113.jpg'),
                292 => array('AppID' => '5996', 'Name' => 'Manish Rajpoot', 'image' => 'IMG20240516101000.jpg'),
                293 => array('AppID' => '5995', 'Name' => 'Manvi', 'image' => 'IMG20240513103750.jpg'),
                294 => array('AppID' => '6106', 'Name' => 'Mohammad Ahmad', 'image' => 'IMG20240513104202.jpg'),
                295 => array('AppID' => '5993', 'Name' => 'Naitik', 'image' => 'IMG20240513104031.jpg'),
                296 => array('AppID' => '5994', 'Name' => 'Nancy', 'image' => 'IMG20240718113216.jpg'),
                297 => array('AppID' => '5992', 'Name' => 'Nayan Singh', 'image' => 'IMG20240516101016.jpg'),
                298 => array('AppID' => '6099', 'Name' => 'Neha Kumari', 'image' => 'IMG20240513104155.jpg'),
                299 => array('AppID' => '5991', 'Name' => 'Poonam', 'image' => 'IMG20240513103806.jpg'),
                300 => array('AppID' => '6013', 'Name' => 'Pradip', 'image' => 'IMG20240513104134.jpg'),
                301 => array('AppID' => '6015', 'Name' => 'Pranshu', 'image' => '2024-05-29-195449IMG20240513104141.jpg'),
                302 => array('AppID' => '5990', 'Name' => 'Pratigya Soni', 'image' => 'IMG20240513103814.jpg'),
                303 => array('AppID' => '6008', 'Name' => 'Prince Sahu', 'image' => 'IMG20240516101132.jpg'),
                304 => array('AppID' => '5989', 'Name' => 'Ranu Rajpoot', 'image' => 'IMG20240516101048.jpg'),
                305 => array('AppID' => '6101', 'Name' => 'Ravi Pratap', 'image' => 'IMG20240718113155.jpg'),
                306 => array('AppID' => '6012', 'Name' => 'Samarth', 'image' => 'IMG20240513104059.jpg'),
                307 => array('AppID' => '5988', 'Name' => 'Shalni', 'image' => 'IMG20240513103824.jpg'),
                308 => array('AppID' => '5987', 'Name' => 'Shivansh', 'image' => 'IMG20240718113207.jpg'),
                309 => array('AppID' => '6139', 'Name' => 'Shivanshu', 'image' => 'IMG20240723083453.jpg'),
                310 => array('AppID' => '5985', 'Name' => 'Srashti', 'image' => 'IMG20240513103832.jpg'),
                311 => array('AppID' => '6011', 'Name' => 'Sudhanshu', 'image' => 'sudhanshu.jpg'),
                312 => array('AppID' => '5984', 'Name' => 'Tarun Kumar Kosta', 'image' => 'IMG20240513104111.jpg'),
                313 => array('AppID' => '5983', 'Name' => 'Vimlesh', 'image' => 'IMG20240513103839.jpg'),
                314 => array('AppID' => '5982', 'Name' => 'Vishnu Iind', 'image' => 'IMG20240513104128.jpg'),
                315 => array('AppID' => '6122', 'Name' => 'Yogesh', 'image' => '2024-07-24-182259IMG20240723083453.jpg'),
                316 => array('AppID' => '6040', 'Name' => 'Aditya Raj', 'image' => 'IMG20240513104346.jpg'),
                317 => array('AppID' => '6039', 'Name' => 'Alok Soni', 'image' => 'IMG20240513104353.jpg'),
                318 => array('AppID' => '6037', 'Name' => 'Anshika I', 'image' => 'IMG20240516095825.jpg'),
                319 => array('AppID' => '6038', 'Name' => 'Anshika Ii', 'image' => 'IMG20240513104255.jpg'),
                320 => array('AppID' => '6036', 'Name' => 'Anshu', 'image' => 'IMG20240513104359.jpg'),
                321 => array('AppID' => '6045', 'Name' => 'Arun', 'image' => 'IMG20240513104808.jpg'),
                322 => array('AppID' => '6035', 'Name' => 'Arun Kumar', 'image' => 'IMG20240513104632.jpg'),
                323 => array('AppID' => '6034', 'Name' => 'Bhoop Singh', 'image' => 'IMG20240513104447.jpg'),
                324 => array('AppID' => '6041', 'Name' => 'Deepansh', 'image' => 'IMG20240516095558.jpg'),
                325 => array('AppID' => '6033', 'Name' => 'Dev Kumar', 'image' => '2024-05-18-210953IMG20240513104527.jpg'),
                326 => array('AppID' => '6032', 'Name' => 'Divya Kumari', 'image' => 'IMG20240516095638.jpg'),
                327 => array('AppID' => '6031', 'Name' => 'Garima', 'image' => 'IMG20240516095618.jpg'),
                328 => array('AppID' => '6042', 'Name' => 'Harash Ii', 'image' => 'IMG20240513104817.jpg'),
                329 => array('AppID' => '6030', 'Name' => 'Harsh I', 'image' => 'IMG20240513104543.jpg'),
                330 => array('AppID' => '6029', 'Name' => 'Himansh', 'image' => 'IMG20240513104601.jpg'),
                331 => array('AppID' => '6028', 'Name' => 'Himanshu', 'image' => '2024-07-24-181132IMG20240722104141.jpg'),
                332 => array('AppID' => '6027', 'Name' => 'Kuldeep Soni', 'image' => 'IMG20240513104643.jpg'),
                333 => array('AppID' => '6026', 'Name' => 'Mayank', 'image' => 'IMG20240513104653.jpg'),
                334 => array('AppID' => '6025', 'Name' => 'Mo. Raheesh', 'image' => 'IMG20240513104703.jpg'),
                335 => array('AppID' => '6133', 'Name' => 'Monika', 'image' => 'IMG20240722104108.jpg'),
                336 => array('AppID' => '6044', 'Name' => 'Monika Ii', 'image' => 'IMG20240513104329.jpg'),
                337 => array('AppID' => '6024', 'Name' => 'Nayan Kumar', 'image' => 'IMG20240513104743.jpg'),
                338 => array('AppID' => '6023', 'Name' => 'Pratiksha', 'image' => '2024-07-24-181028IMG20240722104117.jpg'),
                339 => array('AppID' => '6022', 'Name' => 'Raghvendra', 'image' => 'IMG20240722104156.jpg'),
                340 => array('AppID' => '6021', 'Name' => 'Rakhi', 'image' => 'rakhi.jpg'),
                341 => array('AppID' => '6020', 'Name' => 'Ravi Kumar', 'image' => 'IMG20240723085034.jpg'),
                342 => array('AppID' => '6019', 'Name' => 'Sayni', 'image' => 'sayni.jpg'),
                343 => array('AppID' => '6018', 'Name' => 'Shubham', 'image' => 'shubham.jpg'),
                344 => array('AppID' => '6017', 'Name' => 'Suyash', 'image' => 'IMG20240722104207.jpg'),
            );

            $excelRecord = array(
                0 => array('AppID' => '6152', 'Name' => 'ANUSHKA', 'image' => '1724815461_6152studentphoto.jpg'),
                1 => array('AppID' => '6147', 'Name' => 'BHAGVATI', 'image' => '1724815499_6147studentphoto.jpg'),
                2 => array('AppID' => '6153', 'Name' => 'DRISHTI', 'image' => '1724815530_6153studentphoto.jpg'),
                3 => array('AppID' => '6156', 'Name' => 'HARSH', 'image' => '1724815559_6156studentphoto.jpg'),
                4 => array('AppID' => '6150', 'Name' => 'DUSHYANT SINGH', 'image' => '1724815777_6150studentphoto.jpg'),
                5 => array('AppID' => '6151', 'Name' => 'SANJANA ANURAGI', 'image' => '1724815854_6151studentphoto.jpg'),
                6 => array('AppID' => '6135', 'Name' => 'SHIVA', 'image' => '1722409177_photo.jpg'),
                7 => array('AppID' => '6154', 'Name' => 'PRAVEEN', 'image' => ''),
                8 => array('AppID' => '6149', 'Name' => 'ANSHIKA RAJPOOT', 'image' => 'anshika.jpg'),
                9 => array('AppID' => '6155', 'Name' => 'ANITA', 'image' => '1724816132_6155studentphoto.jpg'),
                10 => array('AppID' => '6142', 'Name' => 'SHILPI DEVI', 'image' => '1724816078_6142studentphoto.jpg'),
                11 => array('AppID' => '5797', 'Name' => 'MOHIT I', 'image' => 'IMG20240513102847.jpg'),
                12 => array('AppID' => '5980', 'Name' => 'ASHVENDRA', 'image' => 'asvendra.jpg'),
                13 => array('AppID' => '6148', 'Name' => 'NIKHIL', 'image' => '1724816391_6148studentphoto.jpg'),
                14 => array('AppID' => '5981', 'Name' => 'VISHNU IST', 'image' => '1724816412_5981studentphoto.jpg'),
                15 => array('AppID' => '6068', 'Name' => 'AANCHAL', 'image' => '090006.jpg'),
                16 => array('AppID' => '6067', 'Name' => 'ABHAY KUMAR', 'image' => '090020.jpg'),
                17 => array('AppID' => '6070', 'Name' => 'ABHILASHA ANURAGI', 'image' => '090005.jpg'),
                18 => array('AppID' => '6065', 'Name' => 'ANKUSH', 'image' => '090022.jpg'),
                19 => array('AppID' => '6064', 'Name' => 'ANSHIKA', 'image' => '090008.jpg'),
                20 => array('AppID' => '6094', 'Name' => 'DHRUV KUMAR', 'image' => '090024.jpg'),
                21 => array('AppID' => '6063', 'Name' => 'JAY RAIKWAR', 'image' => '090023.jpg'),
                22 => array('AppID' => '6071', 'Name' => 'KANCHAN', 'image' => '090009.jpg'),
                23 => array('AppID' => '6100', 'Name' => 'LALIT KUMAR', 'image' => '090026.jpg'),
                24 => array('AppID' => '6062', 'Name' => 'MAHI GUPTA', 'image' => '090010.jpg'),
                25 => array('AppID' => '6061', 'Name' => 'MAITHILI', 'image' => '090004.jpg'),
                26 => array('AppID' => '6060', 'Name' => 'MANYA', 'image' => '090018.jpg'),
                27 => array('AppID' => '6059', 'Name' => 'NAINSI', 'image' => '090003.jpg'),
                28 => array('AppID' => '6046', 'Name' => 'NEERAJ', 'image' => '090012.jpg'),
                29 => array('AppID' => '6098', 'Name' => 'NIKHIL', 'image' => '090025.jpg'),
                30 => array('AppID' => '6058', 'Name' => 'PRADUM KUMAR', 'image' => '090019.jpg'),
                31 => array('AppID' => '6057', 'Name' => 'PRINCE PRAJAPATI', 'image' => '090021.jpg'),
                32 => array('AppID' => '6056', 'Name' => 'RADHIKA', 'image' => '2024-08-07-135908090011.jpg'),
                33 => array('AppID' => '6055', 'Name' => 'RAVI KUMAR', 'image' => '090013.jpg'),
                34 => array('AppID' => '6095', 'Name' => 'RITIK KUMAR', 'image' => '090027.jpg'),
                35 => array('AppID' => '6054', 'Name' => 'RIYA RAJPUT', 'image' => '090007.jpg'),
                36 => array('AppID' => '6053', 'Name' => 'RIYA VERMA', 'image' => '090002.jpg'),
                37 => array('AppID' => '6069', 'Name' => 'SATYARTH', 'image' => '090017.jpg'),
                38 => array('AppID' => '6052', 'Name' => 'SHARDA DEVI', 'image' => '2024-08-07-140023090001.jpg'),
                39 => array('AppID' => '6049', 'Name' => 'VAIBHAV GUPTA', 'image' => '090015.jpg'),
                40 => array('AppID' => '6048', 'Name' => 'YASH KOSTA', 'image' => '090014.jpg'),
                41 => array('AppID' => '6047', 'Name' => 'YOGESH', 'image' => '090016.jpg'),
                42 => array('AppID' => '6119', 'Name' => 'ANSHUL', 'image' => ''),
                43 => array('AppID' => '5833', 'Name' => 'AYUSH', 'image' => ''),
                44 => array('AppID' => '5832', 'Name' => 'DEEPESH', 'image' => ''),
                45 => array('AppID' => '5831', 'Name' => 'ESHIKA', 'image' => ''),
                46 => array('AppID' => '5835', 'Name' => 'GULAM MUENUDDIN', 'image' => ''),
                47 => array('AppID' => '5830', 'Name' => 'JYOTI', 'image' => ''),
                48 => array('AppID' => '5829', 'Name' => 'MAHAK', 'image' => ''),
                49 => array('AppID' => '5828', 'Name' => 'MAHI', 'image' => ''),
                50 => array('AppID' => '5827', 'Name' => 'NITIN', 'image' => ''),
                51 => array('AppID' => '5834', 'Name' => 'PARTHIK', 'image' => ''),
                52 => array('AppID' => '5826', 'Name' => 'PAVAN', 'image' => ''),
                53 => array('AppID' => '5825', 'Name' => 'RADHA', 'image' => ''),
                54 => array('AppID' => '5824', 'Name' => 'ROHAN KOSTA', 'image' => ''),
                55 => array('AppID' => '5823', 'Name' => 'SATYAM', 'image' => ''),
                56 => array('AppID' => '5822', 'Name' => 'SHIVANI', 'image' => ''),
                57 => array('AppID' => '5821', 'Name' => 'SUMIT ANURAGEE', 'image' => ''),
                58 => array('AppID' => '5820', 'Name' => 'TRAPTI KOSHTA', 'image' => ''),
                59 => array('AppID' => '5819', 'Name' => 'YASHIKA SONI', 'image' => ''),
                60 => array('AppID' => '5818', 'Name' => 'YOGENDRA KUMAR', 'image' => ''),
            );
            
            // ambm
            $excelRecord = array(
                0 => array('AppID' => '6342', 'Name' => 'Aarpi', 'image' => '1723440370_6342studentphoto.jpg'),
                1 => array('AppID' => '6334', 'Name' => 'Abaan Khan', 'image' => '1723437613_6334studentphoto.jpg'),
                2 => array('AppID' => '6319', 'Name' => 'Abhi Nishad', 'image' => '1723438280_6319studentphoto.jpg'),
                3 => array('AppID' => '6322', 'Name' => 'Aman', 'image' => '1723438358_6322studentphoto.jpg'),
                4 => array('AppID' => '6364', 'Name' => 'Anabiya', 'image' => '1725862177_6364studentphoto.jpg'),
                5 => array('AppID' => '6325', 'Name' => 'Ananya Nishad', 'image' => '1723438424_6325studentphoto.jpg'),
                6 => array('AppID' => '6343', 'Name' => 'Ankush', 'image' => '1723440501_6343studentphoto.jpg'),
                7 => array('AppID' => '6312', 'Name' => 'Areeba Fatima', 'image' => '1723438645_6312studentphoto.jpg'),
                8 => array('AppID' => '6321', 'Name' => 'Ayan', 'image' => '1723438719_6321studentphoto.jpg'),
                9 => array('AppID' => '6326', 'Name' => 'Ayansh yadav', 'image' => '1723438803_6326studentphoto.jpg'),
                10 => array('AppID' => '6339', 'Name' => 'Dhruv', 'image' => '1723438876_6339studentphoto.jpg'),
                11 => array('AppID' => '6263', 'Name' => 'Divyanshi', 'image' => '1723436572_6263studentphoto.jpg'),
                12 => array('AppID' => '6336', 'Name' => 'Gyanvi Trivedi', 'image' => '1723436187_6336studentphoto.jpg'),
                13 => array('AppID' => '6345', 'Name' => 'Hamza Fatima', 'image' => '1723441130_6345studentphoto.jpg'),
                14 => array('AppID' => '6344', 'Name' => 'Jagrat', 'image' => '1723440588_6344studentphoto.jpg'),
                15 => array('AppID' => '6331', 'Name' => 'Janhvi', 'image' => '1723439021_6331studentphoto.jpg'),
                16 => array('AppID' => '6332', 'Name' => 'Krashna', 'image' => '1723439106_6332studentphoto.jpg'),
                17 => array('AppID' => '6347', 'Name' => 'Kratika', 'image' => '1724904267_6347studentphoto.jpg'),
                18 => array('AppID' => '6338', 'Name' => 'Krishna', 'image' => '1723439191_6338studentphoto.jpg'),
                19 => array('AppID' => '6323', 'Name' => 'Kuldeep', 'image' => '1723439290_6323studentphoto.jpg'),
                20 => array('AppID' => '6346', 'Name' => 'Medhansh Chauhan', 'image' => '1723788653_6346studentphoto.jpg'),
                21 => array('AppID' => '6363', 'Name' => 'Misthi Sivhare', 'image' => '1725861471_6363studentphoto.jpg'),
                22 => array('AppID' => '6313', 'Name' => 'Moh.Abiha Hashmi', 'image' => '1723437464_6313studentphoto.jpg'),
                23 => array('AppID' => '6219', 'Name' => 'Nishika singh', 'image' => '1723436694_6219studentphoto.jpg'),
                24 => array('AppID' => '6324', 'Name' => 'Rishabh', 'image' => '1723436853_6324studentphoto.jpg'),
                25 => array('AppID' => '6333', 'Name' => 'Samayra', 'image' => '1723437030_6333studentphoto.jpg'),
                26 => array('AppID' => '6262', 'Name' => 'Saumya', 'image' => '1723437183_6262studentphoto.jpg'),
                27 => array('AppID' => '6337', 'Name' => 'Shivakshi Tiwari', 'image' => '1724904516_6337studentphoto.jpg'),
                28 => array('AppID' => '6320', 'Name' => 'Shivanya Singh', 'image' => '1723437757_6320studentphoto.jpg'),
                29 => array('AppID' => '6335', 'Name' => 'Shreyansh singh', 'image' => '1725861188_6335studentphoto.jpg'),
                30 => array('AppID' => '6318', 'Name' => 'Trisha Singh', 'image' => '1724904678_6318studentphoto.jpg'),
                31 => array('AppID' => '6360', 'Name' => 'Utkarsh', 'image' => '1725861266_6360studentphoto.jpg'),
                32 => array('AppID' => '6327', 'Name' => 'Ved Singh', 'image' => '1723439459_6327studentphoto.jpg'),
                33 => array('AppID' => '6128', 'Name' => 'AAROHI', 'image' => '1722321036_photo.jpg'),
                34 => array('AppID' => '6131', 'Name' => 'ABHISHEK', 'image' => 'ambm-english-2R_PG- (32).jpeg'),
                35 => array('AppID' => '6133', 'Name' => 'ALOK', 'image' => 'ambm-english-2R_PG- (11).jpeg'),
                36 => array('AppID' => '6135', 'Name' => 'ANAY SINGH', 'image' => 'pg-2R- (6).jpeg'),
                37 => array('AppID' => '6137', 'Name' => 'ANSH RAJ', 'image' => 'ambm-english-2R_PG- (26).jpeg'),
                38 => array('AppID' => '6138', 'Name' => 'ANSHIKA YADAV', 'image' => 'ANSHIKA YADAV.jpg'),
                39 => array('AppID' => '6140', 'Name' => 'AYANSH', 'image' => 'ambm-english-2R_PG- (35).jpeg'),
                40 => array('AppID' => '6145', 'Name' => 'HASNAIN', 'image' => 'Hasnain-pg.jpg'),
                41 => array('AppID' => '6146', 'Name' => 'HIDAYA', 'image' => 'ambm-english-2R_PG- (6).jpeg'),
                42 => array('AppID' => '6148', 'Name' => 'KUNAL SINGH', 'image' => 'ambm-english-2R_PG- (20).jpeg'),
                43 => array('AppID' => '6152', 'Name' => 'MOHAMMAD ALKAIF', 'image' => 'ambm-english-2R_PG- (8).jpeg'),
                44 => array('AppID' => '6311', 'Name' => 'Prashant', 'image' => '1722321606_photo.jpg'),
                45 => array('AppID' => '6154', 'Name' => 'PRATHAM SINGH', 'image' => 'ambm-english-2R_PG- (38).jpeg'),
                46 => array('AppID' => '6157', 'Name' => 'RUDRA PRATAP SINGH', 'image' => 'ambm-english-2R_PG- (39).jpeg'),
                47 => array('AppID' => '6158', 'Name' => 'SAANVI', 'image' => 'ambm-english-2R_PG- (33).jpeg'),
                48 => array('AppID' => '6160', 'Name' => 'SARANSH', 'image' => 'ambm-english-2R_PG- (29).jpeg'),
                49 => array('AppID' => '6161', 'Name' => 'SHAUMIK', 'image' => 'ambm-english-2R_PG- (37).jpeg'),
                50 => array('AppID' => '6162', 'Name' => 'SHIVANSHI NAGAM', 'image' => 'ambm-english-2R_PG- (13).jpeg'),
                51 => array('AppID' => '6165', 'Name' => 'SHRIYANSH SAHU', 'image' => 'ambm-english-2R_PG- (28).jpeg'),
                52 => array('AppID' => '6171', 'Name' => 'VANSH', 'image' => 'ambm-english-2R_PG- (10).jpeg'),
                53 => array('AppID' => '6174', 'Name' => 'YASHDEEP', 'image' => 'pg-2R- (3).jpeg'),
                54 => array('AppID' => '6130', 'Name' => 'ABHI SINGH', 'image' => 'ambm-english-2R_PG- (25).jpeg'),
                55 => array('AppID' => '6132', 'Name' => 'AKSHANSH GUPTA', 'image' => 'ambm-english-2R_PG- (36).jpeg'),
                56 => array('AppID' => '6328', 'Name' => 'Akshara yadav', 'image' => '1723433444_6328studentphoto.jpg'),
                57 => array('AppID' => '6136', 'Name' => 'ANIKA TRIVEDI', 'image' => 'ambm-english-2R_PG- (24).jpeg'),
                58 => array('AppID' => '6139', 'Name' => 'ANUSHKA DHURIYA', 'image' => 'ambm-english-2R_PG- (2).jpeg'),
                59 => array('AppID' => '6330', 'Name' => 'Aradhya', 'image' => '1723433320_6330studentphoto.jpg'),
                60 => array('AppID' => '6340', 'Name' => 'Arav', 'image' => '1723433640_6340studentphoto.jpg'),
                61 => array('AppID' => '6147', 'Name' => 'HUMER UDDIN', 'image' => 'ambm-english-2R_PG- (16).jpeg'),
                62 => array('AppID' => '6329', 'Name' => 'Jaish', 'image' => '1723433512_6329studentphoto.jpg'),
                63 => array('AppID' => '6149', 'Name' => 'MANAS NISHAD', 'image' => 'ambm-english-2R_PG- (14).jpeg'),
                64 => array('AppID' => '6150', 'Name' => 'MANDAVI', 'image' => 'ambm-english-2R_PG- (9).jpeg'),
                65 => array('AppID' => '6151', 'Name' => 'MANEESH', 'image' => 'ambm-english-2R_PG- (12).jpeg'),
                66 => array('AppID' => '6153', 'Name' => 'MUNASHRA KHATUN', 'image' => 'ambm-english-2R_PG- (1).jpeg'),
                67 => array('AppID' => '6155', 'Name' => 'PRINCE NISHAD', 'image' => 'ambm-english-2R_PG- (27).jpeg'),
                68 => array('AppID' => '6159', 'Name' => 'SANYA SINGH', 'image' => 'ambm-english-2R_PG- (21).jpeg'),
                69 => array('AppID' => '6168', 'Name' => 'SWARNIMA SONKAR', 'image' => 'ambm-english-2R_PG- (17).jpeg'),
                70 => array('AppID' => '6169', 'Name' => 'UNNATI SAHU', 'image' => 'ambm-english-2R_PG- (22).jpeg'),
                71 => array('AppID' => '6170', 'Name' => 'USMAN GANI', 'image' => 'ambm-english-2R_PG- (34).jpeg'),
                72 => array('AppID' => '6175', 'Name' => 'YUSUF RAZI AHMAD', 'image' => 'YUSUF RAZI AHMAD.jpg'),
                73 => array('AppID' => '6316', 'Name' => 'Aditi', 'image' => '1722667143_photo.jpg'),
                74 => array('AppID' => '6085', 'Name' => 'ANKUSH', 'image' => 'lkg-a-2R- (4).jpeg'),
                75 => array('AppID' => '6086', 'Name' => 'ANSH', 'image' => 'lkg-a-2R- (1).jpeg'),
                76 => array('AppID' => '6097', 'Name' => 'ANSI YADAV', 'image' => 'WhatsApp Image 2022-08-18 at 10.59.59 AM.jpeg'),
                77 => array('AppID' => '6098', 'Name' => 'ARFIYA KHAN', 'image' => 'WhatsApp Image 2022-08-18 at 10.59.51 AM.jpeg'),
                78 => array('AppID' => '6100', 'Name' => 'ARYAN JHA', 'image' => 'WhatsApp Image 2022-08-18 at 11.00.45 AM.jpeg'),
                79 => array('AppID' => '6101', 'Name' => 'ASMIT YADAV', 'image' => 'WhatsApp Image 2022-08-18 at 11.00.53 AM.jpeg'),
                80 => array('AppID' => '6102', 'Name' => 'ASTHA YADAV', 'image' => '1722667203_photo.jpg'),
                81 => array('AppID' => '6093', 'Name' => 'Avyan Gautam', 'image' => 'AVYAN gautam.jpg'),
                82 => array('AppID' => '6103', 'Name' => 'AYUSH', 'image' => 'WhatsApp Image 2022-08-18 at 11.00.00 AM (1).jpeg'),
                83 => array('AppID' => '6299', 'Name' => 'Bhoomi Rastogi', 'image' => '1722667224_photo.jpg'),
                84 => array('AppID' => '6105', 'Name' => 'DEVIKA SINGH', 'image' => 'WhatsApp Image 2022-08-18 at 10.59.57 AM.jpeg'),
                85 => array('AppID' => '6108', 'Name' => 'KAVYA MISHRA', 'image' => 'Kavya mishra pg-a.jpeg'),
                86 => array('AppID' => '6094', 'Name' => 'MATRIKA SACHAN', 'image' => 'ambm-english-2R_LKG-B (1).jpeg'),
                87 => array('AppID' => '6298', 'Name' => 'Rajvik Trivedi', 'image' => '1722317087_photo.jpg'),
                88 => array('AppID' => '6118', 'Name' => 'RUDRAANSH SAHU', 'image' => 'WhatsApp Image 2022-08-18 at 10.59.55 AM.jpeg'),
                89 => array('AppID' => '6122', 'Name' => 'SHLOK DIWADI', 'image' => 'WhatsApp Image 2022-08-18 at 11.00.48 AM.jpeg'),
                90 => array('AppID' => '6123', 'Name' => 'SIDRA IMAM', 'image' => 'sidra imam pg.jpeg'),
                91 => array('AppID' => '6314', 'Name' => 'Yash', 'image' => '1722667252_photo.jpg'),
                92 => array('AppID' => '6300', 'Name' => 'Zaid', 'image' => '1722667322_photo.jpg'),
                93 => array('AppID' => '6127', 'Name' => 'ZUBAIR AHMAD', 'image' => 'WhatsApp Image 2022-08-18 at 11.00.02 AM.jpeg'),
                94 => array('AppID' => '6096', 'Name' => 'AARYA TIWARI', 'image' => 'WhatsApp Image 2022-08-18 at 10.59.59 AM (1).jpeg'),
                95 => array('AppID' => '6306', 'Name' => 'Abhinav', 'image' => '1722317387_photo.jpg'),
                96 => array('AppID' => '6090', 'Name' => 'AKSHITA PATEL', 'image' => 'lkg-b-2R.jpeg'),
                97 => array('AppID' => '6084', 'Name' => 'ANANYA VERMA', 'image' => 'Ananya verma-lkg.jpg'),
                98 => array('AppID' => '6341', 'Name' => 'Arish', 'image' => '1723433642_6341studentphoto.jpg'),
                99 => array('AppID' => '6091', 'Name' => 'ARMAN', 'image' => 'ambm-english-2R_LKG-B (2).jpeg'),
                100 => array('AppID' => '6099', 'Name' => 'ARNAV BAJPAI', 'image' => 'Arnav bajpai pg.jpeg'),
                101 => array('AppID' => '6092', 'Name' => 'ARYAN Singh', 'image' => 'ambm-english-2R_LKG-B (3).jpeg'),
                102 => array('AppID' => '6302', 'Name' => 'Chandan', 'image' => '1722317822_photo.jpg'),
                103 => array('AppID' => '6304', 'Name' => 'Divyansh', 'image' => '1722317894_photo.jpg'),
                104 => array('AppID' => '6107', 'Name' => 'KAVYA', 'image' => 'WhatsApp Image 2022-08-18 at 10.59.52 AM.jpeg'),
                105 => array('AppID' => '6109', 'Name' => 'KHADEEZA AHMAD', 'image' => 'WhatsApp Image 2022-08-18 at 10.59.51 AM (1).jpeg'),
                106 => array('AppID' => '6112', 'Name' => 'MOHAMMAD AHIL HASMI', 'image' => 'WhatsApp Image 2022-08-18 at 11.00.50 AM.jpeg'),
                107 => array('AppID' => '6114', 'Name' => 'NAITIK', 'image' => 'WhatsApp Image 2022-08-18 at 10.59.56 AM (1).jpeg'),
                108 => array('AppID' => '6309', 'Name' => 'Pawni', 'image' => '1722317933_photo.jpg'),
                109 => array('AppID' => '6116', 'Name' => 'PEEYUSH KUMAR', 'image' => 'WhatsApp Image 2022-08-18 at 11.00.03 AM.jpeg'),
                110 => array('AppID' => '6095', 'Name' => 'RAHIB', 'image' => 'ambm-english-2R_LKG-B (4).jpeg'),
                111 => array('AppID' => '6310', 'Name' => 'Rudransh', 'image' => '1722318024_photo.jpg'),
                112 => array('AppID' => '6121', 'Name' => 'SHIVAY MISHRA', 'image' => 'WhatsApp Image 2022-08-18 at 10.59.53 AM.jpeg'),
                113 => array('AppID' => '6125', 'Name' => 'UMANG DIXIT', 'image' => 'WhatsApp Image 2022-08-18 at 11.00.47 AM.jpeg'),
                114 => array('AppID' => '6126', 'Name' => 'UTKARSH', 'image' => 'WhatsApp Image 2022-08-18 at 11.00.00 AM.jpeg'),
                115 => array('AppID' => '6301', 'Name' => 'Yashi', 'image' => '1722318060_photo.jpg'),
                116 => array('AppID' => '6176', 'Name' => 'ABHIMANYU SINGH', 'image' => 'WhatsApp Image 2022-08-18 at 9.10.18 AM (1).jpeg'),
                117 => array('AppID' => '6177', 'Name' => 'AGAM YADAV', 'image' => 'arshalam-lkg-a.jpeg'),
                118 => array('AppID' => '6257', 'Name' => 'Akshat Chahar', 'image' => '1722307905_photo.jpg'),
                119 => array('AppID' => '6180', 'Name' => 'ANIKA', 'image' => 'WhatsApp Image 2022-08-18 at 9.10.19 AM (1).jpeg'),
                120 => array('AppID' => '6181', 'Name' => 'ANIRUDDHA', 'image' => 'Aniruddh-ukg-A.jpg'),
                121 => array('AppID' => '6182', 'Name' => 'ARSH ALAM', 'image' => 'Arsh-alam-ukg-a.jpg'),
                122 => array('AppID' => '6183', 'Name' => 'ARUSHI', 'image' => 'WhatsApp Image 2022-08-18 at 9.10.13 AM.jpeg'),
                123 => array('AppID' => '6355', 'Name' => 'Ashmit', 'image' => '1724995328_6355studentphoto.jpg'),
                124 => array('AppID' => '6184', 'Name' => 'AVIRAJ', 'image' => 'ambm-english-2R_-UKG-A (2).jpeg'),
                125 => array('AppID' => '6185', 'Name' => 'AYANSH ANURAGI', 'image' => 'WhatsApp Image 2022-08-18 at 9.10.17 AM.jpeg'),
                126 => array('AppID' => '6186', 'Name' => 'HARSHIT', 'image' => 'ambm-english-2R_-UKG-A (3).jpeg'),
                127 => array('AppID' => '6188', 'Name' => 'JANVI YADAV', 'image' => 'WhatsApp Image 2022-08-18 at 9.10.16 AM (1).jpeg'),
                128 => array('AppID' => '6189', 'Name' => 'KAMAD BAJPAI', 'image' => 'kamad-bajpai.jpeg'),
                129 => array('AppID' => '6190', 'Name' => 'MAHIRA', 'image' => 'shivansh-lkg-a.jpeg'),
                130 => array('AppID' => '6290', 'Name' => 'Mansi', 'image' => '1722308101_photo.jpg'),
                131 => array('AppID' => '6191', 'Name' => 'MISHBA', 'image' => 'ambm-english-2R_-UKG-A (1).jpeg'),
                132 => array('AppID' => '6193', 'Name' => 'PRINCE', 'image' => 'prince-lkg-a.jpeg'),
                133 => array('AppID' => '6291', 'Name' => 'RIZWAN', 'image' => '1722308320_photo.jpg'),
                134 => array('AppID' => '6258', 'Name' => 'Shivanya', 'image' => '1722307940_photo.jpg'),
                135 => array('AppID' => '6197', 'Name' => 'SHREYANGI', 'image' => 'WhatsApp Image 2022-08-18 at 9.10.10 AM.jpeg'),
                136 => array('AppID' => '6198', 'Name' => 'SWETA SINGH', 'image' => 'WhatsApp Image 2022-08-18 at 9.10.17 AM (1).jpeg'),
                137 => array('AppID' => '6199', 'Name' => 'VINAY', 'image' => 'WhatsApp Image 2022-08-18 at 9.10.12 AM.jpeg'),
                138 => array('AppID' => '6200', 'Name' => 'ABHAYRAJ YADAV', 'image' => 'WhatsApp Image 2022-08-18 at 10.04.15 AM (2).jpeg'),
                139 => array('AppID' => '6201', 'Name' => 'ADITYA GUPTA', 'image' => 'WhatsApp Image 2022-08-18 at 10.04.24 AM (1).jpeg'),
                140 => array('AppID' => '6202', 'Name' => 'AMAYRA', 'image' => 'ambm-english-2R_UKG-B (2).jpeg'),
                141 => array('AppID' => '6203', 'Name' => 'ANSHIKA', 'image' => 'WhatsApp Image 2022-09-20 at 7.12.32 AM.jpeg'),
                142 => array('AppID' => '6204', 'Name' => 'ARADHYA', 'image' => 'ambm-english-2R_UKG-B (1).jpeg'),
                143 => array('AppID' => '6260', 'Name' => 'Arham Mansuri', 'image' => '1724994521_6260studentphoto.jpg'),
                144 => array('AppID' => '6207', 'Name' => 'AYUSH YADAV', 'image' => 'WhatsApp Image 2022-08-18 at 10.04.17 AM.jpeg'),
                145 => array('AppID' => '6353', 'Name' => 'Ayushi saroj', 'image' => '1724995003_6353studentphoto.jpg'),
                146 => array('AppID' => '6209', 'Name' => 'HONEY', 'image' => 'WhatsApp Image 2022-08-18 at 10.04.24 AM.jpeg'),
                147 => array('AppID' => '6210', 'Name' => 'ISHITA', 'image' => 'WhatsApp Image 2022-09-20 at 7.12.33 AM.jpeg'),
                148 => array('AppID' => '6211', 'Name' => 'PARTH', 'image' => 'ukg-2R-.jpeg'),
                149 => array('AppID' => '6212', 'Name' => 'PIHU RAJPUT', 'image' => 'WhatsApp Image 2022-09-20 at 7.12.33 AM (1).jpeg'),
                150 => array('AppID' => '6213', 'Name' => 'PRIYANSH', 'image' => 'WhatsApp Image 2022-08-18 at 10.04.20 AM (1).jpeg'),
                151 => array('AppID' => '6214', 'Name' => 'PRIYANSHI', 'image' => 'WhatsApp Image 2022-08-18 at 10.04.19 AM (1).jpeg'),
                152 => array('AppID' => '6215', 'Name' => 'RAJMAN', 'image' => 'WhatsApp Image 2022-08-18 at 10.04.15 AM.jpeg'),
                153 => array('AppID' => '6351', 'Name' => 'Saumya Gautam', 'image' => '1724994844_6351studentphoto.jpg'),
                154 => array('AppID' => '6218', 'Name' => 'VEDANT', 'image' => 'ambm-english-2R_UKG-B (3).jpeg'),
                155 => array('AppID' => '5672', 'Name' => 'ABHISHEK PAL', 'image' => 'WhatsApp Image 2022-08-20 at 8.46.21 AM.jpeg'),
                156 => array('AppID' => '5673', 'Name' => 'ADEEBA', 'image' => 'WhatsApp Image 2022-08-20 at 8.46.19 AM (1).jpeg'),
                157 => array('AppID' => '5674', 'Name' => 'ANKIT', 'image' => 'WhatsApp Image 2022-08-20 at 8.46.23 AM (1).jpeg'),
                158 => array('AppID' => '5675', 'Name' => 'ARADHYA', 'image' => 'WhatsApp Image 2022-08-20 at 8.46.21 AM (2).jpeg'),
                159 => array('AppID' => '5676', 'Name' => 'ARPIT', 'image' => 'Arpit.jpg'),
                160 => array('AppID' => '5677', 'Name' => 'ARTH SRIVASTAVA', 'image' => 'WhatsApp Image 2022-08-20 at 8.46.19 AM.jpeg'),
                161 => array('AppID' => '6267', 'Name' => 'Ayushmaan', 'image' => '1724994603_6267studentphoto.jpg'),
                162 => array('AppID' => '5678', 'Name' => 'GEETIKA SINGH', 'image' => 'WhatsApp Image 2022-08-20 at 8.46.18 AM (2).jpeg'),
                163 => array('AppID' => '5679', 'Name' => 'HIFZA', 'image' => 'ambm-english-2R_1st-A (2).jpeg'),
                164 => array('AppID' => '5680', 'Name' => 'JANNAT', 'image' => 'WhatsApp Image 2022-08-20 at 8.46.21 AM (1).jpeg'),
                165 => array('AppID' => '5681', 'Name' => 'JIVIKA', 'image' => 'Jivika.jpg'),
                166 => array('AppID' => '5682', 'Name' => 'MANYA', 'image' => 'WhatsApp Image 2022-08-20 at 8.46.22 AM.jpeg'),
                167 => array('AppID' => '5683', 'Name' => 'MAYANK', 'image' => 'ambm-english-2R_1st-A (3).jpeg'),
                168 => array('AppID' => '5684', 'Name' => 'PRATISHTHA', 'image' => 'ambm-english-2R_1st-A (1).jpeg'),
                169 => array('AppID' => '5685', 'Name' => 'RITIK DWIVEDI', 'image' => 'WhatsApp Image 2022-08-20 at 8.46.23 AM.jpeg'),
                170 => array('AppID' => '5686', 'Name' => 'SANSKRITI', 'image' => 'WhatsApp Image 2022-08-20 at 8.46.22 AM (1).jpeg'),
                171 => array('AppID' => '6265', 'Name' => 'Shivam', 'image' => '1724994559_6265studentphoto.jpg'),
                172 => array('AppID' => '5687', 'Name' => 'SHIVANI', 'image' => 'WhatsApp Image 2022-08-20 at 8.46.20 AM (2).jpeg'),
                173 => array('AppID' => '5688', 'Name' => 'SONAKSHI', 'image' => 'WhatsApp Image 2022-08-20 at 8.46.20 AM (1).jpeg'),
                174 => array('AppID' => '5689', 'Name' => 'SUHAIL HASAN', 'image' => 'WhatsApp Image 2022-08-20 at 8.46.17 AM (1).jpeg'),
                175 => array('AppID' => '6352', 'Name' => 'Vaishnavi Singh', 'image' => '1724994855_6352studentphoto.jpg'),
                176 => array('AppID' => '6248', 'Name' => 'Abhishek Dixit', 'image' => '1722223708_photo.jpg'),
                177 => array('AppID' => '5690', 'Name' => 'ADARSH', 'image' => 'WhatsApp Image 2022-08-22 at 12.52.58 PM (2).jpeg'),
                178 => array('AppID' => '6245', 'Name' => 'Akshat pandey', 'image' => '1722223750_photo.jpg'),
                179 => array('AppID' => '5692', 'Name' => 'ANSHIKA PRAJAPATI', 'image' => 'ambm-english-2R_1B_ (6).jpeg'),
                180 => array('AppID' => '5694', 'Name' => 'ARYAN TIWARI', 'image' => 'Aryan tiwari ukg-b.jpeg'),
                181 => array('AppID' => '6354', 'Name' => 'Astha Singh', 'image' => '1724995183_6354studentphoto.jpg'),
                182 => array('AppID' => '5697', 'Name' => 'KANAK SINGH', 'image' => 'WhatsApp Image 2022-08-22 at 12.53.00 PM (1).jpeg'),
                183 => array('AppID' => '6247', 'Name' => 'Lucky Sonkar', 'image' => '1722223775_photo.jpg'),
                184 => array('AppID' => '5698', 'Name' => 'MEHER VASHU NIGAM', 'image' => 'Mehar Basu nigam ukg-b.jpeg'),
                185 => array('AppID' => '5699', 'Name' => 'RITIK', 'image' => 'ambm-english-2R_1B_ (5).jpeg'),
                186 => array('AppID' => '5700', 'Name' => 'RONAK GUPTA', 'image' => 'ambm-english-2R_1B_ (3).jpeg'),
                187 => array('AppID' => '5701', 'Name' => 'RUCHIKA', 'image' => '1st-B-3R-.jpg'),
                188 => array('AppID' => '5702', 'Name' => 'SAIF', 'image' => 'WhatsApp Image 2022-08-22 at 12.52.56 PM.jpeg'),
                189 => array('AppID' => '5706', 'Name' => 'SHRESHTH DWIVEDI', 'image' => 'ambm-english-2R_1B_ (1).jpeg'),
                190 => array('AppID' => '6259', 'Name' => 'Vaishnavi yadav', 'image' => '1722308750_photo.jpg'),
                191 => array('AppID' => '5707', 'Name' => 'VED YADAV', 'image' => 'Ved-yadav-1st-B.jpg'),
                192 => array('AppID' => '5708', 'Name' => 'AAITAL KHAN', 'image' => 'Aaital khan-2nd.jpg'),
                193 => array('AppID' => '6366', 'Name' => 'Aditi', 'image' => ''),
                194 => array('AppID' => '5709', 'Name' => 'AKSHA KHATOON', 'image' => '2nd-2R- (1).jpeg'),
                195 => array('AppID' => '5713', 'Name' => 'ANSH YADAV', 'image' => 'WhatsApp Image 2022-08-18 at 9.43.36 AM (1).jpeg'),
                196 => array('AppID' => '5714', 'Name' => 'ASHEESH PAL', 'image' => 'ambm-english-2R_2nd_ (3).jpeg'),
                197 => array('AppID' => '5715', 'Name' => 'ATHARV', 'image' => 'Athrav-2nd.jpg'),
                198 => array('AppID' => '6356', 'Name' => 'Atif Imam', 'image' => '1724997098_6356studentphoto.jpg'),
                199 => array('AppID' => '5717', 'Name' => 'AVANTIKA DWIVEDI', 'image' => 'WhatsApp Image 2022-08-18 at 9.43.36 AM.jpeg'),
                200 => array('AppID' => '5718', 'Name' => 'AYAT HUSAIN', 'image' => 'AAYAT HUSAIN-1st.jpeg'),
                201 => array('AppID' => '5719', 'Name' => 'DIVYANSH YADAV', 'image' => 'WhatsApp Image 2022-08-18 at 9.43.40 AM.jpeg'),
                202 => array('AppID' => '5720', 'Name' => 'GAURIK NIGAM', 'image' => 'WhatsApp Image 2022-08-18 at 9.43.31 AM (1).jpeg'),
                203 => array('AppID' => '5721', 'Name' => 'HIFZA', 'image' => 'HIFZA-1st.jpeg'),
                204 => array('AppID' => '5722', 'Name' => 'IRAM', 'image' => '2nd-2R- (6).jpeg'),
                205 => array('AppID' => '5723', 'Name' => 'KAVYA MISHRA', 'image' => 'WhatsApp Image 2022-08-18 at 9.43.33 AM.jpeg'),
                206 => array('AppID' => '5725', 'Name' => 'KAVYA SAHU', 'image' => 'ambm-english-2R_2nd_ (1).jpeg'),
                207 => array('AppID' => '5726', 'Name' => 'MAYANK PRAJAPATI', 'image' => 'WhatsApp Image 2022-08-18 at 9.43.39 AM.jpeg'),
                208 => array('AppID' => '6250', 'Name' => 'Pranjal', 'image' => '1722307925_photo.jpg'),
                209 => array('AppID' => '5728', 'Name' => 'PRAPTI DEVI', 'image' => 'WhatsApp Image 2022-08-18 at 9.43.34 AM.jpeg'),
                210 => array('AppID' => '5729', 'Name' => 'RIYA', 'image' => 'ambm-english-2R_2nd_ (2).jpeg'),
                211 => array('AppID' => '5730', 'Name' => 'SAMRAT CHAURASIA', 'image' => 'WhatsApp Image 2022-08-18 at 9.43.30 AM.jpeg'),
                212 => array('AppID' => '5731', 'Name' => 'SARGAM', 'image' => 'WhatsApp Image 2022-08-18 at 9.43.38 AM (1).jpeg'),
                213 => array('AppID' => '5732', 'Name' => 'SATAKSHI', 'image' => '2nd-2R- (4).jpeg'),
                214 => array('AppID' => '5733', 'Name' => 'SAUMYA PAKHRE', 'image' => 'WhatsApp Image 2022-08-18 at 9.43.37 AM (1).jpeg'),
                215 => array('AppID' => '5734', 'Name' => 'SHAURYA GUPTA', 'image' => 'WhatsApp Image 2022-08-18 at 9.43.35 AM (2).jpeg'),
                216 => array('AppID' => '5735', 'Name' => 'SHRESHTH GUPTA', 'image' => 'WhatsApp Image 2022-08-18 at 9.43.35 AM (1).jpeg'),
                217 => array('AppID' => '5738', 'Name' => 'TANMAY', 'image' => 'WhatsApp Image 2022-08-18 at 9.43.38 AM (2).jpeg'),
                218 => array('AppID' => '5739', 'Name' => 'YUG GUPTA', 'image' => 'WhatsApp Image 2022-08-18 at 9.43.34 AM (1).jpeg'),
                219 => array('AppID' => '5740', 'Name' => 'AABHYA DHURIYA', 'image' => 'WhatsApp Image 2022-09-20 at 11.41.22 AM.jpeg'),
                220 => array('AppID' => '5741', 'Name' => 'AKANKSHA', 'image' => 'WhatsApp Image 2022-09-20 at 11.41.32 AM.jpeg'),
                221 => array('AppID' => '5742', 'Name' => 'AKSHA', 'image' => 'WhatsApp Image 2022-08-18 at 5.52.49 PM (1).jpeg'),
                222 => array('AppID' => '5743', 'Name' => 'ANANYA SONI', 'image' => 'WhatsApp Image 2022-09-20 at 11.41.22 AM (1).jpeg'),
                223 => array('AppID' => '5744', 'Name' => 'ARADHYA SAINI', 'image' => 'WhatsApp Image 2022-08-18 at 5.52.43 PM.jpeg'),
                224 => array('AppID' => '5745', 'Name' => 'ARNAV SACHAN', 'image' => 'WhatsApp Image 2022-08-18 at 5.52.53 PM.jpeg'),
                225 => array('AppID' => '5746', 'Name' => 'ARUSH RAJ TRIVEDI', 'image' => 'WhatsApp Image 2022-08-18 at 5.52.53 PM (1).jpeg'),
                226 => array('AppID' => '5748', 'Name' => 'AYUSH KHARE', 'image' => 'WhatsApp Image 2022-08-18 at 5.52.48 PM (1).jpeg'),
                227 => array('AppID' => '5749', 'Name' => 'AYUSH YADAV', 'image' => 'WhatsApp Image 2022-08-18 at 5.52.49 PM.jpeg'),
                228 => array('AppID' => '6246', 'Name' => 'Dipanshi', 'image' => '1722222624_photo.jpg'),
                229 => array('AppID' => '5750', 'Name' => 'DURGESH KUMAR', 'image' => 'WhatsApp Image 2022-08-18 at 5.52.40 PM.jpeg'),
                230 => array('AppID' => '5751', 'Name' => 'HARNAV SINGH', 'image' => '3rd-A-3R- (2).jpg'),
                231 => array('AppID' => '5752', 'Name' => 'MANSHI VARMA', 'image' => 'WhatsApp Image 2022-08-18 at 5.52.54 PM (1).jpeg'),
                232 => array('AppID' => '5753', 'Name' => 'MO. ALFAIZ', 'image' => '3rd-A-3R- (3).jpg'),
                233 => array('AppID' => '5755', 'Name' => 'PAKHI RAJPUT', 'image' => 'WhatsApp Image 2022-08-18 at 5.52.52 PM.jpeg'),
                234 => array('AppID' => '5756', 'Name' => 'PRINCE VERMA', 'image' => 'ambm-english-2R_3rd-A_ (1).jpeg'),
                235 => array('AppID' => '5757', 'Name' => 'RADHIKA', 'image' => 'WhatsApp Image 2022-08-18 at 5.52.50 PM.jpeg'),
                236 => array('AppID' => '5758', 'Name' => 'RAJ YADAV', 'image' => 'WhatsApp Image 2022-08-18 at 5.52.50 PM (1).jpeg'),
                237 => array('AppID' => '5759', 'Name' => 'RICHA SHARMA', 'image' => 'Richa-sharma-3rd-A.jpg'),
                238 => array('AppID' => '5760', 'Name' => 'SAMRADDH GUPTA', 'image' => 'WhatsApp Image 2022-08-18 at 5.52.47 PM.jpeg'),
                239 => array('AppID' => '5761', 'Name' => 'SONAKSHI SACHAN', 'image' => 'WhatsApp Image 2022-08-18 at 5.52.54 PM.jpeg'),
                240 => array('AppID' => '5762', 'Name' => 'TANYA SACHAN', 'image' => '3rd-A-3R- (1).jpg'),
                241 => array('AppID' => '6244', 'Name' => 'Vedansh Srivastava', 'image' => '1722222339_photo.jpg'),
                242 => array('AppID' => '5785', 'Name' => 'VEDANSH SRIVASTAVA', 'image' => 'WhatsApp Image 2022-08-18 at 5.54.14 PM.jpeg'),
                243 => array('AppID' => '5763', 'Name' => 'YUVRAJ', 'image' => 'ambm-english-2R_3rd-A_ (2).jpeg'),
                244 => array('AppID' => '5764', 'Name' => 'ABHIMANYU', 'image' => 'WhatsApp Image 2022-08-18 at 5.54.11 PM.jpeg'),
                245 => array('AppID' => '5765', 'Name' => 'ADITYA', 'image' => 'WhatsApp Image 2022-08-18 at 5.54.11 PM (1).jpeg'),
                246 => array('AppID' => '5766', 'Name' => 'AHSAS', 'image' => 'WhatsApp Image 2022-09-20 at 11.40.57 AM (1).jpeg'),
                247 => array('AppID' => '5767', 'Name' => 'AKSHAT', 'image' => 'ambm-english-2R_3rd-B (1).jpeg'),
                248 => array('AppID' => '6357', 'Name' => 'Ansh kumar', 'image' => '1724997696_6357studentphoto.jpg'),
                249 => array('AppID' => '5768', 'Name' => 'ANSH YADAV', 'image' => 'WhatsApp Image 2022-08-18 at 5.54.13 PM.jpeg'),
                250 => array('AppID' => '5770', 'Name' => 'ARNAV KUMAR', 'image' => 'WhatsApp Image 2022-08-18 at 5.54.12 PM.jpeg'),
                251 => array('AppID' => '5771', 'Name' => 'ASAD AHMAD', 'image' => 'WhatsApp Image 2022-08-18 at 5.54.10 PM.jpeg'),
                252 => array('AppID' => '5772', 'Name' => 'AVYA SINGH', 'image' => 'WhatsApp Image 2022-08-18 at 5.54.09 PM.jpeg'),
                253 => array('AppID' => '5773', 'Name' => 'AYANSH DWIVEDI', 'image' => 'WhatsApp Image 2022-08-18 at 5.54.12 PM (1).jpeg'),
                254 => array('AppID' => '5774', 'Name' => 'AYUSH KUMAR', 'image' => 'Ayush-kumar-3rd-B.jpg'),
                255 => array('AppID' => '5776', 'Name' => 'HAMMAD KHAN', 'image' => 'WhatsApp Image 2022-08-18 at 5.54.09 PM (2).jpeg'),
                256 => array('AppID' => '5777', 'Name' => 'MAYAN VARMA', 'image' => 'WhatsApp Image 2022-08-18 at 5.54.14 PM (1).jpeg'),
                257 => array('AppID' => '5778', 'Name' => 'NAITIK', 'image' => 'WhatsApp Image 2022-09-20 at 11.40.58 AM.jpeg'),
                258 => array('AppID' => '5780', 'Name' => 'SHANVI', 'image' => 'ambm-english-2R_3rd-B (2).jpeg'),
                259 => array('AppID' => '5781', 'Name' => 'SHIV PRATAP', 'image' => 'WhatsApp Image 2022-08-18 at 5.54.10 PM (1).jpeg'),
                260 => array('AppID' => '5782', 'Name' => 'SONALI VERMA', 'image' => 'ambm-english-2R_3rd-B (3).jpeg'),
                261 => array('AppID' => '5783', 'Name' => 'SOUBHAGYA SACHAN', 'image' => 'WhatsApp Image 2022-08-18 at 5.54.03 PM.jpeg'),
                262 => array('AppID' => '5784', 'Name' => 'VAISHANAVI', 'image' => 'WhatsApp Image 2022-08-18 at 5.54.09 PM (1).jpeg'),
                263 => array('AppID' => '5786', 'Name' => 'VEDANT SINGH', 'image' => 'WhatsApp Image 2022-09-20 at 11.40.57 AM.jpeg'),
                264 => array('AppID' => '6358', 'Name' => 'Yash', 'image' => '1724998603_6358studentphoto.jpg'),
                265 => array('AppID' => '5788', 'Name' => 'YUDDHVEER SINGH', 'image' => 'WhatsApp Image 2022-08-18 at 5.54.12 PM (2).jpeg'),
                266 => array('AppID' => '5809', 'Name' => 'AGRIM SACHAN', 'image' => 'WhatsApp Image 2022-08-18 at 1.06.58 PM.jpeg'),
                267 => array('AppID' => '5810', 'Name' => 'ANAMIKA', 'image' => 'WhatsApp Image 2022-08-18 at 1.06.51 PM.jpeg'),
                268 => array('AppID' => '6240', 'Name' => 'Anmol', 'image' => '1722225710_photo.jpg'),
                269 => array('AppID' => '5790', 'Name' => 'ANSH DIWAKAR', 'image' => '4th-a-2R- (1).jpeg'),
                270 => array('AppID' => '5811', 'Name' => 'ARADHYA DHURIYA', 'image' => 'WhatsApp Image 2022-08-18 at 1.06.54 PM (1).jpeg'),
                271 => array('AppID' => '5791', 'Name' => 'AREESHA', 'image' => 'ambm-english-2R_4th-A (2).jpeg'),
                272 => array('AppID' => '5792', 'Name' => 'AROHI SWARNKAR', 'image' => 'WhatsApp Image 2022-08-18 at 1.09.45 PM.jpeg'),
                273 => array('AppID' => '5812', 'Name' => 'ARSHIYA FATIMA', 'image' => 'WhatsApp Image 2022-08-18 at 1.06.50 PM.jpeg'),
                274 => array('AppID' => '6242', 'Name' => 'Atharav Namdev', 'image' => '1722667822_photo.jpg'),
                275 => array('AppID' => '5813', 'Name' => 'AYUSHMAN SEN', 'image' => 'WhatsApp Image 2022-08-18 at 1.06.57 PM.jpeg'),
                276 => array('AppID' => '5814', 'Name' => 'CHIRAG SACHAN', 'image' => 'WhatsApp Image 2022-08-18 at 1.06.56 PM.jpeg'),
                277 => array('AppID' => '6243', 'Name' => 'Dev Nishad', 'image' => '1722225812_photo.jpg'),
                278 => array('AppID' => '5815', 'Name' => 'DIVYANSH SINGH', 'image' => 'WhatsApp Image 2022-08-18 at 1.06.55 PM (2).jpeg'),
                279 => array('AppID' => '5794', 'Name' => 'GARVIT DWIVEDI', 'image' => 'WhatsApp Image 2022-08-18 at 1.09.43 PM (1).jpeg'),
                280 => array('AppID' => '5816', 'Name' => 'ICHCHHA', 'image' => 'WhatsApp Image 2022-08-22 at 8.56.53 AM.jpeg'),
                281 => array('AppID' => '5817', 'Name' => 'JAINI ANURAGI', 'image' => 'WhatsApp Image 2022-08-18 at 1.06.52 PM (1).jpeg'),
                282 => array('AppID' => '5796', 'Name' => 'KANAK SONKAR', 'image' => 'WhatsApp Image 2022-08-18 at 1.09.42 PM (1).jpeg'),
                283 => array('AppID' => '5797', 'Name' => 'KANAN VARMA', 'image' => 'WhatsApp Image 2022-08-18 at 1.09.38 PM.jpeg'),
                284 => array('AppID' => '5818', 'Name' => 'KESHAV TRIPATHI', 'image' => 'WhatsApp Image 2022-08-18 at 1.06.52 PM.jpeg'),
                285 => array('AppID' => '5798', 'Name' => 'KRISHNA CHAURASHIYA', 'image' => 'WhatsApp Image 2022-08-18 at 1.09.43 PM.jpeg'),
                286 => array('AppID' => '5799', 'Name' => 'LAIBA', 'image' => 'WhatsApp Image 2022-08-18 at 1.09.47 PM (1).jpeg'),
                287 => array('AppID' => '5819', 'Name' => 'MAHAK OMAR', 'image' => 'WhatsApp Image 2022-08-18 at 1.06.56 PM (1).jpeg'),
                288 => array('AppID' => '5820', 'Name' => 'MAHI MAHAN', 'image' => 'WhatsApp Image 2022-08-18 at 1.06.54 PM (2).jpeg'),
                289 => array('AppID' => '5821', 'Name' => 'NAMRA', 'image' => 'WhatsApp Image 2022-08-18 at 1.06.54 PM.jpeg'),
                290 => array('AppID' => '5801', 'Name' => 'NIKHIL DUBEY', 'image' => 'WhatsApp Image 2022-08-18 at 1.09.34 PM.jpeg'),
                291 => array('AppID' => '5822', 'Name' => 'NIKITA', 'image' => 'WhatsApp Image 2022-08-18 at 1.06.53 PM.jpeg'),
                292 => array('AppID' => '5802', 'Name' => 'PAVITRA KUMAR', 'image' => 'WhatsApp Image 2022-08-18 at 1.09.21 PM (1).jpeg'),
                293 => array('AppID' => '5823', 'Name' => 'PRABAL SACHAN', 'image' => 'WhatsApp Image 2022-08-18 at 1.06.57 PM (1).jpeg'),
                294 => array('AppID' => '5803', 'Name' => 'PRAKHAR SINGH', 'image' => 'WhatsApp Image 2022-08-18 at 1.09.44 PM.jpeg'),
                295 => array('AppID' => '5804', 'Name' => 'PRANSHI DEVI', 'image' => 'WhatsApp Image 2022-08-18 at 1.09.46 PM.jpeg'),
                296 => array('AppID' => '6239', 'Name' => 'Pratyush chahar', 'image' => '1722220408_photo.jpg'),
                297 => array('AppID' => '5824', 'Name' => 'RITIK', 'image' => 'WhatsApp Image 2022-08-18 at 1.06.58 PM (1).jpeg'),
                298 => array('AppID' => '5805', 'Name' => 'SURYANSH KISHORE', 'image' => 'WhatsApp Image 2022-08-18 at 1.09.26 PM.jpeg'),
                299 => array('AppID' => '5826', 'Name' => 'SURYANSH YADAV', 'image' => 'WhatsApp Image 2022-08-18 at 1.06.55 PM.jpeg'),
                300 => array('AppID' => '5807', 'Name' => 'UROOJ FATIMA', 'image' => 'WhatsApp Image 2022-08-18 at 1.09.47 PM.jpeg'),
                301 => array('AppID' => '5808', 'Name' => 'VARIDHI SACHAN', 'image' => 'WhatsApp Image 2022-08-18 at 1.09.27 PM.jpeg'),
                302 => array('AppID' => '5828', 'Name' => 'ADEENA RIYAZ', 'image' => 'WhatsApp Image 2022-08-18 at 9.15.33 AM (1).jpeg'),
                303 => array('AppID' => '6286', 'Name' => 'Aiman Fatima', 'image' => '1722496333_photo.jpg'),
                304 => array('AppID' => '6285', 'Name' => 'Alfisha Nahid', 'image' => '1722496356_photo.jpg'),
                305 => array('AppID' => '5829', 'Name' => 'ANANYA', 'image' => 'WhatsApp Image 2022-08-18 at 9.15.36 AM.jpeg'),
                306 => array('AppID' => '5830', 'Name' => 'ANSH KUMAR', 'image' => 'WhatsApp Image 2022-08-18 at 9.15.30 AM (1).jpeg'),
                307 => array('AppID' => '5832', 'Name' => 'APURAV SINGH', 'image' => '1722496397_photo.jpg'),
                308 => array('AppID' => '5833', 'Name' => 'ARADHAYA KUMARI', 'image' => 'WhatsApp Image 2022-08-18 at 9.15.37 AM (1).jpeg'),
                309 => array('AppID' => '5834', 'Name' => 'ATHARAV DWIVEDI', 'image' => 'WhatsApp Image 2022-08-18 at 9.15.32 AM (1).jpeg'),
                310 => array('AppID' => '5835', 'Name' => 'ATHARV OMREY', 'image' => 'WhatsApp Image 2022-08-18 at 9.15.35 AM (2).jpeg'),
                311 => array('AppID' => '6284', 'Name' => 'Avi Pratap Chakradhari', 'image' => '1722496425_photo.jpg'),
                312 => array('AppID' => '5836', 'Name' => 'DEVANSH SAINI', 'image' => 'Devansh-4th-a.jpeg'),
                313 => array('AppID' => '6282', 'Name' => 'Gaurang Goswami', 'image' => '1722324675_photo.jpg'),
                314 => array('AppID' => '5839', 'Name' => 'JAHNVI VERMA', 'image' => 'WhatsApp Image 2022-08-18 at 9.15.31 AM.jpeg'),
                315 => array('AppID' => '6273', 'Name' => 'Kajal', 'image' => '1722309043_photo.jpg'),
                316 => array('AppID' => '6281', 'Name' => 'Kartikey Mishra', 'image' => '1722496507_photo.jpg'),
                317 => array('AppID' => '5840', 'Name' => 'KESHAV SHUKLA', 'image' => 'WhatsApp Image 2022-08-18 at 9.15.33 AM (2).jpeg'),
                318 => array('AppID' => '5841', 'Name' => 'MOHD. ARSH', 'image' => 'WhatsApp Image 2022-08-18 at 9.15.31 AM (1).jpeg'),
                319 => array('AppID' => '5842', 'Name' => 'MOHD. ARSH RAZA', 'image' => 'WhatsApp Image 2022-08-18 at 9.15.36 AM (1).jpeg'),
                320 => array('AppID' => '5843', 'Name' => 'PIYUSH KUMAR', 'image' => 'Piyush-4th-a.jpeg'),
                321 => array('AppID' => '5844', 'Name' => 'PRANSHUL VERMA', 'image' => 'WhatsApp Image 2022-08-18 at 9.15.33 AM.jpeg'),
                322 => array('AppID' => '6283', 'Name' => 'Prateek Jadaun', 'image' => '1722496532_photo.jpg'),
                323 => array('AppID' => '6289', 'Name' => 'Richa', 'image' => '1722496571_photo.jpg'),
                324 => array('AppID' => '6359', 'Name' => 'Rudra Raj', 'image' => '1725001279_6359studentphoto.jpg'),
                325 => array('AppID' => '5845', 'Name' => 'RUDRANSH BHADWAJ', 'image' => 'WhatsApp Image 2022-08-18 at 9.15.38 AM.jpeg'),
                326 => array('AppID' => '5846', 'Name' => 'SAUMYA SINGH', 'image' => 'WhatsApp Image 2022-08-18 at 9.15.34 AM.jpeg'),
                327 => array('AppID' => '6288', 'Name' => 'Virat', 'image' => '1722496826_photo.jpg'),
                328 => array('AppID' => '6287', 'Name' => 'Warisha Fatima', 'image' => '1722496651_photo.jpg'),
                329 => array('AppID' => '5848', 'Name' => 'ZAIRA MANSURI', 'image' => 'WhatsApp Image 2022-08-18 at 9.15.30 AM.jpeg'),
                330 => array('AppID' => '6280', 'Name' => 'Zakir Hasan', 'image' => '1722496628_photo.jpg'),
                331 => array('AppID' => '5849', 'Name' => 'ABHASH BANSAL', 'image' => 'WhatsApp Image 2022-08-19 at 1.40.59 PM (1).jpeg'),
                332 => array('AppID' => '5850', 'Name' => 'ADITI YADAV', 'image' => 'WhatsApp Image 2022-08-19 at 1.40.59 PM.jpeg'),
                333 => array('AppID' => '5851', 'Name' => 'ADITYA YADAV', 'image' => 'WhatsApp Image 2022-09-06 at 9.49.25 AM (1).jpeg'),
                334 => array('AppID' => '5852', 'Name' => 'ALOK', 'image' => 'WhatsApp Image 2022-08-19 at 1.40.56 PM.jpeg'),
                335 => array('AppID' => '5853', 'Name' => 'AMRITA PRAJAPATI', 'image' => 'WhatsApp Image 2022-08-19 at 1.40.58 PM.jpeg'),
                336 => array('AppID' => '5831', 'Name' => 'ANVI CHAURASIYA', 'image' => 'WhatsApp Image 2022-08-18 at 9.15.35 AM.jpeg'),
                337 => array('AppID' => '5854', 'Name' => 'ARCHIT SINGH', 'image' => 'WhatsApp Image 2022-09-06 at 9.49.24 AM.jpeg'),
                338 => array('AppID' => '5855', 'Name' => 'ARPIT SINGH', 'image' => 'WhatsApp Image 2022-09-06 at 9.49.25 AM.jpeg'),
                339 => array('AppID' => '5857', 'Name' => 'ATHARAV SINGH', 'image' => 'WhatsApp Image 2022-08-19 at 1.41.00 PM (1).jpeg'),
                340 => array('AppID' => '5858', 'Name' => 'DIVYANSH PRATAP SINGH', 'image' => 'WhatsApp Image 2022-09-08 at 9.14.08 AM.jpeg'),
                341 => array('AppID' => '5837', 'Name' => 'FARIYA KHATOON', 'image' => 'WhatsApp Image 2022-08-18 at 9.15.32 AM.jpeg'),
                342 => array('AppID' => '5859', 'Name' => 'GAURAV SHARMA', 'image' => 'WhatsApp Image 2022-08-19 at 1.40.57 PM (1).jpeg'),
                343 => array('AppID' => '5860', 'Name' => 'HUMAIRA', 'image' => 'WhatsApp Image 2022-08-19 at 1.41.00 PM.jpeg'),
                344 => array('AppID' => '5861', 'Name' => 'IBRAHEEM RAZI AHAMAD KHAN', 'image' => 'ambm-english-2R_-5th-b-1.jpeg'),
                345 => array('AppID' => '6276', 'Name' => 'Janhavi Dixit', 'image' => '1722309789_photo.jpg'),
                346 => array('AppID' => '6278', 'Name' => 'Kavya Dwivedi', 'image' => '1722309847_photo.jpg'),
                347 => array('AppID' => '5862', 'Name' => 'LAKSHYA SAHU', 'image' => 'WhatsApp Image 2022-08-19 at 1.40.55 PM.jpeg'),
                348 => array('AppID' => '5863', 'Name' => 'NAINSHY', 'image' => 'WhatsApp Image 2022-08-19 at 1.40.57 PM.jpeg'),
                349 => array('AppID' => '6279', 'Name' => 'Navneet Sonkar', 'image' => '1722324301_photo.jpg'),
                350 => array('AppID' => '6256', 'Name' => 'PRATIGYA PAL', 'image' => '1722324367_photo.jpg'),
                351 => array('AppID' => '5864', 'Name' => 'RISHI', 'image' => 'ambm-english-2R_5th-B (1).jpeg'),
                352 => array('AppID' => '6275', 'Name' => 'Rishi Kumar', 'image' => '1722324476_photo.jpg'),
                353 => array('AppID' => '5865', 'Name' => 'RUDRA PRATAP SINGH', 'image' => 'WhatsApp Image 2022-08-19 at 1.40.58 PM (1).jpeg'),
                354 => array('AppID' => '6277', 'Name' => 'Shreya', 'image' => '1722309542_photo.jpg'),
                355 => array('AppID' => '5867', 'Name' => 'VAISHNAVI DHURIYA', 'image' => 'WhatsApp Image 2022-08-19 at 1.41.01 PM (2).jpeg'),
                356 => array('AppID' => '5868', 'Name' => 'VANSH SACHAN', 'image' => 'Vansh Sachan-4th-b.jpeg'),
                357 => array('AppID' => '5869', 'Name' => 'VIDHI DWIVEDI', 'image' => '5th-b-2R- (2).jpeg'),
                358 => array('AppID' => '5870', 'Name' => 'VIHAN SAINI', 'image' => 'ambm-english-2R_5th-B (2).jpeg'),
                359 => array('AppID' => '5871', 'Name' => 'ABHINAV', 'image' => '1722310249_photo.jpg'),
                360 => array('AppID' => '5872', 'Name' => 'ADARSH KUMAR', 'image' => '6th-2R- (4).jpeg'),
                361 => array('AppID' => '5873', 'Name' => 'ADITYA GAUTAM', 'image' => 'WhatsApp Image 2022-08-19 at 2.10.50 PM (1).jpeg'),
                362 => array('AppID' => '6293', 'Name' => 'Amogh Pratap Singh', 'image' => '1722309099_photo.jpg'),
                363 => array('AppID' => '6296', 'Name' => 'Anant kumar', 'image' => '1722309870_photo.jpg'),
                364 => array('AppID' => '5875', 'Name' => 'ANIKET YADAV', 'image' => 'WhatsApp Image 2022-08-19 at 2.10.52 PM.jpeg'),
                365 => array('AppID' => '5876', 'Name' => 'ANKUR KHARE', 'image' => 'WhatsApp Image 2022-08-19 at 2.10.48 PM (1).jpeg'),
                366 => array('AppID' => '5905', 'Name' => 'ANSH KUMAR', 'image' => '1722309708_photo.jpg'),
                367 => array('AppID' => '6292', 'Name' => 'Arav Raj', 'image' => '1722308887_photo.jpg'),
                368 => array('AppID' => '5881', 'Name' => 'ARHAN RAHMAN KHAN', 'image' => '1722308728_photo.jpg'),
                369 => array('AppID' => '5882', 'Name' => 'ARUN', 'image' => 'WhatsApp Image 2022-08-19 at 2.10.49 PM.jpeg'),
                370 => array('AppID' => '5909', 'Name' => 'ASAD', 'image' => '1722310201_photo.jpg'),
                371 => array('AppID' => '5883', 'Name' => 'ATHARAV SHARMA', 'image' => '1722308484_photo.jpg'),
                372 => array('AppID' => '5884', 'Name' => 'ATHARAV SINGH YADAV', 'image' => '1722310175_photo.jpg'),
                373 => array('AppID' => '5885', 'Name' => 'AYUSH SINGH', 'image' => 'WhatsApp Image 2022-08-19 at 2.10.47 PM.jpeg'),
                374 => array('AppID' => '5886', 'Name' => 'AYUSH YADAV', 'image' => 'WhatsApp Image 2022-08-19 at 2.16.45 PM.jpeg'),
                375 => array('AppID' => '5889', 'Name' => 'GOURAV', 'image' => '6th-2R- (1).jpeg'),
                376 => array('AppID' => '5890', 'Name' => 'KUNAL SINGH', 'image' => 'WhatsApp Image 2022-08-19 at 2.10.48 PM.jpeg'),
                377 => array('AppID' => '6297', 'Name' => 'Mohammed Salman', 'image' => '1722310030_photo.jpg'),
                378 => array('AppID' => '5891', 'Name' => 'MUNZIR ALI', 'image' => 'ambm-english-2R_6th-A- (5).jpeg'),
                379 => array('AppID' => '5892', 'Name' => 'PARTH AGNIHOTRI', 'image' => 'WhatsApp Image 2022-08-19 at 2.10.51 PM (1).jpeg'),
                380 => array('AppID' => '5893', 'Name' => 'RAJ TIWARI', 'image' => '1722308350_photo.jpg'),
                381 => array('AppID' => '5920', 'Name' => 'SARTHAK TIWARI', 'image' => 'WhatsApp Image 2022-08-18 at 11.18.10 AM (1).jpeg'),
                382 => array('AppID' => '6295', 'Name' => 'Saubhagya', 'image' => '1722309631_photo.jpg'),
                383 => array('AppID' => '5922', 'Name' => 'SHIVAM KUMAR', 'image' => '1722309244_photo.jpg'),
                384 => array('AppID' => '5924', 'Name' => 'SHUBHYANSH SINGH', 'image' => '1722310290_photo.jpg'),
                385 => array('AppID' => '5898', 'Name' => 'SOM', 'image' => '1722309136_photo.jpg'),
                386 => array('AppID' => '5925', 'Name' => 'SPARSH SONI', 'image' => 'Sparsh soni-5th-b.jpeg'),
                387 => array('AppID' => '5899', 'Name' => 'VIMAL SINGH', 'image' => '1722309364_photo.jpg'),
                388 => array('AppID' => '5900', 'Name' => 'VIRAT SHARMA', 'image' => 'ambm-english-2R_6th-A- (8).jpeg'),
                389 => array('AppID' => '6350', 'Name' => 'Aalima', 'image' => '1724418155_6350studentphoto.jpg'),
                390 => array('AppID' => '5903', 'Name' => 'ADYA SHUKLA', 'image' => 'Adya-shukla-6th-B.jpg'),
                391 => array('AppID' => '5904', 'Name' => 'ALIZA', 'image' => 'WhatsApp Image 2022-08-18 at 11.18.13 AM (2).jpeg'),
                392 => array('AppID' => '5874', 'Name' => 'ANSHIKA JADAUN', 'image' => 'ambm-english-2R_6th-A- (4).jpeg'),
                393 => array('AppID' => '6271', 'Name' => 'Anshika sahu', 'image' => '1722275100_photo.jpg'),
                394 => array('AppID' => '5877', 'Name' => 'ANSHIKA SHUKLA', 'image' => '6th-2R- (2).jpeg'),
                395 => array('AppID' => '5906', 'Name' => 'ANSHIKA TIWARI', 'image' => 'Anshika Tiwari-5th-b.jpeg'),
                396 => array('AppID' => '6348', 'Name' => 'Anushka', 'image' => '1723812327_6348studentphoto.jpg'),
                397 => array('AppID' => '5907', 'Name' => 'ARADHYA SONI', 'image' => 'Aradhya-soni- 6th-B.jpg'),
                398 => array('AppID' => '5880', 'Name' => 'AREEBA', 'image' => 'WhatsApp Image 2022-09-20 at 9.57.40 AM.jpeg'),
                399 => array('AppID' => '5908', 'Name' => 'AROHI DUBEY', 'image' => 'Arohi Dubey-5th-b.jpeg'),
                400 => array('AppID' => '6270', 'Name' => 'Arshiya', 'image' => '1722274983_photo.jpg'),
                401 => array('AppID' => '6349', 'Name' => 'Awani yadav', 'image' => '1724373701_6349studentphoto.jpg'),
                402 => array('AppID' => '5887', 'Name' => 'BUSHRA NAZ', 'image' => 'ambm-english-2R_6th-A- (7).jpeg'),
                403 => array('AppID' => '5888', 'Name' => 'DARSHIKA LAXAKAR', 'image' => 'WhatsApp Image 2022-08-19 at 2.10.54 PM (1).jpeg'),
                404 => array('AppID' => '5910', 'Name' => 'DISHA SACHAN', 'image' => 'Disha-6th-B.jpg'),
                405 => array('AppID' => '5911', 'Name' => 'EKTA SAXENA', 'image' => 'Ekta-6th-B.jpg'),
                406 => array('AppID' => '6268', 'Name' => 'Firdous', 'image' => '1722275151_photo.jpg'),
                407 => array('AppID' => '5913', 'Name' => 'IRAM', 'image' => 'WhatsApp Image 2022-08-18 at 11.18.10 AM (2).jpeg'),
                408 => array('AppID' => '5914', 'Name' => 'ISHIKA SRIVASTAVA', 'image' => 'WhatsApp Image 2022-08-18 at 11.18.11 AM (2).jpeg'),
                409 => array('AppID' => '5901', 'Name' => 'MANVI SACHAN', 'image' => 'WhatsApp Image 2022-08-18 at 11.18.14 AM (1).jpeg'),
                410 => array('AppID' => '5918', 'Name' => 'PRAGYA SHIVHARE', 'image' => 'WhatsApp Image 2022-08-18 at 11.18.15 AM (2).jpeg'),
                411 => array('AppID' => '6269', 'Name' => 'Raksha', 'image' => '1722331864_photo.jpg'),
                412 => array('AppID' => '5919', 'Name' => 'REETIKA', 'image' => 'Ritika-6th-B.jpg'),
                413 => array('AppID' => '5921', 'Name' => 'SAUMYA SAHU', 'image' => 'Saumya-6th-B.jpg'),
                414 => array('AppID' => '5897', 'Name' => 'SHAGUN CHAURASIA', 'image' => 'WhatsApp Image 2022-08-19 at 2.10.54 PM.jpeg'),
                415 => array('AppID' => '5923', 'Name' => 'SHOURYA DWIVEDI', 'image' => 'ShauryaDwivedi-6th.jpg'),
                416 => array('AppID' => '5927', 'Name' => 'TANYA OMAR', 'image' => 'WhatsApp Image 2022-08-18 at 11.18.13 AM.jpeg'),
                417 => array('AppID' => '5928', 'Name' => 'UMME HABIBA', 'image' => 'WhatsApp Image 2022-08-18 at 11.18.10 AM.jpeg'),
                418 => array('AppID' => '5929', 'Name' => 'YASHASVI YADAV', 'image' => 'yashshvi-6th-B.jpg'),
                419 => array('AppID' => '5943', 'Name' => 'ABHAY RAJ SINGH', 'image' => '1722227009_photo.jpg'),
                420 => array('AppID' => '5944', 'Name' => 'ADEEBA', 'image' => '1722227124_photo.jpg'),
                421 => array('AppID' => '5948', 'Name' => 'AKRATI', 'image' => 'WhatsApp Image 2022-08-18 at 11.53.00 AM.jpeg'),
                422 => array('AppID' => '5949', 'Name' => 'ALIYA FAIZAN', 'image' => 'WhatsApp Image 2022-08-18 at 11.53.35 AM (1).jpeg'),
                423 => array('AppID' => '5951', 'Name' => 'ANJALI', 'image' => '1722227582_photo.jpg'),
                424 => array('AppID' => '5935', 'Name' => 'ANSH VERMA', 'image' => '1722225969_photo.jpg'),
                425 => array('AppID' => '5952', 'Name' => 'ANSHIKA SHIVHARE', 'image' => '1722227248_photo.jpg'),
                426 => array('AppID' => '5955', 'Name' => 'ARPIT PAKHARE', 'image' => '1722226269_photo.jpg'),
                427 => array('AppID' => '5957', 'Name' => 'ARYA GOSWAMI', 'image' => '1722226107_photo.jpg'),
                428 => array('AppID' => '5958', 'Name' => 'ARYAN', 'image' => 'WhatsApp Image 2022-08-18 at 11.52.57 AM.jpeg'),
                429 => array('AppID' => '5931', 'Name' => 'AVANTIKA VERMA', 'image' => '1722226061_photo.jpg'),
                430 => array('AppID' => '5932', 'Name' => 'DARSHAN VERMA', 'image' => '1722226491_photo.jpg'),
                431 => array('AppID' => '5964', 'Name' => 'DIVYANSHI', 'image' => 'WhatsApp Image 2022-08-18 at 11.53.34 AM (1).jpeg'),
                432 => array('AppID' => '5965', 'Name' => 'FARIYA ZAIDI', 'image' => '1722309823_photo.jpg'),
                433 => array('AppID' => '5967', 'Name' => 'HIFZA', 'image' => '1722226539_photo.jpg'),
                434 => array('AppID' => '5968', 'Name' => 'INSHA', 'image' => '1722226398_photo.jpg'),
                435 => array('AppID' => '5971', 'Name' => 'KAUSHIKI', 'image' => '1722226611_photo.jpg'),
                436 => array('AppID' => '6255', 'Name' => 'MOHAMMAD ARMAN', 'image' => '1722225577_photo.jpg'),
                437 => array('AppID' => '5934', 'Name' => 'MUSKAN SAHU', 'image' => '1722226638_photo.jpg'),
                438 => array('AppID' => '6252', 'Name' => 'PRINCE KUMAR SAHU', 'image' => '1722225676_photo.jpg'),
                439 => array('AppID' => '5973', 'Name' => 'PRIYANKA NISHAD', 'image' => '1722226765_photo.jpg'),
                440 => array('AppID' => '6254', 'Name' => 'RAJ MISHRA', 'image' => '1722225733_photo.jpg'),
                441 => array('AppID' => '5985', 'Name' => 'SHREYASH GUPTA', 'image' => '1722227176_photo.jpg'),
                442 => array('AppID' => '5987', 'Name' => 'SUMAIYA FATIMA', 'image' => '1722226805_photo.jpg'),
                443 => array('AppID' => '5988', 'Name' => 'SUNITI', 'image' => '1722227306_photo.jpg'),
                444 => array('AppID' => '5989', 'Name' => 'SUYASH SRIVASTAVA', 'image' => '1722226958_photo.jpg'),
                445 => array('AppID' => '5991', 'Name' => 'TANYA RAJPOOT', 'image' => '1722226868_photo.jpg'),
                446 => array('AppID' => '5993', 'Name' => 'VIRAT SAINI', 'image' => '1722227079_photo.jpg'),
                447 => array('AppID' => '5995', 'Name' => 'YOGYATA', 'image' => '1722226837_photo.jpg'),
                448 => array('AppID' => '5997', 'Name' => 'ZUBAIR AFTAB', 'image' => 'WhatsApp Image 2022-08-18 at 11.52.56 AM (2).jpeg'),
                449 => array('AppID' => '5942', 'Name' => 'ABHAY MISHRA', 'image' => 'WhatsApp Image 2022-08-18 at 11.53.32 AM (2).jpeg'),
                450 => array('AppID' => '5945', 'Name' => 'ADITYA RAJ SINGH', 'image' => 'WhatsApp Image 2022-08-18 at 11.53.26 AM.jpeg'),
                451 => array('AppID' => '5947', 'Name' => 'AKASH SAHU', 'image' => 'WhatsApp Image 2022-08-18 at 11.53.35 AM (2).jpeg'),
                452 => array('AppID' => '5953', 'Name' => 'ARHAM QURAISHI', 'image' => '1722224200_photo.jpg'),
                453 => array('AppID' => '5954', 'Name' => 'ARPIT', 'image' => '1722226244_photo.jpg'),
                454 => array('AppID' => '5936', 'Name' => 'ARSH KUMAR', 'image' => '1722224013_photo.jpg'),
                455 => array('AppID' => '5937', 'Name' => 'ASAD AHMAD', 'image' => '1722222388_photo.jpg'),
                456 => array('AppID' => '5959', 'Name' => 'ASMIT SINGH', 'image' => 'WhatsApp Image 2022-08-18 at 11.53.02 AM.jpeg'),
                457 => array('AppID' => '5941', 'Name' => 'ATHARVA SHIVHARE', 'image' => 'WhatsApp Image 2022-08-18 at 11.53.28 AM.jpeg'),
                458 => array('AppID' => '5939', 'Name' => 'ATHARVA SONI', 'image' => '1722222514_photo.jpg'),
                459 => array('AppID' => '5961', 'Name' => 'AYUSH KUMAR', 'image' => '1722222621_photo.jpg'),
                460 => array('AppID' => '5960', 'Name' => 'AYUSH KUMAR OJHA', 'image' => 'WhatsApp Image 2022-08-18 at 11.52.58 AM.jpeg'),
                461 => array('AppID' => '5962', 'Name' => 'AYUSH SINGH', 'image' => '1722223906_photo.jpg'),
                462 => array('AppID' => '5966', 'Name' => 'HARDIK SRIVASTAVA', 'image' => '1722222808_photo.jpg'),
                463 => array('AppID' => '5969', 'Name' => 'ISHAN', 'image' => '1722222898_photo.jpg'),
                464 => array('AppID' => '5970', 'Name' => 'IZAN MANSURI', 'image' => '1722223948_photo.jpg'),
                465 => array('AppID' => '5975', 'Name' => 'RONIT DWIVEDI', 'image' => '1722223111_photo.jpg'),
                466 => array('AppID' => '5976', 'Name' => 'RUDRA PRATAP YADAV', 'image' => 'WhatsApp Image 2022-08-18 at 11.52.59 AM.jpeg'),
                467 => array('AppID' => '5977', 'Name' => 'SAKSHAM SINGH', 'image' => '1722224297_photo.jpg'),
                468 => array('AppID' => '5978', 'Name' => 'SALIM QURAISHI', 'image' => '1722223362_photo.jpg'),
                469 => array('AppID' => '5980', 'Name' => 'SANKALP SACHAN', 'image' => 'WhatsApp Image 2022-08-18 at 11.53.04 AM.jpeg'),
                470 => array('AppID' => '5981', 'Name' => 'SARTHAK DWIVEDI', 'image' => 'WhatsApp Image 2022-08-18 at 11.53.32 AM.jpeg'),
                471 => array('AppID' => '5940', 'Name' => 'SHANI NISHAD', 'image' => '1722223541_photo.jpg'),
                472 => array('AppID' => '5982', 'Name' => 'SHASHWAT', 'image' => '1722223624_photo.jpg'),
                473 => array('AppID' => '5983', 'Name' => 'SHIVAM', 'image' => 'WhatsApp Image 2022-08-18 at 11.52.50 AM.jpeg'),
                474 => array('AppID' => '5986', 'Name' => 'SIDDHARTH YADAV', 'image' => '1722223660_photo.jpg'),
                475 => array('AppID' => '5990', 'Name' => 'SYED ZIYAN HUSAIN', 'image' => 'WhatsApp Image 2022-08-18 at 11.53.36 AM.jpeg'),
                476 => array('AppID' => '5992', 'Name' => 'VARUN', 'image' => 'WhatsApp Image 2022-08-18 at 11.53.04 AM (1).jpeg'),
                477 => array('AppID' => '5994', 'Name' => 'YASH KUMAR', 'image' => '1722223762_photo.jpg'),
                478 => array('AppID' => '5996', 'Name' => 'YUGRATNA SINGH', 'image' => '1722223794_photo.jpg'),
                479 => array('AppID' => '6001', 'Name' => 'ABDUL WASIF', 'image' => 'WhatsApp Image 2022-08-18 at 9.48.21 AM.jpeg'),
                480 => array('AppID' => '6002', 'Name' => 'ABHINAV DHURIYA', 'image' => 'WhatsApp Image 2022-08-18 at 9.48.22 AM (1).jpeg'),
                481 => array('AppID' => '6004', 'Name' => 'ADITI YADAV', 'image' => 'WhatsApp Image 2022-08-18 at 9.48.24 AM.jpeg'),
                482 => array('AppID' => '6005', 'Name' => 'ADITYA PRAKASH', 'image' => 'WhatsApp Image 2022-08-18 at 9.48.18 AM (1).jpeg'),
                483 => array('AppID' => '6006', 'Name' => 'ADYA BANSAL', 'image' => 'WhatsApp Image 2022-08-18 at 9.48.26 AM (2).jpeg'),
                484 => array('AppID' => '6007', 'Name' => 'AMIT KUMAR', 'image' => 'WhatsApp Image 2022-08-18 at 9.48.18 AM.jpeg'),
                485 => array('AppID' => '6008', 'Name' => 'ANJALI SINGH', 'image' => 'WhatsApp Image 2022-09-19 at 10.37.01 AM.jpeg'),
                486 => array('AppID' => '6010', 'Name' => 'AREENA KHATOON', 'image' => 'WhatsApp Image 2022-08-18 at 2.57.15 PM.jpeg'),
                487 => array('AppID' => '6012', 'Name' => 'ASHUTOSH DWIVEDI', 'image' => 'WhatsApp Image 2022-08-18 at 9.49.17 AM.jpeg'),
                488 => array('AppID' => '6013', 'Name' => 'ASTHA MISHRA', 'image' => 'WhatsApp Image 2022-08-18 at 9.48.26 AM.jpeg'),
                489 => array('AppID' => '6015', 'Name' => 'AYUSH KUMAR', 'image' => 'WhatsApp Image 2022-08-18 at 9.49.08 AM.jpeg'),
                490 => array('AppID' => '6017', 'Name' => 'DEEP SHIKHA', 'image' => 'WhatsApp Image 2022-08-18 at 9.49.14 AM.jpeg'),
                491 => array('AppID' => '6232', 'Name' => 'Deva Singh', 'image' => '1722223030_photo.jpg'),
                492 => array('AppID' => '6018', 'Name' => 'DEVANSH CHAUHAN', 'image' => 'ambm-english-2R_8th- (1).jpeg'),
                493 => array('AppID' => '6020', 'Name' => 'EHTESHAM AHAMAD', 'image' => 'WhatsApp Image 2022-08-18 at 9.49.17 AM (1).jpeg'),
                494 => array('AppID' => '6294', 'Name' => 'Himanshu shivahre', 'image' => '1722309357_photo.jpg'),
                495 => array('AppID' => '6022', 'Name' => 'IRFAN AHAMAD', 'image' => 'WhatsApp Image 2022-08-18 at 9.48.15 AM.jpeg'),
                496 => array('AppID' => '6023', 'Name' => 'KOMAL', 'image' => 'WhatsApp Image 2022-08-18 at 9.48.24 AM (1).jpeg'),
                497 => array('AppID' => '6024', 'Name' => 'KRISHNA SHARMA', 'image' => 'WhatsApp Image 2022-08-18 at 9.49.14 AM (2).jpeg'),
                498 => array('AppID' => '6027', 'Name' => 'MANYA OMAR', 'image' => 'WhatsApp Image 2022-08-18 at 9.48.28 AM (1).jpeg'),
                499 => array('AppID' => '6028', 'Name' => 'MARIYAM', 'image' => 'WhatsApp Image 2022-08-18 at 9.48.25 AM.jpeg'),
                500 => array('AppID' => '6233', 'Name' => 'Naitik Sonkar', 'image' => '1722223050_photo.jpg'),
                501 => array('AppID' => '6029', 'Name' => 'NEMISH', 'image' => 'WhatsApp Image 2022-08-18 at 9.48.17 AM (1).jpeg'),
                502 => array('AppID' => '6249', 'Name' => 'Prakhar khare', 'image' => '1722223067_photo.jpg'),
                503 => array('AppID' => '6031', 'Name' => 'PRATEEK YADAV', 'image' => 'WhatsApp Image 2022-08-18 at 9.49.13 AM (1).jpeg'),
                504 => array('AppID' => '6033', 'Name' => 'SAUMYA GUPTA', 'image' => 'WhatsApp Image 2022-08-18 at 9.48.30 AM (1).jpeg'),
                505 => array('AppID' => '6034', 'Name' => 'SAURABH SINGH', 'image' => 'WhatsApp Image 2022-08-18 at 9.48.20 AM.jpeg'),
                506 => array('AppID' => '6037', 'Name' => 'SHIVANGI', 'image' => 'WhatsApp Image 2022-08-18 at 9.48.27 AM (1).jpeg'),
                507 => array('AppID' => '6038', 'Name' => 'SHIVANSH NIGAM', 'image' => 'WhatsApp Image 2022-08-18 at 9.49.12 AM (1).jpeg'),
                508 => array('AppID' => '6039', 'Name' => 'SHIVANSH SHRIVASTAVA', 'image' => 'WhatsApp Image 2022-08-18 at 9.48.23 AM.jpeg'),
                509 => array('AppID' => '6042', 'Name' => 'TEJAS VERMA', 'image' => 'WhatsApp Image 2022-08-18 at 9.48.23 AM (1).jpeg'),
                510 => array('AppID' => '6043', 'Name' => 'VARUN MISHRA', 'image' => 'WhatsApp Image 2022-08-18 at 9.48.16 AM.jpeg'),
                511 => array('AppID' => '6045', 'Name' => 'ABHAY RAJ', 'image' => 'Abhay raj.jpg'),
                512 => array('AppID' => '6046', 'Name' => 'ABHINESH', 'image' => 'Abhinesh.jpg'),
                513 => array('AppID' => '6047', 'Name' => 'ADITYA DEV SINGH', 'image' => 'Aditya dev Vinayak.jpg'),
                514 => array('AppID' => '6048', 'Name' => 'ALOK SAINI', 'image' => '9th-2R-.jpeg'),
                515 => array('AppID' => '6049', 'Name' => 'ANUPAM PAL', 'image' => 'ambm-english-2R_9th- (7).jpeg'),
                516 => array('AppID' => '6050', 'Name' => 'ANURAG SINGH', 'image' => 'ambm-english-2R_9th- (9).jpeg'),
                517 => array('AppID' => '6051', 'Name' => 'ARYAN', 'image' => 'Aryan.jpg'),
                518 => array('AppID' => '6052', 'Name' => 'AYUSH SINGH', 'image' => 'ambm-english-2R_9th- (5).jpeg'),
                519 => array('AppID' => '6053', 'Name' => 'CHETNA GUPTA', 'image' => 'ambm-english-2R_9th- (10).jpeg'),
                520 => array('AppID' => '6054', 'Name' => 'DEVENDRA KUMAR', 'image' => 'WhatsApp Image 2022-08-18 at 11.24.39 AM (2).jpeg'),
                521 => array('AppID' => '6055', 'Name' => 'FATIMA ZUNAIRA', 'image' => 'ambm-english-2R_9th- (11).jpeg'),
                522 => array('AppID' => '6056', 'Name' => 'HARSHIT KUMAR', 'image' => 'WhatsApp Image 2022-08-18 at 11.24.42 AM (1).jpeg'),
                523 => array('AppID' => '6057', 'Name' => 'HARSHIT SINGH', 'image' => 'WhatsApp Image 2022-08-18 at 11.24.38 AM.jpeg'),
                524 => array('AppID' => '6058', 'Name' => 'KRITIKA GUPTA', 'image' => 'ambm-english-2R_9th- (8).jpeg'),
                525 => array('AppID' => '6059', 'Name' => 'MAHAK KHATOON', 'image' => 'WhatsApp Image 2022-08-18 at 11.24.37 AM (2).jpeg'),
                526 => array('AppID' => '6060', 'Name' => 'NAMAN GUPTA', 'image' => 'WhatsApp Image 2022-08-18 at 11.24.38 AM (1).jpeg'),
                527 => array('AppID' => '6061', 'Name' => 'NAMAN OMAR', 'image' => 'WhatsApp Image 2022-08-18 at 11.24.37 AM.jpeg'),
                528 => array('AppID' => '6062', 'Name' => 'NIKHIL', 'image' => 'WhatsApp Image 2022-09-24 at 10.56.22 AM.jpeg'),
                529 => array('AppID' => '6063', 'Name' => 'NUZHAT KHATOON', 'image' => 'WhatsApp Image 2022-08-18 at 11.24.37 AM (1).jpeg'),
                530 => array('AppID' => '6064', 'Name' => 'OM JI', 'image' => 'Omji.jpg'),
                531 => array('AppID' => '6065', 'Name' => 'PAVANI', 'image' => 'WhatsApp Image 2022-08-18 at 11.24.35 AM.jpeg'),
                532 => array('AppID' => '6066', 'Name' => 'PRAGYA TIWARI', 'image' => 'WhatsApp Image 2022-08-18 at 11.24.36 AM.jpeg'),
                533 => array('AppID' => '6067', 'Name' => 'PRAKHAR SACHAN', 'image' => 'WhatsApp Image 2022-08-18 at 11.24.41 AM (2).jpeg'),
                534 => array('AppID' => '6069', 'Name' => 'REHAN ALAM', 'image' => 'ambm-english-2R_9th- (4).jpeg'),
                535 => array('AppID' => '6070', 'Name' => 'RIYA KUSHWAHA', 'image' => 'ambm-english-2R_9th- (6).jpeg'),
                536 => array('AppID' => '6071', 'Name' => 'ROHAN', 'image' => 'ambm-english-2R_9th- (2).jpeg'),
                537 => array('AppID' => '6072', 'Name' => 'SANTOSH', 'image' => 'ambm-english-2R_9th- (3).jpeg'),
                538 => array('AppID' => '6073', 'Name' => 'SHREYANSH PRAKASH', 'image' => 'Shreyansh prakash.jpg'),
                539 => array('AppID' => '6074', 'Name' => 'SHRUTI SRIVASTAVA', 'image' => 'WhatsApp Image 2022-08-18 at 11.24.40 AM (1).jpeg'),
                540 => array('AppID' => '6075', 'Name' => 'SHUBHAM', 'image' => 'WhatsApp Image 2022-08-18 at 11.24.41 AM (1).jpeg'),
                541 => array('AppID' => '6076', 'Name' => 'SURYANSH SINGH', 'image' => 'WhatsApp Image 2022-08-18 at 11.24.36 AM (1).jpeg'),
                542 => array('AppID' => '6077', 'Name' => 'SWETA DHURIYA', 'image' => 'ambm-english-2R_9th- (1).jpeg'),
                543 => array('AppID' => '6078', 'Name' => 'TRIPTI SRIVASTAVA', 'image' => 'WhatsApp Image 2022-08-18 at 11.24.41 AM.jpeg'),
                544 => array('AppID' => '6079', 'Name' => 'TUBA MOIN', 'image' => 'WhatsApp Image 2022-08-18 at 11.24.36 AM (2).jpeg'),
                545 => array('AppID' => '6080', 'Name' => 'UMA MAHESH', 'image' => 'Uma mahesh.jpg'),
                546 => array('AppID' => '6081', 'Name' => 'YAMINI PRAJAPATI', 'image' => 'WhatsApp Image 2022-08-18 at 11.24.34 AM (2).jpeg'),
                547 => array('AppID' => '6082', 'Name' => 'YANSHIKA VERMA', 'image' => 'WhatsApp Image 2022-08-18 at 11.24.35 AM (2).jpeg'),
                548 => array('AppID' => '6083', 'Name' => 'ZIQRA', 'image' => 'WhatsApp Image 2022-08-18 at 11.24.32 AM.jpeg'),
                549 => array('AppID' => '6223', 'Name' => 'ABHIJEET KUMAR', 'image' => '1721703982_photo.png'),
                550 => array('AppID' => '5593', 'Name' => 'AKRAT RAJPUT', 'image' => 'WhatsApp Image 2022-08-18 at 10.22.49 AM.jpeg'),
                551 => array('AppID' => '5594', 'Name' => 'AMAN', 'image' => 'WhatsApp Image 2022-08-18 at 10.23.04 AM.jpeg'),
                552 => array('AppID' => '5595', 'Name' => 'ANANYA NIGAM', 'image' => 'WhatsApp Image 2022-09-19 at 10.44.34 AM.jpeg'),
                553 => array('AppID' => '5597', 'Name' => 'ANSHIKA YADAV', 'image' => 'WhatsApp Image 2022-09-19 at 10.44.28 AM (1).jpeg'),
                554 => array('AppID' => '6362', 'Name' => 'Azad Saini', 'image' => '1725860178_6362studentphoto.jpg'),
                555 => array('AppID' => '5602', 'Name' => 'BHUMI SRIVASTAVA', 'image' => 'WhatsApp Image 2022-08-18 at 10.23.14 AM.jpeg'),
                556 => array('AppID' => '6222', 'Name' => 'Divyansh Singh', 'image' => '1721703727_photo.png'),
                557 => array('AppID' => '6237', 'Name' => 'Harshika Swarnakar', 'image' => '1725859905_6237studentphoto.jpg'),
                558 => array('AppID' => '6231', 'Name' => 'Hridesh Dixit', 'image' => '1721891769_photo.jpg'),
                559 => array('AppID' => '5605', 'Name' => 'KATAYAYAN', 'image' => 'WhatsApp Image 2022-09-19 at 10.44.32 AM (1).jpeg'),
                560 => array('AppID' => '5606', 'Name' => 'LOKESH KUMAR', 'image' => 'WhatsApp Image 2022-08-18 at 10.22.51 AM.jpeg'),
                561 => array('AppID' => '5607', 'Name' => 'MOHD. ASIF ALI', 'image' => 'WhatsApp Image 2022-08-18 at 10.23.01 AM.jpeg'),
                562 => array('AppID' => '5608', 'Name' => 'MOHD. AZAL NAIEM', 'image' => 'WhatsApp Image 2022-08-18 at 10.22.57 AM (1).jpeg'),
                563 => array('AppID' => '5609', 'Name' => 'NIYATI KHARE', 'image' => 'WhatsApp Image 2022-08-18 at 10.23.11 AM.jpeg'),
                564 => array('AppID' => '5610', 'Name' => 'PIYUSH KUMAR', 'image' => 'WhatsApp Image 2022-08-18 at 10.22.45 AM.jpeg'),
                565 => array('AppID' => '6365', 'Name' => 'Pratha Nigam', 'image' => '1725941330_6365studentphoto.jpg'),
                566 => array('AppID' => '5612', 'Name' => 'PRINCE KUMAR', 'image' => 'WhatsApp Image 2022-08-18 at 10.22.48 AM.jpeg'),
                567 => array('AppID' => '5613', 'Name' => 'RAJ', 'image' => 'WhatsApp Image 2022-08-18 at 10.23.05 AM.jpeg'),
                568 => array('AppID' => '6238', 'Name' => 'Ramsha Mansoori', 'image' => '1721966035_photo.jpg'),
                569 => array('AppID' => '6234', 'Name' => 'Rohit Yadav', 'image' => '1721966078_photo.jpg'),
                570 => array('AppID' => '6251', 'Name' => 'Sajal Dixit', 'image' => '1722224677_photo.jpg'),
                571 => array('AppID' => '5615', 'Name' => 'SANSKAR TIWARI', 'image' => 'WhatsApp Image 2022-08-18 at 10.23.03 AM.jpeg'),
                572 => array('AppID' => '6225', 'Name' => 'Satyam', 'image' => '1721705718_photo.png'),
                573 => array('AppID' => '5619', 'Name' => 'SHREYASH KHARE', 'image' => 'WhatsApp Image 2022-08-18 at 10.22.57 AM.jpeg'),
                574 => array('AppID' => '6226', 'Name' => 'Shubhangi pavan', 'image' => '1721705959_photo.png'),
                575 => array('AppID' => '5623', 'Name' => 'SYED MO. HAMZAH', 'image' => '1721966131_photo.jpg'),
                576 => array('AppID' => '5624', 'Name' => 'TEJASVI KHARE', 'image' => 'WhatsApp Image 2022-09-19 at 10.44.32 AM.jpeg'),
                577 => array('AppID' => '6224', 'Name' => 'Uma Yadav', 'image' => '1721704136_photo.png'),
                578 => array('AppID' => '6229', 'Name' => 'Vashudev Mishra', 'image' => '1721891650_photo.jpg'),
                579 => array('AppID' => '6361', 'Name' => 'Viraj', 'image' => '1725860069_6361studentphoto.jpg'),
                580 => array('AppID' => '5627', 'Name' => 'ABHIJEET SINGH', 'image' => 'ambm-english-2R_11th- (1).jpeg'),
                581 => array('AppID' => '5628', 'Name' => 'ANKITA', 'image' => 'WhatsApp Image 2022-08-19 at 2.38.53 PM (2).jpeg'),
                582 => array('AppID' => '5629', 'Name' => 'ANUJ KUMAR', 'image' => 'WhatsApp Image 2022-08-19 at 2.38.29 PM.jpeg'),
                583 => array('AppID' => '5630', 'Name' => 'DISHA NIGAM', 'image' => 'disha-10th.jpeg'),
                584 => array('AppID' => '5631', 'Name' => 'HIMANSHU', 'image' => 'WhatsApp Image 2022-08-19 at 2.38.47 PM (1).jpeg'),
                585 => array('AppID' => '5632', 'Name' => 'JANVI GUPTA', 'image' => 'WhatsApp Image 2022-08-19 at 2.38.54 PM.jpeg'),
                586 => array('AppID' => '5633', 'Name' => 'JYOTI KUSHWAHA', 'image' => 'WhatsApp Image 2022-08-19 at 2.38.53 PM (1).jpeg'),
                587 => array('AppID' => '5634', 'Name' => 'KRISHNA KANT VERMA', 'image' => 'WhatsApp Image 2022-08-19 at 2.38.45 PM.jpeg'),
                588 => array('AppID' => '5635', 'Name' => 'NAITIK KASAUDHAN', 'image' => 'WhatsApp Image 2022-08-19 at 2.38.31 PM.jpeg'),
                589 => array('AppID' => '5636', 'Name' => 'NEESHU', 'image' => 'WhatsApp Image 2022-08-19 at 2.38.48 PM.jpeg'),
                590 => array('AppID' => '5637', 'Name' => 'RUSHNA KHAN', 'image' => 'WhatsApp Image 2022-08-19 at 2.38.50 PM (1).jpeg'),
                591 => array('AppID' => '5638', 'Name' => 'SALIL PRAKASH', 'image' => '11th-SALIL PRAKASH.jpg'),
                592 => array('AppID' => '5639', 'Name' => 'SANIYA', 'image' => 'WhatsApp Image 2022-08-19 at 2.38.51 PM (2).jpeg'),
                593 => array('AppID' => '5640', 'Name' => 'VANSH LAKSHAKAR', 'image' => 'ambm-english-2R_11th- (2).jpeg'),
                594 => array('AppID' => '5641', 'Name' => 'WARISH', 'image' => 'WhatsApp Image 2022-08-19 at 2.38.49 PM (2).jpeg'),
                595 => array('AppID' => '5642', 'Name' => 'ZAINAB KHAN', 'image' => 'WhatsApp Image 2022-08-19 at 2.38.51 PM (1).jpeg'),
            );
            $key = $request->key;
            
            
            for($i=1;$i<=1;$i++){
                // $name = $e['Name'];
                $e = $excelRecord[$i];
                $username = $e['AppID'];
                $param = "CardNoList[0]=$username";
                // Delete Card Record
                $client = new Client();
                $url = 'http://192.168.165.239';
                $response = $client->request(
                    'GET',
                    $url.'/cgi-bin/AccessCard.cgi?action=removeMulti&'.$param, [
                        'verify' => false,
                        'auth' => ['admin', 'tipl9910', 'digest'],
                ]);
            }
            return $param;
            
            
            // echo $key;
            // die;
            // for($i=$key;$i<=283;$i++){
                // [15, ]
            // $tempKey = [28, 29, 36, 41, 45, 49, 52, 53, 55, 59, 67, 70, 86, 92, 95, 99, 102, 103, 106, 109, 111, 113, 115, 116, 118, 126, 136, 141, 143, 148, 149, 151, 153, 155, 166, 169, 171, 172, 173, 175, 183, 193, 195, 196, 201, 205, 208, 215, 249, 250, 252, 257, 262, 265, 269, 270, 274, 276, 280];
            // 
            
            for($i=460;$i<=595;$i++){
                // $e = $excelRecord[$i];
                $e = $excelRecord[$i];
                // $imagePath = $photoDirectory . DIRECTORY_SEPARATOR . $e['image'];
                // if (!file_exists($imagePath)) {
                //     abort(404, 'Image not found');
                // }
                

                // $imageData = file_get_contents($imagePath);

                // $base64Image = base64_encode($imageData);
                echo $i." : ".$e['AppID']."<br>";
                // echo $t.' - ,'.$e['AppID'].' - ,'.$e['Name'],' - ,'.$e['Photo'].' , - '.$base64Image."<br>";
                // return;
                $client = new Client();
                $name = $e['Name'];
                $username = $e['AppID'];
                $param = "&CardName=$name&CardNo=$username&UserID=$username";
                $url = 'http://192.168.144.239';
                $response = $client->request(
                    'GET',
                    $url.'/cgi-bin/recordUpdater.cgi?action=insert&name=AccessControlCard&CardStatus=0'.$param, [
                        'verify' => false,
                        'auth' => ['admin', 'tipl9910', 'digest'],
                ]);
                // // Check response status code
                
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
                    // echo json_encode($jayParsedAry);
                    // echo "<br>";
                    // echo $response->getBody();
                // } else {
                //     // Handle non-200 response (e.g., error handling)
                //     echo 'Request failed with status code: ' . $response->getStatusCode();
                // }
                    
            }
            // return ;
            /*
            $PhotoArray = [];
            
            for ($i = 50; $i <= 60; $i++) {
                $e = $excelRecord[$i];

                $imagePath = $photoDirectory . DIRECTORY_SEPARATOR . $e['image'];

                if (!file_exists($imagePath)) {
                    echo "$i : Image not found : $imagePath<br>";
                    continue; // Skip this iteration if the image does not exist
                }

                $name = $e['Name'];
                $username = $e['AppID'];
                $param = "&CardName=$name&CardNo=$username&UserID=$username";

                echo "$i : $username<br>";

                $imageData = file_get_contents($imagePath);

                $base64Image = base64_encode($imageData);
                $PhotoArray[] = [
                    "UserID" => $username,
                    "PhotoData" => [$base64Image],
                ];
                $jayParsedAry["FaceList"] = $PhotoArray;

                $client = new Client();
                $url = 'http://192.168.202.196';

                try {
                    $response = $client->post($url . '/cgi-bin/AccessFace.cgi?action=insertMulti', [
                        'verify' => false,
                        'auth' => ['admin', 'tipl9910', 'digest'],
                        'headers' => [
                            'Content-Type' => 'application/json',
                        ],
                        'body' => json_encode($jayParsedAry),
                    ]);
                    echo $response->getBody();
                } catch (\Exception $e) {
                    echo "Error with user $username: " . $e->getMessage() . "<br>";
                    continue; // Continue with the next iteration even if there's an error
                }
            }*/
            

        } catch (\Exception $e) {
            // Handle exceptions (e.g., connection error, timeout)
            echo 'Request failed: ' . $e->getMessage();
            
        }
    }

    public function uploadtoTimy(){
        try{
            
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
            // dd($e);
            return exceptionResponse($e);
        }
    }

    public function uploadtoTimyMachineServer(Request $request) {
        try {
            
            $registrations = Registration::select(
                'id','registration_id', 'name',
                DB::raw("IF(dob IS NOT NULL, DATE_FORMAT(dob, '%m-%d'), '00-00') AS dob"),
                'photo'
            )
            ->where([
                ['session', $request->companyid],
                ['status', 'Active'],
                ['s_position', null]
            ])
            ->get();
            echo "Total record : ".count($registrations);
            echo "<br>";
    
            // Temporary connection configuration
            $tempDBConfig = [
                'driver'    => 'mysql',
                'host'      => "localhost",
                'database'  => 'realtime',
                'username'  => 'root',
                'password'  => '',
                'charset'   => 'utf8',
                'collation' => 'utf8_unicode_ci',
                'prefix'    => '',
                'strict'    => false,
            ];
    
            // Set temporary connection
            config(['database.connections.temp_mysql' => $tempDBConfig]);
            $tempDB = DB::connection('temp_mysql');
    
            // Process and insert data into the temporary database
            set_time_limit(600);
            $j = 1;
    
            foreach ($registrations as $r) {
                // $imageUrl = $r->photo;
                
                $photoUrlForBasename = $r->photo;
                $fileName = basename($photoUrlForBasename);
                // $imageUrl = str_replace(" ", "%20", $photoUrlForBasename);
                $imageUrl = "F:/SHARDA SOLUTIONS/2024/mips-belatal/".$fileName;

                if($imageUrl!=null){
                    if ($imageInfo = @getimagesize($imageUrl)) {
                        $imageData = file_get_contents($imageUrl);
                        $base64Image = base64_encode($imageData);
                        $cmdsetup = [
                            "cmd" => "setuserinfo",
                            "enrollid" => (int)$r->registration_id,
                            "name" => $r->name,
                            "backupnum" => 50,
                            "admin" => 0,
                            "birthday" => $r->dob,
                            "record" => $base64Image
                        ];
        
                        $insertData = [
                            "serial" => "AYSC26027690",
                            "name" => "setuserinfo",
                            "content" => json_encode($cmdsetup),
                            "gmt_crate" => now(),
                            "gmt_modified" => now(),
                        ];
        
                        // Insert data into the temporary database
                        $tempDB->table('machine_command')->insert($insertData);
        
                        // Update the registration record's s_position to 1
                        Registration::where('id', $r->id)
                            ->update(['s_position' => 1]);
                        echo $j . ")-> : -> :.-- : " . $r->registration_id . " -: : - " . $r->name . " - : - " . $r->dob." --:-- Photo Insert done.";
                        echo "<br>";
                        // $j++;    
                    }else{
                        echo $j . ")-> : -> :.-- : " . $r->registration_id . " -: : - " . $r->name . " - : - " . $r->dob;
                        echo "<br>";
                    }
                    $j++;    
                }
            }
            // Disconnect from temporary database
            $tempDB->disconnect();
            
            return response()->json(['message' => 'Data processed successfully.']);
            
        } catch (\Exception $e) {
            return exceptionResponse($e);
        }
    }
    

}
