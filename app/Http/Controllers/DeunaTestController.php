<?php

namespace App\Http\Controllers;

use App\Infrastructure\Services\DeunaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeunaTestController extends Controller
{
    public function __construct(
        private DeunaService $deunaService
    ) {}

    /**
     * Test endpoint to verify DeUna integration
     */
    public function testPayment(Request $request): JsonResponse
    {
        try {
            // Sample test data with corrected amount
            $testPaymentData = [
                'order_id' => 'TEST-'.time().'-'.strtoupper(substr(md5(uniqid()), 0, 8)),
                'amount' => 6.90, // Monto correcto del frontend
                'currency' => 'USD',
                'customer' => [
                    'name' => 'Test User',
                    'email' => 'test@bcommerce.com',
                    'phone' => '+593999999999',
                ],
                'items' => [
                    [
                        'name' => 'Test Product',
                        'quantity' => 1,
                        'price' => 6.90,
                    ],
                ],
                'qr_type' => 'dynamic',
                'format' => '2',
                'metadata' => [
                    'source' => 'test_endpoint',
                    'test_mode' => true,
                    'timestamp' => now()->toISOString(),
                ],
            ];

            Log::info('Testing DeUna payment creation', [
                'test_data' => $testPaymentData,
            ]);

            // Create payment
            $result = $this->deunaService->createPayment($testPaymentData);

            return response()->json([
                'success' => true,
                'message' => 'Test payment created successfully',
                'test_data' => $testPaymentData,
                'deuna_response' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('Test payment failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Test payment failed',
                'error' => $e->getMessage(),
                'debug_info' => [
                    'environment_check' => [
                        'api_url' => config('deuna.api_url'),
                        'has_api_key' => ! empty(config('deuna.api_key')),
                        'has_api_secret' => ! empty(config('deuna.api_secret')),
                        'point_of_sale' => config('deuna.point_of_sale'),
                    ],
                ],
            ], 500);
        }
    }

    /**
     * Test reference generation
     */
    public function testReference(Request $request): JsonResponse
    {
        $orderId = $request->input('order_id', 'ORDER-1755006417854-MVOR694JA');

        $shortRef = $this->generateShortReference($orderId);

        return response()->json([
            'original' => $orderId,
            'original_length' => strlen($orderId),
            'short_reference' => $shortRef,
            'short_length' => strlen($shortRef),
            'is_valid' => strlen($shortRef) <= 20,
        ]);
    }

    /**
     * Generate a short reference for DeUna (max 20 characters)
     */
    private function generateShortReference(string $orderId): string
    {
        // If order ID is already 20 chars or less, use it
        if (strlen($orderId) <= 20) {
            return $orderId;
        }

        // Extract meaningful parts and create shorter version
        // Example: ORDER-1755006417854-MVOR694JA -> O1755006417854MVOR (18 chars)
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
}
