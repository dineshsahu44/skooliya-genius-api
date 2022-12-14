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
Route::get('/', [StaticController::class,'show']);
Route::middleware('checkurl')->group(function(){
    Route::any('/loginapi.php', [AuthController::class,'login']);
    Route::group(['middleware' => 'auth.verify'], function(){
        Route::post('/checkuspassapi.php', [AuthController::class,'checkUsPass']);
        Route::post('/changepasswordapi.php', [AuthController::class,'changePassword']);

        Route::post('/createmessageapi.php', [NotificationController::class,'createMessage']);
        Route::get('/messageseeapi.php', [NotificationController::class,'getMessage']);

        Route::post('/onlineclass/banner.php', [BannerController::class,'banner']);
        Route::post('/onlineclass/classes.php', [OnilneActivityController::class,'onlineActivity']);

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

        Route::post('/comment/comment.php', [CommentController::class,'comments']);

        // Route::post('/allclasssection.php', [QuizController::class,'postQuizScore']);createfeedbackapi.php
        // 
    });
});