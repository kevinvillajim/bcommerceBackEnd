<?php

require_once 'bootstrap/app.php';

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->create();

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Verificar el último SellerOrder creado
$sellerOrder = App\Models\SellerOrder::latest()->first();

if ($sellerOrder) {
    echo "✅ ÚLTIMO SELLER ORDER:\n";
    echo "ID: {$sellerOrder->id}\n";
    echo "Order ID: {$sellerOrder->order_id}\n";
    echo "Seller ID: {$sellerOrder->seller_id}\n";
    echo "Original Total: " . ($sellerOrder->original_total ?? 'NULL') . "\n";
    echo "Volume Discount Savings: {$sellerOrder->volume_discount_savings}\n";
    echo "Volume Discounts Applied: " . ($sellerOrder->volume_discounts_applied ? 'true' : 'false') . "\n";
    echo "Shipping Cost: {$sellerOrder->shipping_cost}\n";
    echo "Payment Method: {$sellerOrder->payment_method}\n";
    echo "Created At: {$sellerOrder->created_at}\n";
} else {
    echo "❌ No se encontró ningún SellerOrder\n";
}