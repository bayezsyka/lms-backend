<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /**
         * Definisikan rate limiter "api".
         *
         * Ini dipakai oleh middleware group "api" secara default.
         * Kalau tidak ada, Laravel akan melempar:
         *   MissingRateLimiterException: Rate limiter [api] is not defined.
         */
        RateLimiter::for('api', function (Request $request) {
            // 60 request per menit per user (kalau login) atau per IP (kalau guest)
            return [
                Limit::perMinute(60)->by(
                    optional($request->user())->id ?: $request->ip()
                ),
            ];
        });
    }
}
