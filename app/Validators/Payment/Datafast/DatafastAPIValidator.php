<?php

namespace App\Validators\Payment\Datafast;

use App\Domain\Interfaces\PaymentValidatorInterface;
use App\Domain\ValueObjects\PaymentResult;
use App\Infrastructure\External\PaymentGateway\DatafastService;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Validador para verificación directa con API de Datafast
 *
 * PUNTO DE VALIDACIÓN: API Verification → datafastService->verifyPayment() verificación real
 *
 * FLUJO:
 * 1. Verificación directa con API de Datafast (sin widget)
 * 2. Usado para validaciones programáticas o verificaciones posteriores
 * 3. Maneja respuestas directas de la API de Datafast
 */
class DatafastAPIValidator implements PaymentValidatorInterface
{
    public function __construct(
        private DatafastService $datafastService
    ) {}

    public function validatePayment(array $paymentData): PaymentResult
    {
        Log::info('🔍 DatafastAPIValidator: Validando via API directa', [
            'transaction_id' => $paymentData['transaction_id'] ?? 'N/A',
            'resource_path' => isset($paymentData['resource_path']) ? 'PRESENTE' : 'AUSENTE',
            'validation_mode' => 'direct_api',
        ]);

        try {
            // Validar datos requeridos para API
            $this->validateRequiredFields($paymentData);

            $resourcePath = $paymentData['resource_path'];
            $transactionId = $paymentData['transaction_id'];

            // Verificación directa con API de Datafast
            Log::info('📡 DatafastAPIValidator: Realizando verificación directa con API', [
                'transaction_id' => $transactionId,
                'resource_path' => $resourcePath,
            ]);

            $result = $this->datafastService->verifyPayment($resourcePath);

            Log::info('📡 DatafastAPIValidator: Respuesta de API Datafast', [
                'transaction_id' => $transactionId,
                'result_success' => $result['success'] ?? false,
                'result_code' => $result['result_code'] ?? 'N/A',
                'result_message' => $result['message'] ?? 'N/A',
                'api_verification' => true,
            ]);

            if (! ($result['success'] ?? false)) {
                return $this->handleAPIError($result, $transactionId);
            }

            // Extraer información del pago exitoso
            $amount = $this->extractAmount($result, $paymentData);
            $paymentId = $result['payment_id'] ?? $result['id'] ?? $transactionId;

            Log::info('✅ DatafastAPIValidator: Verificación API exitosa', [
                'transaction_id' => $transactionId,
                'payment_id' => $paymentId,
                'amount' => $amount,
                'result_code' => $result['result_code'],
                'verification_method' => 'direct_api',
            ]);

            return PaymentResult::success(
                transactionId: $transactionId,
                amount: $amount,
                paymentMethod: 'datafast',
                validationType: 'api',
                metadata: [
                    'payment_id' => $paymentId,
                    'result_code' => $result['result_code'],
                    'result_message' => $result['message'] ?? '',
                    'resource_path' => $resourcePath,
                    'verification_method' => 'direct_api',
                    'api_response' => $result,
                    'timestamp' => now()->toISOString(),
                ]
            );

        } catch (Exception $e) {
            Log::error('❌ DatafastAPIValidator: Error en verificación API', [
                'error' => $e->getMessage(),
                'transaction_id' => $paymentData['transaction_id'] ?? 'N/A',
                'verification_method' => 'direct_api',
            ]);

            return PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'api',
                errorMessage: 'Error en verificación API: '.$e->getMessage(),
                errorCode: 'API_VERIFICATION_ERROR',
                metadata: [
                    'original_error' => $e->getMessage(),
                    'verification_method' => 'direct_api',
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
        return 'api';
    }

    /**
     * Maneja errores de API con códigos específicos de Datafast
     */
    private function handleAPIError(array $result, string $transactionId): PaymentResult
    {
        $resultCode = $result['result_code'] ?? '';
        $originalMessage = $result['message'] ?? 'Error en verificación API';

        Log::warning('⚠️ DatafastAPIValidator: Error en verificación API', [
            'transaction_id' => $transactionId,
            'result_code' => $resultCode,
            'message' => $originalMessage,
            'verification_method' => 'direct_api',
        ]);

        // Manejo específico para errores comunes en verificación API
        return match ($resultCode) {
            '800.900.300' => PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'api',
                errorMessage: 'No se encontró transacción real para verificar',
                errorCode: 'NO_TRANSACTION_FOUND',
                metadata: [
                    'result_code' => $resultCode,
                    'verification_method' => 'direct_api',
                    'suggestion' => 'Verificar que la transacción fue procesada correctamente',
                ]
            ),

            '000.200.000' => PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'api',
                errorMessage: 'Transacción pendiente en verificación API',
                errorCode: 'TRANSACTION_PENDING_API',
                metadata: [
                    'result_code' => $resultCode,
                    'status' => 'pending',
                    'verification_method' => 'direct_api',
                ]
            ),

            '900.100.201' => PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'api',
                errorMessage: 'Error de conexión con Gateway en verificación API',
                errorCode: 'GATEWAY_CONNECTION_ERROR',
                metadata: [
                    'result_code' => $resultCode,
                    'verification_method' => 'direct_api',
                    'retry_recommended' => true,
                ]
            ),

            '200.300.404' => PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'api',
                errorMessage: 'Parámetro inválido en verificación API',
                errorCode: 'INVALID_API_PARAMETER',
                metadata: [
                    'result_code' => $resultCode,
                    'verification_method' => 'direct_api',
                    'action' => 'revisar_parametros_request',
                ]
            ),

            default => PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'api',
                errorMessage: $originalMessage ?: "Error en verificación API (Código: {$resultCode})",
                errorCode: $resultCode ?: 'API_UNKNOWN_ERROR',
                metadata: [
                    'result_code' => $resultCode,
                    'original_message' => $originalMessage,
                    'verification_method' => 'direct_api',
                ]
            )
        };
    }

    /**
     * Valida campos requeridos para verificación API
     */
    private function validateRequiredFields(array $paymentData): void
    {
        $required = ['resource_path', 'transaction_id'];

        foreach ($required as $field) {
            if (empty($paymentData[$field])) {
                throw new Exception("Campo requerido para verificación API faltante: {$field}");
            }
        }
    }

    /**
     * Extrae monto del resultado de API
     */
    private function extractAmount(array $result, array $paymentData): float
    {
        return (float) ($result['amount']
            ?? $paymentData['calculated_total']
            ?? $result['total']
            ?? 0.0);
    }
}
