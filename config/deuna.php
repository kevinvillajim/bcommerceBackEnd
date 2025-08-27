<?php

return [

    /*
    |--------------------------------------------------------------------------
    | DeUna API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for DeUna payment gateway integration
    | Based on DeUna API V2 documentation
    |
    */

    'api_url' => env('DEUNA_API_URL', 'https://apim-qa-deuna.azure-api.net'),
    'api_key' => env('DEUNA_API_KEY'),
    'api_secret' => env('DEUNA_API_SECRET'),
    'webhook_secret' => env('DEUNA_WEBHOOK_SECRET'),
    'environment' => env('DEUNA_ENVIRONMENT', 'testing'),
    'webhook_url' => env('DEUNA_WEBHOOK_URL'),
    'point_of_sale' => env('DEUNA_POINT_OF_SALE', '462'),

    /*
    |--------------------------------------------------------------------------
    | Timeout Settings
    |--------------------------------------------------------------------------
    */

    'timeout' => 30,
    'connect_timeout' => 10,

    /*
    |--------------------------------------------------------------------------
    | Currency Settings
    |--------------------------------------------------------------------------
    */

    'default_currency' => 'USD',
    'supported_currencies' => [
        'USD',
        'PEN',
        'COP',
        'MXN',
        'CLP',
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Settings
    |--------------------------------------------------------------------------
    */

    'retry_attempts' => 3,
    'retry_delay' => 1000, // milliseconds

    /*
    |--------------------------------------------------------------------------
    | QR Code Settings
    |--------------------------------------------------------------------------
    */

    'qr_code' => [
        'size' => 300,
        'margin' => 4,
        'format' => 'png',
    ],

];
