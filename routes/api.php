<?php

use App\Http\Controllers\Admin\CourseInstanceController;
use App\Http\Controllers\Admin\CourseTemplateController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Teacher\CourseInstanceController as TeacherCourseInstanceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Semua route di sini otomatis memiliki prefix "/api".
|
*/

/**
 * Route bebas, untuk cek API & CORS.
 * GET /api/ping
 */
Route::get('/ping', function () {
    return response()->json([
        'message' => 'pong',
        'app' => config('app.name'),
        'time' => now()->toDateTimeString(),
    ]);
});

/**
 * AUTH ROUTES
 */
Route::prefix('auth')->group(function () {
    // Tanpa auth
    Route::post('/login', [AuthController::class, 'login']);

    // Butuh token Sanctum
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);

        Route::middleware('role:superadmin')->get('/test-superadmin', function (Request $request) {
            return response()->json([
                'message' => 'Hello, Superadmin!',
                'user' => [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'username' => $request->user()->username,
                    'role' => $request->user()->role,
                ],
            ]);
        });
    });
});

/**
 * ROUTE KHUSUS SUPERADMIN - Manajemen User, Template, & Course Instance
 *
 * Prefix: /api/admin/...
 * Proteksi:
 *  - auth:sanctum
 *  - role:superadmin
 */
Route::middleware(['auth:sanctum', 'role:superadmin'])
    ->prefix('admin')
    ->group(function () {
        /**
         * Manajemen User
         */
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{id}', [AdminUserController::class, 'show']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::put('/users/{id}', [AdminUserController::class, 'update']);
        Route::post('/users/{id}/reset-password', [AdminUserController::class, 'resetPassword']);
        Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);

        /**
         * Manajemen Course Template
         */
        Route::get('/course-templates', [CourseTemplateController::class, 'index']);
        Route::post('/course-templates', [CourseTemplateController::class, 'store']);
        Route::get('/course-templates/{id}', [CourseTemplateController::class, 'show']);
        Route::put('/course-templates/{id}', [CourseTemplateController::class, 'update']);
        Route::delete('/course-templates/{id}', [CourseTemplateController::class, 'destroy']);

        /**
         * Manajemen Course Instance (kelas per semester) oleh Superadmin
         */
        // List semua kelas
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

/**
 * ROUTE KHUSUS DOSEN - Kelas yang diampu & kontrol status kelas (draft <-> active)
 *
 * Prefix: /api/teacher/...
 * Proteksi:
 *  - auth:sanctum
 *  - role:dosen
 */
Route::middleware(['auth:sanctum', 'role:dosen'])
    ->prefix('teacher')
    ->group(function () {
        // List semua kelas yang diampu dosen login
        // GET /api/teacher/course-instances
        Route::get('/course-instances', [TeacherCourseInstanceController::class, 'index']);

        // Dosen mengubah status kelas (hanya draft <-> active)
        // POST /api/teacher/course-instances/{id}/status
        Route::post('/course-instances/{id}/status', [TeacherCourseInstanceController::class, 'updateStatus']);
    });

/**
 * Route protected untuk test Sanctum (opsional, buat debugging).
 * GET /api/sanctum-test
 */
Route::middleware('auth:sanctum')->get('/sanctum-test', function (Request $request) {
    return response()->json([
        'ok' => true,
        'message' => 'Sanctum protected route works.',
        'user' => $request->user(),
    ]);
});
