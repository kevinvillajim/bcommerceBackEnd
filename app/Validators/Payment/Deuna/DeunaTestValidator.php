<?php

namespace App\Validators\Payment\Deuna;

use App\Domain\Interfaces\PaymentValidatorInterface;
use App\Domain\ValueObjects\PaymentResult;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Validador para webhooks de prueba de Deuna
 *
 * PUNTO DE VALIDACIÓN: Test Webhook → handlePaymentStatus() notificaciones de pruebas
 *
 * ESTRUCTURA WEBHOOK DE PRUEBA:
 * {
 *   "status": "SUCCESS",
 *   "amount": 0.01,
 *   "idTransaction": "TEST_4k86f87c-8918-4x29-85d8-ebe33610ebx3",
 *   "internalTransactionReference": "TEST_04-0000007",
 *   "transferNumber": "TEST_044451603432",
 *   "date": "6/24/2024, 4:10:58 PM",
 *   "branchId": "10810",
 *   "posId": "11820",
 *   "currency": "USD",
 *   "description": "Pago de prueba Deuna",
 *   "customerIdentification": "1234567890",
 *   "customerFullName": "CLIENTE DE PRUEBA DEUNA"
 * }
 */
class DeunaTestValidator implements PaymentValidatorInterface
{
    public function validatePayment(array $paymentData): PaymentResult
    {
        Log::info('🧪 DeunaTestValidator: Validando webhook de prueba Deuna', [
            'status' => $paymentData['status'] ?? 'N/A',
            'idTransaction' => $paymentData['idTransaction'] ?? 'N/A',
            'amount' => $paymentData['amount'] ?? 'N/A',
            'is_test' => $this->isTestWebhook($paymentData),
        ]);

        try {
            // Validar que es un webhook de prueba válido
            $this->validateTestWebhookStructure($paymentData);

            // Extraer información según campos documentados
            $transactionId = $this->extractTransactionId($paymentData);
            $status = $this->extractStatus($paymentData);
            $amount = $this->extractAmount($paymentData);

            Log::info('📋 DeunaTestValidator: Información extraída del webhook de prueba', [
                'transaction_id' => $transactionId,
                'status' => $status,
                'amount' => $amount,
                'transfer_number' => $paymentData['transferNumber'] ?? 'N/A',
                'test_mode' => true,
            ]);

            // Verificar si es un pago exitoso de prueba
            if (! $this->isSuccessfulPayment($status)) {
                return $this->handleFailedTestWebhook($status, $transactionId, $paymentData);
            }

            Log::info('✅ DeunaTestValidator: Webhook de prueba exitoso procesado', [
                'transaction_id' => $transactionId,
                'status' => $status,
                'amount' => $amount,
                'test_mode' => true,
                'environment' => config('app.env'),
            ]);

            return PaymentResult::success(
                transactionId: $transactionId,
                amount: $amount,
                paymentMethod: 'deuna',
                validationType: 'test_webhook',
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
                    'notification_type' => 'deuna_webhook_test',
                    'test_mode' => true,
                    'environment' => config('app.env'),
                    'timestamp' => now()->toISOString(),
                ]
            );

        } catch (Exception $e) {
            Log::error('❌ DeunaTestValidator: Error en validación de webhook de prueba', [
                'error' => $e->getMessage(),
                'idTransaction' => $paymentData['idTransaction'] ?? 'N/A',
                'webhook_data' => $paymentData,
            ]);

            return PaymentResult::failure(
                paymentMethod: 'deuna',
                validationType: 'test_webhook',
                errorMessage: 'Error en webhook de prueba Deuna: '.$e->getMessage(),
                errorCode: 'DEUNA_TEST_WEBHOOK_ERROR',
                metadata: [
                    'original_error' => $e->getMessage(),
                    'webhook_data' => $paymentData,
                    'test_mode' => true,
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
        return 'test_webhook';
    }

    /**
     * Verifica si es un webhook de prueba válido
     */
    private function isTestWebhook(array $paymentData): bool
    {
        $transactionId = $paymentData['idTransaction'] ?? '';
        $internalRef = $paymentData['internalTransactionReference'] ?? '';
        $transferNumber = $paymentData['transferNumber'] ?? '';
        $description = $paymentData['description'] ?? '';

        // Identificadores comunes de webhooks de prueba Deuna
        $testIndicators = [
            str_contains($transactionId, 'TEST_'),
            str_contains($internalRef, 'TEST_'),
            str_contains($transferNumber, 'TEST_'),
            str_contains(strtolower($description), 'prueba'),
            str_contains(strtolower($description), 'test'),
            ($paymentData['amount'] ?? 0) <= 0.01, // Montos de prueba típicos
        ];

        return in_array(true, $testIndicators);
    }

    /**
     * Valida estructura del webhook de prueba según documentación Deuna
     */
    private function validateTestWebhookStructure(array $paymentData): void
    {
        // Verificar que es realmente un webhook de prueba
        if (! $this->isTestWebhook($paymentData)) {
            throw new Exception('Webhook no identificado como de prueba según indicadores Deuna');
        }

        // Campos requeridos según documentación Deuna (igual que webhook real)
        $requiredFields = ['status', 'amount', 'idTransaction'];

        foreach ($requiredFields as $field) {
            if (! isset($paymentData[$field])) {
                throw new Exception("Campo requerido faltante en webhook de prueba Deuna: {$field}");
            }
        }

        // Solo permitir webhooks de prueba en entornos no productivos
        if (config('app.env') === 'production') {
            Log::warning('⚠️ Webhook de prueba Deuna en producción bloqueado', [
                'idTransaction' => $paymentData['idTransaction'] ?? 'N/A',
                'environment' => config('app.env'),
            ]);
            throw new Exception('Webhooks de prueba Deuna no permitidos en producción');
        }

        Log::info('✅ Estructura de webhook de prueba Deuna validada', [
            'has_status' => isset($paymentData['status']),
            'has_amount' => isset($paymentData['amount']),
            'has_idTransaction' => isset($paymentData['idTransaction']),
            'is_test_webhook' => true,
            'environment' => config('app.env'),
        ]);
    }

    /**
     * Extrae ID de transacción según documentación
     */
    private function extractTransactionId(array $paymentData): string
    {
        $transactionId = $paymentData['idTransaction']
            ?? $paymentData['internalTransactionReference']
            ?? $paymentData['transferNumber'];

        if (empty($transactionId)) {
            throw new Exception('No se pudo extraer transaction_id del webhook de prueba Deuna');
        }

        return (string) $transactionId;
    }

    /**
     * Extrae estado según documentación
     */
    private function extractStatus(array $paymentData): string
    {
        return $paymentData['status'] ?? 'unknown';
    }

    /**
     * Extrae monto según documentación
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
        $successfulStatuses = [
            'SUCCESS',    // Estado exitoso en webhook según docs
            'APPROVED',   // Estado exitoso en API de consulta según docs
        ];

        return in_array($status, $successfulStatuses);
    }

    /**
     * Maneja webhooks de prueba fallidos
     */
    private function handleFailedTestWebhook(string $status, string $transactionId, array $paymentData): PaymentResult
    {
        Log::warning('⚠️ DeunaTestValidator: Webhook de prueba con estado no exitoso', [
            'transaction_id' => $transactionId,
            'status' => $status,
            'test_mode' => true,
            'transfer_number' => $paymentData['transferNumber'] ?? 'N/A',
        ]);

        // Mapear estados según documentación Deuna
        $errorMessage = match ($status) {
            'PENDING' => 'Transacción de prueba pendiente',
            'REVERSED' => 'Devolución simulada en transacción de prueba',
            'REVERSED_FAILED' => 'Falló la devolución simulada en transacción de prueba',
            default => "Estado no exitoso en webhook de prueba Deuna: {$status}"
        };

        return PaymentResult::failure(
            paymentMethod: 'deuna',
            validationType: 'test_webhook',
            errorMessage: $errorMessage,
            errorCode: 'DEUNA_TEST_WEBHOOK_NOT_SUCCESS',
            metadata: [
                'status' => $status,
                'transaction_id' => $transactionId,
                'transfer_number' => $paymentData['transferNumber'] ?? null,
                'internal_transaction_reference' => $paymentData['internalTransactionReference'] ?? null,
                'webhook_data' => $paymentData,
                'test_mode' => true,
            ]
        );
    }
}
