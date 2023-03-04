<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Config;
use DB;
class ImportDatabaseController extends Controller
{
    // public function checkDatabase(Request $request){
    //     $this->setSchoolDatabase();
    //     return 1;
    // }

    public function importCompanyToSchoolAndSchoolSessions(Request $request){
        try{
            $this->setConnection($request->from_database);
            $company = DB::table('company')->select('id','companyname','city','mobile','email','session','session_start','session_end')->orderBy('id')->paginate(10);
            DB::disconnect();
            $schools = [];
            $school_sessions = [];
            foreach($company->items() as $key=>$record){
                if($key==0){
                    $schools[] = [
                        'id'=>1,
                        'name'=>$record->city,
                        'school'=>$record->companyname,
                        'mobile'=>$record->mobile,
                        'email'=>$record->email,
                    ];
                }
                $school_sessions[] = [
                    'id'=>$record->id,
                    'name'=>$record->session,
                    'start_date'=>$record->session_start,
                    'end_date'=>$record->session_end,
                    'school_id'=>1,
                    'session_type'=>1
                ];
            };

            $this->setConnection($request->to_database);
            DB::table('schools')->insert($schools);
            DB::table('school_sessions')->insert($school_sessions);
            DB::disconnect();
            
            return ['data'=> $company,'status'=> true,"msg"=>"done"];
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importAdmissionToClassesAndSections(Request $request){
        try{
            $this->setConnection($request->from_database);
            $admission = DB::table('admission')->selectRaw('class, GROUP_CONCAT(DISTINCT (section)) AS section')->groupBy('class')->orderBy('class','ASC')->orderBy('section','ASC')->paginate(10000);
            DB::disconnect();
            $this->setConnection($request->to_database);
            foreach($admission->items() as $key=>$record){
                $class_id = DB::table('classes')->insertGetId([
                    'school_id' => 1,
                    'class' => $record->class,
                ]);
                foreach(explode(",",$record->section) as $s){
                    DB::table('sections')->insertGetId([
                        'class_id' => $class_id,
                        'school_id' => 1,
                        'section' => $s,
                    ]);
                }
            };
            DB::disconnect();
            return ['data'=> $admission,'status'=> true,"msg"=>"done"];
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importTeachersToFacultiesAndUsers(Request $request){
        try{
            $this->setConnection($request->from_database);
            // DB::enableQueryLog();
            $teachers = DB::table('teachers')->selectRaw('teachers.*')
            ->join(DB::Raw("(SELECT accountid, MAX(companyid) AS companyid1 FROM teachers GROUP BY accountid) `groupedtt`"),function ($join) {
                $join->on('teachers.accountid', '=', 'groupedtt.accountid')->on('teachers.companyid', '=', 'groupedtt.companyid1');
            })->orderBy('teachers.accountid')->paginate(1000);
            // $teachers = DB::table('teachers')->selectRaw('max(companyid) as max,teachers.*')
            // ->whereNotIn('accountid',[1,2,3])
            // ->orderBy('companyid')->orderBy('accountid')
            // ->groupBy('accountid')
            
            // ->paginate(1000);
            // return $teachers;
            DB::disconnect();
            $this->setConnection($request->to_database);
            if($teachers->currentPage()==1){
                $collection = collect($teachers->items());
                $unique = $collection->unique('accounttype');
                foreach($unique->values()->pluck('accounttype') as $accounttype){
                    $accounttype = $accounttype==''||$accounttype==null?'teacher':$accounttype;
                    $roles = DB::table('roles')->select('role_name')->where('role_name',$accounttype)->get();
                    if($roles->isEmpty()){
                        DB::table('roles')->insert(['role_name' => $accounttype,'school_id'=>1,'status'=>1]);
                    }
                }
            }
            $roles = DB::table('roles')->select('role_name','id')->get()->keyBy('role_name');
            $faculty = [];
            $user = [];
            foreach($teachers->items() as $key=>$record){
                $accounttype = $record->accounttype==''||$record->accounttype==null?'teacher':$record->accounttype;
                $faculty[] = [
                    "school_id"=>1,
                    "faculty_id"=>$record->accountid,
                    "role_id"=>$roles[$accounttype]->id,
                    "name"=>$record->accountname,
                    "photo"=>$record->photo,
                    "status"=>$record->status==1?"Active":"Inactive",//Active
                    "address"=>$record->address1,
                    "city"=>$record->city,
                    "state"=>$record->state,
                    "phone"=>$record->mobile,
                    "punch_id"=>(string)($record->rfid),
                    "dob"=>$record->birthday,
                    "gender"=>$record->gender,
                    "marital_status"=>$record->marital_status,
                    "assignclass"=>$record->assignclass,
                    "noticepermission"=>$record->noticepermission,
                    "gallerypermission"=>$record->gallerypermission,
                    "eventspermission"=>$record->eventspermission,
                    "homeworkpermission"=>$record->homeworkpermission,
                    "onlineclasspermission"=>$record->onlineclasspermission,
                    "quizpermission"=>$record->quizpermission,
                    "smspermission"=>$record->smspermission,
                    "contactnopermission"=>$record->contactnopermission,
                    "oprate_date"=>now(), 
                    "created_at"=>now(),
                    "created_at"=>now(),
                ];
                $user[] = [
                    "name"=>$record->accountname,
                    "username"=>$record->accountid,
                    "password"=>$record->password,
                    "school_id"=>1,
                    "role"=>$accounttype,
                    "status"=>$record->status,
                    "created"=>now(),
                    "modified"=>now(),
                ];
                
            };
            array_walk_recursive($faculty, function(&$value) { $value = $value === "" ? NULL : $value; });
            array_walk_recursive($user, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('faculties')->insert($faculty);
            DB::table('users')->insert($user);
            // dd([$faculty,$user]);
            DB::disconnect();
            return ['data'=> $teachers,'status'=> true,"msg"=>"done"];
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importAdmissionToRegistrationsAndGuardians(Request $request){
        try{
            // admission	registrations	guardians	users
            $this->setConnection($request->from_database);
            $admission = DB::table('admission')->orderBy('companyid','ASC')->orderBy('studentid','ASC')->paginate(2000);
            
            DB::disconnect();
            $this->setConnection($request->to_database);//JSON_OBJECT//CONCAT('{\"class:\"',classes.class,',\"id:\"',classes.id,'}')
            $class_section = DB::table('sections')->selectRaw("JSON_OBJECT('class',classes.class,'id',classes.id) classes,concat('[',GROUP_CONCAT(JSON_OBJECT('section',sections.section,'id',sections.id)),']') sections")
            ->join('classes','sections.class_id','classes.id')
            ->where('sections.school_id',1)
            ->groupBy('class')->get();
            
            $classes = [];
            $sections = [];
            foreach($class_section as $key=>$record){
                $c = json_decode($record->classes);
                $s = json_decode($record->sections);
                foreach($s as $k=>$v){
                    $classes[$c->class]=$c->id;
                    $sections[$c->class][$v->section]=$v->id;
                }
            }
            // dd([$classes,$sections]);
            $students = [];
            $parents = [];
            foreach($admission->items() as $key=>$record){
                $students[] = [
                    'school_id'=>1,
                    'session'=>$record->companyid,
                    'registration_id'=>$record->studentid,
                    'roll_no'=>$record->rollno,//": 0,
                    'scholar_id'=>$record->admissionno,
                    'name'=>$record->name,
                    'gender'=>$record->gender,
                    'dob'=>$record->dob,//": "2021-02-28",
                    'class'=>$classes[$record->class],//": "L.K.G",//1
                    'section'=>$sections[$record->class][$record->section],//": "A",//12
                    'group_id'=>$record->category,//": "Old",//1
                    'address'=>$record->address1,//": "New city bajrang nagar, Mahoba",
                    'city'=>$record->city,
                    'state'=>$record->state,
                    'mobile'=>$record->contactno,
                    'email'=>$record->email,
                    'r_date'=>!empty($record->admissiondate)?$record->admissiondate:null,//": "2021-02-28",
                    'photo'=>$record->photo,//": "https://www.skooliya.com/API/photos/pr13609265891597225133.jpg",
                    'aadhar_no'=>(string)$record->aadharno,
                    'card_no'=>(string)$record->rfid,//": "0012210954",
                    'status'=>$record->status,//": "Active",
                    'deactive_date'=>!empty($record->discon_date)?$record->discon_date:null,
                    'oprate_date'=>now(),
                    'transport' => 'Personal',
                    'photopermission'=> 1,
                    's_type' => 'migrate',
                    'sync_property_id' => (int) 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                // DB::enableQueryLog();

                $parents[] = [
                    're_id'=> DB::raw("(SELECT id FROM registrations WHERE registration_id = $record->studentid and session=$record->companyid)"),
					'registration_id' => $record->studentid,
					'f_name' => $record->fathername,
					'f_mobile' => $record->fathermobile,
					'm_name' => $record->mothername,
					'm_mobile' => $record->mothermobile,
					'sync_property_id' => 1,
                    'created_at'=>now(),
                    'updated_at'=>now()
				];
                
                // dd(\DB::getQueryLog());
            }
            array_walk_recursive($students, function(&$value) { $value = $value === "" ? NULL : $value; });
            array_walk_recursive($parents, function(&$value) { $value = $value === "" ? NULL : $value; });
            
            DB::table('registrations')->insert($students);
            DB::table('guardians')->insert($parents);
            return ['data'=> $admission,'status'=> true,"msg"=>"done"];
            
            // DB::disconnect();
            
            // $this->setConnection($request->to_database);
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importAdmissionToUniqueUsers(Request $request){
        try{
            // admission	registrations	guardians	users
            $this->setConnection($request->from_database);
            // $admission = DB::table('admission')->orderBy('companyid','ASC')->orderBy('studentid','ASC')->paginate(6000);
            $admission = DB::table('admission')->selectRaw('admission.*')
            ->join(DB::Raw("(SELECT studentid, MAX(companyid) AS companyid1 FROM admission GROUP BY studentid) `groupedtt`"),function ($join) {
                $join->on('admission.studentid', '=', 'groupedtt.studentid')->on('admission.companyid', '=', 'groupedtt.companyid1');
            })->paginate(6000);
            // dd($admission);
            DB::disconnect();
            $this->setConnection($request->to_database);
            // $roles = DB::table('roles')->where('role_name','student')->first();
            $users = [];
            foreach($admission->items() as $key=>$record){
                $users[] = [
                    'name' => $record->name,
                    'password' => $record->password,
                    'username'=> $record->studentid,
                    'school_id'=>1,
                    'role'=>'student',
                    'status'=>$record->status=="Active"?1:0,
                    'created'=>now(),
                    'modified'=>now(),
                ];
            }
            // dd($users);
            array_walk_recursive($users, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('users')->insert($users);
            return ['data'=> $admission,'status'=> true,"msg"=>"done"];
            
            // DB::disconnect();
            
            // $this->setConnection($request->to_database);
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importAttendanceToAttendances(Request $request){
        try{
            $this->setConnection($request->from_database);
            $attendances = DB::table('attendance')
            ->whereNotIn('classname',['teacher'])
            ->groupBy(['accountid','entrydate'])
            ->orderBy('id1')->paginate(4000);
            DB::disconnect();
            $this->setConnection($request->to_database);//JSON_OBJECT//CONCAT('{\"class:\"',classes.class,',\"id:\"',classes.id,'}')
            $class_section = DB::table('sections')->selectRaw("JSON_OBJECT('class',classes.class,'id',classes.id) classes,concat('[',GROUP_CONCAT(JSON_OBJECT('section',sections.section,'id',sections.id)),']') sections")
            ->join('classes','sections.class_id','classes.id')
            ->where('sections.school_id',1)
            ->groupBy('class')->get();
            $classes = [];
            $sections = [];
            foreach($class_section as $key=>$record){
                $c = json_decode($record->classes);
                $s = json_decode($record->sections);
                foreach($s as $k=>$v){
                    $classes[$c->class]=$c->id;
                    $sections[$c->class][$v->section]=$v->id;
                }
            }
            $att_record = [];
            foreach($attendances->items() as $key=>$record){
                // dd(date('Y-m-d H:i:s', strtotime("$record->entrydate $record->time")));
                $att_record[] = [
                    'id' =>$record->id1,
                    're_id' => DB::raw("(SELECT id FROM registrations WHERE registration_id = $record->accountid and session=$record->companyid)"),
                    'class_id'=> @$classes[$record->classname],//": "L.K.G",//1
                    'section_id'=> @$sections[$record->classname][$record->sectionname],//": "A",//12
                    'school_id' =>1,
                    'session_id' => $record->companyid,
                    'att_date' => $record->entrydate,
                    'att_time' => $record->time,
                    'att_status' => $record->attendancevalue,
                    'remark' => $record->remarks,
                    'rfid' => @$record->rfid,
                    'oprator' =>1,
                    'oprate_date' => date('Y-m-d H:i:s', strtotime("$record->entrydate $record->time")),
                    'rfid_flag'=> $record->attendanceid,
                ];
            }
            array_walk_recursive($att_record, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('attendances')->insert($att_record);
            // return($att_record);
            return ['data'=> $attendances,'status'=> true,"msg"=>"done"];
            
            // DB::disconnect();
            
            // $this->setConnection($request->to_database);
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importAlbums(Request $request){
        try{
            $this->setConnection($request->from_database);
            $albums_f = DB::table('albums')->orderBy('albumid')->paginate(6000);
            DB::disconnect();
            $this->setConnection($request->to_database);
            $albums_t = [];
            
            foreach($albums_f->items() as $key=>$record){
                // dd(date('Y-m-d H:i:s', strtotime("$record->entrydate $record->time")));
                $albums_t[] = [
                    'albumid'=>$record->albumid,
                    'companyid'=>$record->companyid,
                    'imageurl'=>$record->imageurl,
                    'heading'=>urldecode($record->heading),
                    'postedby'=>$record->postedby,
                    'dateposted'=>$record->dateposted,
                    'creatorid'=>$record->creatorid,
                ];
            }
            array_walk_recursive($albums_t, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('albums')->insert($albums_t);
            // return($att_record);
            return ['data'=> $albums_f,'status'=> true,"msg"=>"done"];
            
            // DB::disconnect();
            
            // $this->setConnection($request->to_database);
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importBirthdayCard(Request $request){
        try{
            $this->setConnection($request->from_database);
            $birthdaycard_f = DB::table('birthdaycard')->orderBy('id')->paginate(50);
            DB::disconnect();
            $this->setConnection($request->to_database);
            $birthdaycard_t = [];
            
            foreach($birthdaycard_f->items() as $key=>$record){
                $birthdaycard_t[] = [
                    'id'=>$record->id,
                    'apptype'=>$record->apptype,
                    'cardimageurl'=>$record->cardimageurl,
                    'status'=>$record->status,
                    'btntext'=>$record->btntext,
                    'type'=>$record->type,
                    'url'=>$record->url,
                    'enabled'=>$request->enabled,
                ];
            }
            array_walk_recursive($birthdaycard_t, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('birthdaycard')->insert($birthdaycard_t);
            // return($att_record);
            return ['data'=> $birthdaycard_f,'status'=> true,"msg"=>"done"];
            
            // DB::disconnect();
            
            // $this->setConnection($request->to_database);
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importComment(Request $request){
        try{
            $this->setConnection($request->from_database);
            $comment_f = DB::table('comment')->orderBy('comment_id')->paginate(6000);
            DB::disconnect();
            $this->setConnection($request->to_database);
            $comment_t = [];
            
            foreach($comment_f->items() as $key=>$record){
                $comment_t[] = [
                    'comment_id'=>$record->comment_id,
                    'comment'=>urldecode($record->comment),
                    'attachment'=>$record->attachment,
                    'accountid'=>$record->accountid,
                    'accountname'=>$record->accountname,
                    'accounttype'=>$record->accounttype,
                    'apptype'=>$record->apptype,
                    'activityname'=>$request->activityname,
                    'activityid'=>$request->activityid,
                    'time'=>$request->time
                ];
            }
            array_walk_recursive($comment_t, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('comment')->insert($comment_t);
            // return($att_record);
            return ['data'=> $comment_f,'status'=> true,"msg"=>"done"];
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importEvents(Request $request){
        try{
            $this->setConnection($request->from_database);
            $events_f = DB::table('events')->orderBy('eventid')->paginate(6000);
            DB::disconnect();
            $this->setConnection($request->to_database);
            $events_t = [];
            
            foreach($events_f->items() as $key=>$record){
                $events_t[] = [
                    'eventid'=>$record->eventid,
                    'companyid'=>$record->companyid,
                    'imageurl'=>$record->imageurl,
                    'heading'=>$record->heading,
                    'description'=>$record->description,
                    'eventdate'=>$record->eventdate,
                    'creatorid'=>$record->creatorid,
                ];
            }
            array_walk_recursive($events_t, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('events')->insert($events_t);
            // return($att_record);
            return ['data'=> $events_f,'status'=> true,"msg"=>"done"];
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importFeedback(Request $request){
        try{
            $this->setConnection($request->from_database);
            $feedback_f = DB::table('feedback')->orderBy('feedbackid')->paginate(6000);
            DB::disconnect();
            $this->setConnection($request->to_database);
            $feedback_t = [];
            
            foreach($feedback_f->items() as $key=>$record){
                $feedback_t[] = [
                    'feedbackid'=>$record->feedbackid,
                    'companyid'=>$record->companyid,
                    'postedbyid'=>$record->postedbyid,
                    'postedby'=>$record->postedby,
                    'apptype'=>$record->apptype,
                    'subject'=>urldecode($record->subject),
                    'description'=>urldecode($record->description),
                    'attachment'=>$record->attachment,
                    'dateposted'=>$record->dateposted,
                ];
            }
            array_walk_recursive($feedback_t, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('feedback')->insert($feedback_t);
            // return($att_record);
            return ['data'=> $feedback_f,'status'=> true,"msg"=>"done"];
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importHoliday(Request $request){
        try{
            $this->setConnection($request->from_database);
            $holiday_f = DB::table('holiday')->orderBy('id')
            ->groupByRaw('CAST(entrydate AS DATE)')
            ->paginate(6000);
            DB::disconnect();
            $this->setConnection($request->to_database);
            $holiday_t = [];
            
            foreach($holiday_f->items() as $key=>$record){
                $holiday_t[] = [
                    'id'=>$record->id,
                    'name'=> urldecode($record->reason),
                    'h_date'=>$record->entrydate,
                    'status'=>'School holiday',
                    'school_id'=>1,
                    'detail'=>null,
                    'session_id'=>$record->companyid,
                ];
            }
            array_walk_recursive($holiday_t, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('holidays')->insert($holiday_t);
            // return($att_record);
            return ['data'=> $holiday_f,'status'=> true,"msg"=>"done"];
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importHwmessage(Request $request){
        try{
            $this->setConnection($request->from_database);
            $hwmessage_f = DB::table('hwmessage')->orderBy('msgid')->paginate(4000);
            DB::disconnect();
            $this->setConnection($request->to_database);
            $hwmessage_t = [];
            
            foreach($hwmessage_f->items() as $key=>$record){
                $hwmessage_t[] = [
                    'msgid'=>$record->msgid,
                    'msgtype'=>$record->msgtype,
                    'msgheading'=>urldecode($record->msgheading),
                    'msgbody'=>urldecode($record->msgbody),
                    'attachment'=>$record->attachment,
                    'companyid'=>$record->companyid,
                    'entrydate'=>$record->entrydate,
                    'status'=>$record->status,
                    'commentstatus'=>$record->commentstatus,
                    'postedbyid'=>$record->postedbyid,
                    'postedby'=>$record->postedby,
                ];
            }
            array_walk_recursive($hwmessage_t, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('hwmessage')->insert($hwmessage_t);
            // return($att_record);
            return ['data'=> $hwmessage_f,'status'=> true,"msg"=>"done"];
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importHwmessageFor(Request $request){
        try{
            $this->setConnection($request->from_database);
            $hwmessagefor_f = DB::table('hwmessagefor')->orderBy('id')->paginate(4000);
            DB::disconnect();
            $this->setConnection($request->to_database);
            $hwmessagefor_t = [];
            
            foreach($hwmessagefor_f->items() as $key=>$record){
                $hwmessagefor_t[] = [
                    'id'=>$record->id,
                    'studentid'=>$record->studentid,
                    'msgid'=>$record->msgid,
                ];
            }
            array_walk_recursive($hwmessagefor_t, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('hwmessagefor')->insert($hwmessagefor_t);
            // return($att_record);
            return ['data'=> $hwmessagefor_f,'status'=> true,"msg"=>"done"];
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importLiveBanner(Request $request){
        try{
            $this->setConnection($request->from_database);
            $live_banner_f = DB::table('live_banner')->orderBy('live_banner_id')->paginate(6000);
            DB::disconnect();
            $this->setConnection($request->to_database);
            $live_banner_t = [];
            
            foreach($live_banner_f->items() as $key=>$record){
                $live_banner_t[] = [
                    'live_banner_id'=>$record->live_banner_id,
                    'companyid'=>$record->companyid,
                    'live_candidate_id'=>$record->live_candidate_id,
                    'live_banner_title'=>$record->live_banner_title,
                    'live_banner_photo'=>$record->live_banner_photo,
                    'live_banner_text'=>$record->live_banner_text,
                    'live_banner_enquiryno'=>$record->live_banner_enquiryno,
                    'classesfor'=>$record->classesfor,
                    'live_banner_status'=>$record->live_banner_status,
                    'date_upto'=>$record->date_upto,
                    'live_banner_date'=>$record->live_banner_date,
                ];
            }
            array_walk_recursive($live_banner_t, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('live_banner')->insert($live_banner_t);
            // return($att_record);
            return ['data'=> $live_banner_f,'status'=> true,"msg"=>"done"];
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importLiveBannerFor(Request $request){
        try{
            $this->setConnection($request->from_database);
            $live_banner_f = DB::table('live_banner_for')->orderBy('live_banner_for_id')->paginate(6000);
            DB::disconnect();
            $this->setConnection($request->to_database);
            $live_banner_t = [];
            
            foreach($live_banner_f->items() as $key=>$record){
                $live_banner_t[] = [
                    'live_banner_for_id'=>$record->live_banner_for_id,
                    'live_banner_id'=>$record->live_banner_id,
                    'class'=>$record->class,
                    'section'=>$record->section,
                    'timestamp'=>$record->timestamp,
                ];
            }
            array_walk_recursive($live_banner_t, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('live_banner_for')->insert($live_banner_t);
            // return($att_record);
            return ['data'=> $live_banner_f,'status'=> true,"msg"=>"done"];
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importLiveClasses(Request $request){
        try{
            $this->setConnection($request->from_database);
            $f = DB::table('live_classes')->orderBy('live_classes_id')->paginate(6000);
            DB::disconnect();
            $this->setConnection($request->to_database);
            $t = [];
            
            foreach($f->items() as $key=>$record){
                $t[] = [
                    'live_classes_id'=>$record->live_classes_id,
                    'companyid'=>$record->companyid,
                    'accountid'=>$record->accountid,
                    'subject'=>urldecode($record->subject),
                    'accountname'=>$record->accountname,
                    'classesfor'=>$record->classesfor,
                    'status'=>$record->status,
                    'posteddate'=>$record->posteddate
                ];
            }
            array_walk_recursive($t, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('live_classes')->insert($t);
            // return($att_record);
            return ['data'=> $f,'status'=> true,"msg"=>"done"];
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importLiveClassesFor(Request $request){
        try{
            $this->setConnection($request->from_database);
            $f = DB::table('live_classes_for')->orderBy('live_classes_for_id')->paginate(6000);
            DB::disconnect();
            $this->setConnection($request->to_database);
            $t = [];
            
            foreach($f->items() as $key=>$record){
                $t[] = [
                    'live_classes_for_id'=>$record->live_classes_for_id,
                    'live_classes_id'=>$record->live_classes_id,
                    'class'=>$record->class,
                    'section'=>$record->section,
                    'timestamp'=>$record->timestamp,
                ];
            }
            array_walk_recursive($t, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('live_classes_for')->insert($t);
            // return($att_record);
            return ['data'=> $f,'status'=> true,"msg"=>"done"];
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importLiveDocs(Request $request){
        try{
            $this->setConnection($request->from_database);
            $f = DB::table('live_docs')->orderBy('live_docs_id')->paginate(6000);
            DB::disconnect();
            $this->setConnection($request->to_database);
            $t = [];
            
            foreach($f->items() as $key=>$record){
                $t[] = [
                    'live_docs_id'=>$record->live_docs_id,
                    'live_classes_id'=>$record->live_classes_id,
                    'type'=>$record->type,
                    'title'=>urldecode($record->title),
                    'attachment'=>$record->attachment,
                    'status'=>$record->status,
                    'commentstatus'=>$record->commentstatus,
                    'postdate'=>$record->postdate,
                ];
            }
            array_walk_recursive($t, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('live_docs')->insert($t);
            return ['data'=> $f,'status'=> true,"msg"=>"done"];
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importLiveExam(Request $request){
        try{
            $this->setConnection($request->from_database);
            $f = DB::table('live_exam')->orderBy('live_exam_id')->paginate(6000);
            DB::disconnect();
            $this->setConnection($request->to_database);
            $t = [];
            
            foreach($f->items() as $key=>$record){
                $t[] = [
                    'live_exam_id'=>$record->live_exam_id,
                    'companyid'=>$record->companyid,
                    'accountid'=>$record->accountid,
                    'accountname'=>$record->accountname,
                    'subject'=>urldecode($record->subject),
                    'classesfor'=>urldecode($record->classesfor),
                    'examlink'=>urldecode($record->examlink),
                    'starttime'=>$record->starttime,
                    'endtime'=>$record->endtime,
                    'attachment'=>$record->attachment,
                    'status'=>$record->status,
                    'posteddate'=>$record->posteddate,
                ];
            }
            array_walk_recursive($t, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('live_exam')->insert($t);
            return ['data'=> $f,'status'=> true,"msg"=>"done"];
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }
    
    public function importLiveExamFor(Request $request){
        try{
            $this->setConnection($request->from_database);
            $f = DB::table('live_exam_for')->orderBy('live_exam_for_id')->paginate(6000);
            DB::disconnect();
            $this->setConnection($request->to_database);
            $t = [];
            foreach($f->items() as $key=>$record){
                $t[] = [
                    'live_exam_for_id' => $record->live_exam_for_id,
                    'live_exam_id'=>$record->live_exam_id,
                    'class'=>$record->class,
                    'section'=>$record->section,
                    'timestamp'=>$record->timestamp
                ];
            }
            array_walk_recursive($t, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('live_exam_for')->insert($t);
            return ['data'=> $f,'status'=> true,"msg"=>"done"];
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importLiveSession(Request $request){
        try{
            $this->setConnection($request->from_database);
            $f = DB::table('live_session')->orderBy('live_session_id')->paginate(6000);
            DB::disconnect();
            $this->setConnection($request->to_database);
            $t = [];
            foreach($f->items() as $key=>$record){
                $t[] = [
                    'live_session_id'=>$record->live_session_id,
                    'companyid'=>$record->companyid,
                    'accountid'=>$record->accountid,
                    'accountname'=>$record->accountname,
                    'subject'=>urldecode($record->subject),
                    'classesfor'=>$record->classesfor,
                    'classid'=>$record->classid,
                    'starttime'=>$record->starttime,
                    'endtime'=>$record->endtime,
                    'attachment'=>$record->attachment,
                    'hostlive'=>$record->hostlive,
                    'status'=>$record->status,
                    'posteddate'=>$record->posteddate,
                ];
            }
            array_walk_recursive($t, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('live_session')->insert($t);
            return ['data'=> $f,'status'=> true,"msg"=>"done"];
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importLiveSessionFor(Request $request){
        try{
            $this->setConnection($request->from_database);
            $f = DB::table('live_session_for')->orderBy('live_session_for_id')->paginate(6000);
            DB::disconnect();
            $this->setConnection($request->to_database);
            $t = [];
            foreach($f->items() as $key=>$record){
                $t[] = [
                    'live_session_for_id'=>$record->live_session_for_id,
                    'live_session_id'=>$record->live_session_id,
                    'class'=>$record->class,
                    'section'=>$record->section,
                    'timestamp'=>$record->timestamp,
                ];
            }
            array_walk_recursive($t, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('live_session_for')->insert($t);
            return ['data'=> $f,'status'=> true,"msg"=>"done"];
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importMainScreenOptions(Request $request){
        try{
            $this->setConnection($request->from_database);
            $f = DB::table('mainscreenoptions')->orderBy('id')->paginate(50);
            DB::disconnect();
            $this->setConnection($request->to_database);
            $t = [];
            foreach($f->items() as $key=>$record){
                $t[] = [
                    'id'=>$record->id,
                    'optionname'=>$record->optionname,
                    'iconurl'=>$record->iconurl,
                    'color'=>$record->color,
                    'redirecturl'=>$record->redirecturl,
                    'accounttype'=>$record->accounttype,
                    'activityname'=>$record->activityname,
                ];
            }
            array_walk_recursive($t, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('mainscreenoptions')->insert($t);
            return ['data'=> $f,'status'=> true,"msg"=>"done"];
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importPhotosVideos(Request $request){
        try{
            $this->setConnection($request->from_database);
            $f = DB::table('photosvideos')->orderBy('photoid')->paginate(6000);
            DB::disconnect();
            $this->setConnection($request->to_database);
            $t = [];
            foreach($f->items() as $key=>$record){
                $t[] = [
                    'photoid'=>$record->photoid,
                    'type'=>$record->type,
                    'url'=>$record->url,
                    'albumid'=>$record->albumid,
                    'dateposted'=>$record->dateposted,
                    'creatorid'=>$record->creatorid
                ];
            }
            array_walk_recursive($t, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('photosvideos')->insert($t);
            return ['data'=> $f,'status'=> true,"msg"=>"done"];
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importQuizAttempt(Request $request){
        try{
            $this->setConnection($request->from_database);
            $f = DB::table('quizattempt')->orderBy('attemptid')->paginate(6000);
            DB::disconnect();
            $this->setConnection($request->to_database);
            $t = [];
            foreach($f->items() as $key=>$record){
                $t[] = [
                    'attemptid'=>$request->attemptid,
                    'quizid'=>$request->quizid,
                    'studentid'=>$request->studentid,
                    'score'=>$request->score,
                    'entrydate'=>$request->entrydate,
                ];
            }
            array_walk_recursive($t, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('quizattempt')->insert($t);
            return ['data'=> $f,'status'=> true,"msg"=>"done"];
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importQuizFor(Request $request){
        try{
            $this->setConnection($request->from_database);
            $f = DB::table('quizfor')->orderBy('quizid')->paginate(6000);
            DB::disconnect();
            $this->setConnection($request->to_database);
            $t = [];
            foreach($f->items() as $key=>$record){
                $t[] = [
                    'quizid'=>$record->quizid,
                    'companyid'=>$record->companyid,
                    'dateposted'=>$record->dateposted,
                    'postedby'=>$record->postedby,
                    'teacherid'=>$record->teacherid,
                    'class'=>$record->class,
                    'type'=>$record->type,
                    'subject'=>urldecode($record->subject),
                    'visibility'=>$record->visibility,
                ];
            }
            array_walk_recursive($t, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('quizfor')->insert($t);
            return ['data'=> $f,'status'=> true,"msg"=>"done"];
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function importQuizquestions(Request $request){
        try{
            $this->setConnection($request->from_database);
            $f = DB::table('quizquestions')->orderBy('quesid')->paginate(6000);
            DB::disconnect();
            $this->setConnection($request->to_database);
            $t = [];
            foreach($f->items() as $key=>$record){
                $t[] = [
                    'quesid'=>$record->quesid,
                    'ques'=>$record->ques,
                    'option1'=>$record->option1,
                    'option2'=>$record->option2,
                    'option3'=>$record->option3,
                    'option4'=>$record->option4,
                    'correctoption'=>$record->correctoption
                ];
            }
            array_walk_recursive($t, function(&$value) { $value = $value === "" ? NULL : $value; });
            DB::table('quizquestions')->insert($t);
            return ['data'=> $f,'status'=> true,"msg"=>"done"];
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function maxAppId(Request $request){
        $this->setConnection($request->from_database);
        $registration_id = DB::table('registrations')->max('registration_id');
        $registration_id = DB::table('registrations')->max('registration_id');
        $f = DB::table('supers')->paginate(50);//student_app_id//staff_app_id
        DB::disconnect();
        $this->setConnection($request->to_database);
    }

    public function setSchoolDatabase(Request $request){
        try{
            $this->setConnection($request->to_database);
            // $school = DB::table('schools')->get();
            // return $school;
            // if ($school->count()==0) {
                $last_school_id = 1;
                if(DB::table('heads')->count()==0){
                    $dataHead = [];
                    foreach(headRecord() as $record){
                        $dataHead[] = [
                            "category_id"=>1,
                            "head"=>$record['name'],
                            "school_id"=>$last_school_id,
                        ];
                    }
                    DB::table('heads')->insert($dataHead);
                }
                
                if(DB::table('masters')->count()==0){
                    $dataMaster = [];
                    foreach(masterRecord() as $record){
                        $dataMaster[] = [
                            "name"=>$record['name'],
                            "value"=>$record['value'],
                            "school_id"=>$last_school_id,
                            "sync_property_id"=>0,
                        ];
                    }
                    DB::table('masters')->insert($dataMaster);
                }
                if(DB::table('formates')->count()==0){
                    $dataFormat = [];
                    foreach(formatsRecord() as $record){
                        $dataFormat[] = [
                            "name"=>$record['name'],
                            "value"=>$record['value'],
                            "school_id"=>$last_school_id,
                            "session_id"=>0,
                        ];
                    }
                    DB::table('formates')->insert($dataFormat);
                }

                if(DB::table('roles')->count()==0){
                    $dataRole = [];
                    foreach(roleRecord() as $record){
                        $dataRole[] = [
                            "role_name"=>$record['role_name'],
                            "capabilities"=>$record['capabilities'],
                            "school_id"=>$last_school_id,
                            "status"=>1,
                        ];
                    }
                    DB::table('roles')->insert($dataRole);
                }
                if(DB::table('supers')->count()==0){
                    $dataSuper = [];
                    foreach(superRecord() as $record){
                        $dataSuper[] = [
                            "meta_key"=>$record['meta_key'],
                            "meta_value"=>$record['meta_value'],
                            "date"=>date("Y-m-d h:i:s"),
                        ];
                    }
                    DB::table('supers')->insert($dataSuper);
                }
                if(DB::table('users')->whereIn('username',['admin','sadmin'])->count()==0){
                    // $roles = DB::table('roles')->select('role_name','id')->get()->keyBy('role_name');
                    $dataUser = [];
                    foreach(userRecord() as $record){
                        $dataUser[] = [
                            // "id"=>$record['id'],
                            "name"=>$record['name'],
                            "username"=>$record['username'],
                            "password"=>$record['password'],
                            "role"=>$record['role'],
                            "school_id"=>$last_school_id,
                            "start_date"=>date("Y-m-d h:i:s"),
                            "created"=>date("Y-m-d h:i:s"),
                            "modified"=>date("Y-m-d h:i:s"),
                            "status"=>1,
                        ];
                    }
                    DB::table('users')->insert($dataUser);
                }
                
                if(DB::table('oauth_clients')->count()==0){
                    $oauthKey = oauthKey();
                    DB::table('oauth_clients')->insert($oauthKey['oauth_clients']);
                }

                if(DB::table('oauth_personal_access_clients')->count()==0){
                    $oauthKey = oauthKey();
                    DB::table('oauth_personal_access_clients')->insert($oauthKey['oauth_personal_access_clients']);
                }
                return ['data'=> '','status'=> true,"msg"=>"done"];
            // }
        }catch(\Exception $e){
            return response()->json(["success"=>false,"msg"=>@$e->getMessage(),"line"=>@$e->getLine()]);
        }
    }

    public function setConnection($database){
        $conf = [
                    'local'=>['host'=>'localhost','username'=>'root','password'=>''],
                    'production'=>['host'=>'217.21.80.2','username'=>'3050884_skooliya','password'=>'Skooliya@123']
                ];
        config::set(['database.connections.mysql' => [
            'driver'    => 'mysql',
            'host'      => (env('APP_ENV')=="local"?'217.21.80.2':"localhost"),//$conf[env('APP_ENV')]['host'],
            'username'  => ($database=='u210117126_skooliya_tms'?'u210117126_skooliya2':$database),//$conf[env('APP_ENV')]['username'],
            'password'  => 'Skooliya@123',//$conf[env('APP_ENV')]['password'],
            'database'  => $database,
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        ]]);
    }
}
