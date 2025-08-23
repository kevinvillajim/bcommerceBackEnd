<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web([
            \App\Http\Middleware\CheckExpiredFeaturedSellers::class,
        ]);

        $middleware->api([
            \App\Http\Middleware\CriticalErrorLoggingMiddleware::class, // Move to first position
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\CheckExpiredFeaturedSellers::class,
        ]);

        // Register route middleware aliases
        $middleware->alias([
            'seller' => \App\Http\Middleware\SellerMiddleware::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'jwt.auth' => \App\Http\Middleware\JwtMiddleware::class,
            'verify.email' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'product.interaction' => \App\Http\Middleware\ProductInteractionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
