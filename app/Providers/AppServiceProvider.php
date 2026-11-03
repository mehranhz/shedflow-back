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
        RateLimiter::for('customer-auth-login', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email', '')));

            return Limit::perMinutes(15, 10)->by($request->ip().'|'.$email);
        });

        RateLimiter::for('customer-auth-claim', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email', '')));

            return Limit::perMinutes(15, 10)->by($request->ip().'|'.$email);
        });

        RateLimiter::for('customer-portal-mutations', function (Request $request) {
            $userId = $request->user()?->id ?? 'guest';

            return Limit::perMinutes(15, 30)->by($request->ip().'|'.$userId);
        });
    }
}
