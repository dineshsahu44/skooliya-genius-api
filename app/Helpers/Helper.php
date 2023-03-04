<?php
use Illuminate\Support\Facades\Auth; 
use Illuminate\Support\Facades\File; 
use App\Models\SchoolSession;
use App\Models\Registration;
use App\Models\Faculty;
use App\Models\User;
use Carbon\Carbon;
// use Log;

    function customResponse($type, $data=null){
        $success = $type==1?1:0;
        $data['success']=$success;
        Log::info('from', ["response"=>$data]);
        return $data;
    }

    function exceptionResponse($e,$data=null){
        $data['error'] = ["msg"=>@$e->getMessage(),"line"=>@$e->getLine()];
        $data['success']=0;
        Log::error('exception', ["response"=>$data]);
        return $data;
    }
    function getAuth(){
        return Auth::guard('api')->user();
    }

    function getUserTableRecord($school_id,$username){
        return User::where([['school_id',$school_id],['username',$username]])->first();
    }
    function implodeClass($assignclass){
        $assignclass =  json_decode($assignclass,true);  
        $temp = array();
        foreach($assignclass as $class){
            foreach($class['section'] as $section){
                $p =  "'".$class['class']."-".$section."'";
                array_push($temp,$p);
            }
        }
        $temp = implode(",",$temp);
        return $temp==''?null:$temp;
    }

    function currentSession(){
        return SchoolSession::where([['school_id',getAuth()->school_id],['status','Active']])->first();
    }

    function getSchoolIdBySessionID($sessionId){
        return SchoolSession::where('id',$sessionId)->first();
    }

    function saveFiles($file,$accpectFiles){
        $current_timestamp = Carbon::now()->timestamp;
        $fileName = $current_timestamp.'_'.cleanSpecial($file->getClientOriginalName());
        $path = '/'.getServerInfo()->path.'/'.getAuth()->school_id.'/'.$accpectFiles['path'];
        // $path = getServerInfo()->path.'/'.getAuth()->school_id.'/'.$accpectFiles['path'];
        $attachment = Storage::disk('my-disk')->putFileAs($path, $file, $fileName);
        return env('APP_URL').$attachment;
        // Here the first argument for putFileAs is the subfolder to save to
    }

    function removeFile($file){
        if(\File::exists(public_path(parse_url($file, PHP_URL_PATH)))){
            File::delete(public_path(parse_url($file, PHP_URL_PATH)));
        }
    }

    function cleanSpecial($string) {
        $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
        $string = preg_replace('/[^A-Za-z0-9\-.-]/', '', $string); // Removes special chars.
     
        return preg_replace('/-+/', '-', $string); // Replaces multiple hyphens with single one.
    }

    function getHostInfoAndDatabase(){
        return [
            'local'=>[
                'host'=>'217.21.80.2',
                'database'  => 'u210117126_3050884_test',
                'username'  => 'u210117126_skooliya',
                'password'  => 'Skooliya@123',
            ],
            'production'=>[
                'host'=>'localhost',
                'database'  => 'u210117126_3050884_test',
                'username'  => 'u210117126_skooliya',
                'password'  => 'Skooliya@123',
            ]
        ];
    }

    function getServerInfo(){
        return Session::get('server');
    }

    function getFacultyInfo($faculty_id,$school_id){
        return Faculty::where([['faculty_id',$faculty_id],['school_id',$school_id]])->first();
    }
    
    function accpectFiles($acceptfor=null){
        $accept = [
            "notification-files"=>[
                "extension-type"=>[
                    'png','jpeg','jpg','gif','pdf','xlsx','docs'
                ],
                "path"=>"app/photo",
                "max-size"=>500//kb
            ],
            "banner-files"=>[
                "extension-type"=>[
                    'png','jpeg','jpg','gif'
                ],
                "path"=>"app/onlineclass/banner",
                "max-size"=>500//kb
            ],
            "online-class-files"=>[
                "extension-type"=>[
                    'png','jpeg','jpg','gif','pdf','xlsx','docs'
                ],
                "path"=>"app/onlineclass/docs",
                "max-size"=>500//kb
            ],
            "album-files"=>[
                "extension-type"=>[
                    'png','jpeg','jpg','gif'
                ],
                "path"=>"app/album",
                "max-size"=>500//kb
            ],
            "gallery-files"=>[
                "extension-type"=>[
                    'png','jpeg','jpg','gif'
                ],
                "path"=>"app/album/gallery",
                "max-size"=>500//kb
            ],
            "event-files"=>[
                "extension-type"=>[
                    'png','jpeg','jpg','gif'
                ],
                "path"=>"app/event",
                "max-size"=>500//kb
            ],
            "feedback-files"=>[
                "extension-type"=>[
                    'png','jpeg','jpg','gif'
                ],
                "path"=>"app/event",
                "max-size"=>500//kb
            ],
            "faculty-photo-files"=>[
                "extension-type"=>[
                    'png','jpeg','jpg','gif'
                ],
                "path"=>"app/profile",
                "max-size"=>500//kb
            ],
            "comment-files"=>[
                "extension-type"=>[
                    'png','jpeg','jpg',
                ],
                "path"=>"app/comment",
                "max-size"=>500//kb
            ],
        ];
        return $acceptfor!=null?$accept[$acceptfor]:$accept;
    }

    function validatorMessage($validator){
        $responseArr['message'] = $validator->messages();
        $responseArr['success'] = 0;
        Log::error('validator', ["response"=>$responseArr]);
        return $responseArr;
    }

    function send_notification($body,$title,$notificationtype,$record)
    {
        //everyone=1 means all, 2 means tokenlistgiven, 3 means teacher only
       
        //session_write_close(); //close the session
        //ignore_user_abort(true); //Prevent echo, print, and flush from killing the script
        //fastcgi_finish_request(); //this returns 200 to the user, and processing continues
        //litespeed_finish_request();//use this for litespeed php this returns 200 to the user, and processing continues
        // try{
        $filteredRecord = Arr::where($record, function ($value, $key) {
            return !empty($value['token'])&& $value['token']!= null;
        });
        // dd($filteredRecord,getServerInfo()->API_ACCESS_KEY);
        foreach($filteredRecord as $fr){
            $data = array
                (
                    'id'  =>$fr['username'],
                    'body' 	=> $body,
                    'title'	=> $title,
                    'notificationtype' => $notificationtype
                );
                
            $fields = array
                (
                    'registration_ids' => [$fr['token']],
                    'priority' => 'high',
                    'data'	=> $data,
                    
                );
                
            $headers = array
                (
                    'Authorization: key=' . getServerInfo()->API_ACCESS_KEY,
                    'Content-Type: application/json'
                );

                // dd($data,$fields,$headers);
            #Send Reponse To FireBase Server	
            $ch = curl_init();
            curl_setopt( $ch,CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send' );
            curl_setopt( $ch,CURLOPT_POST, true );
            curl_setopt( $ch,CURLOPT_HTTPHEADER, $headers );
            curl_setopt( $ch,CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER, false );
            curl_setopt( $ch,CURLOPT_POSTFIELDS, json_encode( $fields ) );
            $result = curl_exec($ch );
            // echo $result;
            curl_close( $ch );
        }
        // }catch(\Exception $e){
        //     dd($e);
        // }
    }

    function notificationType($type=null){
        $noti = [
            "notice"=>[
                "title"=>"Notice",
                "apptype"=>"Notice"
            ],
            "homework"=>[
                "title"=>"Homework",
                "apptype"=>"Homework"
            ],
            "noticeteacher"=>[
                "title"=>"Teacher Specific",
                "apptype"=>"NoticeTeacher"
            ],
            "circular"=>[
                "title"=>"Circular",
                "apptype"=>"Circular"
            ],
            "banner"=>[
                "title"=>"Circular",
                "apptype"=>"Circular"
            ]
        ];
        return $type!=null?$noti[$type]:$noti;
    }

    function getStudentTokenByConcatClass($assignClasses,$sessionId){
        return $studentCount = Registration::select('username','users.device_token as token')
            ->join('classes','classes.id','registrations.class')
            ->join('sections','sections.id','registrations.section')
            // ->join('school_sessions','school_sessions.id','registrations.session')
            ->join('users','users.username','registrations.registration_id')
            ->where('registrations.session',$sessionId)
            // ->where('school_sessions.id',$sessionid)
            ->whereRaw($assignClasses=='all'?'1=1':"CONCAT_WS('-',classes.class,sections.section) IN ($assignClasses)")
            ->get()->toArray();
    }

    function getClassFromAccountID($accountID, $companyID){
        return Registration::select('classes.class','sections.section')
        ->join('classes','classes.id','registrations.class')
        ->join('sections','sections.id','registrations.section')
        ->where([['registration_id',$accountID],['session',$companyID]])->first();
    }

    function pageLimit($page=null){
        // please take limit is always in odd no.
        $page = $page=!null?$page:0;
        $limit = 7;
        $paginate = [
            "offset"=>$page*$limit,
            "limit"=>$limit,
        ];
        return (object)$paginate;
    }

    function checkFacultyClassGetRaw($getclass,$getsection,$facluty_id,$getcompanyid){
        if(($getclass=='All' && $getsection=='All')||($getclass!='All' && $getsection=='All')||($getclass=='All' && $getsection!='All')){
            $faculty = Faculty::select('assignclass')
                ->join('school_sessions','school_sessions.school_id','faculties.school_id')
                ->where([['faculties.faculty_id',$facluty_id],['school_sessions.id',$getcompanyid]])->first();
            if($faculty->assignclass=='all'){
                if($getclass=='All' && $getsection=='All'){
                    $sql ="1=1";
                }else if($getclass!='All' && $getsection=='All'){
                    $sql ="classes.class='".$getclass."'";
                }else if($getclass=='All' && $getsection!='All'){
                    $sql ="sections.section='".$getsection."'";
                }
            }else{
                $assignclass =  json_decode($faculty->assignclass,true);
                $assignclass = @count($assignclass)>0?$assignclass:[];
                $temp = [];
                if($getclass=='All' && $getsection=='All'){
                    foreach($assignclass as $class){
                        foreach($class['section'] as $section){
                            $temp[] =  "'".$class['class']."-".$section."'";
                        }
                    }
                }elseif($getclass!='All' && $getsection=='All'){
                    foreach($assignclass as $class){
                        if($class['class']==$getclass){
                            foreach($class['section'] as $section){
                                $temp[] =  "'".$class['class']."-".$section."'";
                            }
                        }
                    }
                }elseif($getclass=='All' && $getsection!='All'){
                    foreach($assignclass as $class){
                        foreach($class['section'] as $section){
                            if($section==$getsection){
                                $temp[] =  "'".$class['class']."-".$section."'";
                            }
                        }
                    }
                }
                $temp = implode(",",$temp);
                
                if($temp=='')
                    $temp='null';
                
                $sql ="concat_ws('-',classes.class,sections.section) IN ($temp)";
            }
        
        }else {
            $sql ="classes.class='$getclass' and sections.section='$getsection'";
        }
        return $sql;//raw condition for class
    }

    function importTableNames(){
        $tables = [
            ["from"=>"company","to"=>["schools","school_sessions"],"url"=>"/import-database/importCompanyToSchoolAndSchoolSessions"],
            ["from"=>"admission","to"=>['classes','sections'],"url"=>"/import-database/importAdmissionToClassesAndSections"],
            ["from"=>"teachers","to"=>["faculties","users"],"url"=>"/import-database/importTeachersToFacultiesAndUsers"],
            ["from"=>"admission","to"=>["registrations","guardians"],"url"=>"/import-database/importAdmissionToRegistrationsAndGuardians"],
            ["from"=>"admission","to"=>["users"],"url"=>"/import-database/importAdmissionToUniqueUsers"],
            ["from"=>"attendance","to"=>["attendances"],"url"=>"/import-database/importAttendanceToAttendances"],
            ["from"=>"albums","to"=>["albums"],"url"=>"/import-database/importAlbums"],
            
            ["from"=>"birthdaycard","to"=>["birthdaycard"],"url"=>"/import-database/importBirthdayCard"],
            ["from"=>"comment","to"=>["comment"],"url"=>"/import-database/importComment"],
            ["from"=>"events","to"=>["events"],"url"=>"/import-database/importEvents"],
            ["from"=>"feedback","to"=>["feedback"],"url"=>"/import-database/importFeedback"],
            ["from"=>"holiday","to"=>["holidays"],"url"=>"/import-database/importHoliday"],
            ["from"=>"hwmessage","to"=>["hwmessage"],"url"=>"/import-database/importHwmessage"],
            ["from"=>"hwmessagefor","to"=>["hwmessagefor"],"url"=>"/import-database/importHwmessageFor"],
            ["from"=>"live_banner","to"=>["live_banner"],"url"=>"/import-database/importLiveBanner"],
            ["from"=>"live_banner_for","to"=>["live_banner_for"],"url"=>"/import-database/importLiveBannerFor"],
            ["from"=>"live_classes","to"=>["live_classes"],"url"=>"/import-database/importLiveClasses"],
            ["from"=>"Live_classes_for","to"=>["Live_classes_for"],"url"=>"/import-database/importLiveClassesFor"],
            ["from"=>"live_docs","to"=>["live_docs"],"url"=>"/import-database/importLiveDocs"],
            ["from"=>"live_exam","to"=>["live_exam"],"url"=>"/import-database/importLiveExam"],
            ["from"=>"live_exam_for","to"=>["live_exam_for"],"url"=>"/import-database/importLiveExamFor"],
            ["from"=>"live_session","to"=>["live_session"],"url"=>"/import-database/importLiveSession"],
            ["from"=>"live_session_for","to"=>["live_session_for"],"url"=>"/import-database/importLiveSessionFor"],
            ["from"=>"mainscreenoptions","to"=>["mainscreenoptions"],"url"=>"/import-database/importMainScreenOptions"],
            ["from"=>"photosvideos","to"=>["photosvideos"],"url"=>"/import-database/importPhotosVideos"],
            ["from"=>"quizattempt","to"=>["quizattempt"],"url"=>"/import-database/importQuizAttempt"],
            ["from"=>"quizfor","to"=>["quizfor"],"url"=>"/import-database/importQuizFor"],
            ["from"=>"quizquestions","to"=>["quizquestions"],"url"=>"/import-database/importQuizquestions"],
            // ["from"=>"schools","to"=>["schools"],"url"=>"/import-database/setSchoolDatabase"],
        ];
        return $tables;
    }

    function masterRecord(){
        // id,name,value,school_id,sync_property_id
        $rows = [
            ["name"=>"support_expires_date","value"=>"2023-04-15","sync_property_id"=>0],
            ["name"=>"product_expires_date","value"=>"2023-04-15","sync_property_id"=>0],
            ["name"=>"feeCollection","value"=>"1001","sync_property_id"=>0],
            ["name"=>"Employee Library Book Limit","value"=>"1001","sync_property_id"=>0],
            ["name"=>"Employee Library days for book issue","value"=>"1001","sync_property_id"=>0],
            ["name"=>"Employee Library Fine","value"=>"1001","sync_property_id"=>0],
            ["name"=>"project_completion_certificate_text","value"=>"This is to certify that #name d/o / s/o #fname studying in the class #class has done project work on _______________ under the guidance of _____________ support towards the fulfillment of the award of _____________ during the period __________ to ________","sync_property_id"=>0],
            ["name"=>"project_completion_certificate","value"=>"1001","sync_property_id"=>0],
            ["name"=>"participation_certificate_text","value"=>"This is to certify that #name s/o / d/o of #fname studying in class #class has successfully participated in _________________ Compedetion held in _________, on___________.","sync_property_id"=>0],
            ["name"=>"participation_certificate","value"=>"1001","sync_property_id"=>0],
            ["name"=>"BarCode","value"=>"1001","sync_property_id"=>0],
            ["name"=>"inventory_temp_id","value"=>"1001","sync_property_id"=>0],
            ["name"=>"enquiry_form","value"=>"1001","sync_property_id"=>0],
            ["name"=>"enquiry_no","value"=>"1001","sync_property_id"=>0],
            ["name"=>"sibling","value"=>"1001","sync_property_id"=>0],
            ["name"=>"character_certificate_text","value"=>"This is to certify that #name s/o / d/o #fname is a regular student of our school. He/She is stuying in the class #class in the session #session .\r\nAccording to our records his/her date of birth is #dob and his/her address is #address and to the best of m","sync_property_id"=>0],
            ["name"=>"bonafied_certificate_text","value"=>"This is to certify that #name s/o / d/o #fname of class #class residence of #address is a bonafied student of this school.\r\nAccourding to our admission register his/her date of birth is #dob . We wish him/her a bright future and very best life.","sync_property_id"=>0],
            ["name"=>"fee_certificate_text","value"=>"This is to certify that #name s/o / d/o of #fname class #class has paid a total amount of #totalpaid including #monthlypaid per month as monthly tution fees and other school dues from #sdate to #edate .All sums due to this school chargable on his/her amou","sync_property_id"=>0],
            ["name"=>"fee_certificate","value"=>"1001","sync_property_id"=>0],
            ["name"=>"Character","value"=>"1001","sync_property_id"=>0],
            ["name"=>"Bonafied","value"=>"1001","sync_property_id"=>0],
            ["name"=>"TC","value"=>"1001","sync_property_id"=>0],
            ["name"=>"Product_key","value"=>"1001","sync_property_id"=>0],
            ["name"=>"Exam_id","value"=>"1001","sync_property_id"=>0],
            ["name"=>"Faculty Library Book Limit","value"=>"1001","sync_property_id"=>0],
            ["name"=>"Faculty Library days for book issue","value"=>"1001","sync_property_id"=>0],
            ["name"=>"Faculty Library Fine","value"=>"1001","sync_property_id"=>0],
            ["name"=>"Library Max books Limit","value"=>"1001","sync_property_id"=>0],
            ["name"=>"Library days for book issue","value"=>"1001","sync_property_id"=>0],
            ["name"=>"Library fine","value"=>"1001","sync_property_id"=>0],
            ["name"=>"Management_id","value"=>"1001","sync_property_id"=>0],
            ["name"=>"FEE LATE FINE PER DAY","value"=>"1001","sync_property_id"=>0],
            ["name"=>"Inventory Transection ID","value"=>"1001","sync_property_id"=>0],
            ["name"=>"Order_no","value"=>"1001","sync_property_id"=>0],
            ["name"=>"Library Memebr Id","value"=>"1001","sync_property_id"=>0],
            ["name"=>"route_id","value"=>"1001","sync_property_id"=>0],
            ["name"=>"transection_id","value"=>"1001","sync_property_id"=>0],
            ["name"=>"Recipt_id","value"=>"1001","sync_property_id"=>0],
            ["name"=>"Faculty_id","value"=>"1001","sync_property_id"=>0],
            ["name"=>"Employee_id","value"=>"1001","sync_property_id"=>0],
            ["name"=>"Registration_id","value"=>"0","sync_property_id"=>0],
            ["name"=>"student_app_id","value"=>"5000","sync_property_id"=>0],
            ["name"=>"staff_app_id","value"=>"1000","sync_property_id"=>0],
            
        ];
        return $rows;
    }

    function roleRecord(){
        $rows = [
            [
                "role_name"=>"superadmin", 
                "capabilities"=>'{"users":{"allUser":"1","assignPrivileges":"1"},"roles":{"index":"1"},"schools":{"setup":"1","smsSetting":"1","all":"1"},"enquiries":{"all":"1","add":"1"},"classes":{"add":"1"},"structures":{"fee_setting":"1","add":"1","feesReport":"1"},"StudentFeeDetails":{"add":"1","collect":"1","cancelTransection":"1"},"notice_boards":{"index":"1"},"syllabi":{"index":"1"},"holidays":{"add":"1"},"registrations":{"register":"1","record":"1","prmote":"1","importStudents":"1","editBulkRecord":"1","icard":"1","admitCard":"1"},"reports":{"index":"1"},"attendances":{"student":"1","record":"1"},"student_leaves":{"add":"1"},"certificates":{"generate_certificates":"1","report":"1"},"faculties":{"all":"1","icard":"1","record":"1"},"task_managers":{"index":"1","all":"1"},"faculty_attendances":{"attendance":"1"},"employee_attendances":{"attendance":"1"},"faculty_leaves":{"add":"1"},"employee_leaves":{"add":"1"},"messages":{"studentSms":"1","faculty":"1","classAndSectionWiseSms":"1","anySms":"1"},"message_heads":{"type":"1"},"sms_logs":{"index":"1"},"accounts":{"income":"1","expense":"1","moneyTransfer":"1"},"item_details":{"cdreport":"1","add":"1","staffIssue":"1","due":"1","cancelTransection":"1","report":"1","dreport":"1","dreportstaff":"1"},"pay_head_mappings":{"index":"1"},"salary_settings":{"index":"1","monthlyReport":"1"},"salary_details":{"index":"1","salarySlip":"1","report":"1","monthlyEmployersShare":"1","monthlySalary":"1"},"RSubjects":{"add":"1"},"cocategories":{"add":"1","addIndicator":"1"},"ExamSettings":{"examConfig":"1"},"marks":{"edit":"1","record":"1"},"grades":{"edit":"1"},"terms":{"add":"1"},"periods":{"add":"1"},"subjects":{"add":"1"},"ttables":{"teachers":"1","add":"1","fReport":"1"},"pick_points":{"add":"1"},"routes":{"all":"1"},"vehicles":{"all":"1"},"vehicle_routes":{"add":"1"},"drivers":{"managed":"1"},"passengers":{"add":"1","report":"1","editStudentPassenger":"1"},"books":{"all":"1","lset":"1","catalog":"1"},"book_details":{"madd":"1"},"library_members":{"add":"1"},"ivendors":{"add":"1"},"item_orders":{"all":"1"},"item_categories":{"add":"1"},"items":{"add":"1"},"item_sets":{"add":"1"},"buildings":{"add":"1"},"room_masters":{"index":"1"},"bed_allotments":{"index":"1","edit":"1","shifting":"1","hostelers":"1","roomAvailability":"1"}}',
                "sms_setting"=>null,
                "faculty_type"=>null,
                "school_id"=>1,
                "status"=>1
            ],
            [
                "role_name"=>"admin", 
                "capabilities"=>'{"users":{"allUser":"1","assignPrivileges":"1"},"roles":{"index":"1"},"schools":{"setup":"1","smsSetting":"1","all":"1"},"enquiries":{"all":"1","add":"1"},"classes":{"add":"1"},"structures":{"fee_setting":"1","add":"1","feesReport":"1"},"StudentFeeDetails":{"add":"1","collect":"1","cancelTransection":"1"},"notice_boards":{"index":"1"},"syllabi":{"index":"1"},"holidays":{"add":"1"},"registrations":{"register":"1","record":"1","prmote":"1","importStudents":"1","editBulkRecord":"1","icard":"1","admitCard":"1"},"reports":{"index":"1"},"attendances":{"student":"1","record":"1"},"student_leaves":{"add":"1"},"certificates":{"generate_certificates":"1","report":"1"},"faculties":{"all":"1","icard":"1","record":"1"},"task_managers":{"index":"1","all":"1"},"faculty_attendances":{"attendance":"1"},"employee_attendances":{"attendance":"1"},"faculty_leaves":{"add":"1"},"employee_leaves":{"add":"1"},"messages":{"studentSms":"1","faculty":"1","classAndSectionWiseSms":"1","anySms":"1"},"message_heads":{"type":"1"},"sms_logs":{"index":"1"},"accounts":{"income":"1","expense":"1","moneyTransfer":"1"},"item_details":{"cdreport":"1","add":"1","staffIssue":"1","due":"1","cancelTransection":"1","report":"1","dreport":"1","dreportstaff":"1"},"pay_head_mappings":{"index":"1"},"salary_settings":{"index":"1","monthlyReport":"1"},"salary_details":{"index":"1","salarySlip":"1","report":"1","monthlyEmployersShare":"1","monthlySalary":"1"},"RSubjects":{"add":"1"},"cocategories":{"add":"1","addIndicator":"1"},"ExamSettings":{"examConfig":"1"},"marks":{"edit":"1","record":"1"},"grades":{"edit":"1"},"terms":{"add":"1"},"periods":{"add":"1"},"subjects":{"add":"1"},"ttables":{"teachers":"1","add":"1","fReport":"1"},"pick_points":{"add":"1"},"routes":{"all":"1"},"vehicles":{"all":"1"},"vehicle_routes":{"add":"1"},"drivers":{"managed":"1"},"passengers":{"add":"1","report":"1","editStudentPassenger":"1"},"books":{"all":"1","lset":"1","catalog":"1"},"book_details":{"madd":"1"},"library_members":{"add":"1"},"ivendors":{"add":"1"},"item_orders":{"all":"1"},"item_categories":{"add":"1"},"items":{"add":"1"},"item_sets":{"add":"1"},"buildings":{"add":"1"},"room_masters":{"index":"1"},"bed_allotments":{"index":"1","edit":"1","shifting":"1","hostelers":"1","roomAvailability":"1"}}',
                "sms_setting"=>null,
                "faculty_type"=>null,
                "school_id"=>1,
                "status"=>1
            ],
            [
                "role_name"=>"teacher", 
                "capabilities"=>'{"users":{"allUser":"1","assignPrivileges":"1"},"roles":{"index":"1"},"schools":{"setup":"1","smsSetting":"1","all":"1"},"enquiries":{"all":"1","add":"1"},"classes":{"add":"1"},"structures":{"fee_setting":"1","add":"1","feesReport":"1"},"StudentFeeDetails":{"add":"1","collect":"1","cancelTransection":"1"},"notice_boards":{"index":"1"},"syllabi":{"index":"1"},"holidays":{"add":"1"},"registrations":{"register":"1","record":"1","prmote":"1","importStudents":"1","editBulkRecord":"1","icard":"1","admitCard":"1"},"reports":{"index":"1"},"attendances":{"student":"1","record":"1"},"student_leaves":{"add":"1"},"certificates":{"generate_certificates":"1","report":"1"},"faculties":{"all":"1","icard":"1","record":"1"},"task_managers":{"index":"1","all":"1"},"faculty_attendances":{"attendance":"1"},"employee_attendances":{"attendance":"1"},"faculty_leaves":{"add":"1"},"employee_leaves":{"add":"1"},"messages":{"studentSms":"1","faculty":"1","classAndSectionWiseSms":"1","anySms":"1"},"message_heads":{"type":"1"},"sms_logs":{"index":"1"},"accounts":{"income":"1","expense":"1","moneyTransfer":"1"},"item_details":{"cdreport":"1","add":"1","staffIssue":"1","due":"1","cancelTransection":"1","report":"1","dreport":"1","dreportstaff":"1"},"pay_head_mappings":{"index":"1"},"salary_settings":{"index":"1","monthlyReport":"1"},"salary_details":{"index":"1","salarySlip":"1","report":"1","monthlyEmployersShare":"1","monthlySalary":"1"},"RSubjects":{"add":"1"},"cocategories":{"add":"1","addIndicator":"1"},"ExamSettings":{"examConfig":"1"},"marks":{"edit":"1","record":"1"},"grades":{"edit":"1"},"terms":{"add":"1"},"periods":{"add":"1"},"subjects":{"add":"1"},"ttables":{"teachers":"1","add":"1","fReport":"1"},"pick_points":{"add":"1"},"routes":{"all":"1"},"vehicles":{"all":"1"},"vehicle_routes":{"add":"1"},"drivers":{"managed":"1"},"passengers":{"add":"1","report":"1","editStudentPassenger":"1"},"books":{"all":"1","lset":"1","catalog":"1"},"book_details":{"madd":"1"},"library_members":{"add":"1"},"ivendors":{"add":"1"},"item_orders":{"all":"1"},"item_categories":{"add":"1"},"items":{"add":"1"},"item_sets":{"add":"1"},"buildings":{"add":"1"},"room_masters":{"index":"1"},"bed_allotments":{"index":"1","edit":"1","shifting":"1","hostelers":"1","roomAvailability":"1"}}',
                "sms_setting"=>null,
                "faculty_type"=>null,
                "school_id"=>1,
                "status"=>1
            ],
            [
                "role_name"=>"student", 
                "capabilities"=>null,
                "sms_setting"=>null,
                "faculty_type"=>null,
                "school_id"=>1,
                "status"=>1
            ],
            [
                "role_name"=>"school", 
                "capabilities"=>null,
                "sms_setting"=>null,
                "faculty_type"=>null,
                "school_id"=>1,
                "status"=>1
            ]

        ];
        return $rows;
    }

    function superRecord(){
        $rows = [
            [
                "meta_key"=>"_admin_modules",
                "meta_value"=>'{"module":{"0":"2","1":"3","2":"4","3":"5","4":"6","5":"12","6":"7","7":"8","8":"9","9":"10","10":"11","14":"14","15":"0","11":"branch","13":"0","16":"0","17":"17","18":"18","20":"20"}}',
                "date"=>"2017-04-11"
            ],
            [
                "meta_key"=>"student_app_id",//"student_app_id"
                "meta_value"=>5000,
                "date"=>"2017-04-11"
            ],
            [
                "meta_key"=>"staff_app_id",
                "meta_value"=>1001,
                "date"=>"2017-04-11"
            ]
        ];
        return $rows;
    }

    function userRecord(){
        $rows = [
            ["id"=>1,"name"=>"Super Admin","username"=>"sadmin","password"=>"Dinesh@123","role"=>"superadmin","school_id"=>1,"start_date"=>null,"end_date"=>null,"created"=>null,"modified"=>null,"status"=>1,"last_visit_date"=>null,"device_token"=>null],
            ["id"=>2,"name"=>"Admin","username"=>"admin","password"=>"School@1234","role"=>"admin","school_id"=>1,"start_date"=>null,"end_date"=>null,"created"=>null,"modified"=>null,"status"=>1,"last_visit_date"=>null,"device_token"=>null],
            // ["id"=>3,"name"=>"Spuer Admin","username"=>"2","password"=>"sharda44","role"=>"admin","school_id"=>1,"start_date"=>null,"end_date"=>null,"created"=>null,"modified"=>null,"status"=>1,"last_visit_date"=>null,"device_token"=>null],
            // ["id"=>4,"name"=>"Admin","username"=>"1","password"=>"1234","role"=>"admin","school_id"=>1,"start_date"=>null,"end_date"=>null,"created"=>null,"modified"=>null,"status"=>1,"last_visit_date"=>null,"device_token"=>null],
        ];
        return $rows;
    }

    function formatsRecord(){
        // id,name,value,school_id,session_id
        $rows = [
            ["name"=>"Student Registration","value"=>""],
            ["name"=>"Employee Registration","value"=>""],
            ["name"=>"Faculty Registration","value"=>""],
            ["name"=>"library_prfix","value"=>""],
            ["name"=>"Management Registration","value"=>""]
        ];
        return $rows;
    }

    function headRecord(){
        $rows = [
            ["name"=>"Tuition Fees"],
            ["name"=>"Admission Fees"],
            ["name"=>"Exam Fees"],
            ["name"=>"Annual Fees"],
        ];
        return $rows;
    }

    function oauthKey(){
        $oauth_clients = [
            [
                "id" => 2, 
                "user_id" => null, 
                "name" => "Laravel Personal Access Client", 
                "secret" => "UIQOgrT0BwvvqHiUkuQnoHrvmVXCcWeI2mfdviTO", 
                "provider" => null, 
                "redirect" => "http://localhost", 
                "personal_access_client" => 1, 
                "password_client" => 0, 
                "revoked" => 0, 
                "created_at" => "2022-03-15 14:25:23", 
                "updated_at" => "2022-03-15 14:25:23" 
            ], 
            [
                "id" => 3, 
                "user_id" => null, 
                "name" => "Laravel Password Grant Client", 
                "secret" => "5RodZdy8xBduByvIdygFOyhlK93I9AqhdrhLVMQB", 
                "provider" => "users", 
                "redirect" => "http://localhost", 
                "personal_access_client" => 0, 
                "password_client" => 1, 
                "revoked" => 0, 
                "created_at" => "2022-03-15 14:25:27", 
                "updated_at" => "2022-03-15 14:25:27" 
            ], 
            [
                "id" => 4, 
                "user_id" => null, 
                "name" => "Laravel Personal Access Client", 
                "secret" => "YqszP0vZYx4DMmR8b8eFnd08DcWJOXkYOHyPJgYN", 
                "provider" => null, 
                "redirect" => "http://localhost", 
                "personal_access_client" => 1, 
                "password_client" => 0, 
                "revoked" => 0, 
                "created_at" => "2022-03-16 08:08:41", 
                "updated_at" => "2022-03-16 08:08:41" 
            ], 
            [
                "id" => 5, 
                "user_id" => null, 
                "name" => "Laravel Password Grant Client", 
                "secret" => "H6noAtWpC3u1w45vDe99NLs4evKBSFFKkq8ETJkO", 
                "provider" => "users", 
                "redirect" => "http://localhost", 
                "personal_access_client" => 0, 
                "password_client" => 1, 
                "revoked" => 0, 
                "created_at" => "2022-03-16 08:08:45", 
                "updated_at" => "2022-03-16 08:08:45" 
            ] 
        ]; 
       
        $oauth_personal_access_clients = [
                [
                    "id" => 1, 
                    "client_id" => 2, 
                    "created_at" => "2022-03-15 14:25:26", 
                    "updated_at" => "2022-03-15 14:25:26" 
                ], 
                [
                    "id" => 2, 
                    "client_id" => 4, 
                    "created_at" => "2022-03-16 08:08:44", 
                    "updated_at" => "2022-03-16 08:08:44" 
                ] 
            ]; 
    
        return ["oauth_clients"=>$oauth_clients,"oauth_personal_access_clients"=>$oauth_personal_access_clients];
    }
/*
    function schoolStatus(){
        return $status = array('Jr. Secondary'=>'Jr. Secondary','Sr. Secondary'=>'Sr. Secondary','Play Way'=>'Play Way','Nursery'=>'Nursery');
    }
    
    function schoolCategory(){
        return $category = array('Indipendent'=>'Indipendent','Government'=>'Government','Trusty'=>'Trusty');
    }

    function adminAllowedRole(){
        $role = ["superadmin"];//["admin","superadmin"];
        return $role;
    }

    function studentStatus(){
        return $status = array('Active'=>'Active','Deactive'=>'Deactive');
    }
    function examStatus(){
        return $status = array('Pass'=>'Pass','Fail'=>'Fail');
    }
    */
?>