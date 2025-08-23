<?php

/**
 * Script de verificación para el problema del middleware
 * Ejecutar desde backend: php verify_middleware.php
 */
echo "🔍 Verificando problema del middleware...\n\n";

// 1. Verificar que el archivo del middleware existe
$middlewarePath = __DIR__.'/app/Http/Middleware/TrackInteractionMiddleware.php';
if (file_exists($middlewarePath)) {
    echo "✅ TrackInteractionMiddleware.php existe\n";
} else {
    echo "❌ TrackInteractionMiddleware.php NO existe\n";
    exit(1);
}

// 2. Verificar que se puede incluir sin errores
try {
    require_once $middlewarePath;
    echo "✅ TrackInteractionMiddleware se puede incluir\n";
} catch (\Exception $e) {
    echo '❌ Error incluyendo TrackInteractionMiddleware: '.$e->getMessage()."\n";
}

// 3. Verificar que el modelo UserInteraction existe
$modelPath = __DIR__.'/app/Models/UserInteraction.php';
if (file_exists($modelPath)) {
    echo "✅ UserInteraction.php existe\n";
} else {
    echo "❌ UserInteraction.php NO existe\n";
}

// 4. Verificar autoload de Composer
$autoloadPath = __DIR__.'/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    echo "✅ Autoload de Composer existe\n";
    require_once $autoloadPath;
    echo "✅ Autoload cargado correctamente\n";
} else {
    echo "❌ Autoload de Composer NO existe\n";
}

// 5. Verificar si la clase se puede instanciar
try {
    if (class_exists('App\Http\Middleware\TrackInteractionMiddleware')) {
        echo "✅ Clase TrackInteractionMiddleware se puede cargar\n";

        // Intentar crear instancia
        $instance = new \App\Http\Middleware\TrackInteractionMiddleware;
        echo "✅ Se puede instanciar TrackInteractionMiddleware\n";
    } else {
        echo "❌ Clase TrackInteractionMiddleware NO existe en autoload\n";
    }
} catch (\Exception $e) {
    echo '❌ Error instanciando TrackInteractionMiddleware: '.$e->getMessage()."\n";
}

// 6. Verificar el modelo UserInteraction
try {
    if (class_exists('App\Models\UserInteraction')) {
        echo "✅ Clase UserInteraction se puede cargar\n";
    } else {
        echo "❌ Clase UserInteraction NO existe en autoload\n";
    }
} catch (\Exception $e) {
    echo '❌ Error con UserInteraction: '.$e->getMessage()."\n";
}

echo "\n🎯 SOLUCIÓN APLICADA:\n";
echo "- El middleware 'track.interaction' ha sido comentado en Kernel.php\n";
echo "- Esto permite que las rutas de productos funcionen normalmente\n";
echo "- Para solucionarlo permanentemente:\n";
echo "  1. Ejecuta: composer dump-autoload --optimize\n";
echo "  2. Ejecuta: php artisan config:clear\n";
echo "  3. Reinicia el servidor: php artisan serve\n";
echo "  4. Descomenta la línea en app/Http/Kernel.php\n\n";

echo "💡 Script fix_autoload.php ya creado para automatizar estos pasos\n";
