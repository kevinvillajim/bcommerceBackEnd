<?php

namespace App\Http\Controllers;

use App\Models\ExternalPaymentLink;
use App\Services\PaymentProcessingService;
use App\Infrastructure\External\PaymentGateway\DatafastService;
use App\Infrastructure\Services\DeunaService;
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
        private DatafastService $datafastService,
        private DeunaService $deunaService
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

            if (!$link->isAvailableForPayment()) {
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
            $link = ExternalPaymentLink::where('link_code', $linkCode)->first();

            if (!$link || !$link->isAvailableForPayment()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Link de pago no válido o expirado',
                ], 400);
            }

            // Usar DatafastService existente para crear checkout
            $checkoutResult = $this->datafastService->createCheckout([
                'amount' => $link->amount,
                'currency' => 'USD',
                'paymentType' => 'DB',
                'merchantTransactionId' => 'EXT_' . $link->link_code . '_' . time(),
                'customer' => [
                    'givenName' => $link->customer_name,
                    'surname' => $link->customer_name,
                    'email' => 'customer@example.com', // Email genérico para pagos externos
                ],
                'billing' => [
                    'street1' => 'N/A',
                    'city' => 'Quito',
                    'state' => 'Pichincha',
                    'country' => 'EC',
                    'postcode' => '170135',
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
                    'redirect_url' => $checkoutResult['redirect_url'],
                    'payment_method' => 'datafast',
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error initiating Datafast payment for external link', [
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
     * Iniciar pago con Deuna
     */
    public function initiateDeunaPayment(string $linkCode, Request $request): JsonResponse
    {
        try {
            $link = ExternalPaymentLink::where('link_code', $linkCode)->first();

            if (!$link || !$link->isAvailableForPayment()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Link de pago no válido o expirado',
                ], 400);
            }

            // Usar DeunaService existente para crear orden
            $orderData = [
                'order_id' => 'EXT_' . $link->link_code . '_' . time(),
                'amount' => $link->amount,
                'currency' => 'USD',
                'description' => $link->description ?: 'Pago externo',
                'customer' => [
                    'name' => $link->customer_name,
                    'email' => 'customer@example.com', // Email genérico
                ],
                'metadata' => [
                    'external_payment_link_id' => $link->id,
                    'external_payment_link_code' => $link->link_code,
                ],
            ];

            $deunaResult = $this->deunaService->createOrder($orderData);

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
                    'order_id' => $deunaResult['order_id'],
                    'checkout_url' => $deunaResult['checkout_url'],
                    'payment_method' => 'deuna',
                ],
            ]);

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
            $validated = $request->validate([
                'resource_path' => 'required|string',
                'transaction_id' => 'required|string',
            ]);

            $link = ExternalPaymentLink::where('link_code', $linkCode)->first();

            if (!$link) {
                return response()->json([
                    'success' => false,
                    'message' => 'Link de pago no encontrado',
                ], 404);
            }

            // Verificar pago con DatafastService existente
            $verificationResult = $this->datafastService->verifyPayment($validated['resource_path']);

            if (!$verificationResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pago no verificado',
                    'error' => $verificationResult['message'] ?? 'Error de verificación',
                ], 400);
            }

            // Marcar link como pagado
            $link->markAsPaid(
                'datafast',
                $validated['transaction_id'],
                $verificationResult['payment_id'] ?? null
            );

            Log::info('External payment verified and completed', [
                'link_id' => $link->id,
                'link_code' => $linkCode,
                'payment_method' => 'datafast',
                'transaction_id' => $validated['transaction_id'],
                'amount' => $link->amount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pago procesado exitosamente',
                'data' => [
                    'payment_method' => 'datafast',
                    'transaction_id' => $validated['transaction_id'],
                    'amount' => $link->amount,
                    'customer_name' => $link->customer_name,
                    'paid_at' => $link->paid_at->toISOString(),
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de verificación inválidos',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error verifying Datafast payment for external link', [
                'link_code' => $linkCode,
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error verificando el pago',
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
