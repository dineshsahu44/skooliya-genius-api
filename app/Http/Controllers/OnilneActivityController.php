<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LiveClass;
use App\Models\LiveClassFor;
use App\Models\LiveSession;
use App\Models\LiveSessionFor;
use App\Models\LiveDoc;
use DB;
use Storage;
use Validator;

class OnilneActivityController extends Controller
{
    public function onlineActivity(Request $request){
        try{
            //$_GET['work'],$request->work
            $success = ["msg"=>"@$request->work successfully done."];
            if ($request->work == 'insertclass') {
                $validator = Validator::make($request->all(),[
                    'accountid' => 'required',
                    'subject' => 'required',
                    // 'visible'=>'required',
                    'classesfor'=>'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(validatorMessage($validator));
                }
                $user = getAuth();
                $subject = $_POST['subject'];
                $classesfor = $_POST['classesfor'];
                $status = $_POST['visible'];
                $accountid = $_GET['accountid'];
                $currentSession = currentSession();
                $companyid= $currentSession->id;
                $classData = [
                    "companyid"=>$companyid,
                    "accountid"=>$accountid,
                    "subject"=>$subject,
                    "accountname"=>$user->name,
                    "classesfor"=>$classesfor,
                    "status"=>$status,
                    "posteddate"=>now(),
                ];
                $classInsert = LiveClass::create($classData);
                $classFor = [];
                $assignClasses =[];
                foreach(json_decode($classesfor,true) as $aclass){
                    foreach($aclass['section'] as $section){
                        $assignClasses[] = "'".$aclass['class'].'-'.$section."'";
                        $classFor[] = [
                            'live_classes_id'=>$classInsert->id,
                            'class'=>$aclass['class'],
                            'section'=>$section,
                            'timestamp'=>now(),
                        ];
                    }
                }

                LiveClassFor::insert($classFor);
                if($status==1){
                    if(count($assignClasses)>0)
                        $record = getStudentTokenByConcatClass(implode(",",$assignClasses),$companyid);
                    if(count($record)>0)
                        send_notification('Dear, Student New Online Class-Subject Added',$subject,'onlineClassAdded',$record);
                }
                return customResponse(1,$success);
            }elseif ($request->work == 'makevisible') {
                $validator = Validator::make($request->all(),[
                    'visible'=>'required',
                    'classid'=>'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(validatorMessage($validator));
                }
                LiveClass::where('live_classes_id',$request->classid)->update(["status" =>$request->visible]);
                if($request->visible){
                   $class = LiveClass::where('live_classes_id',$request->classid)->first();
                   $assignClasses = json_decode($class->classesfor,true);
                   if(count($assignClasses)>0)
                        $record = getStudentTokenByConcatClass(implodeClass($class->classesfor),$class->companyid);
                    if(count($record)>0)
                    send_notification('Dear, Student New Online Class-Subject Added',$class->subject,'onlineClassAdded',$record);
                }
                return customResponse(1,$success);
            }elseif ($request->work == 'deleteclass') {
                $validator = Validator::make($request->all(),[
                    'classid'=>'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(validatorMessage($validator));
                }
                LiveClass::where('live_classes_id',$request->classid)->delete();
                LiveClassFor::where('live_classes_id',$request->classid)->delete();
                return customResponse(1,$success);
            }elseif ($request->work == 'getclass') {
                $validator = Validator::make($request->all(),[
                    'accountid'=>'required',
                    'companyid'=>'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(validatorMessage($validator));
                }
                $pageLimit = pageLimit(@$request->page);//$_GET['page']
                $candidateid = $request->accountid;
                $GLOBALS['get_companyid']  = $request->companyid;
                if(@$request->accounttype=='student'){
                    $classSection = getClassFromAccountID($candidateid,$GLOBALS['get_companyid']);
                    $liveClass = LiveClass::select(
                        'faculties.photo','faculties.name as accountname','live_classes.live_classes_id as classesid',
                        'companyid','accountid','subject','classesfor','live_classes.status as visible','posteddate',
                        DB::raw('if(DATEDIFF(CURDATE(), posteddate)>15,0,1) as nayabatch')
                        )
                    ->join('live_classes_for','live_classes_for.live_classes_id','live_classes.live_classes_id')
                    ->join('faculties', function ($join) {
                        $join->on('faculties.faculty_id', 'live_classes.accountid')
                        ->where('faculties.School_id', getSchoolIdBySessionID($GLOBALS['get_companyid'])->school_id);
                    })
                    ->where([
                        ['live_classes.companyid',$GLOBALS['get_companyid']],
                        ['live_classes_for.class',$classSection->class],
                        ['live_classes_for.section',$classSection->section],
                        ['live_classes.status',1],
                    ])->orderByDesc('posteddate')->offset($pageLimit->offset)->limit($pageLimit->limit)->get();
                }else{
                    // DB::enableQueryLog();
                    $liveClass = LiveClass::select(
                        'faculties.photo','faculties.name as accountname','live_classes.live_classes_id as classesid',
                        'companyid','accountid','subject','classesfor','live_classes.status as visible','posteddate',
                        DB::raw('if(DATEDIFF(CURDATE(), posteddate)>15,0,1) as nayabatch')
                        )
                    ->join('faculties', function ($join) {
                        $join->on('faculties.faculty_id', 'live_classes.accountid')
                        ->where('faculties.School_id', getSchoolIdBySessionID($GLOBALS['get_companyid'])->school_id);
                    })
                    ->where([
                        ['live_classes.companyid',$GLOBALS['get_companyid']],
                        ['live_classes.accountid',$candidateid]
                    ])->orderByDesc('posteddate')->offset($pageLimit->offset)->limit($pageLimit->limit)->get();
                    // dd(DB::getQueryLog());
                }
                return customResponse(1,["list"=>$liveClass]);
            }elseif ($request->work == 'insertdocs') {
                $acceptFiles = accpectFiles('online-class-files');
                $validator = Validator::make($request->all(),[
                    'type' => 'required|in:file,videolink,doclink',
                    'classid' => 'required',
                    'visible'=>'required',
                    'title'=>'required',
                    'attachment'=>$request->file=='file'?'required|mimes:'.implode(',',$acceptFiles['extension-type']).'|max:'.$acceptFiles['max-size'].'':'',
                ]);

                if ($validator->fails()) {
                    return response()->json(validatorMessage($validator));
                }

                $type = $request->type;
                $classid = $request->classid;
                $status = $request->visible;
                $title = $request->title;
                $appNoti = [
                    "file"=>[
                        "body"=>"New File Added",
                        "app"=>"docAddedToClass",
                    ],
                    "videolink"=>[
                        "body"=>"New Video Added",
                        "app"=>"videoAddedToclass",
                    ],
                    "doclink"=>[
                        "body"=>"New Document Added",
                        "app"=>"docAddedToClass",
                    ]
                ];

                if ($request->hasFile('attachment')&&$type=='file') {
                    $attachment = saveFiles($request->file('attachment'),$acceptFiles);

                    $docsData = [
                        "live_classes_id" => $classid,
                        "type" => $type,
                        "title" => $title,
                        "attachment" => $attachment,
                        "status" => $status,
                        "postdate" => now(),
                        "commentstatus" => 0,
                    ];
                    LiveDoc::create($docsData);
                }else{
                    $attachment = $request->attachment;
                    $docsData = [
                        "live_classes_id" => $classid,
                        "type" => $type,
                        "title" => $title,
                        "attachment" => $attachment,
                        "status" => $status,
                        "postdate" => now(),
                        "commentstatus" => $type=='videolink'?$request->commentstatus:0,
                    ];
                    LiveDoc::create($docsData);
                }

                if($status){
                    $liveClass = LiveClass::where('live_classes_id',$classid)->first();
                    $assignClasses = json_decode($liveClass->classesfor,true);
                    if(count($assignClasses)>0)
                            $record = getStudentTokenByConcatClass(implodeClass($liveClass->classesfor),$liveClass->companyid);
                    if(count($record)>0)
                        send_notification($appNoti[$type]['body'],$liveClass->subject,$appNoti[$type]['app'],$record);
                }
                return customResponse(1,$success);
            }elseif ($request->work == 'deletedocs') {
                $validator = Validator::make($request->all(),[
                    'docsid' => 'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(validatorMessage($validator));
                }
                $docsid = $request->docsid;
                $liveDocs = LiveDoc::where([['type','file'],['live_docs_id',$docsid]])->first();
                if($liveDocs){
                    removeFile($liveDocs->attachment);
                }
                LiveDoc::where('live_docs_id',$docsid)->delete();
                return customResponse(1,$success);
            }elseif ($request->work == 'visibledocs') {
                $validator = Validator::make($request->all(),[
                    'docsid' => 'required',
                    'visible' => 'required',
                ]);
                if ($validator->fails()) {
                    return response()->json(validatorMessage($validator));
                }
                $status_value = $request->visible;
                $docsid = $request->docsid;
                LiveDoc::where('live_docs_id',$docsid)->update(["status" =>$status_value]);
                
                if($status_value){
                    $appNoti = [
                        "file"=>[
                            "body"=>"New File Added",
                            "app"=>"docAddedToClass",
                        ],
                        "videolink"=>[
                            "body"=>"New Video Added",
                            "app"=>"videoAddedToclass",
                        ],
                        "doclink"=>[
                            "body"=>"New Document Added",
                            "app"=>"docAddedToClass",
                        ]
                    ];
                    $liveClass = LiveClass::select('classesfor','type')
                    ->join('live_docs','live_docs.live_classes_id','live_classes.live_classes_id')
                    ->where('live_docs.live_docs_id',$docsid)->first();
                    $assignClasses = json_decode($liveClass->classesfor,true);
                    if(count($assignClasses)>0)
                            $record = getStudentTokenByConcatClass(implodeClass($liveClass->classesfor),$liveClass->companyid);
                    if(count($record)>0)
                        send_notification($appNoti[$liveClass->type]['body'],$liveClass->subject,$appNoti[$liveClass->type]['app'],$record);
                }
                return customResponse(1,$success);            
            }elseif ($request->work == 'getfile') {
                $validator = Validator::make($request->all(),[
                    'classesid' => 'required',
                ]);
                if ($validator->fails()) {
                    return response()->json(validatorMessage($validator));
                }
                $classesid = $_GET['classesid'];
                $pageLimit = pageLimit(@$request->page);//$_GET['page']
                if(@$request->accounttype=="student"){
                    $classDocs = LiveDoc::select(
                        DB::raw("(SELECT 0)  as countcomment"),
                        DB::raw('if(live_docs.attachment is NULL,"",live_docs.attachment) as attachment'),
                        'live_docs.status as visible',
                        'live_docs.commentstatus as commentstatus',
                        'live_docs.postdate as posteddate',
                        'live_docs.title as title',
                        'live_docs.type as type',
                        'live_docs.live_docs_id as docsid'
                        )
                    ->where([['live_classes_id',$classesid],['status',1]])
                    ->whereIn('type',['file','doclink'])
                    ->orderByDesc('postdate')->offset($pageLimit->offset)->limit($pageLimit->limit)->get();
                }else{
                    $classDocs = LiveDoc::select(
                        DB::raw("(SELECT 0)  as countcomment"),
                        DB::raw('if(live_docs.attachment is NULL,"",live_docs.attachment) as attachment'),
                        'live_docs.status as visible',
                        'live_docs.commentstatus as commentstatus',
                        'live_docs.postdate as posteddate',
                        'live_docs.title as title',
                        'live_docs.type as type',
                        'live_docs.live_docs_id as docsid'
                    )
                    ->where('live_classes_id',$classesid)
                    ->whereIn('type',['file','doclink'])
                    ->orderByDesc('postdate')->offset($pageLimit->offset)->limit($pageLimit->limit)->get();
                }
                $result['message'] = $success;
                $result['docslist'] = $classDocs;
                $result['videolist'] = [];
                return customResponse(1,$result);
            }elseif ($request->work == 'getvideolink') {
                $validator = Validator::make($request->all(),[
                    'classesid' => 'required',
                ]);
                if ($validator->fails()) {
                    return response()->json(validatorMessage($validator));
                }
                $classesid = $_GET['classesid'];
                $pageLimit = pageLimit(@$request->page);//$_GET['page']
                // DB::enableQueryLog();
                if(@$request->accounttype=="student"){
                    $classDocs = LiveDoc::select(
                        DB::raw("(SELECT COUNT(comment_id) FROM comment WHERE activityname='video' AND activityid=live_docs_id AND readstatus!=1)  as countcomment"),
                        DB::raw('if(live_docs.attachment is NULL,"",live_docs.attachment) as attachment'),
                        'live_docs.status as visible',
                        'live_docs.commentstatus as commentstatus',
                        'live_docs.postdate as posteddate',
                        'live_docs.title as title',
                        'live_docs.type as type',
                        'live_docs.live_docs_id as docsid'
                        )
                    ->where([['live_classes_id',$classesid],['status',1],['type','videolink']])
                    ->orderByDesc('postdate')->offset($pageLimit->offset)->limit($pageLimit->limit)->get();
                }else{
                    $classDocs = LiveDoc::select(
                        DB::raw("(SELECT COUNT(comment_id) FROM comment WHERE activityname='video' AND activityid=live_docs_id AND readstatus!=1)  as countcomment"),
                        DB::raw('if(live_docs.attachment is NULL,"",live_docs.attachment) as attachment'),
                        'live_docs.status as visible',
                        'live_docs.commentstatus as commentstatus',
                        'live_docs.postdate as posteddate',
                        'live_docs.title as title',
                        'live_docs.type as type',
                        'live_docs.live_docs_id as docsid')
                    ->where([['live_classes_id',$classesid],['type','videolink']])
                    ->orderByDesc('postdate')->offset($pageLimit->offset)->limit($pageLimit->limit)->get();
                }
                // dd(DB::getQueryLog());
                $result['message'] = $success;
                $result['docslist'] = [];
                $result['videolist'] = $classDocs;
                return customResponse(1,$result);
            }elseif ($request->work == 'updatecommentstatus') {
                $validator = Validator::make($request->all(),[
                    'docsid' => 'required',
                    'commentstatus' => 'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(validatorMessage($validator));
                }
                $commentstatus = $_POST['commentstatus'];
                $docsid = $_POST['docsid'];

                LiveDoc::where('live_docs_id',$docsid)->update(['commentstatus'=>$commentstatus]);
                return customResponse(1,$success);
            }elseif ($request->work == 'insertliveclass') {
                $validator = Validator::make($request->all(),[
                    'classesid' => 'required',
                    'meetlink'=> 'required',
                    'accountid'=> 'required',
                    'subject'=> 'required',
                    'classesfor'=> 'required',
                    'visible'=> 'required',
                    'starttime'=> 'required',
                    'endtime'=> 'required',
                ]);
                if ($validator->fails()) {
                    return response()->json(validatorMessage($validator));
                }

                
                $currentSession = currentSession();
                $companyid= $currentSession->id;
                $user = getAuth();

                $meetlink = $_POST['meetlink'];
                $accountid = $_GET['accountid'];
                $subject = $_POST['subject'];
                $classesfor = $_POST['classesfor'];
                $status = $_POST['visible'];
                $starttime = $_POST['starttime'];
                $endtime = $_POST['endtime'];
                $liveData = [
                    'companyid'=> $companyid,
                    'classid' => $meetlink,
                    'accountid'=> $accountid,
                    'accountname'=> $user->name,
                    'subject' => $subject,
                    'classesfor'=> $classesfor,
                    'starttime' => $starttime,
                    'endtime' => $endtime,
                    'status' => $status,
                    'posteddate' => now(),
                ];
                $classInsert = LiveSession::create($liveData);
                $classFor = [];
                $assignClasses =[];
                foreach(json_decode($classesfor,true) as $aclass){
                    foreach($aclass['section'] as $section){
                        $assignClasses[] = "'".$aclass['class'].'-'.$section."'";
                        $classFor[] = [
                            'live_session_id'=>$classInsert->id,
                            'class'=>$aclass['class'],
                            'section'=>$section,
                            'timestamp'=>now(),
                        ];
                    }
                }

                LiveSessionFor::insert($classFor);
                if($status==1){
                    if(count($assignClasses)>0)
                        $record = getStudentTokenByConcatClass(implode(",",$assignClasses),$companyid);
                    if(count($record)>0)
                        send_notification('New live class.', $subject,'onlineClassAdded',$record);
                }
                return customResponse(1,$success);
            }elseif ($request->work == 'makelivevisible') {
                $validator = Validator::make($request->all(),[
                    'visible'=>'required',
                    'classid'=>'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(validatorMessage($validator));
                }
                LiveSession::where('live_session_id',$request->classid)->update(["status" =>$request->visible]);
                if($request->visible){
                   $class = LiveSession::where('live_session_id',$request->classid)->first();
                   $assignClasses = json_decode($class->classesfor,true);
                   if(count($assignClasses)>0)
                        $record = getStudentTokenByConcatClass(implodeClass($class->classesfor),$class->companyid);
                    if(count($record)>0)
                    send_notification('New live class.',$class->subject,'onlineClassAdded',$record);
                }
                return customResponse(1,$success);
            }elseif ($request->work == 'deleteliveclass') {
                $validator = Validator::make($request->all(),[
                    'classid'=>'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(validatorMessage($validator));
                }
                LiveSession::where('live_session_id',$request->classid)->delete();
                LiveSessionFor::where('live_session_id',$request->classid)->delete();
                return customResponse(1,$success);
            }elseif ($request->work == 'getliveclass') {
        
                $validator = Validator::make($request->all(),[
                    'accountid'=>'required',
                    'companyid'=>'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(validatorMessage($validator));
                }
                $pageLimit = pageLimit(@$request->page);//$_GET['page']
                $candidateid = $request->accountid;
                $GLOBALS['get_companyid']  = $request->companyid;
                if(@$request->accounttype=='student'){
                    $classSection = getClassFromAccountID($candidateid,$GLOBALS['get_companyid']);
                    $liveClass = LiveSession::select(
                        'faculties.photo','faculties.name as accountname','classid','live_session.live_session_id as classesid',
                        'companyid','accountid','subject','starttime','endtime','hostlive',DB::raw('if(attachment is NULL, "",attachment) as attachment'),'classesfor','live_session.status as visible','posteddate'
                        )
                    ->join('live_session_for','live_session_for.live_session_id','live_session.live_session_id')
                    ->join('faculties', function ($join) {
                        $join->on('faculties.faculty_id', 'live_session.accountid')
                        ->where('faculties.School_id', getSchoolIdBySessionID($GLOBALS['get_companyid'])->school_id);
                    })
                    ->where([
                        ['live_session.companyid',$GLOBALS['get_companyid']],
                        ['live_session_for.class',$classSection->class],
                        ['live_session_for.section',$classSection->section],
                        ['live_session.status',1],
                    ])->orderByDesc('posteddate')->offset($pageLimit->offset)->limit($pageLimit->limit)->get();
                }else{
                    // DB::enableQueryLog();
                    $liveClass = LiveSession::select(
                        'faculties.photo','faculties.name as accountname','classid','live_session.live_session_id as classesid',
                        'companyid','accountid','subject','starttime','endtime','hostlive',DB::raw('if(attachment is NULL, "",attachment) as attachment'),'classesfor','live_session.status as visible','posteddate'
                        )
                    ->join('faculties', function ($join) {
                        $join->on('faculties.faculty_id', 'live_session.accountid')
                        ->where('faculties.School_id', getSchoolIdBySessionID($GLOBALS['get_companyid'])->school_id);
                    })
                    ->where([
                        ['live_session.companyid',$GLOBALS['get_companyid']],
                        ['live_session.accountid',$candidateid]
                    ])->orderByDesc('posteddate')->offset($pageLimit->offset)->limit($pageLimit->limit)->get();
                    // dd(DB::getQueryLog());
                }
                return customResponse(1,["list"=>$liveClass]);
            }elseif ($request->work == 'checkliveclass') {
                $validator = Validator::make($request->all(),[
                    'liveclassid'=>'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(validatorMessage($validator));
                }
                $liveclassid = $_GET['liveclassid'];
                $liveSession = LiveSession::select('hostlive')->where('live_session_id',$liveclassid)->first();

                $result['hostlive'] = $liveSession->hostlive;
                return customResponse(1,$result);
            }elseif ($request->work == 'hostlive') {
                $validator = Validator::make($request->all(),[
                    'liveclassid'=>'required',
                    'hostvalue'=>'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(validatorMessage($validator));
                }
                $liveclassid = $_GET['liveclassid'];
                $hostvalue = $_POST['hostvalue'];
                LiveSession::where('live_session_id',$liveclassid)->update(["hostlive" =>$hostvalue]);
                if($hostvalue){
                   $class = LiveSession::where('live_session_id',$liveclassid)->first();
                   $assignClasses = json_decode($class->classesfor,true);
                   if(count($assignClasses)>0)
                        $record = getStudentTokenByConcatClass(implodeClass($class->classesfor),$class->companyid);
                    if(count($record)>0)
                    send_notification('Meeting Session Start.',$class->subject,'onlineClassAdded',$record);
                }
                return customResponse(1,$success);
            }elseif ($request->work == 'addlivevideo') {
                $validator = Validator::make($request->all(),[
                    'liveclassid'=>'required',
                    'attachment'=>'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(validatorMessage($validator));
                }
                $liveclassid = $_POST['liveclassid'];
                $attachment = $_POST['attachment'];
                LiveSession::where('live_session_id',$liveclassid)->update(["attachment" =>$attachment]);
                return customResponse(1,$success);
            }else{
                return customResponse(0,["msg"=>"work not define."]);
            }
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }
}
