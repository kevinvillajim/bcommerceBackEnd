<?php

namespace App\Http\Controllers;

use App\Factories\PaymentValidatorFactory;
use App\Services\PaymentProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeunaWebhookController extends Controller
{
    public function __construct(
        private PaymentProcessingService $paymentProcessingService,
        private PaymentValidatorFactory $validatorFactory
    ) {}

    /**
     * Handle DeUna webhook notifications usando arquitectura centralizada
     */
    public function handlePaymentStatus(Request $request): JsonResponse
    {
        try {
            Log::info('🔔 DeUna webhook recibido con arquitectura centralizada', [
                'headers' => $request->headers->all(),
                'body' => $request->all(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Obtener payload JSON
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

            // Auto-detectar tipo de validación
            $validationType = $this->validatorFactory->detectDeunaValidationType($webhookData);

            Log::info('🏭 Auto-detectando validación Deuna', [
                'validation_type' => $validationType,
                'event' => $webhookData['event'] ?? $webhookData['eventType'] ?? 'unknown',
                'payment_id' => $webhookData['payment_id'] ?? $webhookData['idTransaction'] ?? 'unknown',
            ]);

            // Crear validador específico
            $validator = $this->validatorFactory->getValidator('deuna', $validationType);

            // Validar webhook
            $validationResult = $validator->validatePayment($webhookData);

            if ($validationResult->isSuccessful()) {
                Log::info('✅ Webhook Deuna validado exitosamente', [
                    'transaction_id' => $validationResult->metadata['payment_id'] ?? 'N/A',
                    'status' => $validationResult->metadata['status'] ?? 'N/A',
                ]);

                // Buscar usuario asociado
                $transactionId = $validationResult->metadata['payment_id'] ?? null;
                $userId = null;

                if ($transactionId) {
                    $deunaPayment = \App\Models\DeunaPayment::where('payment_id', $transactionId)->first();
                    if ($deunaPayment) {
                        $userId = $deunaPayment->user_id;
                    }
                }

                if ($userId) {
                    // Procesar webhook con usuario identificado
                    // Generar sessionId artificial para webhooks Deuna basado en transaction_id
                    $sessionId = 'deuna_webhook_' . $transactionId;

                    $processingResult = $this->paymentProcessingService->processSuccessfulPayment(
                        $validationResult,
                        $sessionId
                    );

                    if ($processingResult['success']) {
                        Log::info('✅ Webhook Deuna procesado exitosamente', [
                            'order_id' => $processingResult['order']['id'],
                            'transaction_id' => $transactionId,
                        ]);

                        return response()->json([
                            'success' => true,
                            'message' => 'Webhook processed successfully',
                            'data' => [
                                'payment_id' => $transactionId,
                                'order_id' => $processingResult['order']['id'],
                                'status' => 'processed',
                                'processed_at' => now()->toISOString(),
                            ],
                        ]);
                    }

                    Log::warning('⚠️ Error procesando webhook Deuna', [
                        'transaction_id' => $transactionId,
                        'message' => $processingResult['message'] ?? 'Error desconocido',
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Processing failed',
                        'error' => $processingResult['message'] ?? 'Unknown error',
                    ], 200); // 200 para evitar reintentos de Deuna
                }

                Log::warning('⚠️ Webhook Deuna válido pero sin usuario asociado', [
                    'transaction_id' => $transactionId,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Webhook received but no user found',
                ], 200);
            }

            Log::warning('⚠️ Webhook Deuna inválido', [
                'error_code' => $validationResult->errorCode,
                'error_message' => $validationResult->errorMessage,
                'payload' => $webhookData,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook',
                'error' => $validationResult->errorMessage,
            ], 200); // 200 para evitar reintentos de Deuna

        } catch (\Exception $e) {
            Log::error('Error processing DeUna webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'headers' => $request->headers->all(),
            ]);

            // Retornar 200 para evitar reintentos de DeUna
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
     * Simulate successful Deuna payment webhook usando arquitectura centralizada
     */
    public function simulatePaymentSuccess(Request $request): JsonResponse
    {
        if (config('app.env') === 'production') {
            return response()->json([
                'success' => false,
                'message' => 'Simulation endpoint not available in production',
            ], 403);
        }

        try {
            $validated = $request->validate([
                'payment_id' => 'required|string',
                'transaction_id' => 'required|string',
                'simulate_deuna' => 'required|boolean',
                'calculated_total' => 'sometimes|numeric|min:0',
                'session_id' => 'sometimes|string|max:100', // Session ID para recuperar CheckoutData
            ]);

            Log::info('🧪 Simulating Deuna payment success with centralized architecture', [
                'payment_id' => $validated['payment_id'],
                'transaction_id' => $validated['transaction_id'],
                'simulated_by' => 'test_endpoint',
            ]);

            // Verificar que el pago existe
            $existingPayment = \App\Models\DeunaPayment::where('payment_id', $validated['payment_id'])->first();
            if (! $existingPayment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found in database',
                    'payment_id' => $validated['payment_id'],
                ], 404);
            }

            // Crear validador de simulación
            $validator = $this->validatorFactory->getValidator('deuna', 'simulation');

            // Validar simulación
            $validationResult = $validator->validatePayment($validated);

            if ($validationResult->isSuccessful()) {
                // Procesar simulación con servicio centralizado
                // Usar session_id real del request si está disponible, sino generar artificial
                $sessionId = $validated['session_id'] ?? 'deuna_simulation_' . $validated['payment_id'];

                $processingResult = $this->paymentProcessingService->processSuccessfulPayment(
                    $validationResult,
                    $sessionId
                );

                if ($processingResult['success']) {
                    Log::info('✅ Deuna simulation processed successfully', [
                        'order_id' => $processingResult['order']['id'],
                        'payment_id' => $validated['payment_id'],
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Payment simulation completed successfully',
                        'data' => [
                            'payment_id' => $validated['payment_id'],
                            'order_id' => $processingResult['order']['id'],
                            'order_number' => $processingResult['order']['number'],
                            'total' => $processingResult['order']['total'],
                            'simulated_status' => 'completed',
                            'simulation_time' => now()->toISOString(),
                        ],
                        'note' => '🧪 This was a simulated payment for testing purposes',
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Payment simulation processing failed',
                    'error' => $processingResult['message'] ?? 'Unknown error',
                ], 400);
            }

            return response()->json([
                'success' => false,
                'message' => 'Payment simulation validation failed',
                'error' => $validationResult->errorMessage,
                'error_code' => $validationResult->errorCode,
            ], 400);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $e->errors(),
            ], 422);
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
