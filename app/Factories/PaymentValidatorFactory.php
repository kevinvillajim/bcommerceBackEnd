<?php

namespace App\Factories;

use App\Domain\Interfaces\PaymentValidatorInterface;
use App\Validators\Payment\Datafast\DatafastAPIValidator;
use App\Validators\Payment\Datafast\DatafastTestValidator;
use App\Validators\Payment\Datafast\DatafastWebhookValidator;
use App\Validators\Payment\Datafast\DatafastWidgetValidator;
use App\Validators\Payment\Deuna\DeunaSimulationValidator;
use App\Validators\Payment\Deuna\DeunaTestValidator;
use App\Validators\Payment\Deuna\DeunaWebhookValidator;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Factory para crear validadores de pago específicos
 *
 * MAPEO EXACTO de los puntos de validación identificados:
 *
 * Datafast (4 puntos → 4 validadores):
 * - widget: Widget Response → verifyPayment() respuesta real
 * - test: Test Button → verifyPayment() con simulate_success=true
 * - api: API Verification → datafastService->verifyPayment() verificación real
 * - webhook: Webhook → webhook() notificaciones automáticas
 *
 * Deuna (3 puntos → 3 validadores):
 * - webhook: Webhook Real → handlePaymentStatus() notificaciones automáticas
 * - test: Webhook Test → testWebhook() pruebas
 * - simulation: Simulation → simulatePaymentSuccess() simulaciones QR
 */
class PaymentValidatorFactory
{
    public function __construct(
        private \Illuminate\Contracts\Container\Container $container
    ) {}

    /**
     * Crea validador específico para método y tipo de pago usando DI
     */
    public function getValidator(string $paymentMethod, string $validationType): PaymentValidatorInterface
    {
        Log::info('🏭 PaymentValidatorFactory: Creando validador', [
            'payment_method' => $paymentMethod,
            'validation_type' => $validationType,
        ]);

        $validatorClass = match ([$paymentMethod, $validationType]) {
            // ✅ DATAFAST - 4 validadores (mapeo exacto de puntos identificados)
            ['datafast', 'widget'] => DatafastWidgetValidator::class,
            ['datafast', 'test'] => DatafastTestValidator::class,
            ['datafast', 'api'] => DatafastAPIValidator::class,
            ['datafast', 'webhook'] => DatafastWebhookValidator::class,

            // ✅ DEUNA - 3 validadores (mapeo exacto de puntos identificados)
            ['deuna', 'webhook'] => DeunaWebhookValidator::class,
            ['deuna', 'test'] => DeunaTestValidator::class,
            ['deuna', 'simulation'] => DeunaSimulationValidator::class,

            default => throw new InvalidArgumentException(
                "Combinación de pago no soportada: {$paymentMethod}/{$validationType}. ".
                'Métodos disponibles: '.implode(', ', $this->getSupportedMethods())
            )
        };

        $validator = $this->container->make($validatorClass);

        Log::info('✅ Validador creado exitosamente', [
            'payment_method' => $paymentMethod,
            'validation_type' => $validationType,
            'validator_class' => get_class($validator),
        ]);

        return $validator;
    }

    /**
     * Auto-detecta tipo de validación para Datafast basado en request
     */
    public function detectDatafastValidationType(array $requestData): string
    {
        // Test Button detection - ✅ CORREGIDO: Soportar tanto boolean como string
        if (isset($requestData['simulate_success']) &&
            ($requestData['simulate_success'] === true || $requestData['simulate_success'] === 'true')) {
            return 'test';
        }

        // Webhook detection
        if (isset($requestData['webhook_signature']) || isset($requestData['notificationId'])) {
            return 'webhook';
        }

        // Widget Response vs API Verification
        if (isset($requestData['resource_path']) && isset($requestData['transaction_id'])) {
            // Por defecto widget (flujo más común desde frontend)
            return 'widget';
        }

        return 'widget';
    }

    /**
     * Auto-detecta tipo de validación para Deuna basado en request
     */
    public function detectDeunaValidationType(array $requestData): string
    {
        // Manual Simulation detection - ✅ CORREGIDO: Soportar tanto boolean como string
        if (isset($requestData['simulate_deuna']) &&
            ($requestData['simulate_deuna'] === true || $requestData['simulate_deuna'] === 'true')) {
            return 'simulation';
        }

        // Test webhook detection (indicators: TEST_ prefix, prueba keywords, small amounts)
        if ($this->isDeunaTestWebhook($requestData)) {
            return 'test';
        }

        // Default: webhook real
        return 'webhook';
    }

    /**
     * Detecta si es un webhook de prueba de Deuna usando datos reales
     */
    private function isDeunaTestWebhook(array $requestData): bool
    {
        $transactionId = $requestData['idTransaction'] ?? '';
        $internalRef = $requestData['internalTransactionReference'] ?? '';
        $transferNumber = $requestData['transferNumber'] ?? '';
        $description = $requestData['description'] ?? '';
        $amount = $requestData['amount'] ?? 0;

        // Indicadores reales de webhook de prueba basados en los datos que llegan
        return str_contains($transactionId, 'TEST_') ||
               str_contains($internalRef, 'TEST_') ||
               str_contains($transferNumber, 'TEST_') ||
               str_contains(strtolower($description), 'prueba') ||
               str_contains(strtolower($description), 'test') ||
               $amount <= 0.01; // Montos típicos de prueba
    }

    /**
     * Obtiene todos los métodos de pago soportados
     */
    public function getSupportedMethods(): array
    {
        return [
            'datafast/widget',
            'datafast/test',
            'datafast/api',
            'datafast/webhook',
            'deuna/webhook',
            'deuna/test',
            'deuna/simulation',
        ];
    }

    /**
     * Verifica si una combinación método/tipo está soportada
     */
    public function isSupported(string $paymentMethod, string $validationType): bool
    {
        try {
            $this->getValidator($paymentMethod, $validationType);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
