<?php

namespace App\Http\Controllers;

use App\Models\ExternalPaymentLink;
use App\Services\PaymentProcessingService;
use App\Infrastructure\Services\ExternalDatafastService;
use App\Infrastructure\Services\ExternalDeunaService;
use App\Validators\Payment\Datafast\UnifiedDatafastValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Controlador público para procesamiento de pagos externos
 * NO requiere autenticación - acceso público mediante link_code
 */
class ExternalPaymentPublicController extends Controller
{
    public function __construct(
        private ExternalDatafastService $externalDatafastService,
        private ExternalDeunaService $externalDeunaService,
        private UnifiedDatafastValidator $unifiedDatafastValidator
    ) {}

    /**
     * Obtener información del link de pago (página pública)
     */
    public function show(string $linkCode): JsonResponse
    {
        try {
            $link = ExternalPaymentLink::where('link_code', $linkCode)->first();

            if (!$link) {
                return response()->json([
                    'success' => false,
                    'message' => 'Link de pago no encontrado',
                    'error_code' => 'LINK_NOT_FOUND',
                ], 404);
            }

            // Allow status checking even if payment is completed
            if (!$link->isAvailableForPayment() && $link->status !== 'paid') {
                $reason = $link->isExpired() ? 'expirado' : 'no disponible';
                return response()->json([
                    'success' => false,
                    'message' => "El link de pago está {$reason}",
                    'error_code' => $link->isExpired() ? 'LINK_EXPIRED' : 'LINK_NOT_AVAILABLE',
                    'data' => [
                        'status' => $link->status,
                        'is_expired' => $link->isExpired(),
                    ],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'link_code' => $link->link_code,
                    'customer_name' => $link->customer_name,
                    'amount' => $link->amount,
                    'description' => $link->description,
                    'expires_at' => $link->expires_at->toISOString(),
                    'status' => $link->status,
                    'paid_at' => $link->paid_at?->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error showing public payment link', [
                'link_code' => $linkCode,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error cargando información del pago',
            ], 500);
        }
    }

    /**
     * Iniciar pago con Datafast
     */
    public function initiateDatafastPayment(string $linkCode, Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'given_name' => 'required|string|max:48',
                'surname' => 'required|string|max:48',
                'identification' => 'required|string|size:10|regex:/^\d{10}$/',
                'middle_name' => 'nullable|string|max:50',
                'email' => 'required|email',
                'phone' => 'required|string',
                'address' => 'required|string',
                'city' => 'required|string',
                'postal_code' => 'nullable|string',
            ]);

            $link = ExternalPaymentLink::where('link_code', $linkCode)->first();

            if (!$link || !$link->isAvailableForPayment()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Link de pago no válido o expirado',
                ], 400);
            }

            // Generar transaction_id único (mismo formato que sistema principal)
            $transactionId = 'TXN_' . $link->link_code . '_' . time();

            // Usar ExternalDatafastService para crear checkout con datos reales del cliente
            $checkoutResult = $this->externalDatafastService->createCheckout([
                'amount' => $link->amount,
                'currency' => 'USD',
                'paymentType' => 'DB',
                'transaction_id' => $transactionId, // Campo requerido por validatePhase2Structure
                'link_code' => $linkCode, // Para construir shopperResultUrl interno
                'customer' => [
                    'id' => $link->created_by, // ID del usuario que creó el link
                    'given_name' => $validated['given_name'],
                    'surname' => $validated['surname'],
                    'middle_name' => $validated['middle_name'] ?? '',
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
                    'doc_id' => $validated['identification'],
                ],
                'billing' => [
                    'street' => $validated['address'], // Usar 'street' como espera el servicio
                    'address' => $validated['address'], // Fallback
                    'city' => $validated['city'],
                    'state' => $validated['city'],
                    'country' => 'EC',
                    'postcode' => $validated['postal_code'] ?? '',
                ],
                'shipping' => [
                    'street' => $validated['address'], // Usar 'street' como espera el servicio
                    'address' => $validated['address'], // Fallback
                    'city' => $validated['city'],
                    'state' => $validated['city'],
                    'country' => 'EC',
                    'postcode' => $validated['postal_code'] ?? '',
                ],
                'customParameters' => [
                    'external_payment_link_id' => $link->id,
                    'external_payment_link_code' => $link->link_code,
                ],
            ]);

            if (!$checkoutResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error iniciando pago con Datafast',
                    'error' => $checkoutResult['message'] ?? 'Error desconocido',
                ], 500);
            }

            Log::info('External payment Datafast checkout created', [
                'link_id' => $link->id,
                'link_code' => $linkCode,
                'checkout_id' => $checkoutResult['checkout_id'],
                'amount' => $link->amount,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'checkout_id' => $checkoutResult['checkout_id'],
                    'redirect_url' => $checkoutResult['widget_url'], // DatafastService retorna 'widget_url'
                    'payment_method' => 'datafast',
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos del cliente inválidos',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // ✅ LOGS MEJORADOS: Mismo nivel de detalle que sistema interno
            Log::error('❌ Critical error initiating external Datafast payment', [
                'link_code' => $linkCode,
                'link_id' => $link->id ?? 'unknown',
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_line' => $e->getLine(),
                'error_file' => basename($e->getFile()),
                'validated_data' => $validated ?? [],
                'stack_trace' => $e->getTraceAsString(),
                'context' => [
                    'link_exists' => isset($link),
                    'link_amount' => $link->amount ?? 'unknown',
                    'link_status' => $link->status ?? 'unknown',
                    'service_used' => 'ExternalDatafastService',
                ],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno procesando el pago',
                'error_code' => 'INTERNAL_ERROR',
            ], 500);
        }
    }

    /**
     * Iniciar pago con Deuna
     */
    public function initiateDeunaPayment(string $linkCode, Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'given_name' => 'required|string|max:48',
                'surname' => 'required|string|max:48',
                'identification' => 'required|string|size:10|regex:/^\d{10}$/',
                'middle_name' => 'nullable|string|max:50',
                'email' => 'required|email',
                'phone' => 'required|string',
                'address' => 'required|string',
                'city' => 'required|string',
                'postal_code' => 'nullable|string',
            ]);

            $link = ExternalPaymentLink::where('link_code', $linkCode)->first();

            if (!$link || !$link->isAvailableForPayment()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Link de pago no válido o expirado',
                ], 400);
            }

            // Usar ExternalDeunaService para crear orden con datos reales del cliente
            $orderData = [
                'order_id' => 'EXT_' . $link->link_code . '_' . time(),
                'amount' => $link->amount,
                'description' => $link->description ?: 'Pago externo',
                'customer' => [
                    'name' => $link->customer_name,
                    'email' => $validated['email'],
                ],
            ];

            // Use regular DeunaService for external payments
            $deunaResult = $this->externalDeunaService->createOrder($orderData);

            if (!$deunaResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error iniciando pago con Deuna',
                    'error' => $deunaResult['message'] ?? 'Error desconocido',
                ], 500);
            }

            Log::info('External payment Deuna order created', [
                'link_id' => $link->id,
                'link_code' => $linkCode,
                'order_id' => $deunaResult['order_id'],
                'amount' => $link->amount,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_id' => $deunaResult['order_id'],
                    'order_id' => $deunaResult['order_id'],
                    'status' => 'pending',
                    'amount' => $link->amount,
                    'currency' => 'USD',
                    'qr_code_base64' => $deunaResult['qr_code'] ?? null,
                    'payment_url' => $deunaResult['checkout_url'] ?? null,
                    'numeric_code' => null,
                    'created_at' => now()->toISOString(),
                    'expires_at' => now()->addMinutes(10)->toISOString(),
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos del cliente inválidos',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error initiating Deuna payment for external link', [
                'link_code' => $linkCode,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error procesando el pago',
            ], 500);
        }
    }

    /**
     * Verificar y procesar resultado de pago Datafast
     */
    public function verifyDatafastPayment(string $linkCode, Request $request): JsonResponse
    {
        try {
            // ✅ LOGS MEJORADOS: Trazabilidad completa desde el inicio
            Log::info('🎯 External payment Datafast verification started', [
                'link_code' => $linkCode,
                'request_data' => $request->all(),
                'validation_system' => 'UnifiedDatafastValidator',
                'client_ip' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 100),
            ]);

            // Aceptar tanto resourcePath como resource_path (formato de Datafast)
            $resourcePath = $request->input('resourcePath') ?: $request->input('resource_path');
            $transactionId = $request->input('id') ?: $request->input('transaction_id');

            if (!$resourcePath || !$transactionId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parámetros de verificación faltantes',
                    'required' => ['resourcePath o resource_path', 'id o transaction_id'],
                ], 400);
            }

            $validated = [
                'resource_path' => $resourcePath,
                'transaction_id' => $transactionId,
            ];

            $link = ExternalPaymentLink::where('link_code', $linkCode)->first();

            if (!$link) {
                Log::warning('⚠️ External payment link not found', [
                    'link_code' => $linkCode,
                    'client_ip' => $request->ip(),
                    'possible_causes' => [
                        'invalid_link_code',
                        'expired_link',
                        'deleted_link',
                    ],
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Link de pago no encontrado',
                    'error_code' => 'LINK_NOT_FOUND',
                ], 404);
            }

            Log::info('✅ External payment link found', [
                'link_id' => $link->id,
                'link_code' => $linkCode,
                'link_amount' => $link->amount,
                'link_status' => $link->status,
                'customer_name' => $link->customer_name,
                'is_available' => $link->isAvailableForPayment(),
            ]);

            // ✅ VERIFICACIÓN PREVIA: Si el link ya está pagado, retornar éxito inmediatamente
            if ($link->status === 'paid') {
                Log::info('🔄 Link ya está marcado como pagado, retornando datos existentes', [
                    'link_id' => $link->id,
                    'link_code' => $linkCode,
                    'paid_at' => $link->paid_at?->toISOString(),
                    'transaction_id' => $link->transaction_id,
                    'payment_id' => $link->payment_id,
                    'skip_reason' => 'link_already_paid'
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Pago ya procesado exitosamente',
                    'data' => [
                        'payment_method' => $link->payment_method ?? 'datafast',
                        'validation_type' => 'link_status_check',
                        'transaction_id' => $link->transaction_id,
                        'payment_id' => $link->payment_id,
                        'amount' => $link->amount,
                        'customer_name' => $link->customer_name,
                        'paid_at' => $link->paid_at?->toISOString(),
                        'status' => 'already_completed',
                    ],
                ]);
            }

            // ✅ USAR VALIDADOR UNIFICADO: Misma lógica que sistema interno
            $paymentResult = $this->unifiedDatafastValidator->validatePayment($validated);

            if (!$paymentResult->isSuccessful()) {
                Log::warning('External payment validation failed via UnifiedValidator', [
                    'link_code' => $linkCode,
                    'error_message' => $paymentResult->errorMessage,
                    'error_code' => $paymentResult->errorCode,
                    'validation_type' => $paymentResult->validationType,
                    'payment_method' => $paymentResult->paymentMethod,
                ]);

                // ✅ MANEJO ESPECÍFICO ERROR 200.300.404: Misma lógica que DatafastController
                if ($paymentResult->errorCode === '200.300.404') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Sesión de pago ya procesada o expirada',
                        'error_code' => '200.300.404',
                        'validation_data' => [
                            'result' => [
                                'code' => '200.300.404',
                                'description' => 'No payment session found',
                            ],
                        ],
                    ], 400);
                }

                return response()->json([
                    'success' => false,
                    'message' => $paymentResult->errorMessage,
                    'error_code' => $paymentResult->errorCode,
                    'validation_data' => $paymentResult->metadata,
                ], 400);
            }

            // ✅ PAGO EXITOSO: Marcar link como pagado usando datos del validador
            $paymentId = $paymentResult->metadata['payment_id'] ?? $paymentResult->transactionId;

            $link->markAsPaid(
                'datafast',
                $paymentResult->transactionId,
                $paymentId
            );

            Log::info('External payment verified and completed via UnifiedValidator', [
                'link_id' => $link->id,
                'link_code' => $linkCode,
                'payment_method' => $paymentResult->paymentMethod,
                'validation_type' => $paymentResult->validationType,
                'transaction_id' => $paymentResult->transactionId,
                'amount' => $paymentResult->amount,
                'expected_amount' => $link->amount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pago procesado exitosamente',
                'data' => [
                    'payment_method' => $paymentResult->paymentMethod,
                    'validation_type' => $paymentResult->validationType,
                    'transaction_id' => $paymentResult->transactionId,
                    'payment_id' => $paymentId,
                    'amount' => $link->amount,
                    'customer_name' => $link->customer_name,
                    'paid_at' => $link->paid_at->toISOString(),
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('External payment validation exception', [
                'link_code' => $linkCode,
                'validation_errors' => $e->errors(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Datos de verificación inválidos',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            // ✅ LOGS MEJORADOS: Mismo nivel de detalle que sistema interno
            Log::error('❌ Critical error verifying external Datafast payment', [
                'link_code' => $linkCode,
                'link_id' => $link->id ?? 'unknown',
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_line' => $e->getLine(),
                'error_file' => basename($e->getFile()),
                'request_data' => $request->all(),
                'stack_trace' => $e->getTraceAsString(),
                'context' => [
                    'link_exists' => isset($link),
                    'link_amount' => $link->amount ?? 'unknown',
                    'link_status' => $link->status ?? 'unknown',
                    'validation_system' => 'UnifiedDatafastValidator',
                ],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno verificando el pago',
                'error_code' => 'INTERNAL_ERROR',
            ], 500);
        }
    }

    /**
     * Webhook para Deuna (procesar notificaciones automáticas)
     */
    public function deunaWebhook(string $linkCode, Request $request): JsonResponse
    {
        try {
            $webhookData = $request->all();

            Log::info('External payment Deuna webhook received', [
                'link_code' => $linkCode,
                'webhook_data' => $webhookData,
            ]);

            $link = ExternalPaymentLink::where('link_code', $linkCode)->first();

            if (!$link) {
                return response()->json([
                    'success' => false,
                    'message' => 'Link not found',
                ], 404);
            }

            // Verificar que el webhook indica pago exitoso
            $status = $webhookData['status'] ?? null;
            if ($status !== 'SUCCESS') {
                Log::info('External payment Deuna webhook with non-success status', [
                    'link_code' => $linkCode,
                    'status' => $status,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Webhook received but payment not successful',
                ], 200);
            }

            // Marcar link como pagado
            $transactionId = $webhookData['idTransaction'] ?? $webhookData['transferNumber'] ?? 'DEUNA_' . time();
            $paymentId = $webhookData['internalTransactionReference'] ?? null;

            $link->markAsPaid('deuna', $transactionId, $paymentId);

            Log::info('External payment completed via Deuna webhook', [
                'link_id' => $link->id,
                'link_code' => $linkCode,
                'transaction_id' => $transactionId,
                'amount' => $link->amount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error processing Deuna webhook for external link', [
                'link_code' => $linkCode,
                'error' => $e->getMessage(),
                'webhook_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing webhook',
            ], 500);
        }
    }
}
