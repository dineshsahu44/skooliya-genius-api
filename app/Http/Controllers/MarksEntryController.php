<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Section;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\FacultyAssignClass;
use App\Models\SchoolSession;
use App\Models\Registration;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Guardian;
use App\Models\Holiday;
use App\Models\StudentFeeDetail;
use App\Models\FeeTransection;
use App\Models\School;
use App\Models\ClassSetting;
use App\Models\Mark;
use Carbon\Carbon;

use DB;
use Validator;

class MarksEntryController extends Controller
{
    public function classAndSubjectwiseMarksEntry(Request $request){
        $auth_token = $request->header('Authorization');
        $companyid = $request->companyid;
        $accountid = $request->accountid;
        if($request->isMethod('get')) {
            // Create a request with the required parameters
            $new_request = new Request([
                'companyid' => $companyid,
                'accountid' => $accountid,
            ]);
            // Call the getAllClasses function from the StaticController
            $staticController = app(StaticController::class); 
            $assigned_class = $staticController->getAllClassesWithNumeric($new_request);//$staticController->getAllClasses($new_request);            
            $mapedSubject = getMapedSubject($companyid);
            $mapedCoSubject = getMapedCoScholasticSubject($companyid);
            // return $mapedSubject;
            $termAssessment = json_encode($this->getTermAssessment($companyid));
            
            return view("marks-entry", compact("assigned_class","auth_token","mapedSubject","mapedCoSubject","termAssessment"));
        }elseif($request->isMethod('post')){
            if($request->operation=='get'){
                $returnData = $this->getStudentsMarks($request->all());
                $data['studentRecord'] = $returnData['students'];
                $data['classSetting'] = $returnData['classSetting'];
                return response()->json(['success' => 1, 'msg' => "Successfully fetched!", 'data' => $data]);
            }elseif($request->operation=='save'){
                // return $request->all();

                $school = getSchoolIdBySessionID($request->companyid);
                $active_session_id = $request->companyid;// Get active session ID from your logic;
                $school_id = $school->school_id;// Get school ID from your logic;

                $conditions = [
                    'ClassID' => $request->input('class'),
                    'SectionID' => $request->input('section'),
                    'SubjectID' => $request->input('subject'),
                    'TermID' => $request->input('term'),
                    'AssessmentID' => $request->input('assessment'),
                    'SessionID' => $active_session_id,
                    'SchoolID' => $school_id,
                    'TransactionType' => 'Marks',
                ];

                // Find and delete the record
                Mark::where($conditions)->delete();

                $insertRecords = [];
                foreach ($request->input('studentRecord') as $s) {
                    $insertRecords[] = [
                        'ClassID' => $request->input('class'),
                        'SectionID' => $request->input('section'),
                        'SubjectID' => $request->input('subject'),
                        'TermID' => $request->input('term'),
                        'AssessmentID' => $request->input('assessment'),
                        'StudentRegID' => $s['id'],
                        'AppID' => $s['Appid'],
                        'ObtMarks' => !empty($s['ObtMarks']) ? $s['ObtMarks'] : null,
                        'Remarks' => !empty($s['Remarks']) ? $s['Remarks'] : null,
                        'IsAbsent' => !empty($s['IsAbsent']) ? $s['IsAbsent'] : null,
                        'PassMarks' => $request->input('PassMarks'),
                        'MaxMarks' => $request->input('MaxMarks'),
                        'TransactionType' => 'Marks',
                        'SessionID' => $active_session_id,
                        'SchoolID' => $school_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'operated_by' => $request->accountid,
                    ];
                }

                if (Mark::insert($insertRecords)) {
                    $returnData = $this->getStudentsMarks($request->all());
                    $data['studentRecord'] = $returnData['students'];
                    $data['classSetting'] = $returnData['classSetting'];
                    return response()->json(['success' => 1, 'msg' => "Successfully inserted!", 'data' => $data]);
                } else {
                    return response()->json(['success' => 2, 'msg' => "Something went wrong!"]);
                }
            }
            return response()->json(['success' => 0, 'msg' => "Invalid request or operation not supported."]);
        }
    }

    public function getStudentsMarks($data)
    {
        $active_session_id = $data['companyid'];// Get active session ID from your logic;
        
        $students = Registration::join('subject_students', 'subject_students.StudentRegID', '=', 'registrations.id')
            ->join('guardians','guardians.re_id','registrations.id')
            ->join('subjects', 'subjects.id', '=', 'subject_students.SubjectID')
            ->leftJoin('marks', function ($join) use ($data) {
                $join->on('marks.StudentRegID', '=', 'registrations.id')
                    ->on('marks.SubjectID', '=', 'subjects.id')
                    ->on('marks.ClassID', '=', 'registrations.class')
                    ->on('marks.SectionID', '=', 'registrations.section')
                    ->on('marks.SessionID', '=', 'registrations.session')
                    ->where('marks.TermID', '=', $data['term'])
                    ->where('marks.AssessmentID', '=', $data['assessment'])
                    ->where('marks.TransactionType', '=', 'Marks');
            })
            ->where('registrations.session', $active_session_id)
            ->where('registrations.status', 'Active')
            ->where('registrations.class', $data['class'])
            ->where('registrations.section', $data['section'])
            ->where('subjects.id', $data['subject'])
            ->select([
                'registrations.name',
                'registrations.id',
                'registrations.roll_no',
                'registrations.registration_id',
                'registrations.scholar_id',
                'subjects.id as subject_id',
                'subjects.subject_name',
                'registrations.class',
                'registrations.section',
                DB::raw('COALESCE(f_name, "") as fathername'),
                DB::raw('COALESCE(m_name, "") as mothername'),
                DB::raw('IF(marks.ObtMarks IS NOT NULL, marks.ObtMarks, "") AS ObtMarks'),
                DB::raw('IF(marks.Remarks IS NOT NULL, marks.Remarks, "") AS Remarks'),
                'marks.IsAbsent',
            ])
            ->orderBy('registrations.roll_no', 'asc')
            ->get();

        $classSetting = ClassSetting::join('assessments', 'assessments.id', '=', 'class_settings.AssessmentID')
            ->join('subjects', 'subjects.id', '=', 'class_settings.SubjectID')
            ->where('class_settings.SessionID', $active_session_id)
            ->where('class_settings.ClassID', $data['class'])
            ->where('class_settings.SectionID', $data['section'])
            ->where('class_settings.TermID', $data['term'])
            ->where('class_settings.AssessmentID', $data['assessment'])
            ->where('class_settings.SubjectID', $data['subject'])
            ->select([
                'class_settings.ID',
                'assessments.assessment',
                'subjects.subject_name',
                'class_settings.MinMarks',
                'class_settings.MaxMarks',
                'class_settings.TermID',
            ])
            ->first();

        return ['students' => $students, 'classSetting' => $classSetting];
    }

    function getTermAssessment($companyid){
        // $reportSettings = DB::select(
        //     "SELECT
        //         SectionID,
        //         JSON_OBJECTAGG(
        //             TermID,
        //             JSON_OBJECT(
        //                 'Term', JSON_OBJECT('id', TermID, 'term', term),
        //                 'Assessment', AssessmentData
        //             )
        //         ) AS SectionData
        //     FROM (
        //         SELECT
        //             SectionID,
        //             TermID,
        //             terms.term,
        //             CONCAT('[',GROUP_CONCAT( DISTINCT JSON_OBJECT('AssessmentID', AssessmentID, 'AssessmentName', assessments.assessment)),']') AS AssessmentData
        //         FROM
        //             report_settings
        //         INNER JOIN
        //             terms ON terms.id = report_settings.TermID
        //         INNER JOIN
        //             assessments ON assessments.id = report_settings.AssessmentID
        //         WHERE
        //             SessionID = $companyid 
        //         GROUP BY
        //             SectionID, TermID
        //     ) AS Subquery
        //     GROUP BY
        //         SectionID;
        // ");

        $examSettings = DB::select(
            "SELECT
                SectionID,
                JSON_OBJECTAGG(
                    TermID,
                    JSON_OBJECT(
                        'Term', JSON_OBJECT('id', TermID, 'term', term),
                        'Assessment', AssessmentData
                    )
                ) AS SectionData
            FROM (
                SELECT
					exam_settings.section_id as SectionID,
					exam_settings.term_id as TermID,
					terms.term,
					CONCAT('[',GROUP_CONCAT( DISTINCT JSON_OBJECT('AssessmentID', exam_settings.assessment_id, 'AssessmentName', assessments.assessment)),']') AS AssessmentData
				FROM
					exam_settings
				INNER JOIN
					terms ON terms.id = exam_settings.term_id
				INNER JOIN
					assessments ON assessments.id = exam_settings.assessment_id
				WHERE
                    exam_settings.session_id = $companyid
				GROUP BY
					exam_settings.section_id, exam_settings.term_id
            ) AS Subquery
            GROUP BY
                SectionID;
        ");

        $reportSettings = $examSettings;

        $termAssessment = [];
        foreach($reportSettings as $r){
            $r = (array)$r;
            $temp = [];
            $SectionData = json_decode($r['SectionData'], true);
            foreach($SectionData as $k=>$s){
                $temp[$k]=[
                    'Term'=>$s['Term'],
                    'Assessment'=>json_decode($s['Assessment'], true)
                ];
            }
            $termAssessment[$r['SectionID']] = $temp;//$temp;
        }
        return $termAssessment;
    }
}
