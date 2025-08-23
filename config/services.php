<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'shipping_api' => [
        'url' => env('SHIPPING_API_URL', 'https://api.shipping-service.example.com'),
        'key' => env('SHIPPING_API_KEY', 'test_api_key'),
    ],

    'sri' => [
        'url' => env('SRI_API_URL', 'https://api.sri.gob.ec'),
        'api_key' => env('SRI_API_KEY', ''),
        'ruc' => env('SRI_RUC', '0999999999001'),
        'razon_social' => env('SRI_RAZON_SOCIAL', 'EMPRESA DEMO S.A.'),
        'nombre_comercial' => env('SRI_NOMBRE_COMERCIAL', 'EMPRESA DEMO'),
        'direccion_matriz' => env('SRI_DIRECCION_MATRIZ', 'Av. Principal 123'),
        'direccion_establecimiento' => env('SRI_DIRECCION_ESTABLECIMIENTO', 'Av. Principal 123'),
        'contribuyente_especial' => env('SRI_CONTRIBUYENTE_ESPECIAL', 'NO'),
        'obligado_contabilidad' => env('SRI_OBLIGADO_CONTABILIDAD', 'SI'),
        'environment' => env('SRI_ENVIRONMENT', '1'), // 1: Pruebas, 2: Producción
        'series' => env('SRI_SERIES', '001001'),
    ],

    'datafast' => [
        // Configuración de Fase 1 vs Fase 2 (TRUE para testMode=EXTERNAL)
        'use_phase2' => env('DATAFAST_USE_PHASE2', true),

        // URLs de ambiente (Según sitio oficial developers.datafast.com.ec)
        'test_url' => env('DATAFAST_TEST_URL', 'https://test.oppwa.com'),
        'production_url' => env('DATAFAST_PRODUCTION_URL', 'https://oppwa.com'),

        // Configuración de pruebas (Fase 1)
        'test' => [
            'entity_id' => env('DATAFAST_TEST_ENTITY_ID', '8a829418533cf31d01533d06f2ee06fa'),
            'authorization' => env('DATAFAST_TEST_AUTHORIZATION', 'Bearer OGE4Mjk0MTg1MzNjZjMxZDAxNTMzZDA2ZmQwNDA3NDh8WHQ3RjIyUUVOWA=='),
            'mid' => env('DATAFAST_TEST_MID', '1000000406'),
            'tid' => env('DATAFAST_TEST_TID', 'PD100406'),
        ],

        // Configuración de producción (cambiar cuando se tengan los datos reales)
        'production' => [
            'entity_id' => env('DATAFAST_PRODUCTION_ENTITY_ID'),
            'authorization' => env('DATAFAST_PRODUCTION_AUTHORIZATION'),
            'mid' => env('DATAFAST_PRODUCTION_MID'),
            'tid' => env('DATAFAST_PRODUCTION_TID'),
        ],

        // Configuraciones adicionales
        'timeout' => env('DATAFAST_TIMEOUT', 30),
        'debug' => env('DATAFAST_DEBUG', false),
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],
];
