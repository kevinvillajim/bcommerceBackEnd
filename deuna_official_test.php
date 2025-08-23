<?php

// DeUna Integration Test - Based on Official Documentation
require_once __DIR__.'/vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "🔧 DeUna Integration Test - Official Documentation\n";
echo '='.str_repeat('=', 50)."\n";

// Test data based on official documentation structure
$testPaymentData = [
    'order_id' => 'ORDER-'.time().'-DOC-TEST',
    'amount' => 5.99, // Example from docs
    'currency' => 'USD',
    'customer' => [
        'name' => 'Juan Carlos Zambrano Lopez', // From webhook example
        'email' => 'juan.zambrano@example.com',
        'phone' => '593987654321',
    ],
    'items' => [
        [
            'name' => 'Test Product from Docs',
            'quantity' => 1,
            'price' => 5.99,
            'description' => 'Test detail as per documentation',
        ],
    ],
    'qr_type' => 'dynamic', // As specified in docs
    'format' => '2', // QR + Link format as per docs
];

echo "📋 Official DeUna API Configuration:\n";
echo '   - URL: '.config('deuna.api_url')."\n";
echo '   - Point of Sale: '.config('deuna.point_of_sale')."\n";
echo '   - API Key: '.substr(config('deuna.api_key'), 0, 8)."...\n";
echo '   - Environment: '.config('deuna.environment')."\n\n";

echo "📝 Test Payment Data (Per Documentation):\n";
echo json_encode($testPaymentData, JSON_PRETTY_PRINT)."\n\n";

try {
    // Expected payload structure per documentation
    $expectedPayload = [
        'pointOfSale' => config('deuna.point_of_sale'),
        'qrType' => $testPaymentData['qr_type'],
        'amount' => $testPaymentData['amount'],
        'detail' => 'Test detail as per documentation',
        'internalTransactionReference' => $testPaymentData['order_id'],
        'format' => $testPaymentData['format'],
    ];

    echo "🚀 Expected DeUna API Request Payload:\n";
    echo json_encode($expectedPayload, JSON_PRETTY_PRINT)."\n\n";

    // Test use case
    $useCase = app(\App\UseCases\Payment\CreateDeunaPaymentUseCase::class);

    echo "🔄 Creating payment with DeUna API...\n";
    $result = $useCase->execute($testPaymentData);

    echo "✅ Payment created successfully!\n";
    echo "📊 DeUna API Response:\n";
    echo json_encode($result, JSON_PRETTY_PRINT)."\n\n";

    // Validate response structure against documentation
    $responseValidation = [
        'payment_id' => isset($result['payment']['payment_id']),
        'qr_code' => isset($result['qr_code']),
        'payment_url' => isset($result['payment_url']),
        'amount' => isset($result['payment']['amount']),
        'currency' => isset($result['payment']['currency']),
        'status' => isset($result['payment']['status']),
    ];

    echo "📋 Response Validation (per official docs):\n";
    foreach ($responseValidation as $field => $isPresent) {
        $status = $isPresent ? '✅' : '❌';
        echo "   $status $field\n";
    }

    // Test payment status query if we got a payment ID
    if (isset($result['payment']['payment_id'])) {
        echo "\n🔍 Testing Payment Status Query (per docs)...\n";

        try {
            $deunaService = app(\App\Domain\Interfaces\DeunaServiceInterface::class);
            $statusResult = $deunaService->getPaymentStatus($result['payment']['payment_id']);

            echo "✅ Payment status query successful!\n";
            echo "📊 Status Query Response:\n";
            echo json_encode($statusResult, JSON_PRETTY_PRINT)."\n";

            // Validate status response structure
            $statusValidation = [
                'payment_id' => isset($statusResult['payment_id']),
                'status' => isset($statusResult['status']),
                'amount' => isset($statusResult['amount']),
                'currency' => isset($statusResult['currency']),
            ];

            echo "📋 Status Response Validation:\n";
            foreach ($statusValidation as $field => $isPresent) {
                $status = $isPresent ? '✅' : '❌';
                echo "   $status $field\n";
            }

        } catch (Exception $e) {
            echo '⚠️ Status query failed: '.$e->getMessage()."\n";
        }
    }

} catch (Exception $e) {
    echo '❌ Error: '.$e->getMessage()."\n";
    echo '📍 File: '.$e->getFile().':'.$e->getLine()."\n";

    if (str_contains($e->getMessage(), 'cURL error')) {
        echo "🌐 Network Error - Check DeUna API connectivity\n";
    } elseif (str_contains($e->getMessage(), '401') || str_contains($e->getMessage(), '403')) {
        echo "🔐 Authentication Error - Check API credentials\n";
    } elseif (str_contains($e->getMessage(), '400')) {
        echo "📝 Request Error - Check payload structure\n";
    } elseif (str_contains($e->getMessage(), '500')) {
        echo "🏠 DeUna Server Error - Try again later\n";
    }

    echo "🔍 Full trace:\n".$e->getTraceAsString()."\n";
}

echo "\n".str_repeat('=', 60)."\n";
echo "✨ DeUna Integration Test Complete!\n";
echo "📚 Based on Official DeUna API V2 Documentation\n";
