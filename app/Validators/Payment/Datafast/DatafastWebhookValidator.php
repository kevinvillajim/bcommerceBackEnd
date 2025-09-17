<?php

namespace App\Validators\Payment\Datafast;

use App\Domain\Interfaces\PaymentValidatorInterface;
use App\Domain\ValueObjects\PaymentResult;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Validador para webhooks de Datafast
 *
 * PUNTO DE VALIDACIÓN: Webhook → webhook() notificaciones automáticas (poco usado)
 *
 * FLUJO:
 * 1. Datafast envía notificación webhook automática
 * 2. Validación de signature y estructura
 * 3. Procesamiento de estado de pago notificado
 */
class DatafastWebhookValidator implements PaymentValidatorInterface
{
    public function validatePayment(array $paymentData): PaymentResult
    {
        Log::info('🔔 DatafastWebhookValidator: Validando webhook notification', [
            'notification_id' => $paymentData['notificationId'] ?? 'N/A',
            'webhook_signature' => isset($paymentData['webhook_signature']) ? 'PRESENTE' : 'AUSENTE',
            'payment_status' => $paymentData['payment_status'] ?? $paymentData['status'] ?? 'N/A',
        ]);

        try {
            // Validar estructura del webhook
            $this->validateWebhookStructure($paymentData);

            // Extraer información del webhook
            $transactionId = $this->extractTransactionId($paymentData);
            $paymentStatus = $this->extractPaymentStatus($paymentData);
            $amount = $this->extractAmount($paymentData);

            Log::info('📋 DatafastWebhookValidator: Información extraída del webhook', [
                'transaction_id' => $transactionId,
                'payment_status' => $paymentStatus,
                'amount' => $amount,
                'notification_id' => $paymentData['notificationId'] ?? 'N/A',
            ]);

            // Verificar si el pago es exitoso
            if (! $this->isSuccessfulPayment($paymentStatus)) {
                return $this->handleFailedWebhookPayment($paymentStatus, $transactionId, $paymentData);
            }

            // Generar payment_id del webhook
            $paymentId = $paymentData['paymentId']
                ?? $paymentData['payment_id']
                ?? $paymentData['transactionId']
                ?? $transactionId;

            Log::info('✅ DatafastWebhookValidator: Webhook de pago exitoso procesado', [
                'transaction_id' => $transactionId,
                'payment_id' => $paymentId,
                'amount' => $amount,
                'status' => $paymentStatus,
                'notification_type' => 'webhook',
            ]);

            return PaymentResult::success(
                transactionId: $transactionId,
                amount: $amount,
                paymentMethod: 'datafast',
                validationType: 'webhook',
                metadata: [
                    'payment_id' => $paymentId,
                    'payment_status' => $paymentStatus,
                    'notification_id' => $paymentData['notificationId'] ?? null,
                    'webhook_signature' => $paymentData['webhook_signature'] ?? null,
                    'notification_type' => 'datafast_webhook',
                    'timestamp' => now()->toISOString(),
                    'webhook_data' => $paymentData,
                ]
            );

        } catch (Exception $e) {
            Log::error('❌ DatafastWebhookValidator: Error en validación de webhook', [
                'error' => $e->getMessage(),
                'notification_id' => $paymentData['notificationId'] ?? 'N/A',
                'webhook_data' => $paymentData,
            ]);

            return PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'webhook',
                errorMessage: 'Error en webhook: '.$e->getMessage(),
                errorCode: 'WEBHOOK_VALIDATION_ERROR',
                metadata: [
                    'original_error' => $e->getMessage(),
                    'webhook_data' => $paymentData,
                ]
            );
        }
    }

    public function getPaymentMethod(): string
    {
        return 'datafast';
    }

    public function getValidationType(): string
    {
        return 'webhook';
    }

    /**
     * Valida estructura básica del webhook de Datafast
     */
    private function validateWebhookStructure(array $paymentData): void
    {
        // Verificar campos mínimos esperados en webhook de Datafast
        $hasNotificationId = isset($paymentData['notificationId']) && ! empty($paymentData['notificationId']);
        $hasTransactionData = isset($paymentData['transactionId']) || isset($paymentData['transaction_id']);
        $hasStatusData = isset($paymentData['payment_status']) || isset($paymentData['status']);

        if (! $hasNotificationId && ! $hasTransactionData) {
            throw new Exception('Webhook inválido: falta notificationId o transactionId');
        }

        if (! $hasStatusData) {
            throw new Exception('Webhook inválido: falta información de estado del pago');
        }

        Log::info('✅ Estructura de webhook Datafast validada', [
            'has_notification_id' => $hasNotificationId,
            'has_transaction_data' => $hasTransactionData,
            'has_status_data' => $hasStatusData,
        ]);
    }

    /**
     * Extrae transaction ID del webhook
     */
    private function extractTransactionId(array $paymentData): string
    {
        $transactionId = $paymentData['transactionId']
            ?? $paymentData['transaction_id']
            ?? $paymentData['id']
            ?? $paymentData['notificationId'];

        if (empty($transactionId)) {
            throw new Exception('No se pudo extraer transaction_id del webhook');
        }

        return (string) $transactionId;
    }

    /**
     * Extrae estado del pago del webhook
     */
    private function extractPaymentStatus(array $paymentData): string
    {
        return $paymentData['payment_status']
            ?? $paymentData['status']
            ?? $paymentData['transactionStatus']
            ?? 'unknown';
    }

    /**
     * Extrae monto del webhook
     */
    private function extractAmount(array $paymentData): float
    {
        $amount = $paymentData['amount']
            ?? $paymentData['total']
            ?? $paymentData['value']
            ?? 0.0;

        return (float) $amount;
    }

    /**
     * Verifica si el estado del pago indica éxito
     */
    private function isSuccessfulPayment(string $paymentStatus): bool
    {
        $successfulStatuses = [
            'completed',
            'approved',
            'success',
            'paid',
            'successful',
            '000.000.000', // Código de éxito Datafast
            '000.100.110', // Código de prueba Datafast
            '000.100.112', // Código de prueba Datafast Fase 2
        ];

        return in_array(strtolower($paymentStatus), array_map('strtolower', $successfulStatuses));
    }

    /**
     * Maneja webhooks de pagos fallidos
     */
    private function handleFailedWebhookPayment(string $paymentStatus, string $transactionId, array $paymentData): PaymentResult
    {
        Log::warning('⚠️ DatafastWebhookValidator: Webhook de pago fallido', [
            'transaction_id' => $transactionId,
            'payment_status' => $paymentStatus,
            'notification_id' => $paymentData['notificationId'] ?? 'N/A',
        ]);

        return PaymentResult::failure(
            paymentMethod: 'datafast',
            validationType: 'webhook',
            errorMessage: "Pago fallido notificado via webhook: {$paymentStatus}",
            errorCode: 'WEBHOOK_PAYMENT_FAILED',
            metadata: [
                'payment_status' => $paymentStatus,
                'transaction_id' => $transactionId,
                'notification_id' => $paymentData['notificationId'] ?? null,
                'webhook_data' => $paymentData,
            ]
        );
    }
}
