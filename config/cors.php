<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter([
        env('FRONTEND_URL', 'https://comersia.app'),
        // URLs de producción/staging (mismas URLs)
        'https://comersia.app',
        'https://www.comersia.app', 
        // URLs locales para desarrollo
        'http://localhost:3000',
        'http://localhost:3001', 
        'http://localhost:3002',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:3001',
        'http://127.0.0.1:3002',
        'https://localhost:3000',
        'https://127.0.0.1:3000',
    ]),

    'allowed_origins_patterns' => array_filter([
        env('CORS_ALLOWED_ORIGIN_PATTERN'),
        // Patrones flexibles para desarrollo
        '/^https?:\/\/localhost:\d+$/',                   // localhost con cualquier puerto
        '/^https?:\/\/127\.0\.0\.1:\d+$/',              // 127.0.0.1 con cualquier puerto
    ]),

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'Origin',
        'X-CSRF-TOKEN',
        'X-Socket-ID',
    ],

    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
    ],

    'max_age' => 0,

    'supports_credentials' => true,

];
