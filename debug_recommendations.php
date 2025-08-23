<?php

// Script de debugging para el sistema de recomendaciones
require_once __DIR__.'/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\Formatters\ProductFormatter;
use App\Models\Product;
use App\Models\User;
use App\UseCases\Recommendation\GenerateRecommendationsUseCase;

echo "🔍 [DEBUG] Iniciando debugging del sistema de recomendaciones\n";

try {
    // 1. Verificar que haya productos en la base de datos
    $productCount = Product::count();
    echo "📊 [DB] Total de productos: {$productCount}\n";

    if ($productCount === 0) {
        echo "❌ [ERROR] No hay productos en la base de datos\n";
        exit(1);
    }

    // 2. Obtener algunos productos de ejemplo
    $sampleProducts = Product::with('category')->take(3)->get();
    echo "📋 [SAMPLE] Productos de ejemplo:\n";

    foreach ($sampleProducts as $product) {
        echo "  - ID: {$product->id}, Name: {$product->name}, Price: {$product->price}, Rating: {$product->rating}, Rating Count: {$product->rating_count}\n";
        echo '    Category: '.($product->category->name ?? 'No Category')."\n";
        echo '    Images: '.(is_array($product->images) ? count($product->images) : 'Not Array')."\n";
    }

    // 3. Probar el ProductFormatter directamente
    echo "\n🔧 [FORMATTER] Probando ProductFormatter...\n";
    $productFormatter = app(ProductFormatter::class);

    foreach ($sampleProducts as $index => $product) {
        echo "🔄 [PRODUCT {$index}] Formateando producto {$product->id}...\n";

        try {
            $formatted = $productFormatter->formatForApi($product);

            $requiredFields = ['id', 'name', 'price', 'rating', 'rating_count', 'images', 'main_image', 'category_name'];
            $missingFields = [];

            foreach ($requiredFields as $field) {
                if (! isset($formatted[$field])) {
                    $missingFields[] = $field;
                }
            }

            if (empty($missingFields)) {
                echo "  ✅ [SUCCESS] Producto formateado correctamente\n";
                echo "    - Price: {$formatted['price']} (type: ".gettype($formatted['price']).")\n";
                echo "    - Rating: {$formatted['rating']} (type: ".gettype($formatted['rating']).")\n";
                echo "    - Rating Count: {$formatted['rating_count']} (type: ".gettype($formatted['rating_count']).")\n";
                echo '    - Images: '.count($formatted['images'])." items\n";
                echo "    - Category: {$formatted['category_name']}\n";
            } else {
                echo '  ❌ [ERROR] Campos faltantes: '.implode(', ', $missingFields)."\n";
                echo '    - Campos disponibles: '.implode(', ', array_keys($formatted))."\n";
            }

        } catch (\Exception $e) {
            echo "  ❌ [EXCEPTION] Error formateando: {$e->getMessage()}\n";
        }
    }

    // 4. Crear un usuario de prueba
    echo "\n👤 [USER] Creando usuario de prueba...\n";
    $testUser = User::factory()->create([
        'name' => 'Test User Debug',
        'email' => 'debug@test.com',
    ]);
    echo "✅ [USER] Usuario creado con ID: {$testUser->id}\n";

    // 5. Probar el motor de recomendaciones
    echo "\n🤖 [ENGINE] Probando motor de recomendaciones...\n";
    $recommendationsUseCase = app(GenerateRecommendationsUseCase::class);

    try {
        $recommendations = $recommendationsUseCase->execute($testUser->id, 5);

        echo '📊 [RESULT] Recomendaciones obtenidas: '.count($recommendations)."\n";

        if (empty($recommendations)) {
            echo "❌ [ERROR] No se obtuvieron recomendaciones\n";
        } else {
            foreach ($recommendations as $index => $rec) {
                echo "🎯 [REC {$index}] Validando recomendación:\n";

                $requiredFields = ['id', 'name', 'price', 'rating', 'rating_count'];
                $valid = true;

                foreach ($requiredFields as $field) {
                    if (! isset($rec[$field])) {
                        echo "  ❌ [MISSING] Campo '{$field}' faltante\n";
                        $valid = false;
                    } else {
                        echo "  ✅ [OK] {$field}: {$rec[$field]} (".gettype($rec[$field]).")\n";
                    }
                }

                if ($valid) {
                    echo "  🎉 [SUCCESS] Recomendación válida\n";
                } else {
                    echo "  ❌ [INVALID] Recomendación inválida\n";
                    echo '  📋 [AVAILABLE] Campos disponibles: '.implode(', ', array_keys($rec))."\n";
                }
                echo "\n";
            }
        }

    } catch (\Exception $e) {
        echo "❌ [EXCEPTION] Error en motor de recomendaciones: {$e->getMessage()}\n";
        echo "📍 [TRACE] Línea: {$e->getLine()}, Archivo: {$e->getFile()}\n";
    }

    // 6. Limpiar usuario de prueba
    echo "🧹 [CLEANUP] Limpiando usuario de prueba...\n";
    $testUser->delete();

    echo "✅ [COMPLETE] Debugging completado\n";

} catch (\Exception $e) {
    echo "💥 [FATAL] Error fatal: {$e->getMessage()}\n";
    echo "📍 [TRACE] Línea: {$e->getLine()}, Archivo: {$e->getFile()}\n";
    exit(1);
}
