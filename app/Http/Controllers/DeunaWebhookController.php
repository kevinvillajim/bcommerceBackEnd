<?php

namespace App\Http\Controllers;

use App\UseCases\Payment\HandleDeunaWebhookUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeunaWebhookController extends Controller
{
    public function __construct(
        private HandleDeunaWebhookUseCase $handleWebhookUseCase
    ) {}

    /**
     * Handle DeUna webhook notifications
     */
    public function handlePaymentStatus(Request $request): JsonResponse
    {
        try {
            // Log the incoming webhook
            Log::info('DeUna webhook received', [
                'headers' => $request->headers->all(),
                'body' => $request->all(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Get the raw body for signature verification
            $rawBody = $request->getContent();
            $webhookData = json_decode($rawBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON in webhook payload', [
                    'json_error' => json_last_error_msg(),
                    'raw_body' => $rawBody,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid JSON payload',
                ], 400);
            }

            // Get signature from headers
            $signature = $request->header('X-DeUna-Signature')
                ?? $request->header('x-deuna-signature')
                ?? $request->header('signature')
                ?? '';

            Log::info('Processing webhook data', [
                'has_signature' => ! empty($signature),
                'event' => $webhookData['event'] ?? $webhookData['eventType'] ?? 'unknown',
                'payment_id' => $webhookData['payment_id'] ?? $webhookData['idTransacionReference'] ?? 'unknown',
            ]);

            // Process the webhook
            $result = $this->handleWebhookUseCase->execute($webhookData, $signature);

            Log::info('Webhook processed successfully', [
                'payment_id' => $result['payment_id'] ?? 'unknown',
                'event' => $result['event'] ?? 'unknown',
                'status' => $result['status'] ?? 'unknown',
            ]);

            // Return success response
            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully',
                'data' => [
                    'payment_id' => $result['payment_id'],
                    'event' => $result['event'],
                    'status' => $result['status'],
                    'processed_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing DeUna webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'headers' => $request->headers->all(),
            ]);

            // Still return 200 to prevent DeUna from retrying
            // But log the error for investigation
            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed',
                'error' => $e->getMessage(),
                'processed_at' => now()->toISOString(),
            ], 200);
        }
    }

    /**
     * Test webhook endpoint (for development)
     */
    public function testWebhook(Request $request): JsonResponse
    {
        if (config('app.env') !== 'local') {
            return response()->json([
                'success' => false,
                'message' => 'Test endpoint only available in local environment',
            ], 403);
        }

        try {
            Log::info('Test webhook received', [
                'body' => $request->all(),
                'headers' => $request->headers->all(),
            ]);

            // Simulate webhook processing
            $testData = [
                'event' => $request->input('event', 'payment.completed'),
                'payment_id' => $request->input('payment_id', 'TEST-PAY-123'),
                'status' => $request->input('status', 'completed'),
                'amount' => $request->input('amount', 10.00),
                'currency' => $request->input('currency', 'USD'),
                'timestamp' => now()->toISOString(),
            ];

            Log::info('Test webhook data processed', $testData);

            return response()->json([
                'success' => true,
                'message' => 'Test webhook processed successfully',
                'data' => $testData,
                'note' => 'This is a test endpoint for development only',
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing test webhook', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Test webhook processing failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Simulate successful Deuna payment webhook
     * This endpoint simulates a real payment completion for testing
     */
    public function simulatePaymentSuccess(Request $request): JsonResponse
    {
        // Only allow simulation in development/staging environments
        if (config('app.env') === 'production') {
            return response()->json([
                'success' => false,
                'message' => 'Simulation endpoint not available in production',
            ], 403);
        }

        try {
            // Get payment_id from request (should match an existing Deuna payment)
            $paymentId = $request->input('payment_id');
            if (! $paymentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'payment_id is required for simulation',
                ], 400);
            }

            Log::info('🧪 Simulating Deuna payment success', [
                'payment_id' => $paymentId,
                'simulated_by' => 'test_endpoint',
            ]);

            // First, verify that the payment exists in database
            try {
                $existingPayment = \App\Models\DeunaPayment::where('payment_id', $paymentId)->first();
                if (! $existingPayment) {
                    Log::error('❌ Payment not found in database for simulation', [
                        'payment_id' => $paymentId,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Payment not found in database. Cannot simulate non-existent payment.',
                        'payment_id' => $paymentId,
                    ], 404);
                }

                Log::info('✅ Found payment in database for simulation', [
                    'payment_id' => $paymentId,
                    'current_status' => $existingPayment->status,
                    'order_id' => $existingPayment->order_id,
                ]);

            } catch (\Exception $e) {
                Log::error('❌ Error checking payment existence', [
                    'payment_id' => $paymentId,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error checking payment existence: '.$e->getMessage(),
                    'payment_id' => $paymentId,
                ], 500);
            }

            // 🚨 CRITICAL FIX: Use REAL payment data to simulate exact DeUna webhook
            $webhookData = [
                'idTransaction' => $paymentId,
                'status' => 'SUCCESS', // DeUna uses 'SUCCESS' for completed payments
                'event' => 'payment.completed',
                'amount' => $existingPayment->amount, // ✅ USE REAL AMOUNT
                'currency' => $existingPayment->currency ?? 'USD', // ✅ USE REAL CURRENCY
                'customerEmail' => $existingPayment->customer['email'] ?? 'test@example.com', // ✅ REAL CUSTOMER
                'customerFullName' => $existingPayment->customer['name'] ?? 'Test Customer', // ✅ REAL NAME  
                'customerIdentification' => $existingPayment->customer['identification'] ?? '1234567890',
                'transferNumber' => 'SIM-'.time(),
                'branchId' => config('deuna.point_of_sale'), // ✅ USE REAL BRANCH
                'posId' => config('deuna.point_of_sale'), // ✅ USE REAL POS
                'timestamp' => now()->toISOString(),
                'data' => [
                    'payment_id' => $paymentId,
                    'status' => 'completed',
                    'transaction_id' => 'TXN-SIM-'.time(),
                ],
                // ✅ CRITICAL: Include the REAL payment items with product_id
                'items' => $existingPayment->items ?: [],
                // Metadata to identify this as a simulation
                'simulation' => true,
                'simulated_at' => now()->toISOString(),
            ];

            Log::info('🔧 Webhook data prepared for simulation', [
                'payment_id' => $paymentId,
                'amount' => $webhookData['amount'],
                'currency' => $webhookData['currency'],
                'customer_email' => $webhookData['customerEmail'],
                'items_count' => count($webhookData['items']),
                'has_product_ids' => !empty($webhookData['items']) ? array_column($webhookData['items'], 'product_id') : 'no_items',
            ]);

            Log::info('🚀 Processing simulated webhook data', [
                'payment_id' => $paymentId,
                'webhook_data' => $webhookData,
            ]);

            // Process the simulated webhook using the real handler
            $result = $this->handleWebhookUseCase->execute($webhookData, '');

            Log::info('✅ Simulated payment webhook processed successfully', [
                'payment_id' => $paymentId,
                'result' => $result,
            ]);

            // Verify that order was actually created
            try {
                $createdOrder = \App\Models\Order::where('id', $existingPayment->order_id)->first();
                $orderCreated = $createdOrder !== null;

                Log::info('🔍 Post-webhook verification', [
                    'payment_id' => $paymentId,
                    'order_id' => $existingPayment->order_id,
                    'order_created' => $orderCreated,
                    'order_total' => $orderCreated ? $createdOrder->total : null,
                    'webhook_processed' => $result['processed'] ?? false,
                ]);

            } catch (\Exception $e) {
                Log::error('❌ Error verifying order creation', [
                    'payment_id' => $paymentId,
                    'error' => $e->getMessage(),
                ]);
                $orderCreated = false;
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment simulation completed successfully',
                'data' => [
                    'payment_id' => $paymentId,
                    'simulated_status' => 'completed',
                    'webhook_result' => $result,
                    'order_created' => $orderCreated ?? ($result['processed'] ?? false),
                    'order_id' => $existingPayment->order_id ?? null,
                    'simulation_time' => now()->toISOString(),
                ],
                'note' => '🧪 This was a simulated payment for testing purposes',
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error simulating payment success', [
                'payment_id' => $request->input('payment_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment simulation failed',
                'error' => $e->getMessage(),
                'payment_id' => $request->input('payment_id'),
            ], 500);
        }
    }

    /**
     * Get webhook configuration info
     */
    public function getWebhookInfo(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'webhook_url' => config('deuna.webhook_url'),
                'environment' => config('deuna.environment'),
                'endpoints' => [
                    'payment_status' => route('deuna.webhook.payment-status'),
                    'test' => config('app.env') === 'local' ? route('deuna.webhook.test') : 'Not available',
                ],
                'supported_events' => [
                    'payment.created',
                    'payment.pending',
                    'payment.completed',
                    'payment.failed',
                    'payment.cancelled',
                    'payment.refunded',
                ],
                'expected_headers' => [
                    'X-DeUna-Signature',
                    'Content-Type: application/json',
                ],
            ],
        ]);
    }

    /**
     * Verify webhook signature (utility endpoint)
     */
    public function verifySignature(Request $request): JsonResponse
    {
        if (config('app.env') !== 'local') {
            return response()->json([
                'success' => false,
                'message' => 'Signature verification endpoint only available in local environment',
            ], 403);
        }

        try {
            $payload = $request->input('payload', '');
            $signature = $request->input('signature', '');

            if (empty($payload) || empty($signature)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Both payload and signature are required',
                ], 400);
            }

            $webhookSecret = config('deuna.webhook_secret');
            $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
            $providedSignature = str_replace('sha256=', '', $signature);

            $isValid = hash_equals($expectedSignature, $providedSignature);

            return response()->json([
                'success' => true,
                'data' => [
                    'is_valid' => $isValid,
                    'expected_signature' => 'sha256='.$expectedSignature,
                    'provided_signature' => $signature,
                    'payload_length' => strlen($payload),
                    'webhook_secret_configured' => ! empty($webhookSecret),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Signature verification failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
