<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Passport\Token;
use App\Models\Section;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\FacultyAssignClass;
use App\Models\SchoolSession;
use App\Models\Registration;
use App\Models\User;

use App\Models\Guardian;
use App\Models\Holiday;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\HwMessage;
use App\Models\HwMessageFor;
use App\Models\QuizFor;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Album;
use App\Models\PhotoVideo;

use DB;
use Validator;
use Log;

class DeleteController extends Controller
{
    public function deleteRecord(Request $request)
    {
        try{
            $id = $request->id;
            $posttype = $request->posttype;
            if($posttype==='event')
            {
                $deleteResult = Event::where('eventid',$id)->delete();
            }else if($posttype==='message'){
                $deleteResult = HwMessage::leftJoin('hwmessagefor','hwmessagefor.msgid','hwmessage.msgid')
                ->where('hwmessage.msgid',$id)->delete();
            }else if($posttype==='quiz'){
                $deleteResult1 = QuizFor::leftJoin()
                ->where('quizid',$id)->delete();
                if($deleteResult1){
                    $deleteResult = QuizQuestion::where('quizid',$id)->delete();
                }
                // $stmt = $conn->prepare("DELETE FROM quizfor WHERE quizid= $id");

                // if($stmt->execute())
                // {

                // $result['success']=1;

                // $stmt1=$conn->prepare("DELETE FROM quizquestions WHERE quizid= $id");
                // $stmt1->execute();

                // echo json_encode($result);
                // }
                // {
                // $result['success']=0;
                // echo json_encode($result);
                // }
            }else if($posttype==='album'){
                $deleteResult1 = Album::where('albumid',$id)->delete();
                if($deleteResult1){
                    $deleteResult = PhotoVideo::where('albumid',$id)->delete();
                }

                // $sql = "SELECT imageurl FROM `albums` WHERE albumid='$id'";
                // $stmt = $conn->prepare($sql);
                // $stmt->execute();
                // $stmt->bind_result($imageurl);
                // $stmt->fetch();
                // $myFile = pathinfo($imageurl);

                // $upload = "photos/".$myFile['basename'];
                // $path = ROOT."/".APISCRIPT.'/'.$upload;
                // unlink($path);

                // $stmt->close();

                // $sql = "SELECT url FROM `albums` INNER JOIN photosvideos on photosvideos.albumid=albums.albumid and
                // photosvideos.type='p' WHERE albums.albumid= '$id'";
                // $stmt = $conn->prepare($sql);
                // $stmt->execute();
                // $stmt->bind_result($url);
                // while($stmt->fetch()){
                //     $myFile = pathinfo($url);
                //     $upload = "photos/".$myFile['basename'];
                //     $path = ROOT."/".APISCRIPT.'/'.$upload;
                //     unlink($path);

                // }
                // $stmt->close();

                // $stmt = $conn->prepare("DELETE FROM albums WHERE albumid= $id");

                // if($stmt->execute())
                // {

                //     $result['success']=1;

                //     $stmt1=$conn->prepare("DELETE FROM photosvideos WHERE albumid= $id");
                //     $stmt1->execute();

                //     echo json_encode($result);
                // }
                // {
                //     $result['success']=0;
                //     echo json_encode($result);
                // }
            }else if($posttype==='photo'){
                
                $deleteResult = PhotoVideo::where('photoid',$id)->delete();

                // $stmt = $conn->prepare("SELECT url FROM photosvideos WHERE photoid= $id");
                // $stmt->execute();
                // $stmt->bind_result($filepath1);
                // $stmt->fetch();
                // $stmt->close();

                // $stmt = $conn->prepare("DELETE FROM photosvideos WHERE photoid= $id");

                // if($stmt->execute())
                // {

                // $myFile = pathinfo($filepath1);
                // $upload = "photos/".$myFile['basename'];
                // $path = ROOT."/".APISCRIPT.'/'.$upload;
                // unlink($path);
                // $result['success']=1;
                // echo json_encode($result);
                // }
                // else{
                // $result['success']=0;
                // echo json_encode($result);
                // }

            }else if($posttype==='video'){
                $deleteResult = PhotoVideo::where('photoid',$id)->delete();

                // $stmt = $conn->prepare("DELETE FROM photosvideos WHERE photoid= $id");

                // if($stmt->execute())
                // {

                // $result['success']=1;

                // echo json_encode($result);
                // }
                // {
                // $result['success']=0;
                // echo json_encode($result);
                // }

            }else if($posttype==='leave'){
                $deleteResult = Attendance::where('id1',$id)->delete();

                // $stmt = $conn->prepare("DELETE FROM attendance WHERE id1= $id");

                // if($stmt->execute())
                // {

                // $result['success']=1;

                // echo json_encode($result);
                // }
                // {
                // $result['success']=0;
                // echo json_encode($result);
                // }

            }

            if(@$deleteResult){
                return customResponse(1,['msg'=>'Successfully Deleted!']);
            }else{
                return customResponse(1,['msg'=>'Delete Fail!']);
            }
            return customResponse(1,['students'=>$students]);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }
}
