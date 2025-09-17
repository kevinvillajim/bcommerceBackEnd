<?php

namespace App\Validators\Payment\Datafast;

use App\Domain\Interfaces\PaymentValidatorInterface;
use App\Domain\ValueObjects\PaymentResult;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Validador para botón de prueba de Datafast
 *
 * PUNTO DE VALIDACIÓN: Test Button → verifyPayment() con simulate_success=true
 *
 * FLUJO:
 * 1. Usuario hace click en "Simular Pago Exitoso" en frontend
 * 2. Request llega con simulate_success=true
 * 3. Este validador simula una transacción exitosa para testing
 */
class DatafastTestValidator implements PaymentValidatorInterface
{
    public function validatePayment(array $paymentData): PaymentResult
    {
        Log::info('🧪 DatafastTestValidator: Validando simulación de pago', [
            'transaction_id' => $paymentData['transaction_id'] ?? 'N/A',
            'simulate_success' => $paymentData['simulate_success'] ?? 'false',
            'calculated_total' => $paymentData['calculated_total'] ?? 'N/A',
        ]);

        try {
            // Validar que realmente es una simulación
            $this->validateSimulationRequest($paymentData);

            $transactionId = $paymentData['transaction_id'];
            $amount = $this->extractAmount($paymentData);

            // Usar transaction_id real como payment_id para tests
            $paymentId = $transactionId;

            Log::info('✅ DatafastTestValidator: Test de simulación exitoso', [
                'transaction_id' => $transactionId,
                'payment_id' => $paymentId,
                'amount' => $amount,
                'mode' => 'test_simulation',
            ]);

            return PaymentResult::success(
                transactionId: $transactionId,
                amount: $amount,
                paymentMethod: 'datafast',
                validationType: 'test',
                metadata: [
                    'payment_id' => $paymentId,
                    'result_code' => '000.100.110', // Código de pruebas Datafast
                    'result_message' => 'Test de pago exitoso (Modo de prueba)',
                    'simulation_type' => 'manual_test_button',
                    'currency' => 'USD',
                    'environment' => config('app.env'),
                    'timestamp' => now()->toISOString(),
                    'test_data' => $paymentData, // Incluir datos reales del test
                ]
            );

        } catch (Exception $e) {
            Log::error('❌ DatafastTestValidator: Error en simulación', [
                'error' => $e->getMessage(),
                'transaction_id' => $paymentData['transaction_id'] ?? 'N/A',
            ]);

            return PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'test',
                errorMessage: 'Error en simulación de pago: '.$e->getMessage(),
                errorCode: 'TEST_SIMULATION_ERROR',
                metadata: ['original_error' => $e->getMessage()]
            );
        }
    }

    public function getPaymentMethod(): string
    {
        return 'datafast';
    }

    public function getValidationType(): string
    {
        return 'test';
    }

    /**
     * Valida que sea una request de simulación válida
     */
    private function validateSimulationRequest(array $paymentData): void
    {
        // Verificar flag de simulación - ✅ CORREGIDO: Soportar tanto boolean como string
        if (! isset($paymentData['simulate_success']) ||
            ($paymentData['simulate_success'] !== true && $paymentData['simulate_success'] !== 'true')) {
            throw new Exception('Request no es de simulación válida');
        }

        // Verificar campos requeridos
        $required = ['transaction_id'];
        foreach ($required as $field) {
            if (empty($paymentData[$field])) {
                throw new Exception("Campo requerido faltante para simulación: {$field}");
            }
        }

        // Solo permitir simulaciones en entornos no productivos
        if (config('app.env') === 'production') {
            Log::warning('⚠️ Intento de simulación en producción bloqueado', [
                'transaction_id' => $paymentData['transaction_id'],
                'environment' => config('app.env'),
            ]);
            throw new Exception('Simulaciones no permitidas en producción');
        }

        Log::info('✅ Simulación validada correctamente', [
            'transaction_id' => $paymentData['transaction_id'],
            'environment' => config('app.env'),
        ]);
    }

    /**
     * Extrae monto para simulación
     */
    private function extractAmount(array $paymentData): float
    {
        // Priorizar calculated_total que viene del frontend
        $amount = $paymentData['calculated_total'] ?? 1.0;

        if ($amount <= 0) {
            Log::warning('⚠️ Monto inválido en simulación, usando fallback', [
                'original_amount' => $amount,
                'fallback_amount' => 1.0,
            ]);
            $amount = 1.0;
        }

        return (float) $amount;
    }
}
