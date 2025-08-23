<?php

/**
 * Script para regenerar autoload de Composer y limpiar cache de Laravel
 * Ejecutar desde el directorio backend: php fix_autoload.php
 */
echo "🔧 Regenerando autoload y limpiando cache...\n";

// Cambiar al directorio del backend si no estamos ahí
$backendDir = __DIR__;
if (basename($backendDir) !== 'BCommerceBackEnd') {
    $backendDir = __DIR__.'/BCommerceBackEnd';
}

if (! is_dir($backendDir)) {
    echo "❌ Error: No se encontró el directorio backend\n";
    exit(1);
}

chdir($backendDir);
echo '📁 Directorio actual: '.getcwd()."\n";

// Comandos a ejecutar
$commands = [
    'Regenerando autoload de Composer...' => 'composer dump-autoload --optimize',
    'Limpiando cache de configuración...' => 'php artisan config:clear',
    'Limpiando cache de rutas...' => 'php artisan route:clear',
    'Limpiando cache de vistas...' => 'php artisan view:clear',
    'Regenerando cache de configuración...' => 'php artisan config:cache',
];

foreach ($commands as $description => $command) {
    echo "\n🔄 $description\n";
    echo "Ejecutando: $command\n";

    $output = [];
    $returnCode = 0;
    exec($command.' 2>&1', $output, $returnCode);

    if ($returnCode === 0) {
        echo "✅ Completado exitosamente\n";
        if (! empty($output)) {
            echo '   '.implode("\n   ", $output)."\n";
        }
    } else {
        echo "⚠️  Warning - código de retorno: $returnCode\n";
        if (! empty($output)) {
            echo '   '.implode("\n   ", $output)."\n";
        }
    }
}

echo "\n🎉 Proceso completado!\n";
echo "💡 Ahora intenta acceder a http://127.0.0.1:8000/api/products/46\n";
echo "💡 Si el problema persiste, reinicia el servidor: php artisan serve\n";
