<?php

use App\Http\Controllers\Admin\CourseInstanceController;
use App\Http\Controllers\Admin\CourseTemplateController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\Student\AssignmentSubmissionController as StudentAssignmentSubmissionController;
use App\Http\Controllers\Student\GradeController as StudentGradeController;
use App\Http\Controllers\Student\QuizAttemptController as StudentQuizAttemptController;
use App\Http\Controllers\Teacher\AssignmentSubmissionController as TeacherAssignmentSubmissionController;
use App\Http\Controllers\Teacher\CourseInstanceController as TeacherCourseInstanceController;
use App\Http\Controllers\Teacher\GradeController as TeacherGradeController;
use App\Http\Controllers\Teacher\QuizAttemptController as TeacherQuizAttemptController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/ping', function () {
    return response()->json(['message' => 'LMS Backend is running.']);
});

/*
|--------------------------------------------------------------------------
| AUTH ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('auth:sanctum');
});

/*
|--------------------------------------------------------------------------
| SANCTUM TEST ROUTE
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('/sanctum-test', function (Request $request) {
    return response()->json([
        'message' => 'Sanctum token active.',
        'user' => $request->user(),
    ]);
});

/*
|--------------------------------------------------------------------------
| SUPERADMIN ROUTES
|--------------------------------------------------------------------------
|
| Prefix: /api/admin/*
| Middleware: auth:sanctum + role:superadmin
|
*/

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'role:superadmin'])
    ->group(function () {
        // User management
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::get('/users/{id}', [AdminUserController::class, 'show']);
        Route::put('/users/{id}', [AdminUserController::class, 'update']);
        Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);
        Route::post('/users/{id}/reset-password', [AdminUserController::class, 'resetPassword']);

        // Course templates
        Route::get('/course-templates', [CourseTemplateController::class, 'index']);
        Route::post('/course-templates', [CourseTemplateController::class, 'store']);
        Route::get('/course-templates/{id}', [CourseTemplateController::class, 'show']);
        Route::put('/course-templates/{id}', [CourseTemplateController::class, 'update']);
        Route::delete('/course-templates/{id}', [CourseTemplateController::class, 'destroy']);

        // Course instances (kelas per semester)
        Route::get('/course-instances', [CourseInstanceController::class, 'index']);
        Route::post('/course-instances', [CourseInstanceController::class, 'store']);
        Route::get('/course-instances/{id}', [CourseInstanceController::class, 'show']);
        Route::put('/course-instances/{id}', [CourseInstanceController::class, 'update']);
        Route::post('/course-instances/{id}/status', [CourseInstanceController::class, 'updateStatus']);
        Route::delete('/course-instances/{id}', [CourseInstanceController::class, 'destroy']);

        // Enrollment management
        Route::get('/course-instances/{id}/students', [CourseInstanceController::class, 'students']);
        Route::post('/course-instances/{id}/students', [CourseInstanceController::class, 'addStudent']);
        Route::delete('/course-instances/{id}/students/{studentId}', [CourseInstanceController::class, 'removeStudent']);
    });

/*
|--------------------------------------------------------------------------
| TEACHER ROUTES
|--------------------------------------------------------------------------
|
| Prefix: /api/teacher/*
| Middleware: auth:sanctum + role:dosen
|
*/

Route::prefix('teacher')
    ->middleware(['auth:sanctum', 'role:dosen'])
    ->group(function () {

        // List kelas yang diampu dosen login
        Route::get('/course-instances', [TeacherCourseInstanceController::class, 'index']);

        // Dosen mengubah status kelas (draft ↔ active) miliknya
        Route::post('/course-instances/{id}/status', [TeacherCourseInstanceController::class, 'updateStatus']);

        // Assignment submissions
        Route::get('/assignments/{assignment}/submissions', [TeacherAssignmentSubmissionController::class, 'index']);
        Route::post('/assignment-submissions/{submission}/grade', [TeacherAssignmentSubmissionController::class, 'grade']);

        // Quiz attempts
        Route::get('/quizzes/{quiz}/attempts', [TeacherQuizAttemptController::class, 'index']);

        // Grades per kelas
        Route::get('/course-instances/{courseInstance}/grades', [TeacherGradeController::class, 'courseGrades']);
    });

/*
|--------------------------------------------------------------------------
| STUDENT ROUTES
|--------------------------------------------------------------------------
|
| Prefix: /api/student/*
| Middleware: auth:sanctum + role:mahasiswa
|
*/

Route::prefix('student')
    ->middleware(['auth:sanctum', 'role:mahasiswa'])
    ->group(function () {

        // Mahasiswa submit / resubmit tugas
        Route::post('/assignments/{assignment}/submit', [StudentAssignmentSubmissionController::class, 'submit']);

        // Mahasiswa memulai quiz
        Route::post('/quizzes/{quiz}/start', [StudentQuizAttemptController::class, 'start']);

        // Mahasiswa submit jawaban quiz
        Route::post('/quiz-attempts/{attempt}/submit', [StudentQuizAttemptController::class, 'submit']);

        // Mahasiswa lihat hasil quiz (semua attempt untuk satu quiz)
        Route::get('/quizzes/{quiz}/attempts', [StudentQuizAttemptController::class, 'myAttempts']);

        // Mahasiswa lihat grades satu kelas
        Route::get('/course-instances/{courseInstance}/grades', [StudentGradeController::class, 'courseGrades']);
    });

/*
|--------------------------------------------------------------------------
| SHARED ROUTES (SUPERADMIN + DOSEN)
|--------------------------------------------------------------------------
|
| Endpoint yang bisa diakses oleh superadmin dan dosen.
| Termasuk CRUD Section, Material, Assignment, dan Quiz di CourseInstance.
|
*/

Route::middleware(['auth:sanctum', 'role:superadmin,dosen'])
    ->group(function () {

        /*
         * Sections per course instance
         */
        Route::get('/course-instances/{courseInstance}/sections', [SectionController::class, 'index']);
        Route::post('/course-instances/{courseInstance}/sections', [SectionController::class, 'store']);
        Route::put('/sections/{section}', [SectionController::class, 'update']);
        Route::delete('/sections/{section}', [SectionController::class, 'destroy']);

        /*
         * Materials per section
         */
        Route::get('/sections/{section}/materials', [MaterialController::class, 'index']);
        Route::post('/sections/{section}/materials', [MaterialController::class, 'store']);
        Route::get('/materials/{material}', [MaterialController::class, 'show']);
        Route::put('/materials/{material}', [MaterialController::class, 'update']);
        Route::delete('/materials/{material}', [MaterialController::class, 'destroy']);

        /*
         * Assignments per section
         */
        Route::get('/sections/{section}/assignments', [AssignmentController::class, 'index']);
        Route::post('/sections/{section}/assignments', [AssignmentController::class, 'store']);
        Route::get('/assignments/{assignment}', [AssignmentController::class, 'show']);
        Route::put('/assignments/{assignment}', [AssignmentController::class, 'update']);
        Route::delete('/assignments/{assignment}', [AssignmentController::class, 'destroy']);

        /*
         * Quizzes per section
         */
        Route::get('/sections/{section}/quizzes', [QuizController::class, 'index']);
        Route::post('/sections/{section}/quizzes', [QuizController::class, 'store']);
        Route::get('/quizzes/{quiz}', [QuizController::class, 'show']);
        Route::put('/quizzes/{quiz}', [QuizController::class, 'update']);
        Route::delete('/quizzes/{quiz}', [QuizController::class, 'destroy']);
    });

/*
|--------------------------------------------------------------------------
| MATERIAL FILE URL (ALL AUTHENTICATED ROLES)
|--------------------------------------------------------------------------
|
| Endpoint untuk ambil URL file material (dipakai frontend untuk download).
|
*/

Route::middleware('auth:sanctum')->get('/materials/{material}/file-url', [MaterialController::class, 'fileUrl']);
