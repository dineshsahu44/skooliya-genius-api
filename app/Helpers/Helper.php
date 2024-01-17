<?php
use Illuminate\Support\Facades\Auth; 
use Illuminate\Support\Facades\File; 
use App\Models\SchoolSession;
use App\Models\Registration;
use App\Models\Faculty;
use App\Models\User;
use App\Models\Classes;
use App\Models\Section;
use App\Models\HwMessage;
use App\Models\HwMessageFor;
use Illuminate\Support\Facades\DB;
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

    function currentSession($school_id=null){
        $school_id = $school_id==null?getAuth()->school_id:$school_id;
        return SchoolSession::where([['school_id',$school_id],['status','Active']])->first();
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
                "max-size"=>5000//kb
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
        
        try{
            //everyone=1 means all, 2 means tokenlistgiven, 3 means teacher only
       
            //session_write_close(); //close the session
            // ignore_user_abort(true); //Prevent echo, print, and flush from killing the script
            // fastcgi_finish_request(); //this returns 200 to the user, and processing continues
            if(function_exists('litespeed_finish_request')){
                litespeed_finish_request();//use this for litespeed php this returns 200 to the user, and processing continues
            }

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
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    function handle_notification_sms($data,$currentSession){
        try{
            foreach($data['jsondata'] as $r){
                $msg = [
                    'msgtype'=>$r['msgtype'],
                    'msgheading'=>$r['msgheading'],
                    'msgbody'=>$r['SmsContent'],
                    // 'attachment'=>$attachment,
                    'postedbyid'=>$data['postedbyid'],
                    'postedby'=>$data['postedby'],
                    'companyid'=>$currentSession->id,
                    'commentstatus'=>$data['commentstatus'],
                    'entrydate'=>now(),
                ];
                $HwMessage = HwMessage::create($msg);
                // dd($HwMessage);
                if($HwMessage->id){
                    $messagefor = [
                        'studentid'=>$r['AccountID'],
                        'msgid'=>$HwMessage->id
                    ];
                    HwMessageFor::create($messagefor);
                }
                // dd([!empty($r['notificationtoken'])&&($r['FcmFlag']==1),$r]);
                
                if(!empty($r['notificationtoken'])&&($r['FcmFlag']==1)){
                    $record[] = [
                        "username"=>$r['AccountID'],
                        "token"=>$r['notificationtoken'],
                    ];
                    // send_notification($body,$title,$notificationtype,$record)
                    send_notification($r['SmsContent'],$r['msgheading'],"Notice",$record);
                    $fcmStatus = 1;
                }else{
                    $fcmStatus = 0;
                }
                
                // $url = '';
                // if($r['SmsFlag']==1){
                //     if (filter_var(smspart1, FILTER_VALIDATE_URL)&&(smspart2!=""&&smspart2!=null)&&(smspart3!=""&&smspart3!=null)) {
                //         if(strlen($r['ContactNo'])==10&&is_numeric($r['ContactNo'])&&$r['Result']=="Sent"){
                //                     // var url = smspart1+contactno+smspart2+smscontent+smspart3+DLT_TE_ID;
                //             $msg = urlencode($r['SmsContent']);
                //             $url = smspart1.$r['ContactNo'].smspart2.$msg.smspart3.$r['Dlt_Tem_Id'];
                //             $result = call_send_sms($url); 
                //             $smsStatus = 1;
                //         }else{
                //             $result = $r['Result'];
                //             $smsStatus = 0;
                //         }
                //     }else{
                //         $result = $r['Result'];
                //         $smsStatus = 0;
                //     }
                // }else{
                //     $result = $r['Result'];
                //     $smsStatus = 0;
                // }
                
                // $sql = "INSERT INTO `smslog`(`Name`, `AccountID`, `SendFor`, `EntryDate`, `Time`, `ContactNo`, `SmsContent`, `Result`, `SendBy`, `SendByAccountID`,`PageFrom`, `SmsStatus`,`FcmStatus`, `SmsUrl`) VALUES ('".$jsondata['Name']."','".$jsondata['AccountID']."','".$jsondata['SendFor']."','".$jsondata['EntryDate']."','".$jsondata['Time']."','".$jsondata['ContactNo']."','".urlencode($jsondata['SmsContent'])."','".$result."','".$data['postedby']."','".$data['postedbyid']."','".$data['pagefrom']."','".$smsStatus."','".$fcmStatus."','".$url."')";
        
                // $stmt = $conn->prepare($sql);
                // $stmt->execute();
                // $stmt->close();
            }
        }catch(\Exception $e){
            return exceptionResponse($e);
            // Log::info('Machine Attendance school->'.$request->servername, ['for'=>'attendance insertion done send to fcm notification.','data'=>$data1]);
        }
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

    function mainScreenToAll($accounttype){
        $mainscreen = [
            "student"=>[
                [
                    "optionname"=> "Leave Request",
                    "iconurl"=> "https://icons.veryicon.com/png/o/business/personnel-icon/leave-request-2.png",
                    "color"=> "#9b50a0",
                    "redirecturl"=> "https://web.skooliya.com/Leaves/applyleave?role=student",
                    "accounttype"=> "student",
                    "activityname"=> "Leave Request",
                ],
                [
                    "optionname"=> "Parent's Leave",
                    "iconurl"=> "https://api.skooliya.com/images/leave-request.png",
                    "color"=> "#4285f4",
                    "redirecturl"=> "https://web.skooliya.com/Leaves/applyleave?role=guardian",
                    "accounttype"=> "student",
                    "activityname"=> "Parent's Leave Request",
                ],
                [
                    "optionname"=> "Parent's Attendance",
                    "iconurl"=> "https://api.skooliya.com/images/attendance-icon.png",
                    "color"=> "#34a853",
                    "redirecturl"=> "https://api.skooliya.com/api/parentsattendance?role=guardian",
                    "accounttype"=> "student",
                    "activityname"=> "Parent's Attend",
                ]
            ],
            "teacher"=>[
                [
                    "optionname"=> "Leave Request",
                    "iconurl"=> "https://icons.veryicon.com/png/o/business/personnel-icon/leave-request-2.png",
                    "color"=> "#9b50a0",
                    "redirecturl"=> "https://web.skooliya.com/Leaves/applyleave?role=staff&apptype=faculty",
                    "accounttype"=> "teacher",
                    "activityname"=> "Leave Request",
                ],
                [
                    "optionname"=> "Fee Day Book",
                    "iconurl"=> "https://api.skooliya.com/images/Signing-A-Document.png",
                    "color"=> "#6dc9ff",
                    "redirecturl"=> "https://api.skooliya.com/api/fee-day-book?role=staff&apptype=faculty",
                    "accounttype"=> "teacher",
                    "activityname"=>  "Fee Day Book",
                ],
                [
                    "optionname"=> "Fees Details",
                    "iconurl"=> "https://api.skooliya.com/images/document-sign.png",
                    "color"=> "#ffabab",
                    "redirecturl"=> "https://api.skooliya.com/api/fees-details?role=staff&apptype=faculty",
                    "accounttype"=> "teacher",
                    "activityname"=>  "Fees Details",
                ],
                [
                    "optionname"=> "Marks Entry",
                    "iconurl"=> "https://api.skooliya.com/images/Marks-Entry.png",
                    "color"=> "#ff9b00",
                    "redirecturl"=> "https://api.skooliya.com/api/marks-entry?apptype=faculty",
                    "accounttype"=> "teacher",
                    "activityname"=>  "Marks Entry",
                ],
            ],
            "admin"=>[
                [
                    "optionname"=> "Leave Request",
                    "iconurl"=> "https://icons.veryicon.com/png/o/business/personnel-icon/leave-request-2.png",
                    "color"=> "#9b50a0",
                    "redirecturl"=> "https://web.skooliya.com/Leaves/applyleave?role=staff&apptype=faculty",
                    "accounttype"=> "admin",
                    "activityname"=> "Leave Request",
                ],
                [
                    "optionname"=> "Approve Leave",
                    "iconurl"=> "https://icons.veryicon.com/png/o/business/personnel-icon/leave-request-2.png",
                    "color"=> "#00a65a",
                    "redirecturl"=> "https://web.skooliya.com/Leaves/approveleave?role=staff&apptype=faculty",
                    "accounttype"=> "admin",
                    "activityname"=> "Approve Leave",
                ],
                [
                    "optionname"=> "Fee Day Book",
                    "iconurl"=> "https://api.skooliya.com/images/Signing-A-Document.png",
                    "color"=> "#6dc9ff",
                    "redirecturl"=> "https://api.skooliya.com/api/fee-day-book?role=staff&apptype=faculty",
                    "accounttype"=> "admin",
                    "activityname"=>  "Fee Day Book",
                ],
                [
                    "optionname"=> "Fees Details",
                    "iconurl"=> "https://api.skooliya.com/images/document-sign.png",
                    "color"=> "#ffabab",
                    "redirecturl"=> "https://api.skooliya.com/api/fees-details?role=staff&apptype=faculty",
                    "accounttype"=> "admin",
                    "activityname"=>  "Fees Details",
                ],
                [
                    "optionname"=> "Staff Attendance",
                    "iconurl"=> "https://api.skooliya.com/images/staff-attendance.png",
                    "color"=> "#3c8dbc",
                    "redirecturl"=> "https://api.skooliya.com/api/faculty-attendace?apptype=faculty",
                    "accounttype"=> "admin",
                    "activityname"=>  "Staff Attendance",
                ],
                [
                    "optionname"=> "Marks Entry",
                    "iconurl"=> "https://api.skooliya.com/images/Marks-Entry.png",
                    "color"=> "#ff9b00",
                    "redirecturl"=> "https://api.skooliya.com/api/marks-entry?apptype=faculty",
                    "accounttype"=> "admin",
                    "activityname"=>  "Marks Entry",
                ],
            ]
        ];
        return @$mainscreen[$accounttype];
    }

    
    function getIndianCurrency(float $number)
    {
        $decimal = round($number - ($no = floor($number)), 2) * 100;
        $hundred = null;
        $digits_length = strlen($no);
        $i = 0;
        $str = array();
        $words = array(0 => '', 1 => 'one', 2 => 'two',
            3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six',
            7 => 'seven', 8 => 'eight', 9 => 'nine',
            10 => 'ten', 11 => 'eleven', 12 => 'twelve',
            13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen',
            16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen',
            19 => 'nineteen', 20 => 'twenty', 30 => 'thirty',
            40 => 'forty', 50 => 'fifty', 60 => 'sixty',
            70 => 'seventy', 80 => 'eighty', 90 => 'ninety');
        $digits = array('', 'hundred','thousand','lakh', 'crore');
        while( $i < $digits_length ) {
            $divider = ($i == 2) ? 10 : 100;
            $number = floor($no % $divider);
            $no = floor($no / $divider);
            $i += $divider == 10 ? 1 : 2;
            if ($number) {
                $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
                $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
                $str [] = ($number < 21) ? $words[$number].' '. $digits[$counter]. $plural.' '.$hundred:$words[floor($number / 10) * 10].' '.$words[$number % 10]. ' '.$digits[$counter].$plural.' '.$hundred;
            } else $str[] = null;
        }
        $Rupees = implode('', array_reverse($str));
        $paise = ($decimal > 0) ? "." . ($words[$decimal / 10] . " " . $words[$decimal % 10]) . ' Paise' : '';
        return ($Rupees ? $Rupees . 'Rupees ' : '') . $paise;
    }

    function selectedSessionClasses($school_id){
        $classes = Classes::where('school_id', $school_id)->pluck('class', 'id')->toArray();
        return $classes;
    }

    function selectedSessionSection($school_id, $onlySection = null){
        $sections = Section::select(DB::raw('CONCAT(\'[\', GROUP_CONCAT(JSON_OBJECT(\'id\', id, \'section\', section)), \']\') AS section_list, class_id'))
            ->where('school_id', $school_id)
            ->groupBy('class_id')->get();
    
        $list_section = [];
        
        foreach ($sections as $s) {
            $sectionData = json_decode($s->section_list, true);
    
            if ($onlySection != null && $onlySection == 'onlysection') {
                foreach ($sectionData as $sec) {
                    $list_section[] = $sec['section'];
                }
            } else {
                $list_section[$s->class_id] = $sectionData;
            }
        }
    
        if ($onlySection != null && $onlySection == 'onlysection') {
            $list_section = array_unique($list_section);
        }
    
        return $list_section;
    }

    function getMapedSubject($session_id){
        $query = "SELECT 
                CONCAT('{\"',
                    subg.sec_id,'\":',
                    CONCAT(
                        '{\"ClassSection\":', JSON_OBJECT('Class', JSON_OBJECT('Name', subg.class, 'ID', subg.class_id), 'Section', JSON_OBJECT('Name', subg.section, 'ID', subg.sec_id)),
                        ',\"GroupDetails\":[', GROUP_CONCAT(subg.result), ']}'
                    ),
                    '}'
                ) AS finalResult
            FROM 
                (
                    SELECT 
                        CONCAT('{',
                    '\"SubjectGroup\":', JSON_OBJECT('GroupName', sg.subject_group, 'GroupID', sg.id),
                    ',\"Subjects\":[', GROUP_CONCAT( 
                        CONCAT(
                            '{','\"SubjectID\":', s.id,',', '\"SubjectName\":\"', s.subject_name,'\"'),
                        '}'
                        )
                    ,']}'
                ) AS result,
                        sg.id AS sgid, sg.subject_group,
                        c.id AS class_id, c.class,
                        sec.id AS sec_id, sec.section 
                    FROM 
                        subject_plans sp 
                        INNER JOIN subjects s ON s.id=sp.SubjectID 
                        INNER JOIN subject_groups sg ON sg.id=sp.SubjectGroupID 
                        INNER JOIN classes c ON c.id=sp.ClassID
                        INNER JOIN sections sec ON sec.id=sp.SectionID
                    WHERE 
                        SessionID=$session_id
                    GROUP BY 
                        sp.SectionID, sp.SubjectGroupID
                ) AS subg 
            GROUP BY 
                subg.sec_id
        ";
        $result = DB::select($query);
        $subject = [];
        foreach($result as $r){
            foreach(json_decode($r->finalResult,true) as $k=>$s){
                $subject[$k]=$s;
            }   
        }
        return $subject;
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