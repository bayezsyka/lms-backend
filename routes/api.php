<?php

use App\Http\Controllers\Admin\CourseInstanceController;
use App\Http\Controllers\Admin\CourseTemplateController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\Teacher\CourseInstanceController as TeacherCourseInstanceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\QuizController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Semua route di sini otomatis memiliki prefix "/api".
| Definisi route di bawah ini akan melayani seluruh endpoint Backend LMS.
*/

/*
|--------------------------------------------------------------------------
| PUBLIC / DEBUG ROUTES
|--------------------------------------------------------------------------
*/

// Route ping sederhana: GET /api/ping
Route::get('/ping', function () {
    return response()->json([
        'ok' => true,
        'message' => 'LMS Backend is running.',
    ]);
});

/*
|--------------------------------------------------------------------------
| AUTH ROUTES
|--------------------------------------------------------------------------
|
| Prefix: /api/auth/*
|
*/

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });
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

        /*
         * Manajemen User
         */
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::get('/users/{id}', [AdminUserController::class, 'show']);
        Route::put('/users/{id}', [AdminUserController::class, 'update']);
        Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);
        Route::post('/users/{id}/reset-password', [AdminUserController::class, 'resetPassword']);

        /*
         * Course Templates
         */
        Route::get('/course-templates', [CourseTemplateController::class, 'index']);
        Route::post('/course-templates', [CourseTemplateController::class, 'store']);
        Route::get('/course-templates/{id}', [CourseTemplateController::class, 'show']);
        Route::put('/course-templates/{id}', [CourseTemplateController::class, 'update']);
        Route::delete('/course-templates/{id}', [CourseTemplateController::class, 'destroy']);

        /*
         * Course Instances (kelas per semester)
         */
        // List semua kelas dengan filter (semester, status, lecturer_id)
        Route::get('/course-instances', [CourseInstanceController::class, 'index']);

        // Buat kelas baru dari template
        Route::post('/course-instances', [CourseInstanceController::class, 'store']);

        // Detail satu kelas
        Route::get('/course-instances/{id}', [CourseInstanceController::class, 'show']);

        // Update info umum kelas
        Route::put('/course-instances/{id}', [CourseInstanceController::class, 'update']);

        // Ubah status kelas (draft → active → finished) oleh Superadmin
        Route::post('/course-instances/{id}/status', [CourseInstanceController::class, 'updateStatus']);

        // Manajemen mahasiswa di kelas
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
    });

/*
|--------------------------------------------------------------------------
| SHARED ROUTES (SUPERADMIN + DOSEN)
|--------------------------------------------------------------------------
|
| Endpoint yang bisa diakses oleh superadmin dan dosen.
| Termasuk CRUD Section di CourseInstance.
|
*/

Route::middleware(['auth:sanctum', 'role:superadmin,dosen'])
    ->group(function () {

        /*
         * Sections per course instance
         */
        Route::get('/course-instances/{courseInstance}/sections', [\App\Http\Controllers\SectionController::class, 'index']);
        Route::post('/course-instances/{courseInstance}/sections', [\App\Http\Controllers\SectionController::class, 'store']);
        Route::put('/sections/{section}', [\App\Http\Controllers\SectionController::class, 'update']);
        Route::delete('/sections/{section}', [\App\Http\Controllers\SectionController::class, 'destroy']);

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
        // List assignment di section
        Route::get('/sections/{section}/assignments', [AssignmentController::class, 'index']);

        // Buat assignment di section
        Route::post('/sections/{section}/assignments', [AssignmentController::class, 'store']);

        // Detail satu assignment
        Route::get('/assignments/{assignment}', [AssignmentController::class, 'show']);

        // Update assignment
        Route::put('/assignments/{assignment}', [AssignmentController::class, 'update']);

        // Hapus assignment
        Route::delete('/assignments/{assignment}', [AssignmentController::class, 'destroy']);

         /*
         * Quizzes per section
         */
        // List quiz di section
        Route::get('/sections/{section}/quizzes', [QuizController::class, 'index']);

        // Buat quiz di section
        Route::post('/sections/{section}/quizzes', [QuizController::class, 'store']);

        // Detail satu quiz
        Route::get('/quizzes/{quiz}', [QuizController::class, 'show']);

        // Update quiz
        Route::put('/quizzes/{quiz}', [QuizController::class, 'update']);

        // Hapus quiz
        Route::delete('/quizzes/{quiz}', [QuizController::class, 'destroy']);
    });

Route::middleware('auth:sanctum')->get('/materials/{material}/file-url', [MaterialController::class, 'fileUrl']);

/*
|--------------------------------------------------------------------------
| SANCTUM TEST ROUTE (DEBUG)
|--------------------------------------------------------------------------
|
| GET /api/sanctum-test
|
*/

Route::middleware('auth:sanctum')->get('/sanctum-test', function (Request $request) {
    return response()->json([
        'ok' => true,
        'message' => 'Sanctum protected route works.',
        'user' => $request->user(),
    ]);
});
