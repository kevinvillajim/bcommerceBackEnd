<?php

/**
 * Script para reactivar el middleware de tracking de forma segura
 * Ejecutar desde backend: php reactivate_middleware.php
 */
echo "🔄 Reactivando middleware de tracking...\n\n";

$kernelPath = __DIR__.'/app/Http/Kernel.php';

if (! file_exists($kernelPath)) {
    echo "❌ Error: No se encontró app/Http/Kernel.php\n";
    exit(1);
}

// Leer el contenido actual
$kernelContent = file_get_contents($kernelPath);

// Verificar si el middleware está comentado
if (strpos($kernelContent, "// 'track.interaction'") !== false) {
    echo "📝 Descomentando middleware en Kernel.php...\n";

    // Descomentar la línea
    $kernelContent = str_replace(
        "        // TEMPORALMENTE COMENTADO - CAUSA ERROR 500\n        // 'track.interaction' => \\App\\Http\\Middleware\\TrackInteractionMiddleware::class, // Auto-tracking middleware for user interactions",
        "        'track.interaction' => \\App\\Http\\Middleware\\TrackInteractionMiddleware::class, // Auto-tracking middleware for user interactions",
        $kernelContent
    );

    // Guardar el archivo
    file_put_contents($kernelPath, $kernelContent);
    echo "✅ Middleware descomentado en Kernel.php\n";
} else {
    echo "ℹ️  El middleware ya está activo en Kernel.php\n";
}

// Ejecutar comandos de limpieza
echo "\n🔧 Limpiando cache...\n";

$commands = [
    'composer dump-autoload --optimize',
    'php artisan config:clear',
    'php artisan route:clear',
];

foreach ($commands as $command) {
    echo "Ejecutando: $command\n";
    $output = [];
    $returnCode = 0;
    exec($command.' 2>&1', $output, $returnCode);

    if ($returnCode === 0) {
        echo "✅ Completado\n";
    } else {
        echo "⚠️  Warning - código: $returnCode\n";
        if (! empty($output)) {
            echo '   '.implode("\n   ", $output)."\n";
        }
    }
}

echo "\n🎉 Middleware reactivado!\n";
echo "💡 Prueba ahora: http://127.0.0.1:8000/api/products/46\n";
echo "💡 Si hay errores, ejecuta: php disable_middleware.php\n";
