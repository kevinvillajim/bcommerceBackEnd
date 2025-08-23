<?php

// Test script to validate DeUna integration
require_once __DIR__.'/vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "🧪 Testing DeUna Integration\n";
echo '='.str_repeat('=', 40)."\n";

// Test data
$testPaymentData = [
    'order_id' => 'ORDER-'.time().'-TEST123',
    'amount' => 25.50,
    'currency' => 'USD',
    'customer' => [
        'name' => 'Juan Pérez',
        'email' => 'juan.perez@example.com',
        'phone' => '593987654321',
    ],
    'items' => [
        [
            'name' => 'Producto de Prueba',
            'quantity' => 1,
            'price' => 25.50,
            'description' => 'Producto para pruebas de integración',
        ],
    ],
    'qr_type' => 'dynamic',
    'format' => '2',
];

try {
    echo "📝 Test Payment Data:\n";
    echo json_encode($testPaymentData, JSON_PRETTY_PRINT)."\n\n";

    // Test use case
    $useCase = app(\App\UseCases\Payment\CreateDeunaPaymentUseCase::class);

    echo "🔄 Creating payment...\n";
    $result = $useCase->execute($testPaymentData);

    echo "✅ Payment created successfully!\n";
    echo "📊 Result:\n";
    echo json_encode($result, JSON_PRETTY_PRINT)."\n";

} catch (Exception $e) {
    echo '❌ Error: '.$e->getMessage()."\n";
    echo '📍 File: '.$e->getFile().':'.$e->getLine()."\n";
    echo "🔍 Trace:\n".$e->getTraceAsString()."\n";
}

echo "\n".str_repeat('=', 50)."\n";
echo "✨ Test completed!\n";
