<?php

/**
 * Debug script to test DeUna payment creation
 * This script helps debug the payment creation process step by step
 */

require_once __DIR__.'/vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

// Bootstrap Laravel application
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// Sample payment data similar to what frontend sends
$paymentData = [
    'order_id' => 'ORDER-1755006417854-MVOR694JA',
    'amount' => 6.90, // Usando el monto correcto del frontend
    'currency' => 'USD',
    'customer' => [
        'name' => 'Juan Perez',
        'email' => 'test@test.com',
    ],
    'items' => [
        [
            'name' => 'Kevin Villacreses',
            'quantity' => 1,
            'price' => 6.90,
        ],
    ],
    'qr_type' => 'dynamic',
    'format' => '2',
    'metadata' => [
        'source' => 'bcommerce_frontend',
        'user_id' => 25,
        'cart_id' => 2,
        'checkout_timestamp' => '2025-08-12T13:46:57.854Z',
    ],
];

echo "🚀 Debugging DeUna Payment Creation\n";
echo "=====================================\n\n";

echo "📋 Payment Data:\n";
echo '- Order ID: '.$paymentData['order_id'].' (Length: '.strlen($paymentData['order_id']).")\n";
echo '- Amount: $'.$paymentData['amount']."\n";
echo '- Customer: '.$paymentData['customer']['name'].' ('.$paymentData['customer']['email'].")\n\n";

// Test the short reference generation
function generateShortReference(string $orderId): string
{
    // If order ID is already 20 chars or less, use it
    if (strlen($orderId) <= 20) {
        return $orderId;
    }

    // Extract meaningful parts and create shorter version
    // Example: ORDER-1755006417854-MVOR694JA -> O1755006417854M694JA (20 chars)
    if (preg_match('/ORDER-(\d+)-([A-Z0-9]+)/', $orderId, $matches)) {
        $timestamp = $matches[1];
        $code = $matches[2];

        // Create short version: O + timestamp + first 4 chars of code
        $shortRef = 'O'.$timestamp.substr($code, 0, 4);

        // If still too long, truncate timestamp
        if (strlen($shortRef) > 20) {
            $shortRef = 'O'.substr($timestamp, -10).substr($code, 0, 4);
        }

        return substr($shortRef, 0, 20);
    }

    // Fallback: use last 20 characters
    return substr($orderId, -20);
}

$shortRef = generateShortReference($paymentData['order_id']);
echo "🔄 Reference Conversion:\n";
echo '- Original: '.$paymentData['order_id'].' ('.strlen($paymentData['order_id'])." chars)\n";
echo '- Short: '.$shortRef.' ('.strlen($shortRef)." chars)\n";
echo '- Valid for DeUna: '.(strlen($shortRef) <= 20 ? '✅ YES' : '❌ NO')."\n\n";

// Test the payload structure that would be sent to DeUna
$payload = [
    'pointOfSale' => '462', // From config
    'qrType' => $paymentData['qr_type'],
    'amount' => (float) $paymentData['amount'],
    'detail' => 'Kevin Villacreses', // From items
    'internalTransactionReference' => $shortRef,
    'format' => $paymentData['format'],
];

echo "📤 DeUna API Payload:\n";
echo json_encode($payload, JSON_PRETTY_PRINT)."\n\n";

// Validate all fields
echo "✅ Validation Results:\n";
echo '- pointOfSale: '.(isset($payload['pointOfSale']) ? '✅ Set' : '❌ Missing')."\n";
echo '- qrType: '.(isset($payload['qrType']) ? '✅ Set' : '❌ Missing')."\n";
echo '- amount: '.(is_numeric($payload['amount']) && $payload['amount'] > 0 ? '✅ Valid ($'.$payload['amount'].')' : '❌ Invalid')."\n";
echo '- detail: '.(isset($payload['detail']) && ! empty($payload['detail']) ? '✅ Set' : '❌ Missing')."\n";
echo '- internalTransactionReference: '.(strlen($payload['internalTransactionReference']) <= 20 ? '✅ Valid ('.strlen($payload['internalTransactionReference']).' chars)' : '❌ Too long')."\n";
echo '- format: '.(isset($payload['format']) ? '✅ Set' : '❌ Missing')."\n\n";

echo "🔐 Environment Check:\n";
$apiUrl = env('DEUNA_API_URL', 'NOT_SET');
$apiKey = env('DEUNA_API_KEY', 'NOT_SET');
$apiSecret = env('DEUNA_API_SECRET', 'NOT_SET');
$pointOfSale = env('DEUNA_POINT_OF_SALE', 'NOT_SET');

echo '- API URL: '.($apiUrl !== 'NOT_SET' ? '✅ Configured' : '❌ Missing')."\n";
echo '- API Key: '.($apiKey !== 'NOT_SET' ? '✅ Configured' : '❌ Missing')."\n";
echo '- API Secret: '.($apiSecret !== 'NOT_SET' ? '✅ Configured' : '❌ Missing')."\n";
echo '- Point of Sale: '.($pointOfSale !== 'NOT_SET' ? "✅ Configured ($pointOfSale)" : '❌ Missing')."\n\n";

echo "🎯 Next Steps:\n";
echo "1. Verify environment variables are set in .env\n";
echo "2. Test the actual API call using the corrected payload\n";
echo "3. Update frontend to send correct amount (6.90 instead of 6.15)\n";
echo "4. Verify the short reference generation works correctly\n\n";

echo "📊 Amount Issue Analysis:\n";
echo "- Frontend sent: $6.15\n";
echo "- Should be: $6.90 (as shown in checkout calculation)\n";
echo '- Difference: $'.(6.90 - 6.15)."\n";
echo "- This suggests the frontend cart calculation is not matching what's sent to backend\n\n";

echo "🔧 Debug completed!\n";
