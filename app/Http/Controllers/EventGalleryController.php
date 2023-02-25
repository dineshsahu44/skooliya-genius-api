<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PhotoVideo;
use App\Models\Album;
use App\Models\Event;
use DB;
use Validator;

class EventGalleryController extends Controller
{
    public function addAlbumVideo(Request $request){
        try{
            // $url=$_POST['url'];
            // $albumid=$_POST['albumid'];
            // $creatorid=$_POST['creatorid'];
            $validator = Validator::make($request->all(),[
                'url' => 'required',
                'albumid' => 'required',
                'creatorid' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            $video = [
                'type'=> 'v',
                'url'=> $request->url,
                'albumid'=> $request->albumid,
                'creatorid'=> $request->creatorid,
                'dateposted'=>now(),
            ];
            PhotoVideo::create($video);
            return customResponse(1);

        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function addAlbumPhotos(Request $request){
        try{
            // $albumid=$_POST['albumid'];
            // $itemid=$_POST['itemid'];
            // $creatorid=$_POST['creatorid'];
            $acceptFiles = accpectFiles('gallery-files');
            $validator = Validator::make($request->all(),[
                'itemid' => 'required',
                'albumid' => 'required',
                'creatorid' => 'required',
                'attachment' => 'required|mimes:'.implode(',',$acceptFiles['extension-type']).'|max:'.$acceptFiles['max-size'].'',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }

            if ($request->hasFile('attachment')) {
                $attachment = saveFiles($request->file('attachment'),$acceptFiles);
            }

            $video = [
                'type'=> 'p',
                'url'=> $attachment,
                'albumid'=> $request->albumid,
                'creatorid'=> $request->creatorid,
                'dateposted'=>now(),
            ];
            PhotoVideo::create($video);
            return customResponse(1,['itemid'=>$request->itemid]);

        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function createAlbum(Request $request){
        try{
            // $heading=$_POST['heading'];
            // $postedby=$_POST['postedby'];
            // $creatorid=$_POST['creatorid'];
            $acceptFiles = accpectFiles('album-files');
            $validator = Validator::make($request->all(),[
                'heading' => 'required',
                'postedby' => 'required',
                'creatorid' => 'required',
                'attachment' => 'required|mimes:'.implode(',',$acceptFiles['extension-type']).'|max:'.$acceptFiles['max-size'].''
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            if ($request->hasFile('attachment')) {
                $attachment = saveFiles($request->file('attachment'),$acceptFiles);
            }

            $currentSession = currentSession();
            $companyid= $currentSession->id;
            $album = [
                'imageurl'=> $attachment,
                'heading'=> $request->heading,
                'postedby'=> $request->postedby,
                'creatorid'=> $request->creatorid,
                'companyid'=> $companyid,
                'dateposted'=>now(),
            ];
            $albumInsert = Album::create($album);
            return customResponse(1,['albumid'=>$albumInsert->id]);

        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function getAlbums(Request $request){
        try{
            // $companyid=$_GET['companyid'];
            $validator = Validator::make($request->all(),[
                'companyid' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            $pageLimit = pageLimit(@$request->page);
            $albums = Album::where('companyid',$request->companyid)
                    ->orderByDesc('dateposted')->offset($pageLimit->offset)->limit($pageLimit->limit)->get();
            return customResponse(1,['list'=>$albums]);

        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function createEvent(Request $request){
        try{
            // $heading=$_POST['heading'];
            // $description=$_POST['description'];
            // $eventdate=$_POST['eventdate'];
            // $creatorid=$_POST['creatorid'];	
            $acceptFiles = accpectFiles('event-files');
            $validator = Validator::make($request->all(),[
                'heading' => 'required',
                'description' => 'required',
                'creatorid' => 'required',
                'attachment' => 'required|mimes:'.implode(',',$acceptFiles['extension-type']).'|max:'.$acceptFiles['max-size'].'',
                'eventdate' => 'required|date',
            ]);
            
            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            
            if ($request->hasFile('attachment')) {
                $attachment = saveFiles($request->file('attachment'),$acceptFiles);
            }

            $currentSession = currentSession();
            $companyid= $currentSession->id;
            $event = [
                'imageurl'=> $attachment,
                'heading'=> $request->heading,
                'description'=> $request->description,
                'creatorid'=> $request->creatorid,
                'companyid'=> $companyid,
                'eventdate'=> $request->eventdate,
            ];
            Event::create($event);
            return customResponse(1,["msg"=>"event created."]);

        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function getEvents(Request $request){
        try{
            // $companyid=$_GET['companyid'];
            $validator = Validator::make($request->all(),[
                'companyid' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            $pageLimit = pageLimit(@$request->page);
            $event = Event::where('companyid',$request->companyid)
                    ->orderByDesc('eventdate')->offset($pageLimit->offset)->limit($pageLimit->limit)->get();
            return customResponse(1,['list'=>$event]);

        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function getGalleryItem(Request $request){
        try{
            // $_GET['albumid']
            $validator = Validator::make($request->all(),[
                'albumid' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            $galleryItem = PhotoVideo::where('albumid',$request->albumid)
                    ->orderByDesc('dateposted')->get();
            return customResponse(1,['list'=>$galleryItem]);

        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }
}
