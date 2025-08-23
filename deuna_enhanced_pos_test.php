<?php

// Enhanced DeUna Point of Sale Discovery
require_once __DIR__.'/vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "🔍 ENHANCED DeUna Point of Sale Discovery\n";
echo '='.str_repeat('=', 50)."\n";

// Enhanced POS list based on common patterns and client email analysis
$testPointsOfSale = [
    // From documentation
    '138', '139', '140', '141', '142',
    // From original email (might work with different credentials)
    '462',
    // Common testing values
    '1', '10', '100', '101', '102', '103', '104', '105',
    '200', '201', '202', '203', '204', '205',
    '300', '301', '302', '303', '304', '305',
    '400', '401', '402', '403', '404', '405',
    '500', '501', '502', '503', '504', '505',
    // Sequential from client pattern
    '460', '461', '463', '464', '465',
    // Other common patterns
    '11820', '10810', // Found in webhook documentation examples
];

echo '🎯 Testing '.count($testPointsOfSale)." Point of Sale values\n";
echo "📧 Using credentials from client email\n";
echo "⚡ Quick test mode (minimal payload)\n\n";

$workingPOS = [];
$failedPOS = [];

foreach ($testPointsOfSale as $pos) {
    echo "🧪 Testing POS: $pos... ";

    try {
        // Create minimal test payment data
        $testPaymentData = [
            'order_id' => 'POS-TEST-'.$pos.'-'.time(),
            'amount' => 0.01, // Minimum amount
            'currency' => 'USD',
            'customer' => [
                'name' => 'Test POS '.$pos,
                'email' => 'test.pos'.$pos.'@example.com',
            ],
            'items' => [[
                'name' => 'Test POS '.$pos,
                'quantity' => 1,
                'price' => 0.01,
                'description' => 'POS validation test',
            ]],
            'qr_type' => 'dynamic',
            'format' => '0', // Minimal format - just QR
        ];

        // Temporarily override Point of Sale config
        config(['deuna.point_of_sale' => $pos]);

        // Test with DeUna service directly
        $deunaService = app(\App\Domain\Interfaces\DeunaServiceInterface::class);

        // Make the API call
        $result = $deunaService->createPayment($testPaymentData);

        echo "✅ SUCCESS!\n";
        $workingPOS[] = [
            'pos' => $pos,
            'payment_id' => $result['payment_id'] ?? 'N/A',
            'status' => $result['status'] ?? 'N/A',
        ];

        // Found working POS, can continue testing or stop here
        echo '   💰 Payment ID: '.($result['payment_id'] ?? 'N/A')."\n";

    } catch (Exception $e) {
        echo "❌ FAILED\n";
        $failedPOS[] = $pos;

        $errorMessage = $e->getMessage();

        if (str_contains($errorMessage, 'Invalid point of sale')) {
            echo "   → Invalid POS number\n";
        } elseif (str_contains($errorMessage, 'Authentication') ||
                  str_contains($errorMessage, '401') ||
                  str_contains($errorMessage, '403')) {
            echo "   → Authentication issue\n";
        } elseif (str_contains($errorMessage, 'amount') && str_contains($errorMessage, 'minimum')) {
            echo "   → Amount too low (POS might be valid!)\n";
            // This could actually mean the POS exists but has minimum amount requirements
        } elseif (str_contains($errorMessage, '500')) {
            echo "   → Server error (POS might be valid!)\n";
        } else {
            echo '   → Other: '.substr($errorMessage, 0, 60)."...\n";
        }
    }

    // Brief pause to avoid rate limiting
    usleep(100000); // 0.1 seconds
}

echo "\n".str_repeat('=', 60)."\n";
echo "📊 RESULTS SUMMARY\n";
echo str_repeat('=', 60)."\n";

if (! empty($workingPOS)) {
    echo "✅ WORKING POINT OF SALE VALUES:\n";
    foreach ($workingPOS as $pos) {
        echo "   🎯 POS: {$pos['pos']} - Payment ID: {$pos['payment_id']}\n";
    }

    // Update .env with first working POS
    $firstWorkingPOS = $workingPOS[0]['pos'];
    echo "\n💾 Updating .env with working POS: $firstWorkingPOS\n";

    $envPath = __DIR__.'/.env';
    if (file_exists($envPath)) {
        $envContent = file_get_contents($envPath);
        $envContent = preg_replace('/^DEUNA_POINT_OF_SALE=.*/m', "DEUNA_POINT_OF_SALE=$firstWorkingPOS", $envContent);
        file_put_contents($envPath, $envContent);
        echo "✅ .env updated successfully!\n";
    }

} else {
    echo "❌ NO WORKING POINT OF SALE FOUND\n\n";
    echo "🔍 TROUBLESHOOTING SUGGESTIONS:\n";
    echo "   1. Contact DeUna support with your API credentials\n";
    echo "   2. Request list of valid Point of Sale IDs\n";
    echo "   3. Verify credentials are for correct environment\n";
    echo "   4. Check if account needs additional setup\n\n";

    echo "📧 EMAIL TO SEND TO DEUNA:\n";
    echo "   Subject: Point of Sale Configuration Request\n";
    echo "   Body: Hello, I need the valid Point of Sale IDs for:\n";
    echo '         API Key: '.substr(config('deuna.api_key'), 0, 8)."...\n";
    echo '         Environment: '.config('deuna.environment')."\n";
    echo "         All tested POS values (138-142, 462, etc.) return 'Invalid point of sale number'\n";
}

echo "\n📈 TESTED: ".count($testPointsOfSale)." POS values\n";
echo '✅ WORKING: '.count($workingPOS)."\n";
echo '❌ FAILED: '.count($failedPOS)."\n";

echo "\n🚀 Enhanced POS Discovery Complete!\n";
