<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::find(25);
$product = App\Models\Product::find(54);

if (! $user || ! $product) {
    echo "ERROR: Usuario 25 o Producto 54 no encontrado\n";
    exit;
}

$orderId = 'TEST-CORRECTED-'.time();
$paymentId = 'PAY-CORRECTED-'.time();

echo "🧪 CREANDO NUEVO PAGO DEUNA PARA PROBAR CORRECCIONES\n";
echo "=================================================\n";
echo 'Usuario: '.$user->name.' (ID: '.$user->id.")\n";
echo 'Producto: '.$product->name.' (ID: '.$product->id.")\n";
echo 'Order ID: '.$orderId."\n";
echo 'Payment ID: '.$paymentId."\n\n";

$items = [
    [
        'product_id' => $product->id,
        'name' => $product->name,
        'quantity' => 1,
        'price' => $product->price,
    ],
];

$deunaPayment = App\Models\DeunaPayment::create([
    'order_id' => $orderId,
    'payment_id' => $paymentId,
    'amount' => 6.90,
    'currency' => 'USD',
    'status' => 'pending',
    'customer' => [
        'name' => $user->name,
        'email' => $user->email,
        'phone' => '0999999999',
    ],
    'items' => $items,
    'metadata' => [
        'user_id' => $user->id,
        'test_correcciones' => true,
    ],
]);

// Simular webhook data
$webhookData = [
    'idTransaction' => $paymentId,
    'status' => 'SUCCESS',
    'event' => 'payment.completed',
    'transferNumber' => 'TRF-CORRECTED-'.uniqid(),
    'branchId' => 'BRANCH-TEST',
    'posId' => 'POS-TEST',
    'customerIdentification' => '0999999999001',
    'customerFullName' => $user->name,
    'transaction_id' => 'TXN-CORRECTED-'.uniqid(),
    'amount' => 6.90,
    'currency' => 'USD',
    'timestamp' => date('c'),
    'data' => [
        'payment_id' => $paymentId,
        'transaction_id' => 'TXN-CORRECTED-'.uniqid(),
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
    echo "🚀 Ejecutando webhook con correcciones...\n";
    $result = $handleWebhookUseCase->execute($webhookData, '');

    echo "✅ WEBHOOK PROCESADO CON ÉXITO!\n";
    echo 'Status: '.$result['status']."\n";
    echo 'Message: '.$result['message']."\n\n";

    // Verificar orden creada
    echo "📋 VERIFICANDO ORDEN DEUNA CORREGIDA...\n";
    $order = \App\Models\Order::where('id', $orderId)
        ->orWhere('order_number', $orderId)
        ->orderBy('created_at', 'desc')
        ->first();

    if ($order) {
        echo "✅ ORDEN DEUNA ENCONTRADA:\n";
        echo 'ID: '.$order->id."\n";
        echo 'User ID: '.$order->user_id."\n";
        echo 'Seller ID: '.($order->seller_id ?? 'NULL')."\n";
        echo 'Payment Method: '.$order->payment_method."\n";
        echo 'Payment ID: '.($order->payment_id ?? 'NULL')."\n";
        echo 'Status: '.$order->status."\n";

        echo "\n---TOTALES CORREGIDOS---\n";
        echo 'Original Total: $'.number_format($order->original_total, 2)."\n";
        echo 'Subtotal Products: $'.number_format($order->subtotal_products, 2)."\n";
        echo 'Seller Discounts: $'.number_format($order->seller_discount_savings ?? 0, 2)."\n";
        echo 'Shipping Cost: $'.number_format($order->shipping_cost, 2)."\n";
        echo 'IVA Amount: $'.number_format($order->iva_amount, 2)."\n";
        echo 'Total: $'.number_format($order->total, 2)."\n";
        echo 'Free Shipping Threshold: $'.number_format($order->free_shipping_threshold ?? 0, 2)."\n";

        if ($order->payment_details) {
            echo "\n---PAYMENT DETAILS CORREGIDOS---\n";
            $paymentDetails = json_decode($order->payment_details, true);
            foreach ($paymentDetails as $key => $value) {
                echo $key.': '.(is_array($value) ? json_encode($value) : $value)."\n";
            }
        }

        if ($order->pricing_breakdown) {
            echo "\n---PRICING BREAKDOWN---\n";
            $breakdown = json_decode($order->pricing_breakdown, true);
            foreach ($breakdown as $key => $value) {
                echo $key.': '.(is_numeric($value) ? '$'.number_format($value, 2) : $value)."\n";
            }
        }

        echo "\n=== VERIFICACIÓN FINAL ===\n";
        $allFieldsCorrect =
            ! is_null($order->seller_id) &&
            ! is_null($order->payment_id) &&
            ! is_null($order->seller_discount_savings) &&
            ! is_null($order->free_shipping_threshold) &&
            ! is_null($order->payment_details);

        if ($allFieldsCorrect) {
            echo "🎉 ¡TODOS LOS CAMPOS CORREGIDOS EXITOSAMENTE!\n";
            echo '✅ seller_id: '.$order->seller_id."\n";
            echo '✅ payment_id: '.$order->payment_id."\n";
            echo '✅ seller_discount_savings: $'.number_format($order->seller_discount_savings, 2)."\n";
            echo '✅ free_shipping_threshold: $'.number_format($order->free_shipping_threshold, 2)."\n";
            echo "✅ payment_details: Present\n";
        } else {
            echo "❌ Algunos campos aún faltan:\n";
            if (is_null($order->seller_id)) {
                echo "❌ seller_id: NULL\n";
            }
            if (is_null($order->payment_id)) {
                echo "❌ payment_id: NULL\n";
            }
            if (is_null($order->seller_discount_savings)) {
                echo "❌ seller_discount_savings: NULL\n";
            }
            if (is_null($order->free_shipping_threshold)) {
                echo "❌ free_shipping_threshold: NULL\n";
            }
            if (is_null($order->payment_details)) {
                echo "❌ payment_details: NULL\n";
            }
        }

    } else {
        echo "❌ ERROR: Orden no encontrada\n";
    }

} catch (Exception $e) {
    echo '❌ ERROR EN WEBHOOK: '.$e->getMessage()."\n";
    echo 'Trace: '.$e->getTraceAsString()."\n";
}
