<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Registration;
use App\Models\Faculty;
use App\Models\HwMessage;
use App\Models\HwMessageFor;
use DB;
use Storage;
use Validator;

class NotificationController extends Controller
{
    public $successMsg = ["msg"=>"create announcement successfully done."];
    public $errorMsg = ["msg"=>"create announcement not done."];
    public function createMessage(Request $request){
        
        if($request->work=='updatecommentstatus'){
            $success = HwMessage::where('msgid',$request->msgid)->update(["commentstatus" =>$request->commentstatus]);
            if($success){
                return customResponse(1,["msg"=>"$request->work done."]);
            }else{
                return customResponse(0,["msg"=>"$request->work not done."]);
            }
        }
        else{
            $acceptFiles = accpectFiles('notification-files');
            $validator = Validator::make($request->all(),[
                'msgtype' => 'required|in:circular,notice,noticeteacher,homework',
                'msgheading'=>'required',
                'msgbody'=>'required',
                'msgbody'=>'required',
                'attachment' => 'mimes:'.implode(',',$acceptFiles['extension-type']).'|max:'.$acceptFiles['max-size'].''
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            $user = getAuth();
            $currentSession = currentSession();
            $companyid= $currentSession->id;
            $messageforarray = json_decode($request->students, true);
            $msgid=$request->msgid;
            $msgtype=$request->msgtype;
            $msgheading=$request->msgheading;
            $msgbody=$request->msgbody;
            $postedbyid=$user->username;
            $postedby=$user->name;
            $smsflag=$request->smsflag;
            $hindiflag=$request->hindiflag;
            $commentstatus=$request->commentstatus;
            $attachment = null;
            if ($request->hasFile('attachment')) {
                $attachment = saveFiles($request->file('attachment'),$acceptFiles);
            }
                        
            if($msgtype==='notice' or $msgtype==='homework' or $msgtype==='noticeteacher')
            {
                if($msgid==0){
                    $data = [
                        'msgtype'=>$msgtype,
                        'msgheading'=>$msgheading,
                        'msgbody'=>$msgbody,
                        'attachment'=>$attachment,
                        'postedbyid'=>$postedbyid,
                        'postedby'=>$postedby,
                        'companyid'=>$companyid,
                        'commentstatus'=>$commentstatus,
                        'entrydate'=>now(),
                    ];
                    $HwMessage = HwMessage::create($data);
                    if($HwMessage->id){
                        return $this->sendToIndividual($messageforarray,$HwMessage->id,$smsflag,$msgheading,$msgtype);
                    }else{
                        return response()->json(customResponse(0,$this->errorMsg));
                    }
                }else{
                    return $this->sendToIndividual($messageforarray,$msgid,$smsflag,$msgheading,$msgtype);
                }
            }else if($msgtype==='circular'){
                try{
                    $data = [
                        'msgtype'=>$msgtype,
                        'msgheading'=>$msgheading,
                        'msgbody'=>$msgbody,
                        'attachment'=>$attachment,
                        'postedbyid'=>$postedbyid,
                        'postedby'=>$postedby,
                        'companyid'=>$companyid,
                        'commentstatus'=>$commentstatus,
                        'entrydate'=>now(),
                    ];
                    // DB::beginTransaction();
                    $HwMessage = HwMessage::create($data);
                    if($HwMessage->id){
                        $msgid=$HwMessage->id;
                        $students = Registration::select('registrations.registration_id','users.device_token')->join('users','users.username','registrations.registration_id')->where([['registrations.session',$currentSession->id],['registrations.status','Active'],['users.school_id',$currentSession->school_id]])->get();

                        //find all student a time
                        $messagefor = [];
                        $record = [];
                        foreach($students as $stu){
                            $messagefor[] = [
                                'studentid'=>$stu->registration_id,
                                'msgid'=>$msgid
                            ];
                            $record[] = [
                                "username"=>$stu->registration_id,
                                "token"=>$stu->device_token,
                            ];
                        }
                        //find all teachers a time
                        $faculties = Faculty::select('faculties.faculty_id','users.device_token')->join('users','users.username','faculties.faculty_id')->where([['faculties.school_id',$currentSession->school_id],['faculties.status','Active']])->get();
                        
                        foreach($faculties as $fac){
                            $messagefor[] = [
                                'studentid'=>$fac->faculty_id,
                                'msgid'=>$msgid
                            ];
                            $record[] = [
                                "username"=>$fac->faculty_id,
                                "token"=>$fac->device_token,
                            ];
                        }
                        HwMessageFor::insert($messagefor);
                        
                        $result = customResponse(1,$this->successMsg);
                        echo json_encode($result);
                        if(count($record)>0){
                            send_notification($msgheading,notificationType($msgtype)['title'],notificationType($msgtype)['apptype'],$record);
                        }
                        die;
                    }else{
                        $result = customResponse(0,$this->errorMsg);
                    }
                    // DB::commit();
                    return $result;
                }catch(\Exception $e){
                    DB::rollback();
                    return exceptionResponse($e);
                }
            }
        }
    }

    public function sendToIndividual($sendFor,$msgid,$smsflag,$msgheading,$msgtype)
    {
        $messagefor=[];
        foreach($sendFor as $temp1){
            if (!empty($temp1))
            {
                $messagefor[] = [
                    'studentid'=>$temp1,
                    'msgid'=>$msgid
                ];
            }
        }
        HwMessageFor::insert($messagefor);
    
        $result['success']=1;
        //temparary script to sms testing return
        $result['sms'] = $smsflag=="true"?1:-1;//-1 sms button not true//0 sending failed//1 success        
        $result['msgid']=$msgid;
        echo json_encode($result);
        if(count($sendFor)>0){
            $record = User::select('username','device_token as token')->whereIn('username',$sendFor)->get()->toArray();
            send_notification($msgheading,notificationType($msgtype)['title'],notificationType($msgtype)['apptype'],$record);
        }
        die;
        return $result;
    }

    public function getMessage(Request $request){
        try{
            // $companyid=$_GET['companyid'];
            // $accounttype = $_GET['accounttype'];
            // $accountid = $_GET['accountid'];
            // $flag = $_GET['flag'];
            $validator = Validator::make($request->all(),[
                'companyid' => 'required',
                'accountid' => 'required',
                // 'accounttype' => 'required',
                'flag' => 'required|in:showbyme,showforme',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            $pageLimit = pageLimit(@$request->page);
            $hwmessage = [];
            if($request->flag=='showbyme'){
                $hwmessage = HwMessage::select(DB::Raw("0 as countcomment"),'hwmessage.msgid','hwmessage.msgheading','hwmessage.msgbody'
                ,'hwmessage.entrydate','hwmessage.postedbyid','hwmessage.postedby',DB::Raw("if(hwmessage.attachment IS NULL ,'',hwmessage.attachment ) as attachment"),
                'hwmessage.msgtype','hwmessage.commentstatus',DB::Raw("1 as smsflag"))
                ->where([['companyid',$request->companyid],['postedbyid',$request->accountid]])
                ->orderByDesc('entrydate')->offset($pageLimit->offset)->limit($pageLimit->limit)->get();
            }elseif($request->flag=='showforme'){
                $hwmessage = HwMessage::select(DB::Raw("0 as countcomment"),'hwmessage.msgid','hwmessage.msgheading','hwmessage.msgbody'
                ,'hwmessage.entrydate','hwmessage.postedbyid','hwmessage.postedby',DB::Raw("if(hwmessage.attachment IS NULL ,'',hwmessage.attachment ) as attachment"),
                'hwmessage.msgtype','hwmessage.commentstatus',DB::Raw("1 as smsflag"))
                ->join('hwmessagefor','hwmessagefor.msgid','hwmessage.msgid')
                ->where([['companyid',$request->companyid],['studentid',$request->accountid]])
                ->orderByDesc('entrydate')->offset($pageLimit->offset)->limit($pageLimit->limit)->get();
            }
            
            return customResponse(1,['list'=>$hwmessage]);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function msgSendToRecord(Request $request){
        try{
            $msgid = $request->msgid;
            $companyid = $request->companyid;
            $msgtype = $request->msgtype;
            if($msgtype=='noticeteacher'){
                $hwmsgfor = HwMessageFor::select('faculties.name as accountname','faculties.faculty_id as accountid')
                ->join('faculties','hwmessagefor.studentid','faculties.faculty_id')
                ->where('hwmessagefor.msgid',$msgid)
                ->orderBy('name')
                ->get();
                // return customResponse(1,['list'=>$hwmsgfor]);
                // return $hwmsgfor;
                // $sql = "SELECT accountname, accountid FROM hwmessagefor,`teachers` 
                // WHERE msgid=$msgid AND teachers.accountid=hwmessagefor.studentid AND companyid=$companyid ORDER BY accountname ASC ";
                // //$sql = "SELECT `teachers`.`accountname`, `teachers`.`accountid` FROM hwmessagefor 
                // INNER JOIN `teachers` on teachers.accountid=hwmessagefor.studentid and teachers.companyid='$companyid' 
                // WHERE hwmessagefor.msgid='$msgid' ORDER BY teachers.accountname ASC";
                // ///echo $sql;
                // $stmt = $conn->prepare($sql);
                
                // //executing the query 
                // $stmt->execute();
                
                // //binding results to the query 
                // $stmt->bind_result($accountname,$accountid);
                
                // $details = array(); 
                
                // //traversing through all the result 
                // while($stmt->fetch()){
                //     $temp = array();
                //     $temp['accountid'] = $accountid; 
                //     $temp['accountname']=$accountname;


                //     array_push($details, $temp);
                // }

            }else{
                $hwmsgfor = HwMessageFor::select('registrations.registration_id as studentid','registrations.name',
                DB::Raw('concat(classes.class," ",sections.section) as class'))
                ->join('registrations','hwmessagefor.studentid',DB::Raw("registrations.registration_id and registrations.session=$companyid"))
                ->join('classes','classes.id','registrations.class')
                ->join('sections','sections.id','registrations.section')
                ->where('hwmessagefor.msgid',$msgid)
                ->orderBy('classes.class')
                ->orderBy('sections.section')
                ->orderBy('registrations.name')
                ->get();
                // return $hwmsgfor;
                // $stmt = $conn->prepare("SELECT admission.studentid,name,class,admission.section FROM hwmessagefor,admission  
                // WHERE msgid=$msgid AND admission.studentid=hwmessagefor.studentid AND companyid=$companyid 
                // ORDER BY class ASC,section ASC,name ASC ");
                
                // //executing the query 
                // $stmt->execute();
                
                // //binding results to the query 
                // $stmt->bind_result($studentid,$name,$class,$section);
                
                // $details = array(); 
                
                // //traversing through all the result 
                // while($stmt->fetch()){
                //     $temp = array();
                //     $temp['studentid'] = $studentid; 
                //     $temp['class'] = $class." ".$section; 
                //     $temp['name']=$name;


                //     array_push($details, $temp);
                // }
            } 
            // $conn->close();
            // $result['success']=1;
            // $result['list']=$details;
            
            // //displaying the result in json format 
            // echo json_encode($result);
            return customResponse(1,['list'=>$hwmsgfor]);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }
}
