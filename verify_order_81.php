<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 VERIFICANDO ORDEN DEUNA CREADA (ID 81)\n";
echo "=========================================\n";

$order = \App\Models\Order::find(81);

if ($order) {
    echo "✅ ORDEN DEUNA ENCONTRADA:\n";
    echo 'ID: '.$order->id."\n";
    echo 'Order Number: '.$order->order_number."\n";
    echo 'User ID: '.$order->user_id."\n";
    echo 'Seller ID: '.$order->seller_id."\n";
    echo 'Payment Method: '.$order->payment_method."\n";
    echo 'Payment ID: '.$order->payment_id."\n";
    echo 'Status: '.$order->status."\n";
    echo 'Payment Status: '.$order->payment_status."\n";

    echo "\n---TOTALES DEUNA---\n";
    echo 'Original Total: $'.number_format($order->original_total, 2)."\n";
    echo 'Subtotal Products: $'.number_format($order->subtotal_products, 2)."\n";
    echo 'Seller Discounts: $'.number_format($order->seller_discount_savings, 2)."\n";
    echo 'Shipping Cost: $'.number_format($order->shipping_cost, 2)."\n";
    echo 'IVA Amount: $'.number_format($order->iva_amount, 2)."\n";
    echo 'Total: $'.number_format($order->total, 2)."\n";

    if ($order->pricing_breakdown) {
        echo "\n---PRICING BREAKDOWN DEUNA---\n";
        $breakdown = json_decode($order->pricing_breakdown, true);
        foreach ($breakdown as $key => $value) {
            echo $key.': '.(is_numeric($value) ? '$'.number_format($value, 2) : $value)."\n";
        }
    }

    // Verificar items
    $items = $order->items;
    echo "\n---ITEMS DEUNA---\n";
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
    printf("Shipping: \$5.00 vs \$%.2f %s\n", $order->shipping_cost,
        abs($order->shipping_cost - 5.00) < 0.01 ? '✅' : '❌');
    printf("IVA: \$0.90 vs \$%.2f %s\n", $order->iva_amount,
        abs($order->iva_amount - 0.90) < 0.01 ? '✅' : '❌');
    printf("Total: \$6.90 vs \$%.2f %s\n", $order->total,
        abs($order->total - 6.90) < 0.01 ? '✅' : '❌');

    $allCorrect =
        abs($order->original_total - 2.00) < 0.01 &&
        abs($order->subtotal_products - 1.00) < 0.01 &&
        abs($order->shipping_cost - 5.00) < 0.01 &&
        abs($order->iva_amount - 0.90) < 0.01 &&
        abs($order->total - 6.90) < 0.01;

    if ($allCorrect) {
        echo "\n🎉 ¡ORDEN DEUNA: TODOS LOS CÁLCULOS SON CORRECTOS!\n";
    } else {
        echo "\n❌ Hay diferencias en los cálculos de DeUna\n";
    }

    // Ahora buscar una orden de Datafast para comparar
    echo "\n".str_repeat('=', 50)."\n";
    echo "BUSCANDO ORDEN DE DATAFAST PARA COMPARAR\n";
    echo str_repeat('=', 50)."\n";

    // Buscar una orden de Datafast del mismo producto
    $datafastOrder = \App\Models\Order::where('payment_method', 'datafast')
        ->where('original_total', 2.00)
        ->orderBy('created_at', 'desc')
        ->first();

    if ($datafastOrder) {
        echo "✅ ORDEN DATAFAST ENCONTRADA PARA COMPARAR:\n";
        echo 'ID: '.$datafastOrder->id."\n";
        echo 'Order Number: '.$datafastOrder->order_number."\n";
        echo 'Payment Method: '.$datafastOrder->payment_method."\n";

        echo "\n---TOTALES DATAFAST---\n";
        echo 'Original Total: $'.number_format($datafastOrder->original_total, 2)."\n";
        echo 'Subtotal Products: $'.number_format($datafastOrder->subtotal_products, 2)."\n";
        echo 'Seller Discounts: $'.number_format($datafastOrder->seller_discount_savings, 2)."\n";
        echo 'Shipping Cost: $'.number_format($datafastOrder->shipping_cost, 2)."\n";
        echo 'IVA Amount: $'.number_format($datafastOrder->iva_amount, 2)."\n";
        echo 'Total: $'.number_format($datafastOrder->total, 2)."\n";

        echo "\n🔍 COMPARACIÓN DIRECTA DEUNA vs DATAFAST:\n";
        printf("Original Total: DeUna \$%.2f vs Datafast \$%.2f %s\n",
            $order->original_total, $datafastOrder->original_total,
            abs($order->original_total - $datafastOrder->original_total) < 0.01 ? '✅' : '❌');
        printf("Subtotal Products: DeUna \$%.2f vs Datafast \$%.2f %s\n",
            $order->subtotal_products, $datafastOrder->subtotal_products,
            abs($order->subtotal_products - $datafastOrder->subtotal_products) < 0.01 ? '✅' : '❌');
        printf("Seller Discounts: DeUna \$%.2f vs Datafast \$%.2f %s\n",
            $order->seller_discount_savings, $datafastOrder->seller_discount_savings,
            abs($order->seller_discount_savings - $datafastOrder->seller_discount_savings) < 0.01 ? '✅' : '❌');
        printf("Shipping: DeUna \$%.2f vs Datafast \$%.2f %s\n",
            $order->shipping_cost, $datafastOrder->shipping_cost,
            abs($order->shipping_cost - $datafastOrder->shipping_cost) < 0.01 ? '✅' : '❌');
        printf("IVA: DeUna \$%.2f vs Datafast \$%.2f %s\n",
            $order->iva_amount, $datafastOrder->iva_amount,
            abs($order->iva_amount - $datafastOrder->iva_amount) < 0.01 ? '✅' : '❌');
        printf("Total: DeUna \$%.2f vs Datafast \$%.2f %s\n",
            $order->total, $datafastOrder->total,
            abs($order->total - $datafastOrder->total) < 0.01 ? '✅' : '❌');

        $bothSystemsMatch =
            abs($order->original_total - $datafastOrder->original_total) < 0.01 &&
            abs($order->subtotal_products - $datafastOrder->subtotal_products) < 0.01 &&
            abs($order->seller_discount_savings - $datafastOrder->seller_discount_savings) < 0.01 &&
            abs($order->shipping_cost - $datafastOrder->shipping_cost) < 0.01 &&
            abs($order->iva_amount - $datafastOrder->iva_amount) < 0.01 &&
            abs($order->total - $datafastOrder->total) < 0.01;

        if ($bothSystemsMatch) {
            echo "\n🎉 ¡ÉXITO TOTAL! DEUNA Y DATAFAST CALCULAN EXACTAMENTE IGUAL!\n";
            echo "La estandarización de precios funciona perfectamente.\n";
        } else {
            echo "\n⚠️ Hay diferencias entre DeUna y Datafast\n";
        }
    } else {
        echo "❌ No se encontró orden de Datafast para comparar\n";
        echo "Por favor, realiza una compra del mismo producto con Datafast\n";
    }
} else {
    echo "❌ ERROR: Orden 81 no encontrada\n";
}
