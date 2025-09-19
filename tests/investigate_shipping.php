<?php
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Cargar configuración de Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Investigando registros shipping anómalos...\n\n";

// Buscar los tracking numbers específicos
$trackingNumbers = ['TRK097334006332', 'TRK097278007908', 'TRK097277004046'];

echo "📋 Buscando tracking numbers: " . implode(', ', $trackingNumbers) . "\n\n";

try {
    $shippings = DB::table('shippings')
        ->whereIn('tracking_number', $trackingNumbers)
        ->orderBy('created_at', 'desc')
        ->get();

    echo "📊 Encontrados: " . $shippings->count() . " registros\n\n";

    foreach ($shippings as $shipping) {
        echo "🔸 ID: {$shipping->id}\n";
        echo "   Order ID: '{$shipping->order_id}'\n";
        echo "   Tracking: {$shipping->tracking_number}\n";
        echo "   Status: {$shipping->status}\n";

        // Mostrar todas las propiedades disponibles
        echo "   TODAS LAS PROPIEDADES:\n";
        foreach ((array)$shipping as $key => $value) {
            echo "     {$key}: '{$value}'\n";
        }
        echo "\n";
    }

    // Investigar los seller_orders relacionados
    echo "🔍 Investigando seller_orders relacionados...\n\n";

    $sellerOrderIds = [139, 140, 141];

    $sellerOrders = DB::table('seller_orders')
        ->whereIn('id', $sellerOrderIds)
        ->get();

    foreach ($sellerOrders as $sellerOrder) {
        echo "🔸 SellerOrder ID: {$sellerOrder->id}\n";
        echo "   Order ID: '{$sellerOrder->order_id}'\n";
        echo "   Seller ID: {$sellerOrder->seller_id}\n";
        echo "   Status: {$sellerOrder->status}\n";
        echo "   Total: {$sellerOrder->total}\n";
        echo "   Created: {$sellerOrder->created_at}\n\n";
    }

    // Buscar las orders principales
    $orderIds = $sellerOrders->pluck('order_id')->filter()->unique();

    if ($orderIds->count() > 0) {
        echo "🔍 Buscando orders principales...\n\n";

        $orders = DB::table('orders')
            ->whereIn('id', $orderIds)
            ->get();

        foreach ($orders as $order) {
            echo "🔸 Order ID: {$order->id}\n";
            echo "   Order Number: {$order->order_number}\n";
            echo "   User ID: {$order->user_id}\n";
            echo "   Status: {$order->status}\n";
            echo "   Total: {$order->total}\n";
            echo "   Created: {$order->created_at}\n\n";
        }
    } else {
        echo "❌ No hay order_ids válidos en los seller_orders!\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}