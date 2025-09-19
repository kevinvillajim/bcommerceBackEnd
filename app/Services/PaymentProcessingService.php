<?php

namespace App\Services;

use App\Domain\ValueObjects\CheckoutData;
use App\Domain\ValueObjects\PaymentResult;
use App\UseCases\Checkout\ProcessCheckoutUseCase;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio centralizado para procesamiento de pagos exitosos
 *
 * FUNCIONALIDAD PRINCIPAL:
 * - Recibe PaymentResult validado de cualquier método de pago
 * - Usa CheckoutData temporal para crear orden via ProcessCheckoutUseCase
 * - Garantiza misma lógica para todos los métodos (Datafast, Deuna, futuros)
 * - Maneja sellers, ganancias, facturación automática
 */
class PaymentProcessingService
{
    public function __construct(
        private ProcessCheckoutUseCase $processCheckoutUseCase,
        private CheckoutDataService $checkoutDataService
    ) {}

    /**
     * Procesa pago exitoso usando CheckoutData temporal
     *
     * FLUJO UNIFICADO para TODOS los métodos de pago:
     * 1. Recuperar CheckoutData temporal
     * 2. Validar que no haya expirado
     * 3. Llamar ProcessCheckoutUseCase (sellers, ganancias, facturación)
     * 4. Limpiar datos temporales
     * 5. Retornar información de orden creada
     */
    public function processSuccessfulPayment(PaymentResult $paymentResult, string $sessionId): array
    {
        Log::info('🎯 PaymentProcessingService: Iniciando procesamiento unificado', [
            'payment_method' => $paymentResult->paymentMethod,
            'validation_type' => $paymentResult->validationType,
            'transaction_id' => $paymentResult->transactionId,
            'amount' => $paymentResult->amount,
            'session_id' => $sessionId,
        ]);

        return DB::transaction(function () use ($paymentResult, $sessionId) {
            try {
                // 1. Recuperar CheckoutData temporal
                $checkoutData = $this->checkoutDataService->retrieve($sessionId);
                if (! $checkoutData) {
                    Log::error('❌ CheckoutData no encontrado en cache', [
                        'session_id' => $sessionId,
                        'transaction_id' => $paymentResult->transactionId,
                        'payment_method' => $paymentResult->paymentMethod,
                        'cache_key_attempted' => "checkout_data_{$sessionId}",
                        'possible_causes' => [
                            'session_expired_after_30min',
                            'frontend_didnt_save_session_id',
                            'cache_cleared_manually',
                            'different_session_id_format'
                        ]
                    ]);

                    throw new Exception("CheckoutData no encontrado o expirado para sessionId: {$sessionId}. El pago fue exitoso pero no se puede procesar la orden sin datos de checkout.");
                }

                Log::info('✅ CheckoutData recuperado para procesamiento', [
                    'user_id' => $checkoutData->userId,
                    'session_id' => $sessionId,
                    'final_total' => $checkoutData->getFinalTotal(),
                    'items_count' => count($checkoutData->items),
                ]);

                // 2. Validar consistencia de montos
                $this->validateAmountConsistency($paymentResult, $checkoutData);

                // 3. Preparar datos para ProcessCheckoutUseCase
                $paymentData = $checkoutData->createBasePaymentData(
                    $paymentResult->paymentMethod,
                    $paymentResult->transactionId,
                    $paymentResult->transactionId // Usar transactionId como paymentId si no hay otro
                );

                // Agregar metadata del validador
                $paymentData = array_merge($paymentData, [
                    'validation_type' => $paymentResult->validationType,
                    'validator_metadata' => $paymentResult->metadata,
                ]);

                // 4. Ejecutar ProcessCheckoutUseCase (LÓGICA CENTRALIZADA)
                Log::info('🔄 Ejecutando ProcessCheckoutUseCase con datos validados', [
                    'user_id' => $checkoutData->userId,
                    'payment_method' => $paymentResult->paymentMethod,
                    'validation_type' => $paymentResult->validationType,
                ]);

                $checkoutResult = $this->processCheckoutUseCase->execute(
                    userId: $checkoutData->userId,
                    paymentData: $paymentData,
                    shippingData: $checkoutData->getProcessCheckoutShippingData(),
                    billingData: $checkoutData->getProcessCheckoutBillingData(),
                    items: $checkoutData->getProcessCheckoutItems(),
                    sellerId: null, // Se detecta automáticamente
                    discountCode: $checkoutData->discountCode,
                    calculatedTotals: $checkoutData->getProcessCheckoutTotals()
                );

                // 5. Limpiar datos temporales (orden ya creada)
                $this->checkoutDataService->remove($sessionId);

                Log::info('🎉 Pago procesado exitosamente - Orden creada', [
                    'order_id' => $checkoutResult['order']->getId(),
                    'order_number' => $checkoutResult['order']->getOrderNumber(),
                    'total' => $checkoutResult['order']->getTotal(),
                    'seller_orders_count' => count($checkoutResult['seller_orders'] ?? []),
                    'payment_method' => $paymentResult->paymentMethod,
                    'validation_type' => $paymentResult->validationType,
                ]);

                // 6. Retornar información estructurada
                return [
                    'success' => true,
                    'order' => [
                        'id' => $checkoutResult['order']->getId(),
                        'number' => $checkoutResult['order']->getOrderNumber(),
                        'total' => $checkoutResult['order']->getTotal(),
                        'status' => $checkoutResult['order']->getStatus(),
                    ],
                    'seller_orders' => array_map(function ($sellerOrder) {
                        return [
                            'id' => $sellerOrder->getId(),
                            'seller_id' => $sellerOrder->getSellerId(),
                            'total' => $sellerOrder->getTotal(),
                        ];
                    }, $checkoutResult['seller_orders'] ?? []),
                    'payment' => [
                        'method' => $paymentResult->paymentMethod,
                        'validation_type' => $paymentResult->validationType,
                        'transaction_id' => $paymentResult->transactionId,
                        'amount' => $paymentResult->amount,
                    ],
                    'events_triggered' => [
                        'OrderCreated' => true,
                        'invoice_generation' => 'automatic',
                    ],
                    'checkout_data_cleaned' => true,
                ];

            } catch (Exception $e) {
                Log::error('❌ Error en PaymentProcessingService', [
                    'error' => $e->getMessage(),
                    'payment_method' => $paymentResult->paymentMethod,
                    'validation_type' => $paymentResult->validationType,
                    'transaction_id' => $paymentResult->transactionId,
                    'session_id' => $sessionId,
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new Exception('Error al procesar pago: '.$e->getMessage());
            }
        });
    }

    /**
     * Valida consistencia entre monto del pago y CheckoutData
     */
    private function validateAmountConsistency(PaymentResult $paymentResult, CheckoutData $checkoutData): void
    {
        $paymentAmount = round($paymentResult->amount, 2);
        $checkoutAmount = round($checkoutData->getFinalTotal(), 2);

        if (abs($paymentAmount - $checkoutAmount) > 0.01) { // Tolerancia de 1 centavo
            Log::error('💰 Inconsistencia de montos detectada', [
                'payment_amount' => $paymentAmount,
                'checkout_amount' => $checkoutAmount,
                'difference' => abs($paymentAmount - $checkoutAmount),
                'payment_method' => $paymentResult->paymentMethod,
                'session_id' => $checkoutData->sessionId,
            ]);

            throw new Exception(
                "Inconsistencia de montos: Pago={$paymentAmount}, Checkout={$checkoutAmount}"
            );
        }

        Log::info('✅ Validación de montos exitosa', [
            'payment_amount' => $paymentAmount,
            'checkout_amount' => $checkoutAmount,
            'payment_method' => $paymentResult->paymentMethod,
        ]);
    }

    /**
     * Maneja pago fallido (logging y limpieza)
     */
    public function handleFailedPayment(PaymentResult $paymentResult, string $sessionId): array
    {
        Log::warning('⚠️ Pago fallido procesado', [
            'payment_method' => $paymentResult->paymentMethod,
            'validation_type' => $paymentResult->validationType,
            'error_message' => $paymentResult->errorMessage,
            'error_code' => $paymentResult->errorCode,
            'session_id' => $sessionId,
        ]);

        // No limpiamos CheckoutData en caso de fallo - permitir reintentos

        return [
            'success' => false,
            'error' => [
                'message' => $paymentResult->errorMessage,
                'code' => $paymentResult->errorCode,
                'payment_method' => $paymentResult->paymentMethod,
                'validation_type' => $paymentResult->validationType,
            ],
            'retry_allowed' => true,
            'session_id' => $sessionId,
        ];
    }
}
