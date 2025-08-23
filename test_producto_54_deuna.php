<?php

/**
 * 🧪 PRUEBA ESPECÍFICA: Producto Kevin Villacreses (ID 54)
 * Comparar cálculos entre DeUna y Datafast
 */

// Función para hacer request HTTP
function makeHttpRequest($url, $data, $headers = [])
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
        'Content-Type: application/json',
        'Accept: application/json',
    ], $headers));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $error,
    ];
}

echo "🧪 PRUEBA ESPECÍFICA: Producto Kevin Villacreses (ID 54)\n";
echo "====================================================\n\n";

// Configuración
$baseUrl = 'http://localhost:8000';
$webhookUrl = $baseUrl.'/api/webhooks/deuna/payment-status';

// PASO 1: Verificar datos del producto
echo "📋 PASO 1: Verificando producto ID 54...\n";

$productVerificationScript = "
\$product = App\\Models\\Product::find(54);
if (\$product) {
    echo 'PRODUCTO ENCONTRADO:' . PHP_EOL;
    echo 'ID: ' . \$product->id . PHP_EOL;
    echo 'Nombre: ' . \$product->name . PHP_EOL;
    echo 'Precio original: $' . \$product->price . PHP_EOL;
    echo 'Descuento del vendedor: ' . \$product->discount_percentage . '%' . PHP_EOL;
    echo 'Precio con descuento: $' . (\$product->price * (1 - \$product->discount_percentage / 100)) . PHP_EOL;
    echo 'Stock: ' . \$product->stock . PHP_EOL;
    echo 'Seller ID: ' . \$product->seller_id . PHP_EOL;
} else {
    echo 'ERROR: Producto 54 no encontrado' . PHP_EOL;
}
";

echo "Ejecuta este comando para verificar el producto:\n";
echo "php artisan tinker --execute=\"{$productVerificationScript}\"\n\n";
echo 'Presiona ENTER cuando hayas verificado el producto...';
fgets(STDIN);

// PASO 2: Crear el pago DeUna
echo "\n📋 PASO 2: Creando pago DeUna para producto 54...\n";

$orderId = 'TEST-PROD54-'.time();
$paymentId = 'PAY-PROD54-'.time();
$transactionId = 'TXN-PROD54-'.uniqid();

// Datos del webhook según estructura esperada
$webhookData = [
    'idTransaction' => $paymentId,
    'status' => 'SUCCESS',
    'event' => 'payment.completed',
    'transferNumber' => 'TRF-PROD54-'.uniqid(),
    'branchId' => 'BRANCH-TEST',
    'posId' => 'POS-TEST',
    'customerIdentification' => '0999999999001',
    'customerFullName' => 'Test Customer Producto 54',
    'transaction_id' => $transactionId,
    'amount' => 6.90, // Total esperado: $1.00 + $5.00 + $0.90 = $6.90
    'currency' => 'USD',
    'timestamp' => date('c'),
    'data' => [
        'payment_id' => $paymentId,
        'transaction_id' => $transactionId,
        'status' => 'SUCCESS',
    ],
];

$createPaymentScript = "
\$user = App\\Models\\User::find(25); // Usuario específico
\$product = App\\Models\\Product::find(54); // Producto específico Kevin Villacreses

if (!\$user) {
    echo 'ERROR: Usuario 25 no encontrado' . PHP_EOL;
    exit;
}

if (!\$product) {
    echo 'ERROR: Producto 54 no encontrado' . PHP_EOL;
    exit;
}

echo 'CREANDO PAGO DEUNA PARA:' . PHP_EOL;
echo 'Usuario: ' . \$user->name . ' (ID: ' . \$user->id . ')' . PHP_EOL;
echo 'Producto: ' . \$product->name . ' (ID: ' . \$product->id . ')' . PHP_EOL;
echo 'Precio original: $' . \$product->price . PHP_EOL;
echo 'Descuento: ' . \$product->discount_percentage . '%' . PHP_EOL;

\$items = [
    [
        'product_id' => \$product->id,
        'name' => \$product->name,
        'quantity' => 1,
        'price' => \$product->price // Precio original, el descuento se aplicará en el webhook
    ]
];

\$deunaPayment = App\\Models\\DeunaPayment::create([
    'order_id' => '{$orderId}',
    'payment_id' => '{$paymentId}',
    'amount' => {$webhookData['amount']},
    'currency' => 'USD',
    'status' => 'pending',
    'customer' => [
        'name' => \$user->name,
        'email' => \$user->email,
        'phone' => '0999999999'
    ],
    'items' => \$items,
    'metadata' => [
        'user_id' => \$user->id,
        'test_producto_54' => true
    ]
]);

echo 'PAGO DEUNA CREADO:' . PHP_EOL;
echo 'ID: ' . \$deunaPayment->id . PHP_EOL;
echo 'Order ID: ' . \$deunaPayment->order_id . PHP_EOL;
echo 'Payment ID: ' . \$deunaPayment->payment_id . PHP_EOL;
echo 'Amount: $' . \$deunaPayment->amount . PHP_EOL;
";

echo "Ejecuta este comando para crear el pago DeUna:\n";
echo "php artisan tinker --execute=\"{$createPaymentScript}\"\n\n";
echo 'Presiona ENTER cuando hayas creado el pago...';
fgets(STDIN);

// PASO 3: Enviar webhook
echo "\n📋 PASO 3: Enviando webhook DeUna...\n";

$result = makeHttpRequest($webhookUrl, $webhookData, [
    'X-DeUna-Signature: test-signature-prod54-'.hash('sha256', json_encode($webhookData)),
]);

echo "HTTP Code: {$result['http_code']}\n";

if ($result['error']) {
    echo "❌ Error de CURL: {$result['error']}\n";
    exit(1);
}

$response = json_decode($result['response'], true);

if ($result['http_code'] === 200 && $response && $response['success']) {
    echo "✅ Webhook DeUna procesado exitosamente!\n";
    echo '✅ Payment ID: '.($response['data']['payment_id'] ?? 'N/A')."\n";
    echo '✅ Status: '.($response['data']['status'] ?? 'N/A')."\n";
    echo '✅ Event: '.($response['data']['event'] ?? 'N/A')."\n";
} else {
    echo "❌ Error en webhook:\n";
    echo 'Response: '.$result['response']."\n";
    exit(1);
}

// PASO 4: Verificar orden creada
echo "\n📋 PASO 4: Verificando orden DeUna creada...\n";

$verifyOrderScript = "
\$order = App\\Models\\Order::where('id', '{$orderId}')->first();

if (\$order) {
    echo '✅ ORDEN DEUNA CREADA:' . PHP_EOL;
    echo 'ID: ' . \$order->id . PHP_EOL;
    echo 'Order Number: ' . \$order->order_number . PHP_EOL;
    echo 'User ID: ' . \$order->user_id . PHP_EOL;
    echo 'Seller ID: ' . \$order->seller_id . PHP_EOL;
    echo 'Payment Method: ' . \$order->payment_method . PHP_EOL;
    echo '---TOTALES---' . PHP_EOL;
    echo 'Original Total: $' . \$order->original_total . PHP_EOL;
    echo 'Subtotal Products: $' . \$order->subtotal_products . PHP_EOL;
    echo 'Seller Discounts: $' . \$order->seller_discount_savings . PHP_EOL;
    echo 'Shipping Cost: $' . \$order->shipping_cost . PHP_EOL;
    echo 'IVA Amount: $' . \$order->iva_amount . PHP_EOL;
    echo 'Total: $' . \$order->total . PHP_EOL;
    echo '---PRICING BREAKDOWN---' . PHP_EOL;
    if (\$order->pricing_breakdown) {
        \$breakdown = json_decode(\$order->pricing_breakdown, true);
        foreach (\$breakdown as \$key => \$value) {
            echo \$key . ': ' . (is_numeric(\$value) ? '$' . \$value : \$value) . PHP_EOL;
        }
    }
    
    // Verificar items
    \$items = \$order->items;
    echo '---ITEMS---' . PHP_EOL;
    echo 'Items count: ' . \$items->count() . PHP_EOL;
    foreach (\$items as \$item) {
        echo 'Product: ' . \$item->product_name . PHP_EOL;
        echo 'Quantity: ' . \$item->quantity . PHP_EOL;
        echo 'Price: $' . \$item->price . PHP_EOL;
        echo 'Original Price: $' . \$item->original_price . PHP_EOL;
        echo 'Subtotal: $' . \$item->subtotal . PHP_EOL;
    }
} else {
    echo '❌ ERROR: Orden DeUna no encontrada' . PHP_EOL;
}

echo PHP_EOL . '=== PARA COMPARACIÓN ====' . PHP_EOL;
echo 'Busca una orden de Datafast del mismo producto para comparar...' . PHP_EOL;
echo 'SELECT * FROM orders WHERE payment_method = \"datafast\" AND original_total = 2 ORDER BY created_at DESC LIMIT 1;' . PHP_EOL;
";

echo "Ejecuta este comando para verificar la orden DeUna:\n";
echo "php artisan tinker --execute=\"{$verifyOrderScript}\"\n\n";

echo "🎉 PRUEBA COMPLETADA\n";
echo "===================\n";
echo "Ahora compara los resultados con una orden de Datafast del mismo producto.\n";
echo "Ambas deberían mostrar:\n";
echo "- Original Total: $2.00\n";
echo "- Subtotal Products: $1.00 (con descuento 50%)\n";
echo "- Shipping: $5.00\n";
echo "- IVA: $0.90 (15% sobre $6.00)\n";
echo "- Total: $6.90\n";
