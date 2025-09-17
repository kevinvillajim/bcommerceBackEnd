<?php

namespace App\Validators\Payment\Deuna;

use App\Domain\Interfaces\PaymentValidatorInterface;
use App\Domain\ValueObjects\PaymentResult;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Validador para simulaciones manuales de Deuna
 *
 * PUNTO DE VALIDACIÓN: Manual Simulation → verifyPayment() con simulate_deuna=true
 *
 * FLUJO:
 * 1. Usuario hace click en "Simular Pago Deuna" en frontend
 * 2. Request llega con simulate_deuna=true
 * 3. Este validador simula una transacción exitosa de Deuna para testing
 * 4. Genera respuesta simulada compatible con estructura Deuna
 */
class DeunaSimulationValidator implements PaymentValidatorInterface
{
    public function validatePayment(array $paymentData): PaymentResult
    {
        Log::info('🧪 DeunaSimulationValidator: Validando simulación manual de Deuna', [
            'transaction_id' => $paymentData['transaction_id'] ?? 'N/A',
            'simulate_deuna' => $paymentData['simulate_deuna'] ?? 'false',
            'calculated_total' => $paymentData['calculated_total'] ?? 'N/A',
        ]);

        try {
            // Validar que realmente es una simulación
            $this->validateSimulationRequest($paymentData);

            $transactionId = $paymentData['transaction_id'];
            $amount = $this->extractAmount($paymentData);

            // Usar transaction_id real como payment_id para tests
            $paymentId = $transactionId;

            Log::info('✅ DeunaSimulationValidator: Test de simulación exitoso', [
                'transaction_id' => $transactionId,
                'payment_id' => $paymentId,
                'amount' => $amount,
                'mode' => 'deuna_simulation',
                'environment' => config('app.env'),
            ]);

            return PaymentResult::success(
                transactionId: $transactionId,
                amount: $amount,
                paymentMethod: 'deuna',
                validationType: 'simulation',
                metadata: [
                    'payment_id' => $paymentId,
                    'status' => 'SUCCESS',
                    'simulation_type' => 'manual_deuna_button',
                    'currency' => 'USD',
                    'environment' => config('app.env'),
                    'timestamp' => now()->toISOString(),
                    'test_data' => $paymentData, // Incluir datos reales del test
                ]
            );

        } catch (Exception $e) {
            Log::error('❌ DeunaSimulationValidator: Error en simulación', [
                'error' => $e->getMessage(),
                'transaction_id' => $paymentData['transaction_id'] ?? 'N/A',
            ]);

            return PaymentResult::failure(
                paymentMethod: 'deuna',
                validationType: 'simulation',
                errorMessage: 'Error en simulación de pago Deuna: '.$e->getMessage(),
                errorCode: 'DEUNA_SIMULATION_ERROR',
                metadata: ['original_error' => $e->getMessage()]
            );
        }
    }

    public function getPaymentMethod(): string
    {
        return 'deuna';
    }

    public function getValidationType(): string
    {
        return 'simulation';
    }

    /**
     * Valida que sea una request de simulación válida
     */
    private function validateSimulationRequest(array $paymentData): void
    {
        // Verificar flag de simulación - acepta tanto boolean true como string 'true'
        if (! isset($paymentData['simulate_deuna']) ||
            ($paymentData['simulate_deuna'] !== true && $paymentData['simulate_deuna'] !== 'true')) {
            throw new Exception('Request no es de simulación Deuna válida');
        }

        // Verificar campos requeridos
        $required = ['transaction_id'];
        foreach ($required as $field) {
            if (empty($paymentData[$field])) {
                throw new Exception("Campo requerido faltante para simulación Deuna: {$field}");
            }
        }

        // Solo permitir simulaciones en entornos no productivos
        if (config('app.env') === 'production') {
            Log::warning('⚠️ Intento de simulación Deuna en producción bloqueado', [
                'transaction_id' => $paymentData['transaction_id'],
                'environment' => config('app.env'),
            ]);
            throw new Exception('Simulaciones Deuna no permitidas en producción');
        }

        Log::info('✅ Simulación Deuna validada correctamente', [
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
            Log::warning('⚠️ Monto inválido en simulación Deuna, usando fallback', [
                'original_amount' => $amount,
                'fallback_amount' => 1.0,
            ]);
            $amount = 1.0;
        }

        return (float) $amount;
    }
}
