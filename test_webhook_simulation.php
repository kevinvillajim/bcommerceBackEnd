<?php

/**
 * 🧪 SIMULACIÓN COMPLETA DE WEBHOOK DE PAGO
 *
 * Este script simula un pago real usando CURL para llamar al webhook endpoint
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

echo "🧪 INICIANDO SIMULACIÓN COMPLETA DE PAGO\n";
echo "=====================================\n\n";

// Configuración
$baseUrl = 'http://localhost:8000'; // Ajusta según tu configuración
$webhookUrl = $baseUrl.'/api/webhooks/deuna/payment-status';

// PASO 1: Preparar datos de prueba (simulados como si vinieran de DeUna)
echo "📋 PASO 1: Preparando datos de prueba...\n";

$orderId = 'TEST-ORDER-'.time();
$paymentId = 'PAY-TEST-'.time();
$transactionId = 'TXN-'.uniqid();

// Datos del webhook como los enviaría DeUna
$webhookData = [
    'idTransaction' => $paymentId,
    'status' => 'SUCCESS',
    'event' => 'payment.completed',
    'transferNumber' => 'TRF-'.uniqid(),
    'branchId' => 'BRANCH-TEST',
    'posId' => 'POS-TEST',
    'customerIdentification' => '0999999999001',
    'customerFullName' => 'Test Customer',
    'transaction_id' => $transactionId,
    'amount' => 1322.44,
    'currency' => 'USD',
    'timestamp' => date('c'),
    'data' => [
        'payment_id' => $paymentId,
        'transaction_id' => $transactionId,
        'status' => 'SUCCESS',
    ],
];

echo "✅ Order ID: {$orderId}\n";
echo "✅ Payment ID: {$paymentId}\n";
echo "✅ Transaction ID: {$transactionId}\n";
echo "✅ Amount: \${$webhookData['amount']}\n";

// PASO 2: Crear el pago DeUna en la base de datos (simular estado inicial)
echo "\n📋 PASO 2: Creando pago en base de datos...\n";

// Aquí necesitarías ejecutar este código via artisan tinker o crear un comando
$createPaymentScript = "
\$user = App\\Models\\User::first();
\$products = App\\Models\\Product::where('stock', '>', 5)->take(2)->get();

\$items = [];
foreach (\$products as \$index => \$product) {
    \$quantity = \$index + 2;
    \$items[] = [
        'product_id' => \$product->id,
        'name' => \$product->name,
        'quantity' => \$quantity,
        'price' => \$product->price
    ];
}

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
        'test_simulation' => true
    ]
]);

echo 'Payment created with ID: ' . \$deunaPayment->id;
";

echo "Ejecuta este código en tinker para crear el pago:\n";
echo "php artisan tinker --execute=\"{$createPaymentScript}\"\n\n";
echo 'Presiona ENTER cuando hayas ejecutado el comando...';
fgets(STDIN);

// PASO 3: Enviar webhook
echo "\n📋 PASO 3: Enviando webhook al servidor...\n";

$result = makeHttpRequest($webhookUrl, $webhookData, [
    'X-DeUna-Signature: test-signature-'.hash('sha256', json_encode($webhookData)),
]);

echo "HTTP Code: {$result['http_code']}\n";

if ($result['error']) {
    echo "❌ Error de CURL: {$result['error']}\n";
    exit(1);
}

$response = json_decode($result['response'], true);

if ($result['http_code'] === 200 && $response && $response['success']) {
    echo "✅ Webhook procesado exitosamente!\n";
    echo '✅ Payment ID: '.($response['data']['payment_id'] ?? 'N/A')."\n";
    echo '✅ Status: '.($response['data']['status'] ?? 'N/A')."\n";
    echo '✅ Event: '.($response['data']['event'] ?? 'N/A')."\n";
} else {
    echo "❌ Error en webhook:\n";
    echo 'Response: '.$result['response']."\n";
    exit(1);
}

// PASO 4: Verificar resultados
echo "\n📋 PASO 4: Verificando resultados...\n";

$verifyScript = "
// Verificar orden creada
\$order = App\\Models\\Order::where('id', '{$orderId}')->first();
if (\$order) {
    echo 'ORDEN ENCONTRADA: ' . \$order->order_number;
    echo 'Estado: ' . \$order->status;
    echo 'Total: \$' . \$order->total;
    echo 'Payment Status: ' . \$order->payment_status;
    
    // Verificar items de la orden
    \$items = \$order->items;
    echo 'Items: ' . \$items->count();
    foreach (\$items as \$item) {
        echo '- ' . \$item->product->name . ': ' . \$item->quantity . ' x \$' . \$item->price;
    }
} else {
    echo 'ERROR: Orden no encontrada';
}

// Verificar pago actualizado
\$payment = App\\Models\\DeunaPayment::where('payment_id', '{$paymentId}')->first();
if (\$payment) {
    echo 'PAGO ACTUALIZADO: ' . \$payment->status;
    if (\$payment->transaction_id) {
        echo 'Transaction ID: ' . \$payment->transaction_id;
    }
} else {
    echo 'ERROR: Pago no encontrado';
}

// Verificar factura
\$invoice = App\\Models\\Invoice::where('order_id', '{$orderId}')->first();
if (\$invoice) {
    echo 'FACTURA GENERADA: ' . \$invoice->invoice_number;
    echo 'Total factura: \$' . \$invoice->total_amount;
} else {
    echo 'FACTURA: No generada aún (puede estar en cola)';
}

// Verificar stock reducido
\$products = App\\Models\\Product::where('stock', '>', 0)->take(2)->get();
echo 'STOCK ACTUAL:';
foreach (\$products as \$product) {
    echo \$product->name . ': ' . \$product->stock;
}
";

echo "Ejecuta este código para verificar resultados:\n";
echo "php artisan tinker --execute=\"{$verifyScript}\"\n\n";

echo "🎉 SIMULACIÓN COMPLETADA\n";
echo "======================\n";
echo "1. ✅ Pago creado en BD\n";
echo "2. ✅ Webhook enviado y procesado\n";
echo "3. 🔄 Verificar creación de orden\n";
echo "4. 🔄 Verificar reducción de stock\n";
echo "5. 🔄 Verificar generación de factura\n";
echo "6. 🔄 Verificar notificaciones\n";
echo "\nEjecuta el script de verificación para completar la prueba.\n";
