<?php

// Quick DeUna Point of Sale Validation Test
require_once __DIR__.'/vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "🔧 DeUna Point of Sale Validation Test\n";
echo '='.str_repeat('=', 50)."\n";

// Test different Points of Sale from documentation
$testPointsOfSale = ['138', '139', '140', '141', '142'];

echo '📋 Testing Points of Sale from PDF documentation: '.implode(', ', $testPointsOfSale)."\n\n";

foreach ($testPointsOfSale as $pos) {
    echo "🧪 Testing Point of Sale: $pos\n";
    echo str_repeat('-', 30)."\n";

    try {
        // Create test payment data
        $testPaymentData = [
            'order_id' => 'POS-TEST-'.$pos.'-'.time(),
            'amount' => 1.00, // Minimal amount for testing
            'currency' => 'USD',
            'customer' => [
                'name' => 'Test Customer POS '.$pos,
                'email' => 'test.pos'.$pos.'@example.com',
            ],
            'items' => [[
                'name' => 'Test Product POS '.$pos,
                'quantity' => 1,
                'price' => 1.00,
                'description' => 'Test product for Point of Sale validation',
            ]],
            'qr_type' => 'dynamic',
            'format' => '2',
        ];

        // Temporarily override Point of Sale config
        config(['deuna.point_of_sale' => $pos]);

        echo "📝 Using Point of Sale: $pos\n";
        echo "💰 Amount: $1.00\n";

        // Test with DeUna service directly
        $deunaService = app(\App\Domain\Interfaces\DeunaServiceInterface::class);

        // Make the API call
        $result = $deunaService->createPayment($testPaymentData);

        echo "✅ SUCCESS! Point of Sale $pos works!\n";
        echo '📊 Response: payment_id = '.($result['payment_id'] ?? 'N/A')."\n";
        echo '🔗 Has QR: '.(isset($result['qr_code_base64']) ? 'YES' : 'NO')."\n";
        echo '🌐 Has Link: '.(isset($result['payment_url']) ? 'YES' : 'NO')."\n";

        // This POS works, we can stop here
        echo "\n🎉 FOUND WORKING POINT OF SALE: $pos\n";
        echo "💾 Updating .env with working POS...\n";

        // Update .env file
        $envPath = __DIR__.'/.env';
        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            $envContent = preg_replace('/^DEUNA_POINT_OF_SALE=.*/m', "DEUNA_POINT_OF_SALE=$pos", $envContent);
            file_put_contents($envPath, $envContent);
            echo "✅ .env updated with DEUNA_POINT_OF_SALE=$pos\n";
        }

        break; // Exit loop on first success

    } catch (Exception $e) {
        echo '❌ FAILED: '.$e->getMessage()."\n";

        if (str_contains($e->getMessage(), 'Entity does not exist') ||
            str_contains($e->getMessage(), 'not found') ||
            str_contains($e->getMessage(), '404')) {
            echo "   → Point of Sale $pos not available with these credentials\n";
        } elseif (str_contains($e->getMessage(), '401') || str_contains($e->getMessage(), '403')) {
            echo "   → Authentication issue (check API credentials)\n";
        } else {
            echo '   → Other error: '.substr($e->getMessage(), 0, 100)."...\n";
        }
    }

    echo "\n";
}

echo str_repeat('=', 60)."\n";
echo "🚀 Point of Sale validation complete!\n";
echo "💡 If no POS worked, contact DeUna support for correct POS values\n";
echo "📧 Email: Check with client services for active Point of Sale IDs\n";
