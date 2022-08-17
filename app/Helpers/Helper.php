<?php
use Illuminate\Support\Facades\Auth; 
use Illuminate\Support\Facades\File; 
use App\Models\SchoolSession;
use App\Models\Registration;
use App\Models\Faculty;
use App\Models\User;
use Carbon\Carbon;

    function customResponse($type, $data=null){
        $success = $type==1?1:0;
        $data['success']=$success;
        return $data;
    }

    function exceptionResponse($e,$data=null){
        $data['error'] = ["msg"=>@$e->getMessage(),"line"=>@$e->getLine()];
        $data['success']=0;
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
        $attachment = Storage::disk('my-disk')->putFileAs($path, $file, $fileName);
        return env('APP_URL').'/'.$attachment;
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
                "max-size"=>200//kb
            ],
            "banner-files"=>[
                "extension-type"=>[
                    'png','jpeg','jpg','gif'
                ],
                "path"=>"app/onlineclass/banner",
                "max-size"=>200//kb
            ],
            "online-class-files"=>[
                "extension-type"=>[
                    'png','jpeg','jpg','gif','pdf','xlsx','docs'
                ],
                "path"=>"app/onlineclass/docs",
                "max-size"=>200//kb
            ],
            "album-files"=>[
                "extension-type"=>[
                    'png','jpeg','jpg','gif'
                ],
                "path"=>"app/album",
                "max-size"=>200//kb
            ],
            "gallery-files"=>[
                "extension-type"=>[
                    'png','jpeg','jpg','gif'
                ],
                "path"=>"app/album/gallery",
                "max-size"=>200//kb
            ],
            "event-files"=>[
                "extension-type"=>[
                    'png','jpeg','jpg','gif'
                ],
                "path"=>"app/event",
                "max-size"=>200//kb
            ],
            "feedback-files"=>[
                "extension-type"=>[
                    'png','jpeg','jpg','gif'
                ],
                "path"=>"app/event",
                "max-size"=>200//kb
            ],
            "faculty-photo-files"=>[
                "extension-type"=>[
                    'png','jpeg','jpg','gif'
                ],
                "path"=>"app/profile",
                "max-size"=>200//kb
            ],
        ];
        return $acceptfor!=null?$accept[$acceptfor]:$accept;
    }

    function validatorMessage($validator){
        $responseArr['message'] = $validator->messages();
        $responseArr['success'] = 0;
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
?>