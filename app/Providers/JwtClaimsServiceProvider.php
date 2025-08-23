<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;
use Tymon\JWTAuth\Claims\Factory as ClaimsFactory;

class JwtClaimsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(ClaimsFactory $claimsFactory)
    {
        // Override the 'exp' claim generation to ensure type safety
        $claimsFactory->extend('exp', function () {
            // Explicitly convert TTL to integer
            $ttl = (int) config('jwt.ttl', 60);

            // Ensure we're using integer minutes
            return Carbon::now()->addMinutes($ttl);
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
