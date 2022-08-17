<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\QuizAttempt;
use App\Models\QuizFor;
use App\Models\QuizQuestion;
use DB;
use Storage;
use Validator;
class QuizController extends Controller
{
    public function addQuestion(Request $request){
        try{
            $validator = Validator::make($request->all(),[
                'ques' => 'required',
                'option1'=>'required',
                'option2'=>'required',
                'option3'=>'required',
                'option4'=>'required',
                'correctoption'=>'required',
                'quizid' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }

            $quizData = [
                'ques'=> $request->ques,
                'option1'=> $request->option1,
                'option2'=> $request->option2,
                'option3'=> $request->option3,
                'option4'=> $request->option4,
                'correctoption'=> $request->correctoption,
                'quizid'=> $request->quizid
            ];
            $quizInsert = QuizQuestion::create($quizData);
            return customResponse(1,["quizid"=>$quizInsert->id]);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function createQuiz(Request $request){
        try{
            $validator = Validator::make($request->all(),[
                'postedby' => 'required',
                'teacherid'=>'required',
                'class'=>'required',
                'type'=>'required',
                'subject'=>'required',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            $currentSession = currentSession();
            $companyid= $currentSession->id;
            $quizData = [
                'postedby'=> $request->postedby,
                'teacherid'=> $request->teacherid,
                'class'=> $request->class,
                'type'=> $request->type,
                'subject'=> $request->subject,
                'companyid'=> $companyid,
                'dateposted'=> now(),
            ];
            $quizInsert = QuizFor::create($quizData);
            return customResponse(1,["quizid"=>$quizInsert->id]);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function getQuizScore(Request $request){
        try{
            // $quizid=$_GET['quizid'];
            // $studentid=$_GET['studentid'];
            $validator = Validator::make($request->all(),[
                'quizid' => 'required',
                'studentid'=>'required',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            $quizScore = QuizAttempt::select('score','entrydate')->where([['quizid',$request->quizid],['studentid',$request->studentid]])->get();
            return customResponse(1,["list"=>$quizScore]);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function quizCheck(Request $request){
        try{
            // $quizid=$_GET['quizid'];
            $validator = Validator::make($request->all(),[
                'quizid' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            
            $quizFor = QuizFor::select(DB::raw('COUNT(quesid) as count'),'type','teacherid')
            ->join('quizquestions','quizquestions.quizid','quizfor.quizid')
            ->where('quizfor.quizid',$request->quizid)->first();
            if($quizFor){
                return customResponse(1,["details"=>[$quizFor->count,$quizFor->type,$quizFor->teacherid]]);
            }
            return customResponse(0);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function getQuizes(Request $request){
        try{
            // $companyid=$_GET['companyid'];//$_GET['studentid']
            $validator = Validator::make($request->all(),[
                'companyid' => 'required',
                'studentid' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            $pageLimit = pageLimit(@$request->page);
            $stuClass = getClassFromAccountID($request->studentid, $request->companyid);
            $quizs = QuizFor::where([['companyid',$request->companyid],['class',$stuClass->class]])
                ->orderByDesc('dateposted')->offset($pageLimit->offset)->limit($pageLimit->limit)->get();
            return customResponse(1,["list"=>$quizs]);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function quizQuestions(Request $request){
        try{
            // quizid='".$_GET['quizid']."'
            $validator = Validator::make($request->all(),[
                'quizid' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }

            $questions = QuizQuestion::where('quizid',$request->quizid)->orderByAsc('quesid')->get();
            return customResponse(1,["list"=>$questions]);
        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function quizTeacherSee(Request $request){
        try{
            // $companyid=$_GET['companyid'];//$_GET['teacherid']
            $validator = Validator::make($request->all(),[
                'companyid' => 'required',
                'teacherid' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }

            $pageLimit = pageLimit(@$request->page);
            
            $quizs = QuizFor::where([['companyid',$request->companyid],['teacherid',$request->teacherid]])
                ->orderByDesc('dateposted')->offset($pageLimit->offset)->limit($pageLimit->limit)->get();
            return customResponse(1,["list"=>$quizs]);

        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    public function quizVisibilty(Request $request){
        try{
            // $quizid=$_POST['quizid'];
            $validator = Validator::make($request->all(),[
                'quizid' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            QuizFor::where('quizid',$request->quizid)->update(['visibility'=>1]);
            return customResponse(1,["msg"=>"visible done."]);

        }catch(\Exception $e){
            return exceptionResponse($e);
        }
        
    }

    public function postQuizScore(Request $request){
        try{
            // $quizid = $_POST['quizid'];
            // $studentid=$_POST['studentid'];
            // $score=$_POST['score'];
            $validator = Validator::make($request->all(),[
                'quizid' => 'required',
                'studentid' => 'required',
                'score' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(validatorMessage($validator));
            }
            $quizscore = [
                'quizid'=> $request->quizid,
                'studentid'=> $request->studentid,
                'score'=> $request->score,
                'entrydate'=> now(),
            ];
            QuizAttempt::create($quizscore);
            return customResponse(1);

        }catch(\Exception $e){
            return exceptionResponse($e);
        }
    }

    
}
