<?php

namespace App\Validators\Payment\Datafast;

use App\Domain\Interfaces\PaymentValidatorInterface;
use App\Domain\ValueObjects\PaymentResult;
use App\Infrastructure\External\PaymentGateway\DatafastService;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Validador unificado para Datafast - Reemplaza múltiples validadores
 *
 * FLUJO ÚNICO SIMPLIFICADO:
 * 1. Auto-detección del tipo de validación (widget, test, webhook)
 * 2. Validación específica según el contexto
 * 3. Respuesta estandarizada para todos los casos
 *
 * ELIMINA:
 * - DatafastWidgetValidator
 * - DatafastTestValidator
 * - Lógica duplicada de validación
 */
class UnifiedDatafastValidator implements PaymentValidatorInterface
{
    public function __construct(
        private DatafastService $datafastService
    ) {}

    public function validatePayment(array $paymentData): PaymentResult
    {
        Log::info('🔄 UnifiedDatafastValidator: Iniciando validación unificada', [
            'transaction_id' => $paymentData['transaction_id'] ?? 'N/A',
            'has_resource_path' => isset($paymentData['resource_path']),
            'has_simulate_success' => isset($paymentData['simulate_success']),
        ]);

        try {
            // Auto-detección del tipo de validación
            $validationType = $this->detectValidationType($paymentData);

            Log::info('🎯 Tipo de validación detectado', [
                'type' => $validationType,
                'transaction_id' => $paymentData['transaction_id'] ?? 'N/A',
            ]);

            // Ejecutar validación específica
            return match($validationType) {
                'widget' => $this->validateWidgetResponse($paymentData),
                'test' => $this->validateTestSimulation($paymentData),
                'webhook' => $this->validateWebhookData($paymentData),
                default => $this->handleUnknownType($paymentData)
            };

        } catch (Exception $e) {
            Log::error('❌ UnifiedDatafastValidator: Error en validación', [
                'error' => $e->getMessage(),
                'transaction_id' => $paymentData['transaction_id'] ?? 'N/A',
            ]);

            return PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'unified',
                errorMessage: 'Error en validación de pago: ' . $e->getMessage(),
                errorCode: 'VALIDATION_ERROR',
                metadata: ['original_error' => $e->getMessage()]
            );
        }
    }

    /**
     * Auto-detecta el tipo de validación basado en los datos
     */
    private function detectValidationType(array $paymentData): string
    {
        // Test simulation - Flag explícito
        if (isset($paymentData['simulate_success']) &&
            ($paymentData['simulate_success'] === true || $paymentData['simulate_success'] === 'true')) {
            return 'test';
        }

        // Widget response - Contiene resource_path
        if (isset($paymentData['resource_path']) && !empty($paymentData['resource_path'])) {
            return 'widget';
        }

        // Webhook - Contiene event o eventType
        if (isset($paymentData['event']) || isset($paymentData['eventType'])) {
            return 'webhook';
        }

        // Si tiene payment_id pero no resource_path, probablemente webhook
        if (isset($paymentData['payment_id']) && !isset($paymentData['resource_path'])) {
            return 'webhook';
        }

        return 'unknown';
    }

    /**
     * Valida respuesta del widget de Datafast
     */
    private function validateWidgetResponse(array $paymentData): PaymentResult
    {
        Log::info('🔍 Validando respuesta de widget Datafast');

        // Validar campos requeridos del widget
        $this->validateRequiredFields($paymentData, ['resource_path', 'transaction_id']);

        $resourcePath = $paymentData['resource_path'];
        $transactionId = $paymentData['transaction_id'];

        // Verificar pago con API real de Datafast
        $result = $this->datafastService->verifyPayment($resourcePath);

        Log::info('📡 Respuesta de API Datafast', [
            'transaction_id' => $transactionId,
            'result_success' => $result['success'] ?? false,
            'result_code' => $result['result_code'] ?? 'N/A',
        ]);

        if (!($result['success'] ?? false)) {
            return $this->handleDatafastError($result, $transactionId);
        }

        // Extraer información del pago exitoso
        $amount = $this->extractAmount($result, $paymentData);
        $paymentId = $result['payment_id'] ?? $result['id'] ?? $transactionId;

        Log::info('✅ Widget Datafast validado exitosamente', [
            'transaction_id' => $transactionId,
            'payment_id' => $paymentId,
            'amount' => $amount,
        ]);

        return PaymentResult::success(
            transactionId: $transactionId,
            amount: $amount,
            paymentMethod: 'datafast',
            validationType: 'widget',
            metadata: [
                'payment_id' => $paymentId,
                'result_code' => $result['result_code'],
                'result_message' => $result['message'] ?? '',
                'resource_path' => $resourcePath,
                'validation_type' => 'widget_api',
                'api_response' => $result,
            ]
        );
    }

    /**
     * Valida simulación de test
     */
    private function validateTestSimulation(array $paymentData): PaymentResult
    {
        Log::info('🧪 Validando simulación de test');

        // Solo permitir simulaciones en entornos no productivos
        if (config('app.env') === 'production') {
            Log::warning('⚠️ Simulación bloqueada en producción', [
                'transaction_id' => $paymentData['transaction_id'] ?? 'N/A',
            ]);

            return PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'test',
                errorMessage: 'Simulaciones no permitidas en producción',
                errorCode: 'SIMULATION_BLOCKED'
            );
        }

        $this->validateRequiredFields($paymentData, ['transaction_id']);

        $transactionId = $paymentData['transaction_id'];
        $amount = $this->extractAmount($paymentData, []);

        Log::info('✅ Simulación de test validada', [
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'environment' => config('app.env'),
        ]);

        return PaymentResult::success(
            transactionId: $transactionId,
            amount: $amount,
            paymentMethod: 'datafast',
            validationType: 'test',
            metadata: [
                'payment_id' => $transactionId,
                'result_code' => '000.100.110', // Código de pruebas Datafast
                'result_message' => 'Test de pago exitoso (Simulación)',
                'simulation_type' => 'manual_test',
                'validation_type' => 'test_simulation',
                'environment' => config('app.env'),
                'timestamp' => now()->toISOString(),
            ]
        );
    }

    /**
     * Valida datos de webhook
     */
    private function validateWebhookData(array $paymentData): PaymentResult
    {
        Log::info('🔔 Validando datos de webhook');

        // Para webhooks, los campos pueden variar según el proveedor
        $paymentId = $paymentData['payment_id'] ?? $paymentData['idTransaction'] ?? null;
        $status = $paymentData['status'] ?? $paymentData['state'] ?? 'unknown';

        if (!$paymentId) {
            return PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'webhook',
                errorMessage: 'ID de pago faltante en webhook',
                errorCode: 'MISSING_PAYMENT_ID'
            );
        }

        // Verificar si el estado indica éxito
        $isSuccessful = in_array(strtolower($status), ['completed', 'success', 'approved', 'paid']);

        if (!$isSuccessful) {
            Log::info('⚠️ Webhook recibido con estado no exitoso', [
                'payment_id' => $paymentId,
                'status' => $status,
            ]);

            return PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'webhook',
                errorMessage: "Pago no exitoso: {$status}",
                errorCode: 'PAYMENT_NOT_SUCCESSFUL',
                metadata: [
                    'payment_id' => $paymentId,
                    'status' => $status,
                    'validation_type' => 'webhook',
                ]
            );
        }

        $amount = $this->extractAmount($paymentData, []);

        Log::info('✅ Webhook validado exitosamente', [
            'payment_id' => $paymentId,
            'status' => $status,
            'amount' => $amount,
        ]);

        return PaymentResult::success(
            transactionId: $paymentId,
            amount: $amount,
            paymentMethod: 'datafast',
            validationType: 'webhook',
            metadata: [
                'payment_id' => $paymentId,
                'status' => $status,
                'event_type' => $paymentData['event'] ?? $paymentData['eventType'] ?? 'payment_completed',
                'validation_type' => 'webhook',
                'webhook_data' => $paymentData,
            ]
        );
    }

    /**
     * Maneja tipos desconocidos
     */
    private function handleUnknownType(array $paymentData): PaymentResult
    {
        Log::warning('⚠️ Tipo de validación desconocido', [
            'data_keys' => array_keys($paymentData),
            'transaction_id' => $paymentData['transaction_id'] ?? 'N/A',
        ]);

        return PaymentResult::failure(
            paymentMethod: 'datafast',
            validationType: 'unknown',
            errorMessage: 'Tipo de validación no reconocido',
            errorCode: 'UNKNOWN_VALIDATION_TYPE',
            metadata: [
                'available_keys' => array_keys($paymentData),
                'detection_failed' => true,
            ]
        );
    }

    /**
     * Maneja errores específicos de Datafast con códigos optimizados
     */
    private function handleDatafastError(array $result, string $transactionId): PaymentResult
    {
        $resultCode = $result['result_code'] ?? '';
        $originalMessage = $result['message'] ?? 'Error desconocido';

        // Códigos de éxito que pueden venir como "error"
        if (in_array($resultCode, ['000.000.000', '000.100.110', '000.100.112'])) {
            return PaymentResult::success(
                transactionId: $transactionId,
                amount: $this->extractAmount($result, []),
                paymentMethod: 'datafast',
                validationType: 'widget',
                metadata: [
                    'result_code' => $resultCode,
                    'message' => $originalMessage,
                    'validation_type' => 'widget_success',
                ]
            );
        }

        // Mapear errores específicos
        $errorMapping = [
            '800.100.151' => ['message' => 'Tarjeta inválida. Verifique los datos.', 'code' => 'INVALID_CARD'],
            '800.100.155' => ['message' => 'Fondos insuficientes. Verifique saldo.', 'code' => 'INSUFFICIENT_FUNDS'],
            '100.100.303' => ['message' => 'Tarjeta expirada. Use una vigente.', 'code' => 'CARD_EXPIRED'],
            '800.100.168' => ['message' => 'Tarjeta restringida. Contacte su banco.', 'code' => 'CARD_RESTRICTED'],
            '900.100.201' => ['message' => 'Error de conexión. Intente nuevamente.', 'code' => 'GATEWAY_ERROR'],
            '000.200.100' => ['message' => 'Checkout creado, pago no completado.', 'code' => 'CHECKOUT_PENDING'],
        ];

        $error = $errorMapping[$resultCode] ?? [
            'message' => $originalMessage ?: "Error en procesamiento (Código: {$resultCode})",
            'code' => $resultCode ?: 'UNKNOWN_ERROR'
        ];

        return PaymentResult::failure(
            paymentMethod: 'datafast',
            validationType: 'widget',
            errorMessage: $error['message'],
            errorCode: $error['code'],
            metadata: [
                'result_code' => $resultCode,
                'original_message' => $originalMessage,
                'validation_type' => 'widget_error',
            ]
        );
    }

    /**
     * Extrae monto de diferentes estructuras
     */
    private function extractAmount(array $data, array $fallbackData): float
    {
        $amount = $data['amount'] ??
                 $data['calculated_total'] ??
                 $data['total'] ??
                 $fallbackData['calculated_total'] ??
                 $fallbackData['amount'] ??
                 0.0;

        return (float) $amount;
    }

    /**
     * Valida campos requeridos
     */
    private function validateRequiredFields(array $data, array $required): void
    {
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Campo requerido faltante: {$field}");
            }
        }
    }

    public function getPaymentMethod(): string
    {
        return 'datafast';
    }

    public function getValidationType(): string
    {
        return 'unified';
    }
}