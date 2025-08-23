<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Leer datos del webhook guardados
$webhookData = json_decode(file_get_contents('webhook_test_data.txt'), true);

echo "🧪 PRUEBA DIRECTA DEL WEBHOOK\n";
echo "=============================\n";
echo 'Order ID: '.$webhookData['order_id']."\n";
echo 'Payment ID: '.$webhookData['payment_id']."\n";
echo 'Amount: $'.$webhookData['amount']."\n\n";

// Simular el webhook data
$simulatedWebhookData = [
    'idTransaction' => $webhookData['payment_id'],
    'status' => 'SUCCESS',
    'event' => 'payment.completed',
    'transferNumber' => 'TRF-TEST-'.uniqid(),
    'branchId' => 'BRANCH-TEST',
    'posId' => 'POS-TEST',
    'customerIdentification' => '0999999999001',
    'customerFullName' => 'Test Customer',
    'transaction_id' => 'TXN-'.uniqid(),
    'amount' => $webhookData['amount'],
    'currency' => 'USD',
    'timestamp' => date('c'),
    'data' => [
        'payment_id' => $webhookData['payment_id'],
        'transaction_id' => 'TXN-'.uniqid(),
        'status' => 'SUCCESS',
    ],
];

// Llamar directamente al UseCase
$deunaServiceInterface = app(\App\Domain\Interfaces\DeunaServiceInterface::class);
$deunaPaymentRepository = app(\App\Domain\Repositories\DeunaPaymentRepositoryInterface::class);
$orderRepository = app(\App\Domain\Repositories\OrderRepositoryInterface::class);
$productRepository = app(\App\Domain\Repositories\ProductRepositoryInterface::class);
$orderStatusHandler = app(\App\Services\OrderStatusHandler::class);

$handleWebhookUseCase = new \App\UseCases\Payment\HandleDeunaWebhookUseCase(
    $deunaServiceInterface,
    $deunaPaymentRepository,
    $orderRepository,
    $productRepository,
    $orderStatusHandler
);

try {
    echo "🚀 Ejecutando webhook UseCase...\n";
    $result = $handleWebhookUseCase->execute($simulatedWebhookData, '');

    echo "✅ WEBHOOK PROCESADO CON ÉXITO!\n";
    echo 'Payment ID: '.$result['payment_id']."\n";
    echo 'Status: '.$result['status']."\n";
    echo 'Event: '.$result['event']."\n";
    echo 'Message: '.$result['message']."\n";

    // Verificar orden creada
    echo "\n📋 VERIFICANDO ORDEN CREADA...\n";
    $order = \App\Models\Order::where('id', $webhookData['order_id'])->first();

    if ($order) {
        echo "✅ ORDEN DEUNA ENCONTRADA:\n";
        echo 'ID: '.$order->id."\n";
        echo 'User ID: '.$order->user_id."\n";
        echo 'Seller ID: '.$order->seller_id."\n";
        echo 'Payment Method: '.$order->payment_method."\n";
        echo 'Status: '.$order->status."\n";
        echo "---TOTALES---\n";
        echo 'Original Total: $'.$order->original_total."\n";
        echo 'Subtotal Products: $'.$order->subtotal_products."\n";
        echo 'Seller Discounts: $'.$order->seller_discount_savings."\n";
        echo 'Shipping Cost: $'.$order->shipping_cost."\n";
        echo 'IVA Amount: $'.$order->iva_amount."\n";
        echo 'Total: $'.$order->total."\n";

        if ($order->pricing_breakdown) {
            echo "---PRICING BREAKDOWN---\n";
            $breakdown = json_decode($order->pricing_breakdown, true);
            foreach ($breakdown as $key => $value) {
                echo $key.': '.(is_numeric($value) ? '$'.number_format($value, 2) : $value)."\n";
            }
        }

        // Verificar items
        $items = $order->items;
        echo "---ITEMS---\n";
        echo 'Items count: '.$items->count()."\n";
        foreach ($items as $item) {
            echo 'Product: '.$item->product_name."\n";
            echo 'Quantity: '.$item->quantity."\n";
            echo 'Price: $'.$item->price."\n";
            echo 'Original Price: $'.$item->original_price."\n";
            echo 'Subtotal: $'.$item->subtotal."\n";
        }

        echo "\n=== COMPARACIÓN CON CÁLCULOS ESPERADOS ===\n";
        echo "ESPERADO vs ACTUAL:\n";
        echo 'Original Total: $2.00 vs $'.$order->original_total."\n";
        echo 'Subtotal Products: $1.00 vs $'.$order->subtotal_products."\n";
        echo 'Shipping: $5.00 vs $'.$order->shipping_cost."\n";
        echo 'IVA: $0.90 vs $'.$order->iva_amount."\n";
        echo 'Total: $6.90 vs $'.$order->total."\n";

        $allCorrect =
            abs($order->original_total - 2.00) < 0.01 &&
            abs($order->subtotal_products - 1.00) < 0.01 &&
            abs($order->shipping_cost - 5.00) < 0.01 &&
            abs($order->iva_amount - 0.90) < 0.01 &&
            abs($order->total - 6.90) < 0.01;

        if ($allCorrect) {
            echo "\n🎉 ¡TODOS LOS CÁLCULOS SON CORRECTOS!\n";
        } else {
            echo "\n❌ Hay diferencias en los cálculos\n";
        }
    } else {
        echo "❌ ERROR: Orden no encontrada\n";
    }

} catch (Exception $e) {
    echo '❌ ERROR EN WEBHOOK: '.$e->getMessage()."\n";
    echo 'Trace: '.$e->getTraceAsString()."\n";
}
