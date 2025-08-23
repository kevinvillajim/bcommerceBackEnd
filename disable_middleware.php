<?php

/**
 * Script para deshabilitar rápidamente el middleware problemático
 * Ejecutar desde backend: php disable_middleware.php
 */
echo "🚫 Deshabilitando middleware problemático...\n\n";

$kernelPath = __DIR__.'/app/Http/Kernel.php';

if (! file_exists($kernelPath)) {
    echo "❌ Error: No se encontró app/Http/Kernel.php\n";
    exit(1);
}

// Leer el contenido actual
$kernelContent = file_get_contents($kernelPath);

// Verificar si el middleware está activo
if (strpos($kernelContent, "'track.interaction' => \\App\\Http\\Middleware\\TrackInteractionMiddleware::class") !== false) {
    echo "📝 Comentando middleware en Kernel.php...\n";

    // Comentar la línea
    $kernelContent = str_replace(
        "        'track.interaction' => \\App\\Http\\Middleware\\TrackInteractionMiddleware::class, // Auto-tracking middleware for user interactions",
        "        // TEMPORALMENTE COMENTADO - CAUSA ERROR 500\n        // 'track.interaction' => \\App\\Http\\Middleware\\TrackInteractionMiddleware::class, // Auto-tracking middleware for user interactions",
        $kernelContent
    );

    // Guardar el archivo
    file_put_contents($kernelPath, $kernelContent);
    echo "✅ Middleware comentado en Kernel.php\n";
} else {
    echo "ℹ️  El middleware ya está deshabilitado\n";
}

// Limpiar cache
echo "\n🔧 Limpiando cache...\n";
exec('php artisan config:clear 2>&1', $output, $returnCode);
if ($returnCode === 0) {
    echo "✅ Cache limpiado\n";
} else {
    echo "⚠️  Warning limpiando cache\n";
}

echo "\n🎉 Middleware deshabilitado!\n";
echo "💡 Ahora debería funcionar: http://127.0.0.1:8000/api/products/46\n";
echo "💡 Para reactivar: php reactivate_middleware.php\n";
