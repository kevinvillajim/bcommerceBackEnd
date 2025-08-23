<?php

/**
 * Script de debug para encontrar el problema con productos
 */

require_once __DIR__.'/../../vendor/autoload.php';

// Configurar Laravel
$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 INICIANDO DEBUG DE PRODUCTOS\n";
echo "================================\n\n";

try {
    // 1. Verificar conexión a base de datos
    echo "1. ✅ Verificando conexión a MySQL...\n";
    $connection = DB::connection();
    $connection->getPdo();
    echo '   ✅ Conexión exitosa a: '.DB::getDatabaseName()."\n\n";

    // 2. Contar productos totales
    echo "2. 📊 Contando productos...\n";
    $totalProducts = DB::table('products')->count();
    echo "   Total productos en DB: {$totalProducts}\n";

    $publishedProducts = DB::table('products')->where('published', 1)->count();
    echo "   Productos publicados: {$publishedProducts}\n";

    $activeProducts = DB::table('products')->where('status', 'active')->count();
    echo "   Productos activos: {$activeProducts}\n";

    $publishedAndActive = DB::table('products')
        ->where('published', 1)
        ->where('status', 'active')
        ->count();
    echo "   Productos publicados Y activos: {$publishedAndActive}\n\n";

    // 3. Probar query básica como la que usa el repository
    echo "3. 🔍 Probando query del repository...\n";
    $products = DB::table('products')
        ->where('products.published', true)
        ->where('products.status', 'active')
        ->select('products.id', 'products.name', 'products.price', 'products.stock',
            'products.published', 'products.status', 'products.category_id')
        ->limit(5)
        ->get();

    echo '   Productos encontrados con query del repository: '.$products->count()."\n";

    if ($products->count() > 0) {
        echo "   Muestra de productos:\n";
        foreach ($products as $product) {
            echo "   - ID: {$product->id}, Nombre: {$product->name}, Precio: {$product->price}, Stock: {$product->stock}\n";
            echo '     Published: '.($product->published ? 'true' : 'false').", Status: {$product->status}\n";
        }
    }
    echo "\n";

    // 4. Probar con diferentes valores de published
    echo "4. 🔍 Probando diferentes valores de 'published'...\n";

    $published1 = DB::table('products')->where('published', 1)->count();
    echo "   published = 1: {$published1}\n";

    $publishedTrue = DB::table('products')->where('published', true)->count();
    echo "   published = true: {$publishedTrue}\n";

    $published0 = DB::table('products')->where('published', 0)->count();
    echo "   published = 0: {$published0}\n";

    $publishedFalse = DB::table('products')->where('published', false)->count();
    echo "   published = false: {$publishedFalse}\n\n";

    // 5. Probar con diferentes valores de status
    echo "5. 🔍 Probando diferentes valores de 'status'...\n";

    $statusActive = DB::table('products')->where('status', 'active')->count();
    echo "   status = 'active': {$statusActive}\n";

    $statusDraft = DB::table('products')->where('status', 'draft')->count();
    echo "   status = 'draft': {$statusDraft}\n";

    $statusInactive = DB::table('products')->where('status', 'inactive')->count();
    echo "   status = 'inactive': {$statusInactive}\n\n";

    // 6. Probar query SIN filtros
    echo "6. 🔍 Probando query SIN filtros...\n";
    $allProducts = DB::table('products')
        ->select('products.id', 'products.name', 'products.published', 'products.status')
        ->limit(10)
        ->get();

    echo '   Productos sin filtros: '.$allProducts->count()."\n";
    if ($allProducts->count() > 0) {
        echo "   Muestra:\n";
        foreach ($allProducts->take(5) as $product) {
            echo "   - ID: {$product->id}, Nombre: {$product->name}\n";
            echo '     Published: '.json_encode($product->published).' (tipo: '.gettype($product->published).")\n";
            echo "     Status: {$product->status}\n";
        }
    }
    echo "\n";

    // 7. Verificar tipos de datos en la tabla
    echo "7. 🔍 Verificando estructura de tabla...\n";
    $columns = DB::select('DESCRIBE products');
    foreach ($columns as $column) {
        if (in_array($column->Field, ['published', 'status', 'featured'])) {
            echo "   {$column->Field}: {$column->Type} - Default: {$column->Default} - Null: {$column->Null}\n";
        }
    }
    echo "\n";

    // 8. Probar usando Eloquent
    echo "8. 🔍 Probando con Eloquent...\n";
    $eloquentProducts = \App\Models\Product::limit(3)->get(['id', 'name', 'published', 'status']);
    echo '   Productos con Eloquent: '.$eloquentProducts->count()."\n";

    if ($eloquentProducts->count() > 0) {
        foreach ($eloquentProducts as $product) {
            echo "   - ID: {$product->id}, Nombre: {$product->name}\n";
            echo '     Published: '.json_encode($product->published).' (tipo: '.gettype($product->published).")\n";
            echo "     Status: {$product->status}\n";
        }
    }
    echo "\n";

    // 9. Probar la consulta EXACTA del repository
    echo "9. 🔍 Probando consulta EXACTA del repository...\n";
    $query = \App\Models\Product::query()
        ->where('products.published', true)
        ->where('products.status', 'active');

    echo '   SQL generado: '.$query->toSql()."\n";
    echo '   Bindings: '.json_encode($query->getBindings())."\n";

    $results = $query->limit(3)->get();
    echo '   Resultados: '.$results->count()."\n\n";

    // 10. Conclusiones
    echo "10. 📝 CONCLUSIONES:\n";
    echo "===================\n";

    if ($totalProducts == 0) {
        echo "❌ No hay productos en la base de datos\n";
    } else {
        echo "✅ Hay {$totalProducts} productos en total\n";

        if ($publishedAndActive == 0) {
            echo "❌ PROBLEMA ENCONTRADO: No hay productos con published=true Y status='active'\n";
            echo "   Posible causa: Los valores de published o status no coinciden con los esperados\n";
            echo "   Productos publicados: {$publishedProducts}\n";
            echo "   Productos activos: {$activeProducts}\n";
        } else {
            echo "✅ Hay {$publishedAndActive} productos que deberían aparecer\n";
            echo "❓ El problema puede estar en otro lugar (joins, formatters, frontend, etc.)\n";
        }
    }

} catch (Exception $e) {
    echo '❌ ERROR: '.$e->getMessage()."\n";
    echo 'Trace: '.$e->getTraceAsString()."\n";
}
