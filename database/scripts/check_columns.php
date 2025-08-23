<?php

/**
 * Script para verificar columnas en la tabla products
 */

require_once __DIR__.'/../../vendor/autoload.php';

// Configurar Laravel
$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 VERIFICANDO COLUMNAS EN TABLA PRODUCTS\n";
echo "==========================================\n\n";

try {
    // Obtener todas las columnas
    $columns = \Illuminate\Support\Facades\Schema::getColumnListing('products');

    echo "✅ Columnas existentes en 'products':\n";
    foreach ($columns as $column) {
        echo "   - {$column}\n";
    }

    echo "\n📊 Total columnas: ".count($columns)."\n\n";

    // Verificar columnas específicas que están causando errores
    $requiredColumns = ['main_image_url', 'main_image', 'is_active'];

    echo "🔍 Verificando columnas problemáticas:\n";
    foreach ($requiredColumns as $col) {
        $exists = in_array($col, $columns);
        $status = $exists ? '✅' : '❌';
        echo "   {$status} {$col}: ".($exists ? 'EXISTS' : 'MISSING')."\n";
    }

    echo "\n📋 ESTRUCTURA DE TABLA products:\n";
    $tableInfo = DB::select('DESCRIBE products');
    foreach ($tableInfo as $column) {
        echo "   {$column->Field} ({$column->Type}) - Default: {$column->Default}\n";
    }

} catch (Exception $e) {
    echo '❌ ERROR: '.$e->getMessage()."\n";
}
