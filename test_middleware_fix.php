<?php

/**
 * Script rápido para limpiar cache y probar la API
 * Ejecutar desde backend: php test_middleware_fix.php
 */
echo "🔧 Limpiando cache para aplicar cambios al middleware...\n";

$commands = [
    'php artisan config:clear',
    'php artisan route:clear',
    'composer dump-autoload --optimize',
];

foreach ($commands as $command) {
    echo "Ejecutando: $command\n";
    $output = [];
    $returnCode = 0;
    exec($command.' 2>&1', $output, $returnCode);

    if ($returnCode === 0) {
        echo "✅ Completado\n";
    } else {
        echo "⚠️ Warning - código: $returnCode\n";
        if (! empty($output)) {
            echo '   '.implode("\n   ", $output)."\n";
        }
    }
}

echo "\n🧪 Probando API endpoint...\n";

// Probar la API directamente
$url = 'http://127.0.0.1:8000/api/products/51';
echo "Probando: $url\n";

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Accept: application/json',
        'timeout' => 10,
    ],
]);

$response = @file_get_contents($url, false, $context);
$httpCode = 0;

if (isset($http_response_header)) {
    foreach ($http_response_header as $header) {
        if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
            $httpCode = (int) $matches[1];
            break;
        }
    }
}

if ($response !== false && $httpCode === 200) {
    echo "✅ API funciona correctamente - HTTP $httpCode\n";
    $data = json_decode($response, true);
    if (isset($data['data']['name'])) {
        echo '✅ Producto encontrado: '.$data['data']['name']."\n";
    }
} else {
    echo "❌ API aún tiene problemas - HTTP $httpCode\n";
    if ($response) {
        echo 'Respuesta: '.substr($response, 0, 200)."...\n";
    }
}

echo "\n💡 Revisa los logs del servidor para ver los mensajes de [TRACK-DEBUG]\n";
echo "💡 Si funciona, ve a http://localhost:3000/products/51 en el navegador\n";
