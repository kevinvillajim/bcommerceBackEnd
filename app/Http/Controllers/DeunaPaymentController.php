<?php

namespace App\Http\Controllers;

use App\Domain\Interfaces\DeunaServiceInterface;
use App\Domain\Repositories\DeunaPaymentRepositoryInterface;
use App\Domain\Services\PricingCalculatorService;
use App\UseCases\Payment\CreateDeunaPaymentUseCase;
use App\UseCases\Payment\GenerateQRDeUnatUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DeunaPaymentController extends Controller
{
    public function __construct(
        private CreateDeunaPaymentUseCase $createPaymentUseCase,
        private GenerateQRDeUnatUseCase $generateQRUseCase,
        private DeunaPaymentRepositoryInterface $deunaPaymentRepository,
        private DeunaServiceInterface $deunaService,
        private PricingCalculatorService $pricingService
    ) {}

    /**
     * Generate a short reference for debugging purposes
     */
    private function generateShortReference(string $orderId): string
    {
        // If order ID is already 20 chars or less, use it
        if (strlen($orderId) <= 20) {
            return $orderId;
        }

        // Extract meaningful parts and create shorter version
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

    /**
     * Create a new DeUna payment
     */
    public function createPayment(Request $request): JsonResponse
    {
        try {
            // ✅ NUEVO: Validar datos incluyendo CheckoutData temporal
            $validator = Validator::make($request->all(), [
                'order_id' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0.01|max:999999.99',
                'currency' => 'sometimes|string|size:3|in:USD,PEN,COP,MXN,CLP',
                'customer.name' => 'required|string|max:255',
                'customer.email' => 'required|email|max:255',
                'customer.phone' => 'sometimes|string|max:20',
                'items' => 'sometimes|array',
                'items.*.name' => 'required_with:items|string|max:255',
                'items.*.quantity' => 'required_with:items|integer|min:1',
                'items.*.price' => 'required_with:items|numeric|min:0',
                'items.*.product_id' => 'required_with:items|integer|min:1',
                'qr_type' => 'sometimes|string|in:static,dynamic',
                'format' => 'sometimes|string|in:0,1,2,3,4',
                'metadata' => 'sometimes|array',
                // ✅ CAMPOS PARA CHECKOUTDATA TEMPORAL
                'session_id' => 'sometimes|string|max:100',
                'validated_at' => 'sometimes|string',
                'checkout_data' => 'sometimes|array', // Objeto completo de checkout
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $paymentData = $validator->validated();

            // ✅ DETECTAR SI ES CHECKOUTDATA TEMPORAL
            $hasSessionId = isset($paymentData['session_id']) && ! empty($paymentData['session_id']);
            $hasValidatedAt = isset($paymentData['validated_at']) && ! empty($paymentData['validated_at']);
            $hasCheckoutData = isset($paymentData['checkout_data']) && ! empty($paymentData['checkout_data']);
            $isTemporalCheckout = $hasSessionId && $hasValidatedAt;

            if ($isTemporalCheckout) {
                Log::info('🎯 DeUna: Procesando CheckoutData temporal validado', [
                    'session_id' => $paymentData['session_id'],
                    'validated_at' => $paymentData['validated_at'],
                    'has_checkout_data' => $hasCheckoutData,
                    'order_id' => $paymentData['order_id'],
                    'amount' => $paymentData['amount'],
                ]);
            }

            Log::info('🧮 Creating DeUna payment via API using PricingCalculatorService', [
                'order_id' => $paymentData['order_id'],
                'frontend_amount' => $paymentData['amount'],
                'customer_email' => $paymentData['customer']['email'] ?? 'not_provided',
                'items_count' => count($paymentData['items'] ?? []),
                'is_temporal_checkout' => $isTemporalCheckout,
                'session_id' => $paymentData['session_id'] ?? 'none',
            ]);

            // 🔍 DEBUG: Log the exact items received from frontend
            if (isset($paymentData['items'])) {
                Log::info('🔍 ITEMS RECEIVED FROM FRONTEND', [
                    'items_count' => count($paymentData['items']),
                    'items' => $paymentData['items'],
                    'first_item_keys' => ! empty($paymentData['items']) ? array_keys($paymentData['items'][0]) : 'no_items',
                ]);

                // 🔧 CRITICAL VALIDATION: Check for missing product_id in any item
                foreach ($paymentData['items'] as $index => $item) {
                    if (! isset($item['product_id']) || $item['product_id'] === null) {
                        Log::error('❌ CRITICAL: Missing product_id in item received from frontend', [
                            'item_index' => $index,
                            'item_keys' => array_keys($item),
                            'item_data' => $item,
                            'all_items' => $paymentData['items'],
                        ]);

                        return response()->json([
                            'success' => false,
                            'message' => 'Validation failed: Missing product_id in cart items',
                            'errors' => [
                                'items' => ["Item at index {$index} is missing product_id field"],
                            ],
                            'debug' => [
                                'item_index' => $index,
                                'item_name' => $item['name'] ?? 'Unknown',
                                'available_fields' => array_keys($item),
                            ],
                        ], 422);
                    }
                }

                Log::info('✅ All items have valid product_id fields', [
                    'items_validated' => count($paymentData['items']),
                    'product_ids' => array_map(fn ($item) => $item['product_id'], $paymentData['items']),
                ]);

                // 🧮 NUEVO: Usar PricingCalculatorService para validar/recalcular monto
                $user = $request->user();
                $userId = $user->id;

                // Preparar items para el servicio de pricing
                $pricingItems = [];
                foreach ($paymentData['items'] as $item) {
                    $pricingItems[] = [
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                    ];
                }

                // Extraer código de cupón del metadata si existe
                $couponCode = $paymentData['metadata']['coupon_code'] ?? null;

                Log::info('🧮 Recalculando totales con PricingCalculatorService para Deuna', [
                    'user_id' => $userId,
                    'items_count' => count($pricingItems),
                    'coupon_code' => $couponCode,
                    'frontend_amount' => $paymentData['amount'],
                ]);

                // Calcular totales usando servicio centralizado
                $pricingResult = $this->pricingService->calculateCartTotals(
                    $pricingItems,
                    $userId,
                    $couponCode
                );

                $backendCalculatedTotal = $pricingResult['final_total'];
                $frontendAmount = (float) $paymentData['amount'];
                $difference = abs($backendCalculatedTotal - $frontendAmount);
                $tolerance = 0.01; // Tolerancia de $0.01 para diferencias de redondeo

                if ($difference > $tolerance) {
                    Log::error('❌ DISCREPANCIA DETECTADA: Frontend vs Backend total en Deuna', [
                        'frontend_amount' => $frontendAmount,
                        'backend_calculated' => $backendCalculatedTotal,
                        'difference' => $difference,
                        'tolerance' => $tolerance,
                        'pricing_breakdown' => [
                            'subtotal_original' => $pricingResult['subtotal_original'],
                            'subtotal_with_discounts' => $pricingResult['subtotal_with_discounts'],
                            'seller_discounts' => $pricingResult['seller_discounts'],
                            'volume_discounts' => $pricingResult['volume_discounts'],
                            'coupon_discount' => $pricingResult['coupon_discount'],
                            'iva_amount' => $pricingResult['iva_amount'],
                            'shipping_cost' => $pricingResult['shipping_cost'],
                        ],
                        'items' => $pricingItems,
                        'user_id' => $userId,
                        'coupon_code' => $couponCode,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Discrepancia de pricing detectada',
                        'error' => 'El monto calculado por el frontend no coincide con el backend',
                        'debug' => [
                            'frontend_amount' => $frontendAmount,
                            'backend_calculated' => $backendCalculatedTotal,
                            'difference' => round($difference, 2),
                            'message' => 'Por favor actualiza tu carrito y vuelve a intentar',
                        ],
                        'pricing_breakdown' => [
                            'subtotal_original' => $pricingResult['subtotal_original'],
                            'subtotal_with_discounts' => $pricingResult['subtotal_with_discounts'],
                            'total_discounts' => $pricingResult['total_discounts'],
                            'iva_amount' => $pricingResult['iva_amount'],
                            'shipping_cost' => $pricingResult['shipping_cost'],
                            'final_total_correct' => $backendCalculatedTotal,
                        ],
                    ], 422);
                }

                // ✅ Usar el monto recalculado por el backend para garantizar consistencia
                $paymentData['amount'] = $backendCalculatedTotal;

                Log::info('✅ Totales validados exitosamente para Deuna', [
                    'frontend_amount' => $frontendAmount,
                    'backend_calculated' => $backendCalculatedTotal,
                    'difference' => $difference,
                    'final_amount_used' => $paymentData['amount'],
                ]);
            } else {
                Log::info('🔍 NO ITEMS received in payment request');

                // Si no hay items, usar el monto del frontend (caso legacy)
                Log::warning('⚠️ Usando monto del frontend sin validación (no hay items)', [
                    'amount' => $paymentData['amount'],
                    'order_id' => $paymentData['order_id'],
                ]);
            }

            // Execute use case
            $result = $this->createPaymentUseCase->execute($paymentData);

            return response()->json([
                'success' => true,
                'message' => 'Payment created successfully',
                'data' => [
                    'payment_id' => $result['payment']['payment_id'],
                    'order_id' => $result['payment']['order_id'],
                    'amount' => $result['payment']['amount'],
                    'currency' => $result['payment']['currency'],
                    'status' => $result['payment']['status'],
                    'qr_code_base64' => $result['qr_code'],
                    'payment_url' => $result['payment_url'],
                    'numeric_code' => $result['numeric_code'] ?? null,
                    'created_at' => $result['payment']['created_at'],
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating DeUna payment via API', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Parse error message if it contains JSON details
            $errorMessage = $e->getMessage();
            $detailedError = null;

            if (strpos($errorMessage, 'Failed to create DeUna payment: Error creating DeUna payment:') === 0) {
                // Extract JSON part from nested error message
                $jsonStart = strrpos($errorMessage, '{');
                if ($jsonStart !== false) {
                    $jsonPart = substr($errorMessage, $jsonStart);
                    $decodedError = json_decode($jsonPart, true);
                    if ($decodedError && isset($decodedError['response']['message'])) {
                        $detailedError = $decodedError['response']['message'];
                        if (is_array($detailedError)) {
                            $detailedError = implode(', ', $detailedError);
                        }
                    }
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment',
                'error' => $detailedError ?? $errorMessage,
                'debug' => [
                    'order_id' => $request->input('order_id'),
                    'amount' => $request->input('amount'),
                    'short_reference' => $this->generateShortReference($request->input('order_id', '')),
                ],
            ], 500);
        }
    }

    /**
     * Generate QR code for payment
     */
    public function generateQR(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'payment_id' => 'sometimes|string|max:255',
                'order_id' => 'sometimes|string|max:255',
                'amount' => 'required_without:payment_id,order_id|numeric|min:0.01',
                'customer.name' => 'required_without:payment_id,order_id|string|max:255',
                'customer.email' => 'required_without:payment_id,order_id|email|max:255',
                'qr_type' => 'sometimes|string|in:static,dynamic',
                'format' => 'sometimes|string|in:0,1,2,3,4',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();

            Log::info('Generating QR code via API', [
                'payment_id' => $data['payment_id'] ?? null,
                'order_id' => $data['order_id'] ?? null,
            ]);

            $result = $this->generateQRUseCase->execute($data);

            return response()->json([
                'success' => true,
                'message' => 'QR code generated successfully',
                'data' => [
                    'payment_id' => $result['payment_id'],
                    'qr_code_base64' => $result['qr_code_base64'],
                    'payment_url' => $result['payment_url'],
                    'numeric_code' => $result['numeric_code'] ?? null,
                    'status' => $result['status'],
                    'amount' => $result['amount'],
                    'currency' => $result['currency'],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating QR code via API', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate QR code',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(string $paymentId): JsonResponse
    {
        try {
            Log::info('Getting payment status via API', ['payment_id' => $paymentId]);

            // Find payment in database
            $payment = $this->deunaPaymentRepository->findByPaymentId($paymentId);
            if (! $payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found',
                ], 404);
            }

            // Try to get updated status from DeUna
            try {
                $deunaStatus = $this->deunaService->getPaymentStatus($paymentId);

                // Update local payment if status changed
                if (isset($deunaStatus['status']) && $deunaStatus['status'] !== $payment->getStatus()) {
                    $payment->setStatus($deunaStatus['status']);
                    $this->deunaPaymentRepository->update($payment);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'payment_id' => $payment->getPaymentId(),
                        'order_id' => $payment->getOrderId(),
                        'status' => $deunaStatus['status'] ?? $payment->getStatus(),
                        'amount' => $payment->getAmount(),
                        'currency' => $payment->getCurrency(),
                        'qr_code_base64' => $payment->getQrCode(),
                        'payment_url' => $payment->getPaymentUrl(),
                        'created_at' => $payment->getCreatedAt(),
                        'updated_at' => $payment->getUpdatedAt(),
                        'completed_at' => $payment->getCompletedAt(),
                        'deuna_details' => $deunaStatus,
                    ],
                ]);

            } catch (\Exception $e) {
                // If DeUna API fails, return local data
                Log::warning('Failed to get status from DeUna API, returning local data', [
                    'payment_id' => $paymentId,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'payment_id' => $payment->getPaymentId(),
                        'order_id' => $payment->getOrderId(),
                        'status' => $payment->getStatus(),
                        'amount' => $payment->getAmount(),
                        'currency' => $payment->getCurrency(),
                        'qr_code_base64' => $payment->getQrCode(),
                        'payment_url' => $payment->getPaymentUrl(),
                        'created_at' => $payment->getCreatedAt(),
                        'updated_at' => $payment->getUpdatedAt(),
                        'completed_at' => $payment->getCompletedAt(),
                        'warning' => 'Using cached data, DeUna API unavailable',
                    ],
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error getting payment status via API', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get payment status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payment by order ID
     */
    public function getPaymentByOrderId(string $orderId): JsonResponse
    {
        try {
            Log::info('Getting payment by order ID via API', ['order_id' => $orderId]);

            $payment = $this->deunaPaymentRepository->findByOrderId($orderId);
            if (! $payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found for order',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_id' => $payment->getPaymentId(),
                    'order_id' => $payment->getOrderId(),
                    'status' => $payment->getStatus(),
                    'amount' => $payment->getAmount(),
                    'currency' => $payment->getCurrency(),
                    'qr_code_base64' => $payment->getQrCode(),
                    'payment_url' => $payment->getPaymentUrl(),
                    'customer' => $payment->getCustomer(),
                    'items' => $payment->getItems(),
                    'created_at' => $payment->getCreatedAt(),
                    'updated_at' => $payment->getUpdatedAt(),
                    'completed_at' => $payment->getCompletedAt(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting payment by order ID via API', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get payment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List payments with filters
     */
    public function listPayments(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'sometimes|string|in:created,pending,completed,failed,cancelled,refunded',
                'order_id' => 'sometimes|string|max:255',
                'currency' => 'sometimes|string|size:3',
                'from_date' => 'sometimes|date',
                'to_date' => 'sometimes|date|after_or_equal:from_date',
                'customer_email' => 'sometimes|email',
                'limit' => 'sometimes|integer|min:1|max:100',
                'offset' => 'sometimes|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $filters = $validator->validated();
            $limit = $filters['limit'] ?? 50;
            $offset = $filters['offset'] ?? 0;

            unset($filters['limit'], $filters['offset']);

            Log::info('Listing payments via API', ['filters' => $filters, 'limit' => $limit, 'offset' => $offset]);

            $payments = $this->deunaPaymentRepository->getWithFilters($filters, $limit, $offset);

            $paymentData = $payments->map(function ($payment) {
                return [
                    'payment_id' => $payment->getPaymentId(),
                    'order_id' => $payment->getOrderId(),
                    'status' => $payment->getStatus(),
                    'amount' => $payment->getAmount(),
                    'currency' => $payment->getCurrency(),
                    'customer' => $payment->getCustomer(),
                    'created_at' => $payment->getCreatedAt(),
                    'updated_at' => $payment->getUpdatedAt(),
                    'completed_at' => $payment->getCompletedAt(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $paymentData,
                'meta' => [
                    'count' => $payments->count(),
                    'limit' => $limit,
                    'offset' => $offset,
                    'filters_applied' => $filters,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error listing payments via API', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to list payments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a payment
     */
    public function cancelPayment(string $paymentId, Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => 'sometimes|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $reason = $request->input('reason', 'Cancelled by user');

            Log::info('Cancelling DeUna payment via API', [
                'payment_id' => $paymentId,
                'reason' => $reason,
            ]);

            // Find payment in database first
            $payment = $this->deunaPaymentRepository->findByPaymentId($paymentId);
            if (! $payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found',
                ], 404);
            }

            // Check if payment can be cancelled
            if (! in_array($payment->getStatus(), ['created', 'pending'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment cannot be cancelled in current status: '.$payment->getStatus(),
                ], 400);
            }

            // Cancel payment via DeUna API
            $result = $this->deunaService->cancelPayment($paymentId, $reason);

            // Update local payment status
            $payment->markAsCancelled($reason);
            $this->deunaPaymentRepository->update($payment);

            return response()->json([
                'success' => true,
                'message' => 'Payment cancelled successfully',
                'data' => [
                    'payment_id' => $paymentId,
                    'status' => 'cancelled',
                    'reason' => $reason,
                    'cancelled_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error cancelling DeUna payment via API', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel payment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Void/Refund a payment
     */
    public function voidPayment(string $paymentId, Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:0.01',
                'reason' => 'sometimes|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $amount = $request->input('amount');
            $reason = $request->input('reason', 'Void/refund requested by user');

            Log::info('Processing void/refund for DeUna payment via API', [
                'payment_id' => $paymentId,
                'amount' => $amount,
                'reason' => $reason,
            ]);

            // Find payment in database first
            $payment = $this->deunaPaymentRepository->findByPaymentId($paymentId);
            if (! $payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found',
                ], 404);
            }

            // Check if payment can be refunded
            if ($payment->getStatus() !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only completed payments can be refunded. Current status: '.$payment->getStatus(),
                ], 400);
            }

            // Check amount doesn't exceed payment amount
            if ($amount > $payment->getAmount()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Refund amount cannot exceed payment amount',
                ], 400);
            }

            // Process void/refund via DeUna API
            $result = $this->deunaService->refundPayment($paymentId, $amount, $reason);

            // Update local payment status
            $payment->markAsRefunded($amount);
            $this->deunaPaymentRepository->update($payment);

            return response()->json([
                'success' => true,
                'message' => 'Payment void/refund processed successfully',
                'data' => [
                    'payment_id' => $paymentId,
                    'status' => 'refunded',
                    'refund_amount' => $amount,
                    'reason' => $reason,
                    'refunded_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing void/refund for DeUna payment via API', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId,
                'amount' => $amount ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process void/refund',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
