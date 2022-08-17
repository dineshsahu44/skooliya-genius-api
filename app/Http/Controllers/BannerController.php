<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LiveBanner;
use App\Models\LiveBannerFor;
use DB;
use Storage;
use Validator;

class BannerController extends Controller
{
    public function banner(Request $request){
        try{
            $success = ["msg"=>"@$request->work successfully done."];
            if($request->work=='insertbanner'){
                $acceptFiles = accpectFiles('banner-files');
                $validator = Validator::make($request->all(),[
                    'accountid' => 'required',
                    'heading' => 'required',
                    'description'=>'required',
                    'expirydate'=>'required',
                    // 'visible'=>'required',
                    'classesfor'=>'required',
                    'attachment' => 'required|mimes:'.implode(',',$acceptFiles['extension-type']).'|max:'.$acceptFiles['max-size'].''
                ]);

                if ($validator->fails()) {
                    return response()->json(validatorMessage($validator));
                }
                $currentSession = currentSession();
                $companyid= $currentSession->id;
                $candidateid = $request->accountid;
                $banner_title = $request->heading;
                $banner_text = $request->description;
                $date_upto = $request->expirydate;
                $status = $request->visible!=null?$request->visible:0;
                $classesfor = $request->classesfor;
                if ($request->hasFile('attachment')) {
                    $attachment = saveFiles($request->file('attachment'),$acceptFiles);
                }
                $bannerData = [
                    'companyid'=>$companyid,
                    'live_candidate_id'=>$candidateid,
                    'live_banner_title'=>$banner_title,
                    'live_banner_photo'=>$attachment,
                    'live_banner_text'=>$banner_text,
                    'live_banner_date'=>now(),
                    'live_banner_status'=>$status,
                    'date_upto'=>$date_upto,
                    'classesfor'=>$classesfor
                ];
                $bannerInset = LiveBanner::create($bannerData);
                $bannerFor = [];
                $assignClasses =[];
                foreach(json_decode($classesfor,true) as $aclass){
                    foreach($aclass['section'] as $section){
                        $assignClasses[] = "'".$aclass['class'].'-'.$section."'";
                        $bannerFor[] = [
                            'live_banner_id'=>$bannerInset->id,
                            'class'=>$aclass['class'],
                            'section'=>$section,
                            'timestamp'=>now(),
                        ];
                    }
                }
                
                LiveBannerFor::insert($bannerFor);
                //if visible status is 1 then send notification
                if($status==1){
                    if(count($assignClasses)>0)
                        $record = getStudentTokenByConcatClass(implode(",",$assignClasses),$companyid);
                    if(count($record)>0)
                        send_notification($banner_text,$banner_title,'onlineBannerAdded',$record);
                }
                return customResponse(1,$success);
            }elseif($request->work=='makevisible'){
                $validator = Validator::make($request->all(),[
                    'visible'=>'required',
                    'bannerid'=>'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(validatorMessage($validator));
                }
                LiveBanner::where('live_banner_id',$request->bannerid)->update(["live_banner_status" =>$request->visible]);
                if($request->visible){
                   $banner = LiveBanner::where('live_banner_id',$request->bannerid)->first();
                   $assignClasses = json_decode($banner->classesfor,true);
                   if(count($assignClasses)>0)
                        $record = getStudentTokenByConcatClass(implodeClass($banner->classesfor),$banner->companyid);
                    if(count($record)>0)
                        send_notification($banner->live_banner_text,$banner->live_banner_title,'onlineBannerAdded',$record);
                }
                return customResponse(1,$success);
            }elseif($request->work=='deletebanner'){
                $validator = Validator::make($request->all(),[
                    'bannerid'=>'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(validatorMessage($validator));
                }
                $banner = LiveBanner::where('live_banner_id',$request->bannerid)->first();
                removeFile($banner->live_banner_photo);
                LiveBanner::where('live_banner_id',$request->bannerid)->delete();
                LiveBannerFor::where('live_banner_id',$request->bannerid)->delete();
                return customResponse(1,$success);
            }elseif($request->work=='getbanner'){
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
                    $liveBaner = LiveBanner::select(
                        'faculties.name as accountname','live_banner.live_banner_id as bannerid',
                        'live_banner.companyid','live_banner.live_candidate_id as accountid','live_banner.live_banner_title as heading',
                        'live_banner_photo as attachment', 'live_banner_text as description','live_banner_status as visible',
                        'classesfor', 'date_upto as datexpiry', 'live_banner_date as postdate'
                        )
                    ->join('live_banner_for','live_banner_for.live_banner_id','live_banner.live_banner_id')
                    ->join('faculties', function ($join) {
                        $join->on('faculties.faculty_id', 'live_banner.live_candidate_id')
                        ->where('faculties.School_id', getSchoolIdBySessionID($GLOBALS['get_companyid'])->school_id);
                    })
                    ->where([
                        ['live_banner.companyid',$GLOBALS['get_companyid']],
                        ['live_banner_for.class',$classSection->class],
                        ['live_banner_for.section',$classSection->section],
                        ['live_banner.live_banner_status',1],
                    ])->orderByDesc('live_banner_date')->offset($pageLimit->offset)->limit($pageLimit->limit)->get();
                }else{
                    $liveBaner = LiveBanner::select(
                        'faculties.name as accountname','live_banner.live_banner_id as bannerid',
                        'live_banner.companyid','live_banner.live_candidate_id as accountid','live_banner.live_banner_title as heading',
                        'live_banner_photo as attachment', 'live_banner_text as description','live_banner_status as visible',
                        'classesfor', 'date_upto as datexpiry', 'live_banner_date as postdate'
                        )
                    ->join('faculties', function ($join) {
                        $join->on('faculties.faculty_id', 'live_banner.live_candidate_id')
                        ->where('faculties.School_id', getSchoolIdBySessionID($GLOBALS['get_companyid'])->school_id);
                    })
                    ->where([
                        ['live_banner.companyid',$GLOBALS['get_companyid']],
                        ['live_banner.live_candidate_id',$candidateid]
                    ])->orderByDesc('live_banner_date')->offset($pageLimit->offset)->limit($pageLimit->limit)->get();
                }
                return customResponse(1,["list"=>$liveBaner]);
            }else{
                return customResponse(0,["msg"=>"work not define."]);
            }
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }
}
