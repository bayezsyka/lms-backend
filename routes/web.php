<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Route di sini pakai middleware "web" dan biasanya untuk halaman HTML.
| Untuk project ini, kita cuma pakai route sederhana saja.
|
*/

/**
 * Halaman root (opsional).
 * Bisa nanti dipakai untuk landing page atau sekadar info.
 */
Route::get('/', function () {
    return response()->json([
        'message' => 'LMS Backend is running.',
    ]);
});

/**
 * Route "login" untuk fallback middleware auth saat request BUKAN JSON.
 *
 * Jadi kalau ada request ke route yang butuh auth
 * tapi user belum login dan request datang dari browser (Accept: text/html),
 * Laravel akan redirect ke route bernama "login" ini.
 */
Route::get('/login', function () {
    return response()->json([
        'message' => 'Unauthenticated. Please login via the frontend application.',
    ], 401);
})->name('login');
