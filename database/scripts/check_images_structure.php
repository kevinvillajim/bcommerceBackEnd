<?php

/**
 * Script para verificar estructura del campo images
 */

require_once __DIR__.'/../../vendor/autoload.php';

// Configurar Laravel
$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 VERIFICANDO ESTRUCTURA DEL CAMPO IMAGES\n";
echo "==========================================\n\n";

try {
    // Obtener un producto con imágenes
    $product = \App\Models\Product::whereNotNull('images')
        ->where('images', '!=', '')
        ->where('images', '!=', '[]')
        ->first();

    if ($product) {
        echo "✅ Producto encontrado: {$product->name}\n";
        $rawImages = $product->getRawOriginal('images');
        echo '📷 Campo images raw: '.var_export($rawImages, true)."\n\n";

        // Intentar decodificar como JSON
        $imagesDecoded = json_decode($rawImages, true);
        if ($imagesDecoded !== null) {
            echo "✅ Es JSON válido\n";
            echo "📷 Estructura decodificada:\n";
            print_r($imagesDecoded);

            // Verificar si es un array
            if (is_array($imagesDecoded)) {
                echo '✅ Es un array con '.count($imagesDecoded)." elementos\n";
                if (! empty($imagesDecoded)) {
                    $firstImage = $imagesDecoded[0];
                    echo "🖼️ Primera imagen:\n";
                    if (is_array($firstImage)) {
                        print_r($firstImage);
                        // Buscar campo con URL
                        foreach ($firstImage as $key => $value) {
                            if (strpos($key, 'url') !== false || strpos($key, 'original') !== false || strpos($key, 'src') !== false) {
                                echo "🎯 Posible campo de imagen: {$key} = {$value}\n";
                            }
                        }
                    } else {
                        echo "🎯 Primera imagen (string): {$firstImage}\n";
                    }
                }
            }
        } else {
            echo "❌ No es JSON válido - podría ser string simple\n";
            echo "📷 Contenido: {$product->images}\n";
        }

    } else {
        echo "❌ No se encontró ningún producto con imágenes\n";

        // Buscar cualquier producto para ver estructura
        $anyProduct = \App\Models\Product::first();
        if ($anyProduct) {
            echo "📷 Producto sin imágenes - campo images: '{$anyProduct->images}'\n";
        }
    }

} catch (Exception $e) {
    echo '❌ ERROR: '.$e->getMessage()."\n";
}
