<?php

return [
    // Configuración de logs para evitar saturación
    'logging' => [
        'enabled' => env('DATAFAST_LOG_ENABLED', env('APP_DEBUG', false)),
        'level' => env('DATAFAST_LOG_LEVEL', 'error'), // Solo errores en producción
        'max_size' => 1024 * 1024, // 1MB máximo por log
    ],

    // Límites de procesamiento
    'processing' => [
        'max_retries' => 3,
        'timeout' => 30, // segundos
        'memory_limit' => '256M',
    ],

    // Configuración existente de Datafast
    'environment' => env('DATAFAST_ENVIRONMENT', 'test'),
    'merchant_id' => env('DATAFAST_MERCHANT_ID'),
    'api_key' => env('DATAFAST_API_KEY'),
    'api_secret' => env('DATAFAST_API_SECRET'),
];
