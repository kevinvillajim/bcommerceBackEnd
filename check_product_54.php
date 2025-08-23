<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$product = App\Models\Product::find(54);

if ($product) {
    echo "PRODUCTO ENCONTRADO:\n";
    echo 'ID: '.$product->id."\n";
    echo 'Nombre: '.$product->name."\n";
    echo 'Precio original: $'.$product->price."\n";
    echo 'Descuento del vendedor: '.$product->discount_percentage."%\n";
    echo 'Precio con descuento: $'.($product->price * (1 - $product->discount_percentage / 100))."\n";
    echo 'Stock: '.$product->stock."\n";
    echo 'Seller ID: '.$product->seller_id."\n";

    echo "\n=== CÁLCULOS ESPERADOS ===\n";
    $originalPrice = $product->price;
    $discountPercent = $product->discount_percentage;
    $discountedPrice = $originalPrice * (1 - $discountPercent / 100);
    $shipping = 5.00; // Assuming < $50 threshold
    $taxableBase = $discountedPrice + $shipping;
    $iva = $taxableBase * 0.15;
    $total = $taxableBase + $iva;

    echo 'Subtotal original: $'.number_format($originalPrice, 2)."\n";
    echo 'Descuentos aplicados: -$'.number_format($originalPrice - $discountedPrice, 2)."\n";
    echo 'Subtotal con descuentos: $'.number_format($discountedPrice, 2)."\n";
    echo 'Envío: $'.number_format($shipping, 2)."\n";
    echo 'Subtotal final (base gravable): $'.number_format($taxableBase, 2)."\n";
    echo 'IVA (15%): $'.number_format($iva, 2)."\n";
    echo 'Total pagado: $'.number_format($total, 2)."\n";
} else {
    echo "ERROR: Producto 54 no encontrado\n";
}
