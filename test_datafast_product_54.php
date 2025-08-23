<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 PRUEBA DATAFAST - PRODUCTO KEVIN VILLACRESES (ID 54)\n";
echo "====================================================\n";

$user = App\Models\User::find(25);
$product = App\Models\Product::find(54);

if (! $user || ! $product) {
    echo "❌ ERROR: Usuario 25 o Producto 54 no encontrado\n";
    exit;
}

echo 'Usuario: '.$user->name.' (ID: '.$user->id.")\n";
echo 'Producto: '.$product->name.' (ID: '.$product->id.")\n";
echo 'Precio original: $'.$product->price."\n";
echo 'Descuento vendedor: '.$product->discount_percentage."%\n\n";

// Preparar datos para el checkout según la signatura del método
$userId = $user->id;
$paymentData = [
    'method' => 'datafast',
    'amount' => 6.90, // Expected total based on calculations
];
$shippingData = [
    'street' => 'Test Street 123',
    'city' => 'Quito',
    'postal_code' => '170501',
    'country' => 'Ecuador',
];
$items = [
    [
        'product_id' => $product->id,
        'quantity' => 1,
        'price' => $product->price,
    ],
];
$sellerId = null; // Will be determined from product
$discountCode = null; // Sin código de descuento para esta prueba

// Obtener las dependencias necesarias
$cartRepository = app(\App\Domain\Repositories\ShoppingCartRepositoryInterface::class);
$orderRepository = app(\App\Domain\Repositories\OrderRepositoryInterface::class);
$productRepository = app(\App\Domain\Repositories\ProductRepositoryInterface::class);
$sellerOrderRepository = app(\App\Domain\Repositories\SellerOrderRepositoryInterface::class);
$paymentGateway = app(\App\Domain\Interfaces\PaymentGatewayInterface::class);
$createOrderUseCase = app(\App\UseCases\Order\CreateOrderUseCase::class);
$configService = app(\App\Services\ConfigurationService::class);
$applyCartDiscountCodeUseCase = app(\App\UseCases\Cart\ApplyCartDiscountCodeUseCase::class);

$processCheckoutUseCase = new \App\UseCases\Checkout\ProcessCheckoutUseCase(
    $cartRepository,
    $orderRepository,
    $productRepository,
    $sellerOrderRepository,
    $paymentGateway,
    $createOrderUseCase,
    $configService,
    $applyCartDiscountCodeUseCase
);

try {
    echo "🚀 Ejecutando ProcessCheckoutUseCase para Datafast...\n";

    $result = $processCheckoutUseCase->execute($userId, $paymentData, $shippingData, $items, $sellerId, $discountCode);

    echo "✅ CHECKOUT PROCESADO CON ÉXITO!\n";
    echo 'Resultado recibido: '.json_encode($result, JSON_PRETTY_PRINT)."\n\n";

    // Extraer información del resultado
    $orderEntity = $result['order'] ?? null;
    $orderId = $orderEntity ? $orderEntity->getId() : null;
    $orderNumber = $orderEntity ? $orderEntity->getOrderNumber() : null;
    $totalAmount = $result['pricing_info']['totals']['final_total'] ?? null;

    if ($orderId) {
        echo 'Order ID: '.$orderId."\n";
        echo 'Order Number: '.$orderNumber."\n";
        echo 'Total Amount: $'.$totalAmount."\n\n";
    }

    // Verificar la orden creada
    echo "📋 VERIFICANDO ORDEN DATAFAST CREADA...\n";
    $order = \App\Models\Order::find($orderId);

    if ($order) {
        echo "✅ ORDEN DATAFAST ENCONTRADA:\n";
        echo 'ID: '.$order->id."\n";
        echo 'Order Number: '.$order->order_number."\n";
        echo 'User ID: '.$order->user_id."\n";
        echo 'Seller ID: '.$order->seller_id."\n";
        echo 'Payment Method: '.($order->payment_method ?? 'pending')."\n";
        echo 'Status: '.$order->status."\n";

        echo "\n---TOTALES DATAFAST---\n";
        echo 'Original Total: $'.number_format($order->original_total, 2)."\n";
        echo 'Subtotal Products: $'.number_format($order->subtotal_products, 2)."\n";
        echo 'Seller Discounts: $'.number_format($order->seller_discount_savings, 2)."\n";
        echo 'Shipping Cost: $'.number_format($order->shipping_cost, 2)."\n";
        echo 'IVA Amount: $'.number_format($order->iva_amount, 2)."\n";
        echo 'Total: $'.number_format($order->total, 2)."\n";

        if ($order->pricing_breakdown) {
            echo "\n---PRICING BREAKDOWN DATAFAST---\n";
            $breakdown = json_decode($order->pricing_breakdown, true);
            foreach ($breakdown as $key => $value) {
                echo $key.': '.(is_numeric($value) ? '$'.number_format($value, 2) : $value)."\n";
            }
        }

        // Verificar items
        $items = $order->items;
        echo "\n---ITEMS DATAFAST---\n";
        echo 'Items count: '.$items->count()."\n";
        foreach ($items as $item) {
            echo 'Product: '.$item->product_name."\n";
            echo 'Product ID: '.$item->product_id."\n";
            echo 'Quantity: '.$item->quantity."\n";
            echo 'Price: $'.number_format($item->price, 2)."\n";
            echo 'Original Price: $'.number_format($item->original_price, 2)."\n";
            echo 'Subtotal: $'.number_format($item->subtotal, 2)."\n";
        }

        echo "\n=== COMPARACIÓN CON CÁLCULOS ESPERADOS ===\n";
        echo "ESPERADO vs ACTUAL:\n";
        printf("Original Total: \$2.00 vs \$%.2f %s\n", $order->original_total,
            abs($order->original_total - 2.00) < 0.01 ? '✅' : '❌');
        printf("Subtotal Products: \$1.00 vs \$%.2f %s\n", $order->subtotal_products,
            abs($order->subtotal_products - 1.00) < 0.01 ? '✅' : '❌');
        printf("Seller Discounts: \$1.00 vs \$%.2f %s\n", $order->seller_discount_savings,
            abs($order->seller_discount_savings - 1.00) < 0.01 ? '✅' : '❌');
        printf("Shipping: \$5.00 vs \$%.2f %s\n", $order->shipping_cost,
            abs($order->shipping_cost - 5.00) < 0.01 ? '✅' : '❌');
        printf("IVA: \$0.90 vs \$%.2f %s\n", $order->iva_amount,
            abs($order->iva_amount - 0.90) < 0.01 ? '✅' : '❌');
        printf("Total: \$6.90 vs \$%.2f %s\n", $order->total,
            abs($order->total - 6.90) < 0.01 ? '✅' : '❌');

        $allCorrect =
            abs($order->original_total - 2.00) < 0.01 &&
            abs($order->subtotal_products - 1.00) < 0.01 &&
            abs($order->seller_discount_savings - 1.00) < 0.01 &&
            abs($order->shipping_cost - 5.00) < 0.01 &&
            abs($order->iva_amount - 0.90) < 0.01 &&
            abs($order->total - 6.90) < 0.01;

        if ($allCorrect) {
            echo "\n🎉 ¡ORDEN DATAFAST: TODOS LOS CÁLCULOS SON CORRECTOS!\n";
        } else {
            echo "\n❌ Hay diferencias en los cálculos de Datafast\n";
        }

        // Comparar con DeUna
        echo "\n".str_repeat('=', 50)."\n";
        echo "COMPARANDO CON ORDEN DEUNA ID 81\n";
        echo str_repeat('=', 50)."\n";

        $deunaOrder = \App\Models\Order::find(81);
        if ($deunaOrder) {
            echo "🔍 COMPARACIÓN DIRECTA DATAFAST vs DEUNA:\n";
            printf("Original Total: Datafast \$%.2f vs DeUna \$%.2f %s\n",
                $order->original_total, $deunaOrder->original_total,
                abs($order->original_total - $deunaOrder->original_total) < 0.01 ? '✅' : '❌');
            printf("Subtotal Products: Datafast \$%.2f vs DeUna \$%.2f %s\n",
                $order->subtotal_products, $deunaOrder->subtotal_products,
                abs($order->subtotal_products - $deunaOrder->subtotal_products) < 0.01 ? '✅' : '❌');
            printf("Seller Discounts: Datafast \$%.2f vs DeUna \$%.2f %s\n",
                $order->seller_discount_savings, $deunaOrder->seller_discount_savings,
                abs($order->seller_discount_savings - $deunaOrder->seller_discount_savings) < 0.01 ? '✅' : '❌');
            printf("Shipping: Datafast \$%.2f vs DeUna \$%.2f %s\n",
                $order->shipping_cost, $deunaOrder->shipping_cost,
                abs($order->shipping_cost - $deunaOrder->shipping_cost) < 0.01 ? '✅' : '❌');
            printf("IVA: Datafast \$%.2f vs DeUna \$%.2f %s\n",
                $order->iva_amount, $deunaOrder->iva_amount,
                abs($order->iva_amount - $deunaOrder->iva_amount) < 0.01 ? '✅' : '❌');
            printf("Total: Datafast \$%.2f vs DeUna \$%.2f %s\n",
                $order->total, $deunaOrder->total,
                abs($order->total - $deunaOrder->total) < 0.01 ? '✅' : '❌');

            $bothSystemsMatch =
                abs($order->original_total - $deunaOrder->original_total) < 0.01 &&
                abs($order->subtotal_products - $deunaOrder->subtotal_products) < 0.01 &&
                abs($order->seller_discount_savings - $deunaOrder->seller_discount_savings) < 0.01 &&
                abs($order->shipping_cost - $deunaOrder->shipping_cost) < 0.01 &&
                abs($order->iva_amount - $deunaOrder->iva_amount) < 0.01 &&
                abs($order->total - $deunaOrder->total) < 0.01;

            if ($bothSystemsMatch) {
                echo "\n🎉🎉 ¡ÉXITO TOTAL! DATAFAST Y DEUNA CALCULAN EXACTAMENTE IGUAL! 🎉🎉\n";
                echo "La estandarización de precios funciona perfectamente en ambos sistemas.\n";
            } else {
                echo "\n⚠️ Hay diferencias entre Datafast y DeUna\n";
            }
        }

        // Guardar el ID para referencia
        file_put_contents('datafast_test_order_id.txt', $order->id);
        echo "\n📝 ID de orden Datafast guardado: ".$order->id."\n";

    } else {
        echo "❌ ERROR: Orden no encontrada después del checkout\n";
    }

} catch (Exception $e) {
    echo '❌ ERROR EN CHECKOUT: '.$e->getMessage()."\n";
    echo 'Trace: '.$e->getTraceAsString()."\n";
}
