<?php

namespace App\Validators\Payment\Deuna;

use App\Domain\Interfaces\PaymentValidatorInterface;
use App\Domain\ValueObjects\PaymentResult;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Validador para webhooks reales de Deuna
 *
 * PUNTO DE VALIDACIÓN: Webhook Real → handlePaymentStatus() notificaciones automáticas
 *
 * ESTRUCTURA SEGÚN DOCUMENTACIÓN DEUNA:
 * {
 *   "status": "SUCCESS",
 *   "amount": 0.02,
 *   "idTransaction": "4k86f87c-8918-4x29-85d8-ebe33610ebx3",
 *   "internalTransactionReference": "04-0000007",
 *   "transferNumber": "044451603432",
 *   "date": "6/24/2024, 4:10:58 PM",
 *   "branchId": "10810",
 *   "posId": "11820",
 *   "currency": "USD",
 *   "description": "Pago 2545-11782-29580",
 *   "customerIdentification": "0503846256",
 *   "customerFullName": "JUAN CARLOS ZAMBRANO LOPEZ"
 * }
 */
class DeunaWebhookValidator implements PaymentValidatorInterface
{
    public function validatePayment(array $paymentData): PaymentResult
    {
        Log::info('🔔 DeunaWebhookValidator: Validando webhook real de Deuna', [
            'status' => $paymentData['status'] ?? 'N/A',
            'idTransaction' => $paymentData['idTransaction'] ?? 'N/A',
            'amount' => $paymentData['amount'] ?? 'N/A',
            'internalTransactionReference' => $paymentData['internalTransactionReference'] ?? 'N/A',
        ]);

        try {
            // Validar estructura del webhook según documentación Deuna
            $this->validateDeunaWebhookStructure($paymentData);

            // Extraer información según campos documentados
            $transactionId = $this->extractTransactionId($paymentData);
            $status = $this->extractStatus($paymentData);
            $amount = $this->extractAmount($paymentData);

            Log::info('📋 DeunaWebhookValidator: Información extraída', [
                'transaction_id' => $transactionId,
                'status' => $status,
                'amount' => $amount,
                'transfer_number' => $paymentData['transferNumber'] ?? 'N/A',
            ]);

            // Verificar si es un pago exitoso según documentación
            if (! $this->isSuccessfulPayment($status)) {
                return $this->handleFailedDeunaWebhook($status, $transactionId, $paymentData);
            }

            Log::info('✅ DeunaWebhookValidator: Webhook de pago exitoso procesado', [
                'transaction_id' => $transactionId,
                'status' => $status,
                'amount' => $amount,
                'transfer_number' => $paymentData['transferNumber'] ?? 'N/A',
            ]);

            return PaymentResult::success(
                transactionId: $transactionId,
                amount: $amount,
                paymentMethod: 'deuna',
                validationType: 'webhook',
                metadata: [
                    'payment_id' => $transactionId,
                    'status' => $status,
                    'transfer_number' => $paymentData['transferNumber'] ?? null,
                    'internal_transaction_reference' => $paymentData['internalTransactionReference'] ?? null,
                    'branch_id' => $paymentData['branchId'] ?? null,
                    'pos_id' => $paymentData['posId'] ?? null,
                    'currency' => $paymentData['currency'] ?? 'USD',
                    'date' => $paymentData['date'] ?? null,
                    'description' => $paymentData['description'] ?? null,
                    'customer_identification' => $paymentData['customerIdentification'] ?? null,
                    'customer_full_name' => $paymentData['customerFullName'] ?? null,
                    'webhook_data' => $paymentData,
                    'notification_type' => 'deuna_webhook_real',
                    'timestamp' => now()->toISOString(),
                ]
            );

        } catch (Exception $e) {
            Log::error('❌ DeunaWebhookValidator: Error en validación', [
                'error' => $e->getMessage(),
                'idTransaction' => $paymentData['idTransaction'] ?? 'N/A',
                'webhook_data' => $paymentData,
            ]);

            return PaymentResult::failure(
                paymentMethod: 'deuna',
                validationType: 'webhook',
                errorMessage: 'Error en webhook Deuna: '.$e->getMessage(),
                errorCode: 'DEUNA_WEBHOOK_ERROR',
                metadata: [
                    'original_error' => $e->getMessage(),
                    'webhook_data' => $paymentData,
                ]
            );
        }
    }

    public function getPaymentMethod(): string
    {
        return 'deuna';
    }

    public function getValidationType(): string
    {
        return 'webhook';
    }

    /**
     * Valida estructura del webhook según documentación Deuna
     */
    private function validateDeunaWebhookStructure(array $paymentData): void
    {
        // Campos requeridos según documentación Deuna
        $requiredFields = ['status', 'amount', 'idTransaction'];

        foreach ($requiredFields as $field) {
            if (! isset($paymentData[$field])) {
                throw new Exception("Campo requerido faltante en webhook Deuna: {$field}");
            }
        }

        Log::info('✅ Estructura de webhook Deuna validada según documentación', [
            'has_status' => isset($paymentData['status']),
            'has_amount' => isset($paymentData['amount']),
            'has_idTransaction' => isset($paymentData['idTransaction']),
            'has_internalTransactionReference' => isset($paymentData['internalTransactionReference']),
        ]);
    }

    /**
     * Extrae ID de transacción según documentación
     */
    private function extractTransactionId(array $paymentData): string
    {
        // Prioridad según documentación Deuna
        $transactionId = $paymentData['idTransaction']
            ?? $paymentData['internalTransactionReference']
            ?? $paymentData['transferNumber'];

        if (empty($transactionId)) {
            throw new Exception('No se pudo extraer transaction_id del webhook Deuna');
        }

        return (string) $transactionId;
    }

    /**
     * Extrae estado según documentación ("SUCCESS" es exitoso)
     */
    private function extractStatus(array $paymentData): string
    {
        return $paymentData['status'] ?? 'unknown';
    }

    /**
     * Extrae monto según documentación (número decimal separado por punto)
     */
    private function extractAmount(array $paymentData): float
    {
        return (float) ($paymentData['amount'] ?? 0.0);
    }

    /**
     * Verifica si es pago exitoso según documentación Deuna
     */
    private function isSuccessfulPayment(string $status): bool
    {
        // Estados exitosos según documentación Deuna
        $successfulStatuses = [
            'SUCCESS',    // Estado exitoso en webhook según docs
            'APPROVED',   // Estado exitoso en API de consulta según docs
        ];

        return in_array($status, $successfulStatuses);
    }

    /**
     * Maneja webhooks de pagos no exitosos según estados documentados
     */
    private function handleFailedDeunaWebhook(string $status, string $transactionId, array $paymentData): PaymentResult
    {
        Log::warning('⚠️ DeunaWebhookValidator: Webhook con estado no exitoso', [
            'transaction_id' => $transactionId,
            'status' => $status,
            'transfer_number' => $paymentData['transferNumber'] ?? 'N/A',
        ]);

        // Mapear estados según documentación Deuna
        $errorMessage = match ($status) {
            'PENDING' => 'Transacción pendiente, espere un momento',
            'REVERSED' => 'Se realizó devolución al cliente',
            'REVERSED_FAILED' => 'Falló la devolución desde Banca Móvil',
            default => "Estado no exitoso en webhook Deuna: {$status}"
        };

        return PaymentResult::failure(
            paymentMethod: 'deuna',
            validationType: 'webhook',
            errorMessage: $errorMessage,
            errorCode: 'DEUNA_WEBHOOK_NOT_SUCCESS',
            metadata: [
                'status' => $status,
                'transaction_id' => $transactionId,
                'transfer_number' => $paymentData['transferNumber'] ?? null,
                'internal_transaction_reference' => $paymentData['internalTransactionReference'] ?? null,
                'webhook_data' => $paymentData,
            ]
        );
    }
}
