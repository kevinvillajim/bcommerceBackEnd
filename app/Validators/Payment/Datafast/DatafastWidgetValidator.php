<?php

namespace App\Validators\Payment\Datafast;

use App\Domain\Interfaces\PaymentValidatorInterface;
use App\Domain\ValueObjects\PaymentResult;
use App\Infrastructure\External\PaymentGateway\DatafastService;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Validador para respuestas del widget de Datafast
 *
 * PUNTO DE VALIDACIÓN: Widget Response → verifyPayment() respuesta real del widget
 * MANEJO OPTIMIZADO: Códigos de error específicos de Datafast para mejor UX
 */
class DatafastWidgetValidator implements PaymentValidatorInterface
{
    public function __construct(
        private DatafastService $datafastService
    ) {}

    public function validatePayment(array $paymentData): PaymentResult
    {
        Log::info('🔍 DatafastWidgetValidator: Validando respuesta de widget', [
            'transaction_id' => $paymentData['transaction_id'] ?? 'N/A',
            'resource_path' => isset($paymentData['resource_path']) ? 'PRESENTE' : 'AUSENTE',
        ]);

        try {
            // Validar datos requeridos del widget
            $this->validateRequiredFields($paymentData);

            $resourcePath = $paymentData['resource_path'];
            $transactionId = $paymentData['transaction_id'];

            // Verificar pago con API real de Datafast
            $result = $this->datafastService->verifyPayment($resourcePath);

            Log::info('📡 DatafastWidgetValidator: Respuesta de API Datafast', [
                'transaction_id' => $transactionId,
                'result_success' => $result['success'] ?? false,
                'result_code' => $result['result_code'] ?? 'N/A',
                'result_message' => $result['message'] ?? 'N/A',
            ]);

            if (! ($result['success'] ?? false)) {
                return $this->handleDatafastError($result, $transactionId);
            }

            // Extraer información del pago exitoso
            $amount = $this->extractAmount($result, $paymentData);
            $paymentId = $result['payment_id'] ?? $result['id'] ?? $transactionId;

            Log::info('✅ DatafastWidgetValidator: Pago verificado exitosamente', [
                'transaction_id' => $transactionId,
                'payment_id' => $paymentId,
                'amount' => $amount,
                'result_code' => $result['result_code'],
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
                    'api_response' => $result,
                ]
            );

        } catch (Exception $e) {
            Log::error('❌ DatafastWidgetValidator: Error en validación', [
                'error' => $e->getMessage(),
                'transaction_id' => $paymentData['transaction_id'] ?? 'N/A',
            ]);

            return PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'widget',
                errorMessage: 'Error al validar pago con widget: '.$e->getMessage(),
                errorCode: 'WIDGET_VALIDATION_ERROR',
                metadata: ['original_error' => $e->getMessage()]
            );
        }
    }

    /**
     * Maneja errores específicos de Datafast con mensajes optimizados
     */
    private function handleDatafastError(array $result, string $transactionId): PaymentResult
    {
        $resultCode = $result['result_code'] ?? '';
        $originalMessage = $result['message'] ?? 'Error desconocido';

        // Map códigos de error principales para mejor UX
        return match ($resultCode) {
            // ✅ TRANSACCIONES EXITOSAS
            '000.000.000' => PaymentResult::success(
                transactionId: $transactionId,
                amount: $this->extractAmount($result, []),
                paymentMethod: 'datafast',
                validationType: 'widget',
                metadata: ['result_code' => $resultCode, 'message' => 'Transacción exitosa']
            ),

            '000.100.110' => PaymentResult::success(
                transactionId: $transactionId,
                amount: $this->extractAmount($result, []),
                paymentMethod: 'datafast',
                validationType: 'widget',
                metadata: ['result_code' => $resultCode, 'message' => 'Aprobado (Pruebas Fase 1)']
            ),

            '000.100.112' => PaymentResult::success(
                transactionId: $transactionId,
                amount: $this->extractAmount($result, []),
                paymentMethod: 'datafast',
                validationType: 'widget',
                metadata: ['result_code' => $resultCode, 'message' => 'Aprobado (Pruebas Fase 2)']
            ),

            // ⚠️ CHECKOUT CREADO - NO ES ERROR
            '000.200.100' => PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'widget',
                errorMessage: 'Checkout creado exitosamente pero pago no completado',
                errorCode: 'CHECKOUT_CREATED_PENDING',
                metadata: ['result_code' => $resultCode, 'status' => 'pending']
            ),

            // 🚫 ERRORES DE TARJETA
            '800.100.151' => PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'widget',
                errorMessage: 'Tarjeta inválida. Verifique los datos de su tarjeta.',
                errorCode: 'INVALID_CARD',
                metadata: ['result_code' => $resultCode, 'user_action' => 'verificar_datos_tarjeta']
            ),

            '800.100.155' => PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'widget',
                errorMessage: 'Fondos insuficientes. Verifique el saldo de su tarjeta.',
                errorCode: 'INSUFFICIENT_FUNDS',
                metadata: ['result_code' => $resultCode, 'user_action' => 'verificar_saldo']
            ),

            '800.100.174' => PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'widget',
                errorMessage: 'Monto inválido. Contacte al comercio.',
                errorCode: 'INVALID_AMOUNT',
                metadata: ['result_code' => $resultCode, 'user_action' => 'contactar_comercio']
            ),

            '100.100.303' => PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'widget',
                errorMessage: 'Tarjeta expirada. Use una tarjeta vigente.',
                errorCode: 'CARD_EXPIRED',
                metadata: ['result_code' => $resultCode, 'user_action' => 'usar_tarjeta_vigente']
            ),

            '800.100.168' => PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'widget',
                errorMessage: 'Tarjeta restringida. Contacte a su banco.',
                errorCode: 'CARD_RESTRICTED',
                metadata: ['result_code' => $resultCode, 'user_action' => 'contactar_banco']
            ),

            // 🚫 ERRORES DE BANCO/CONEXIÓN
            '900.100.201' => PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'widget',
                errorMessage: 'Error de conexión con el Gateway. Intente nuevamente.',
                errorCode: 'GATEWAY_ERROR',
                metadata: ['result_code' => $resultCode, 'user_action' => 'reintentar']
            ),

            '900.100.300' => PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'widget',
                errorMessage: 'Desconexión durante la transacción. Verifique con su banco.',
                errorCode: 'CONNECTION_LOST',
                metadata: ['result_code' => $resultCode, 'user_action' => 'verificar_banco']
            ),

            // 🚫 ERRORES DE AUTENTICACIÓN 3DS
            '100.380.401' => PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'widget',
                errorMessage: 'Falla en autenticación 3D Secure.',
                errorCode: '3DS_AUTH_FAILED',
                metadata: ['result_code' => $resultCode, 'user_action' => 'reintentar_autenticacion']
            ),

            '100.380.501' => PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'widget',
                errorMessage: 'Tiempo agotado para código de verificación.',
                errorCode: '3DS_TIMEOUT',
                metadata: ['result_code' => $resultCode, 'user_action' => 'reintentar_rapido']
            ),

            // 🚫 ERRORES TÉCNICOS
            '800.900.300' => PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'widget',
                errorMessage: 'No se completó una transacción real. Use el botón de prueba.',
                errorCode: 'NO_REAL_TRANSACTION',
                metadata: ['result_code' => $resultCode, 'user_action' => 'usar_boton_prueba']
            ),

            '000.200.000' => PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'widget',
                errorMessage: 'Transacción pendiente de procesamiento.',
                errorCode: 'TRANSACTION_PENDING',
                metadata: ['result_code' => $resultCode, 'status' => 'pending']
            ),

            // 🚫 DEFAULT - ERROR GENERAL
            default => PaymentResult::failure(
                paymentMethod: 'datafast',
                validationType: 'widget',
                errorMessage: $originalMessage ?: "Error en procesamiento de pago (Código: {$resultCode})",
                errorCode: $resultCode ?: 'UNKNOWN_ERROR',
                metadata: ['result_code' => $resultCode, 'original_message' => $originalMessage]
            )
        };
    }

    public function getPaymentMethod(): string
    {
        return 'datafast';
    }

    public function getValidationType(): string
    {
        return 'widget';
    }

    private function validateRequiredFields(array $paymentData): void
    {
        $required = ['resource_path', 'transaction_id'];
        foreach ($required as $field) {
            if (empty($paymentData[$field])) {
                throw new Exception("Campo requerido faltante: {$field}");
            }
        }
    }

    private function extractAmount(array $result, array $paymentData): float
    {
        return (float) ($result['amount'] ?? $paymentData['calculated_total'] ?? $result['total'] ?? 0.0);
    }
}
