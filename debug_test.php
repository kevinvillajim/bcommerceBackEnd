<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use App\Services\PriceVerificationService;

echo "🔍 DATOS REALES USADOS EN LOS TESTS:\n\n";

// Producto real
$product = DB::table('products')->whereNotNull('seller_id')->first();
echo "Producto ID: {$product->id}\n";
echo "Precio base: \${$product->price}\n";
echo "Seller ID: {$product->seller_id}\n";
echo "Descuento seller: " . ($product->discount_percentage ?? 0) . "%\n";

// Calcular precio esperado después de descuento seller
$discountAmount = $product->price * (($product->discount_percentage ?? 0) / 100);
$expectedPrice = $product->price - $discountAmount;
echo "Precio esperado después de descuento seller: \${$expectedPrice}\n\n";

// Test real del servicio
echo "🧪 PRUEBA REAL CON PriceVerificationService:\n";
$service = app(PriceVerificationService::class);

$testItem = [
    [
        'product_id' => $product->id,
        'quantity' => 1,
        'seller_id' => $product->seller_id,
        'price' => round($expectedPrice, 2)
    ]
];

echo "Item enviado al servicio:\n";
print_r($testItem);

$result = $service->verifyItemPrices($testItem, 1);
echo "Resultado: " . ($result ? "✅ ACEPTADO" : "❌ RECHAZADO") . "\n\n";

// Test de tampering
echo "🚨 PRUEBA DE TAMPERING:\n";
$tamperedItem = [
    [
        'product_id' => $product->id,
        'quantity' => 1,
        'seller_id' => $product->seller_id,
        'price' => 0.01 // Precio manipulado
    ]
];

$tamperedResult = $service->verifyItemPrices($tamperedItem, 1);
echo "Precio manipulado (0.01): " . ($tamperedResult ? "❌ INCORRECTAMENTE ACEPTADO" : "✅ CORRECTAMENTE RECHAZADO") . "\n";