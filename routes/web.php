<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImportDatabaseController;
use App\Http\Controllers\AttendanceController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::any('/', function(){
    return view('welcome');
});
Route::any('/import-database', function(){
    return view('import-database');
});//setSchoolDatabase
Route::get('/linkstorage', function () {
    Artisan::call('storage:link');
});
Route::post('/import-database/setSchoolDatabase', [ImportDatabaseController::class,'setSchoolDatabase']);//setSchoolDatabase

Route::middleware('checkdatabase')->group(function(){
    Route::any('/import-database/importCompanyToSchoolAndSchoolSessions', [ImportDatabaseController::class,'importCompanyToSchoolAndSchoolSessions']);
    Route::any('/import-database/importAdmissionToClassesAndSections', [ImportDatabaseController::class,'importAdmissionToClassesAndSections']);
    Route::any('/import-database/importTeachersToFacultiesAndUsers', [ImportDatabaseController::class,'importTeachersToFacultiesAndUsers']);
    Route::any('/import-database/importAdmissionToRegistrationsAndGuardians', [ImportDatabaseController::class,'importAdmissionToRegistrationsAndGuardians']);
    Route::any('/import-database/importAdmissionToUniqueUsers', [ImportDatabaseController::class,'importAdmissionToUniqueUsers']);
    Route::any('/import-database/importAttendanceToAttendances', [ImportDatabaseController::class,'importAttendanceToAttendances']);
    
    Route::any('/import-database/importAlbums', [ImportDatabaseController::class,'importAlbums']);
    Route::any('/import-database/importBirthdayCard', [ImportDatabaseController::class,'importBirthdayCard']);
    Route::any('/import-database/importComment', [ImportDatabaseController::class,'importComment']);
    Route::any('/import-database/importEvents', [ImportDatabaseController::class,'importEvents']);
    Route::any('/import-database/importFeedback', [ImportDatabaseController::class,'importFeedback']);
    Route::any('/import-database/importHoliday', [ImportDatabaseController::class,'importHoliday']);
    Route::any('/import-database/importHwmessage', [ImportDatabaseController::class,'importHwmessage']);
    Route::any('/import-database/importHwmessageFor', [ImportDatabaseController::class,'importHwmessageFor']);
    Route::any('/import-database/importLiveBanner', [ImportDatabaseController::class,'importLiveBanner']);
    Route::any('/import-database/importLiveBannerFor', [ImportDatabaseController::class,'importLiveBannerFor']);
    Route::any('/import-database/importLiveClasses', [ImportDatabaseController::class,'importLiveClasses']);
    Route::any('/import-database/importLiveClassesFor', [ImportDatabaseController::class,'importLiveClassesFor']);
    Route::any('/import-database/importLiveDocs', [ImportDatabaseController::class,'importLiveDocs']);
    Route::any('/import-database/importLiveExam', [ImportDatabaseController::class,'importLiveExam']);
    Route::any('/import-database/importLiveExamFor', [ImportDatabaseController::class,'importLiveExamFor']);
    Route::any('/import-database/importLiveSession', [ImportDatabaseController::class,'importLiveSession']);
    Route::any('/import-database/importLiveSessionFor', [ImportDatabaseController::class,'importLiveSessionFor']);
    Route::any('/import-database/importMainScreenOptions', [ImportDatabaseController::class,'importMainScreenOptions']);
    Route::any('/import-database/importPhotosVideos', [ImportDatabaseController::class,'importPhotosVideos']);
    Route::any('/import-database/importQuizAttempt', [ImportDatabaseController::class,'importQuizAttempt']);
    Route::any('/import-database/importQuizFor', [ImportDatabaseController::class,'importQuizFor']);
    Route::any('/import-database/importQuizquestions', [ImportDatabaseController::class,'importQuizquestions']);
    Route::any('/import-database/setSchoolDatabase', [ImportDatabaseController::class,'setSchoolDatabase']);
});

// Route::get('/jexcel', function () {
//     return view('jexcel-test');
// });
// Route::get('/excel-test1', function () {
//     return view('excel-test1');
// });
Route::get('logs', [\Rap2hpoutre\LaravelLogViewer\LogViewerController::class, 'index']);
Route::any('/parse-log', [AttendanceController::class,'logtojson']);