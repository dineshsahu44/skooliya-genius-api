<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\OnilneActivityController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\EventGalleryController;
use App\Http\Controllers\StaticController;
use App\Http\Controllers\FeesController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\FacultyController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DeleteController;
use App\Http\Controllers\OfflineApiController;
use App\Http\Controllers\AttendanceController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::any('/', function(){
    return view('welcome');
});

Route::any('/autoattendance', [StaticController::class,'autoAttendance']);
Route::any('/hw/gsdt', [StaticController::class,'setEpt']);
Route::get('/takeatour.php', [StaticController::class,'takeATour']);

Route::middleware('checkurl')->group(function(){
    Route::post('/loginapi.php', [AuthController::class,'login']);
    Route::post('/tokenhandleapi.php', [AuthController::class,'tokenHandleApi']);
    Route::any('/fee-day-book',[FeesController::class,'feesDayBook']);
    Route::any('/fees-details',[FeesController::class,'feesDetails']);
    Route::get('/studentfeecard.php',[FeesController::class,'studentFeeCard']);
    Route::any('/faculty-attendace',[AttendanceController::class,'facultyAttendace']);

    Route::any('machine-attendance',[AttendanceController::class,'machineAttendance']);
    Route::group(['middleware' => 'auth.verify'], function(){
        Route::post('/checkuspassapi.php', [AuthController::class,'checkUsPass']);
        Route::post('/changepasswordapi.php', [AuthController::class,'changePassword']);
        
        
        Route::post('/createmessageapi.php', [NotificationController::class,'createMessage']);
        Route::get('/messageseeapi.php', [NotificationController::class,'getMessage']);

        Route::any('/onlineclass/banner.php', [BannerController::class,'banner']);
        Route::any('/onlineclass/classes.php', [OnilneActivityController::class,'onlineActivity']);

        Route::post('/addquizquestions.php', [QuizController::class,'addQuestion']);
        Route::post('/createquizid.php', [QuizController::class,'createQuiz']);
        Route::get('/getquizscoreapi.php', [QuizController::class,'getQuizScore']);
        Route::get('/quizcheckapi.php', [QuizController::class,'quizCheck']);
        Route::get('/quizesapi.php', [QuizController::class,'getQuizes']);
        Route::get('/quizquestionsapi.php', [QuizController::class,'quizQuestions']);
        Route::get('/quizteacherseeapi.php', [QuizController::class,'quizTeacherSee']);
        Route::post('/quizvisibiltyapi.php', [QuizController::class,'quizVisibilty']);
        Route::post('/postquizscoreapi.php', [QuizController::class,'postQuizScore']);

        Route::post('/createalbumapi.php', [EventGalleryController::class,'createAlbum']);
        Route::get('/albumsapi.php', [EventGalleryController::class,'getAlbums']);
        Route::post('/addvideoapi.php', [EventGalleryController::class,'addAlbumVideo']);
        Route::post('/uploadphotosapi.php', [EventGalleryController::class,'addAlbumPhotos']);
        Route::get('/photovideoapi.php', [EventGalleryController::class,'getGalleryItem']);
        Route::post('/createeventapi.php', [EventGalleryController::class,'createEvent']);
        Route::get('/eventsapi.php', [EventGalleryController::class,'getEvents']);
        
        Route::get('/allclasssection.php', [StaticController::class,'getAllClasses']);
        Route::post('/createfeedbackapi.php', [StaticController::class,'createFeedback']);
        Route::get('/getfeedbackapi.php', [StaticController::class,'getFeedback']);
        Route::get('/getbirthdaycardapi.php', [StaticController::class,'getBirthdayCard']);
        Route::get('/getbusesinfoapi.php', [StaticController::class,'getBuses']);
        Route::get('/getbusstudentapi.php', [StaticController::class,'getBusInfo']);
        Route::get('/getholidaysapi.php', [StaticController::class,'getHolidays']);
        Route::post('/holidaycreatedeleteapi.php', [StaticController::class,'createDeleteHoliday']);
        Route::get('/schoolinfo.php', [StaticController::class,'schoolProfile']);

        Route::get('/feecollectionapi.php', [FeesController::class,'feeCollection']);
        Route::get('/feesvoucherapi.php', [FeesController::class,'feesVoucher']);

        // Route::get('/getallstudentsapi.php', [StudentController::class,'getStudents']);//in this old api only one different that is status of student is all active and deactivate
        Route::get('/getstudentsapi.php', [StudentController::class,'getStudents']);
        Route::get('/studentprofileapi.php', [StudentController::class,'studentProfile']);
        Route::post('/addupdatestudent.php', [StudentController::class,'updateStudent']);
        Route::any('/attandancetakeclasswise.php', [StudentController::class,'markAttendance']);
        Route::get('/attendanceapi.php', [StudentController::class,'getStudentAttendance']);
        Route::get('/attendancecount.php', [StudentController::class,'getAttendanceCount']);
        Route::get('/attendancereport.php', [StudentController::class,'getAttendanceReport']);

        Route::get('/getteacherapi.php', [FacultyController::class,'getTeachers']);
        Route::post('/uploadprofilepicapi.php', [FacultyController::class,'updateFacultyPhoto']);
        Route::post('/changepermissionapi.php', [FacultyController::class,'changeFacultyPermission']);
        Route::get('/teacherpermissionapi.php', [FacultyController::class,'facultyPermission']);
        Route::post('/comment/comment.php', [CommentController::class,'comments']);
        Route::post('/deleteapi.php', [DeleteController::class,'deleteRecord']);
        Route::get('/msgsendtoapi.php', [NotificationController::class,'msgSendToRecord']);
        

        // Route::get('/fee-day-book',[FeesController::class,'feesDayBook']);

        Route::get('/studentreportcard.php',function(){
            return "Coming Soon";
        });
        Route::get('/leaverequest',function(){
            $v = 'Please Reload Data Follow These Steps<br><img src="https://p7.hiclipart.com/preview/35/345/173/hamburger-button-menu-bar-computer-icons-horizontal-line.jpg" width="30", height="30"> <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/5/58/Font_Awesome_5_solid_arrow-right.svg/1200px-Font_Awesome_5_solid_arrow-right.svg.png" width="30" height="30"> Reload Data';
            return $v;
        });
        Route::get('/parentsleaverequest',function(){
            $v = 'Please Reload Data Follow These Steps<br><img src="https://p7.hiclipart.com/preview/35/345/173/hamburger-button-menu-bar-computer-icons-horizontal-line.jpg" width="30", height="30"> <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/5/58/Font_Awesome_5_solid_arrow-right.svg/1200px-Font_Awesome_5_solid_arrow-right.svg.png" width="30" height="30"> Reload Data';
            return $v;
        });
        Route::get('/parentsattendance',function(){
            return "Coming Soon";
        });
        // Route::post('/allclasssection.php', [QuizController::class,'postQuizScore']);createfeedbackapi.php
        // 
    });
});
Route::group(['prefix' => "offline/{schoolname}", 'middleware' => 'checkofflineapi'], function () {
    Route::any('/admission.php',function(){
        return "Coming Soon";//OfflineApiController
    });
    Route::any('/attendanceclasswise.php',function(){
        return "Coming Soon";
    });
    Route::any('/attendancestudentwise.php',function(){
        return "Coming Soon";
    });
    Route::any('/check.php',function(){
        return "Coming Soon";
    });
    Route::any('/enquiry.php',function(){
        return "Coming Soon";
    });
    Route::any('/feestransaction.php',function(){
        return "Coming Soon";
    });
    Route::any('/feesvoucher.php',function(){
        return "Coming Soon";
    });
    Route::any('/fileuploads.php',function(){
        return "Coming Soon";
    });
    
    Route::any('/jsonbus.php',function(){
        return "Coming Soon";
    });
    
    Route::any('/jsoncompany.php',[OfflineApiController::class,'company']);
    Route::any('/delcomp.php',[OfflineApiController::class,'deleteCompany']);
    Route::any('/pic.php',function(){
        return "Coming Soon";
    });
    
    Route::any('/setting.php',function(){
        return "Coming Soon";
    });
    
    Route::any('/teachers.php',function(){
        return "Coming Soon";
    });
});