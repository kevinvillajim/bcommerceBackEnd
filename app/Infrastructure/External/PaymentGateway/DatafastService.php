<?php

namespace App\Infrastructure\External\PaymentGateway;

use App\Domain\Interfaces\PaymentGatewayInterface;
use App\Services\ConfigurationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DatafastService implements PaymentGatewayInterface
{
    private string $baseUrl;

    private string $entityId;

    private string $authorization;

    private bool $isProduction;

    private bool $usePhase2; // Control para cambiar entre Fase 1 y Fase 2

    private ConfigurationService $configService;

    public function __construct(ConfigurationService $configService)
    {
        $this->configService = $configService;
        $this->isProduction = config('app.env') === 'production';
        $this->usePhase2 = config('services.datafast.use_phase2', true); // Por defecto Fase 2

        if ($this->isProduction) {
            // Configuración de producción
            $this->baseUrl = config('services.datafast.production_url');
            $this->entityId = config('services.datafast.production.entity_id');
            $this->authorization = config('services.datafast.production.authorization');
        } else {
            // Configuración de pruebas
            $this->baseUrl = config('services.datafast.test_url');
            $this->entityId = config('services.datafast.test.entity_id');
            $this->authorization = config('services.datafast.test.authorization');
        }

        // Validar que las configuraciones críticas no sean null
        if (empty($this->baseUrl)) {
            throw new \Exception('Datafast Base URL no configurada. Verifique las variables de entorno DATAFAST_'.($this->isProduction ? 'PRODUCTION' : 'TEST').'_URL');
        }

        if (empty($this->entityId)) {
            throw new \Exception('Datafast Entity ID no configurado. Verifique las variables de entorno DATAFAST_'.($this->isProduction ? 'PRODUCTION' : 'TEST').'_ENTITY_ID');
        }

        if (empty($this->authorization)) {
            throw new \Exception('Datafast Authorization no configurada. Verifique las variables de entorno DATAFAST_'.($this->isProduction ? 'PRODUCTION' : 'TEST').'_AUTHORIZATION');
        }
    }

    /**
     * Crear un checkout de Datafast (Paso 1)
     */
    public function createCheckout(array $orderData): array
    {
        try {
            $url = $this->baseUrl.'/v1/checkouts';

            // Verificar que tenemos los datos mínimos requeridos
            if (! isset($orderData['amount']) || $orderData['amount'] <= 0) {
                throw new \Exception('Monto inválido para el checkout');
            }

            // Decidir qué datos usar según la configuración
            if ($this->usePhase2 && isset($orderData['customer'])) {
                $data = $this->buildPhase2Data($orderData);
                Log::info('Datafast: Usando Fase 2 (datos completos)');
            } else {
                $data = $this->buildPhase1Data($orderData);
                Log::info('Datafast: Usando Fase 1 (datos básicos)');
            }

            Log::info('🚀 Datafast: Iniciando creación de checkout', [
                'url' => $url,
                'entity_id' => $this->entityId,
                'amount' => $data['amount'] ?? null,
                'currency' => $data['currency'] ?? 'USD',
                'payment_type' => $data['paymentType'] ?? 'DB',
                'data_count' => count($data),
                'has_customer_data' => isset($orderData['customer']),
                'has_shipping_data' => isset($orderData['shipping']),
                'phase' => $this->usePhase2 ? 2 : 1,
            ]);

            // Log de datos sensibles solo en desarrollo
            if (config('app.debug')) {
                Log::debug('Datafast: Datos completos del checkout', $data);
            }

            $response = Http::timeout(30) // Timeout de 30 segundos
                ->withHeaders([
                    'Authorization' => $this->authorization,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ])
                ->asForm()
                ->post($url, $data);

            $responseData = $response->json();

            Log::info('✅ Datafast: Respuesta recibida de checkout', [
                'http_status' => $response->status(),
                'has_checkout_id' => isset($responseData['id']),
                'checkout_id' => $responseData['id'] ?? null,
                'result_code' => $responseData['result']['code'] ?? null,
                'result_description' => $responseData['result']['description'] ?? null,
                'response_successful' => $response->successful(),
                'raw_response' => config('app.debug') ? $responseData : null,
            ]);

            if ($response->successful() && isset($responseData['id'])) {
                // Construir la URL del widget con el checkoutId
                $widgetBaseUrl = rtrim($this->baseUrl, '/').'/v1/paymentWidgets.js';
                $widgetUrl = $widgetBaseUrl.'?checkoutId='.$responseData['id'];

                Log::info('✅ Checkout creado con éxito', [
                    'checkout_id' => $responseData['id'],
                    'widget_url' => $widgetUrl,
                    'base_url' => $this->baseUrl,
                ]);

                return [
                    'success' => true,
                    'checkout_id' => $responseData['id'],
                    'widget_url' => $widgetUrl,
                    'message' => 'Checkout creado exitosamente',
                ];
            }

            // Manejar errores específicos
            $errorMessage = 'Error al crear checkout';
            $errorCode = null;

            if (isset($responseData['result'])) {
                $errorMessage = $responseData['result']['description'] ?? $errorMessage;
                $errorCode = $responseData['result']['code'] ?? null;

                // Mensajes específicos para errores comunes
                if ($errorCode === '200.300.404') {
                    $errorMessage = 'Parámetro inválido o faltante. Verifique los datos enviados.';
                    Log::error('Datafast: Error 200.300.404 - Datos enviados:', $data);
                } elseif ($errorCode === '200.100.101') {
                    $errorMessage = 'Formato de solicitud inválido. Verifique la estructura de los datos.';
                }
            }

            Log::error('Datafast: Error en checkout', [
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'response_data' => $responseData,
            ]);

            return [
                'success' => false,
                'message' => $errorMessage,
                'error_code' => $errorCode,
                'full_response' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('Datafast: Error al crear checkout', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Error de comunicación con Datafast: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Construir datos para Fase 1 (parámetros básicos) - RECOMENDADO PARA EMPEZAR
     */
    private function buildPhase1Data(array $orderData): array
    {
        $data = [
            'entityId' => $this->entityId,
            'amount' => number_format($orderData['amount'], 2, '.', ''),
            'currency' => 'USD',
            'paymentType' => 'DB',
        ];

        Log::info('Datafast Fase 1: Datos básicos construidos', [
            'amount' => $data['amount'],
            'entity_id' => $data['entityId'],
        ]);

        return $data;
    }

    /**
     * Construir datos para Fase 2 (todos los campos requeridos) - SOLO CUANDO FASE 1 FUNCIONE
     */
    private function buildPhase2Data(array $orderData): array
    {
        // Validar estructura básica requerida para Fase 2
        $this->validatePhase2Structure($orderData);

        // Calcular impuestos dinámicamente (IVA Ecuador)
        $amount = $orderData['amount'];
        // ✅ COMPLETAMENTE DINÁMICO: Sin fallback hardcoded en gateway de pago
        $taxRatePercentage = $this->configService->getConfig('payment.taxRate');

        if ($taxRatePercentage === null) {
            throw new \Exception('Tax rate no configurado en BD - Requerido para procesamiento de pagos');
        }
        $taxRate = $taxRatePercentage / 100; // Convertir % a decimal
        $baseImponible = round($amount / (1 + $taxRate), 2); // Base sin IVA
        $taxAmount = round($amount - $baseImponible, 2); // IVA calculado
        $base0 = 0.00; // Productos exentos de impuestos

        // Verificar que la suma sea correcta
        $calculatedTotal = $base0 + $baseImponible + $taxAmount;
        if (abs($calculatedTotal - $amount) > 0.01) {
            Log::warning('Datafast: Discrepancia en cálculo de impuestos', [
                'amount' => $amount,
                'calculated_total' => $calculatedTotal,
                'base_0' => $base0,
                'base_imponible' => $baseImponible,
                'tax_amount' => $taxAmount,
            ]);
        }

        $customer = $orderData['customer'];

        $data = [
            'entityId' => $this->entityId,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => 'USD',
            'paymentType' => 'DB',

            // Datos del cliente - validados y con longitudes correctas
            'customer.givenName' => $this->sanitizeString($customer['given_name'] ?? 'Cliente', 48),
            'customer.middleName' => $this->sanitizeString($customer['middle_name'] ?? 'De', 50),
            'customer.surname' => $this->sanitizeString($customer['surname'] ?? 'Prueba', 48),
            'customer.ip' => $this->getValidIp($customer['ip'] ?? request()->ip()),
            'customer.merchantCustomerId' => $this->sanitizeString($customer['id'], 16),
            'merchantTransactionId' => $this->sanitizeString($orderData['transaction_id'] ?? ('TXN_'.time()), 255),
            'customer.email' => $this->validateEmail($customer['email'] ?? null),
            'customer.identificationDocType' => 'IDCARD',
            'customer.identificationDocId' => $this->formatDocumentId($customer['doc_id'] ?? null),
            'customer.phone' => $this->formatPhone($customer['phone'] ?? null),

            // Datos de envío y facturación - con longitudes validadas
            // ✅ CORREGIDO: Usar 'street' consistentemente con frontend, fallback a 'address' para retrocompatibilidad
            'shipping.street1' => $this->sanitizeString(
                $this->validateAddress($orderData['shipping']['street'] ?? $orderData['shipping']['address'] ?? null),
                100
            ),
            'shipping.country' => $this->formatCountryCode($orderData['shipping']['country'] ?? 'EC'),
            'billing.street1' => $this->sanitizeString(
                $this->validateAddress($orderData['billing']['street'] ?? $orderData['billing']['address'] ?? null),
                100
            ),
            'billing.country' => $this->formatCountryCode($orderData['billing']['country'] ?? 'EC'),

            // Modo de prueba (solo en ambiente de desarrollo, no en producción)
            'testMode' => 'EXTERNAL', // ✅ FASE 2 ACTIVADA - coincide con credenciales y .env

            // URL de resultado para redirección después del pago
            // ✅ CORREGIDO: Usar puerto 3000 como está en .env
            'shopperResultUrl' => env('FRONTEND_URL', 'http://localhost:3000').'/datafast-result',

            // Parámetros personalizados - Impuestos (formato exacto)
            'customParameters[SHOPPER_VAL_BASE0]' => number_format($base0, 2, '.', ''),
            'customParameters[SHOPPER_VAL_BASEIMP]' => number_format($baseImponible, 2, '.', ''),
            'customParameters[SHOPPER_VAL_IVA]' => number_format($taxAmount, 2, '.', ''),

            // Datos del comercio (MID/TID según configuración)
            'customParameters[SHOPPER_MID]' => $this->isProduction ?
                config('services.datafast.production.mid') :
                config('services.datafast.test.mid', '1000000505'),
            'customParameters[SHOPPER_TID]' => $this->isProduction ?
                config('services.datafast.production.tid') :
                config('services.datafast.test.tid', 'PD100406'),

            // Datos de identificación (valores fijos)
            'customParameters[SHOPPER_ECI]' => '0103910',
            'customParameters[SHOPPER_PSERV]' => '17913101',
            'customParameters[SHOPPER_VERSIONDF]' => '2',

            // Risk parameters (nombre del comercio)
            'risk.parameters[USER_DATA2]' => $this->sanitizeString(config('app.name', 'MiComercio'), 30),
        ];

        // Agregar datos del producto (primer item)
        if (isset($orderData['items']) && count($orderData['items']) > 0) {
            $firstItem = $orderData['items'][0];
            $data['cart.items[0].name'] = $this->sanitizeString($firstItem['name'] ?? 'Producto', 255);
            $data['cart.items[0].description'] = $this->sanitizeString($firstItem['description'] ?? 'Descripción', 255);
            $data['cart.items[0].price'] = number_format($firstItem['price'] ?? $amount, 2, '.', '');
            $data['cart.items[0].quantity'] = max(1, intval($firstItem['quantity'] ?? 1));
        } else {
            // Datos por defecto si no hay items
            $data['cart.items[0].name'] = 'Producto de prueba';
            $data['cart.items[0].description'] = 'Descripción del producto';
            $data['cart.items[0].price'] = number_format($amount, 2, '.', '');
            $data['cart.items[0].quantity'] = 1;
        }

        // Validar que los datos finales construidos sean correctos
        $this->validateBuiltPhase2Data($data);

        Log::info('Datafast Fase 2: Datos completos construidos', [
            'amount' => $data['amount'],
            'customer_name' => $data['customer.givenName'].' '.$data['customer.surname'],
            'doc_id' => $data['customer.identificationDocId'],
            'transaction_id' => $data['merchantTransactionId'],
        ]);

        return $data;
    }

    /**
     * Validar estructura básica requerida para Fase 2 (sin validar contenido)
     */
    private function validatePhase2Structure(array $orderData): void
    {
        $requiredFields = ['customer', 'shipping', 'transaction_id'];

        foreach ($requiredFields as $field) {
            if (! isset($orderData[$field])) {
                throw new \Exception("Campo requerido faltante para Fase 2: {$field}");
            }
        }

        // Solo validar que customer sea un array, no su contenido
        if (! is_array($orderData['customer'])) {
            throw new \Exception('El campo customer debe ser un array');
        }
    }

    /**
     * Validar datos finales construidos para Fase 2 (con fallbacks aplicados)
     */
    private function validateBuiltPhase2Data(array $data): void
    {
        $requiredDataFields = [
            'customer.givenName',
            'customer.surname',
            'customer.email',
            'customer.identificationDocId',
            'entityId',
            'amount',
        ];

        foreach ($requiredDataFields as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Campo de datos construidos faltante: {$field}");
            }
        }
    }

    /**
     * Sanitizar string según longitud máxima
     */
    private function sanitizeString(string $value, int $maxLength): string
    {
        // Remover caracteres especiales problemáticos
        $value = str_replace(['&', '<', '>', '"', "'"], '', $value);
        $value = trim($value);

        // Truncar a longitud máxima
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    /**
     * Formatear ID de documento a 10 dígitos
     */
    private function formatDocumentId(?string $docId): string
    {
        if (empty($docId)) {
            throw new \Exception('Cédula del cliente es requerida - no se permiten datos falsos');
        }

        // Remover todo lo que no sea dígito
        $docId = preg_replace('/\D/', '', $docId);

        // ✅ CORRECCIÓN: Auto-extraer cédula desde RUC si es necesario
        if (strlen($docId) === 13) {
            // Es RUC, extraer cédula (primeros 10 dígitos)
            $extractedCedula = substr($docId, 0, 10);
            \Log::info('🔧 DatafastService: Auto-extracción de cédula desde RUC', [
                'ruc_original' => $docId,
                'cedula_extraida' => $extractedCedula
            ]);
            return $extractedCedula;
        }

        if (strlen($docId) !== 10) {
            throw new \Exception('Cédula del cliente debe tener exactamente 10 dígitos: ' . $docId);
        }

        return $docId;
    }

    /**
     * Formatear teléfono
     */
    private function formatPhone(?string $phone): string
    {
        if (empty($phone)) {
            throw new \Exception('Teléfono del cliente es requerido - no se permiten datos falsos');
        }

        // Remover espacios y caracteres especiales, mantener solo números y +
        $phone = preg_replace('/[^\d+]/', '', $phone);

        if (strlen($phone) < 7) {
            throw new \Exception('Teléfono del cliente inválido (muy corto): ' . $phone);
        }

        return substr($phone, 0, 25);
    }

    /**
     * Validar email
     */
    private function validateEmail(?string $email): string
    {
        if (empty($email)) {
            throw new \Exception('Email del cliente es requerido - no se permiten datos falsos');
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('Email del cliente inválido: ' . $email);
        }

        return substr($email, 0, 128);
    }

    /**
     * Formatear código de país a 2 caracteres
     */
    private function formatCountryCode(string $country): string
    {
        $country = strtoupper(trim($country));

        // Validar que sean exactamente 2 caracteres alfabéticos
        if (strlen($country) === 2 && preg_match('/^[A-Z]{2}$/', $country)) {
            return $country;
        }

        return 'EC'; // País por defecto
    }

    /**
     * Validar dirección requerida
     */
    private function validateAddress(?string $address): string
    {
        if (empty($address)) {
            throw new \Exception('Dirección del cliente es requerida - no se permiten datos falsos');
        }

        return trim($address);
    }

    /**
     * Obtener IP válida
     */
    private function getValidIp(string $ip): string
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return '127.0.0.1'; // IP por defecto
        }

        return $ip;
    }

    /**
     * Verificar el estado de una transacción (Paso 3)
     */
    public function verifyPayment(string $resourcePath): array
    {
        try {
            // Limpiar el resourcePath si viene con el dominio
            if (strpos($resourcePath, 'http') === 0) {
                $parsedUrl = parse_url($resourcePath);
                $resourcePath = $parsedUrl['path'] ?? $resourcePath;
            }

            // Asegurar que el resourcePath comience con /
            if (strpos($resourcePath, '/') !== 0) {
                $resourcePath = '/'.$resourcePath;
            }

            $url = $this->baseUrl.$resourcePath.'?entityId='.$this->entityId;

            Log::info('Datafast: Verificando pago', [
                'resource_path' => $resourcePath,
                'url' => $url,
            ]);

            $response = Http::withHeaders([
                'Authorization' => $this->authorization,
            ])->get($url);

            $responseData = $response->json();

            Log::info('🔍 Datafast: Respuesta de verificación de pago', [
                'http_status' => $response->status(),
                'result_code' => $responseData['result']['code'] ?? 'no_code',
                'result_description' => $responseData['result']['description'] ?? null,
                'payment_brand' => $responseData['paymentBrand'] ?? null,
                'payment_type' => $responseData['paymentType'] ?? null,
                'amount' => $responseData['amount'] ?? null,
                'currency' => $responseData['currency'] ?? null,
                'has_payment_id' => isset($responseData['id']),
                'payment_id' => $responseData['id'] ?? null,
                'response_successful' => $response->successful(),
                'raw_response' => config('app.debug') ? $responseData : null,
            ]);

            // Manejar errores de autorización comunes en Fase 1
            if (! $response->successful()) {
                $statusCode = $response->status();
                $resultCode = $responseData['result']['code'] ?? null;
                $description = $responseData['result']['description'] ?? 'Error desconocido';

                Log::warning('Datafast: Error HTTP en verificación', [
                    'status' => $statusCode,
                    'result_code' => $resultCode,
                    'description' => $description,
                    'full_response' => $responseData,
                ]);

                // Error 403 con código 800.900.300 es común en Fase 1
                // cuando no hay una transacción real completada
                if ($statusCode === 403 && $resultCode === '800.900.300') {
                    return [
                        'success' => false,
                        'message' => 'No se encontró una transacción completada para este checkout. En modo de prueba (Fase 1), esto significa que no se realizó un pago real.',
                        'result_code' => $resultCode,
                        'phase_1_note' => 'Este error es normal en Fase 1 si no se completó un pago con tarjeta real.',
                        'transaction_data' => $responseData,
                    ];
                }

                // Otros errores HTTP
                return [
                    'success' => false,
                    'message' => "Error HTTP {$statusCode}: {$description}",
                    'result_code' => $resultCode,
                    'transaction_data' => $responseData,
                ];
            }

            // Respuesta exitosa - verificar códigos de resultado
            if (isset($responseData['result'])) {
                $resultCode = $responseData['result']['code'] ?? '';

                // Códigos de éxito según la documentación
                $successCodes = [
                    '000.000.000', // Transaction succeeded (Producción)
                    '000.100.110', // Request successfully processed in 'Merchant in Integrator Test Mode' (Pruebas Fase 1)
                    '000.100.112',  // Request successfully processed in 'Merchant in Connector Test Mode' (Pruebas Fase 2)
                ];

                $isSuccessful = in_array($resultCode, $successCodes);

                if ($isSuccessful) {
                    Log::info('Datafast: Pago verificado exitosamente', [
                        'result_code' => $resultCode,
                        'payment_id' => $responseData['id'] ?? null,
                    ]);

                    return [
                        'success' => true,
                        'payment_id' => $responseData['id'] ?? null,
                        'status' => 'completed',
                        'result_code' => $resultCode,
                        'message' => $responseData['result']['description'] ?? 'Pago completado exitosamente',
                        'transaction_data' => $responseData,
                        'amount' => $responseData['amount'] ?? null,
                        'currency' => $responseData['currency'] ?? null,
                    ];
                } else {
                    // Verificar si es un código de checkout creado pero sin transacción
                    if ($resultCode === '000.200.100') {
                        return [
                            'success' => false,
                            'message' => 'Checkout creado exitosamente pero no se ha completado ninguna transacción',
                            'result_code' => $resultCode,
                            'transaction_data' => $responseData,
                        ];
                    }

                    return [
                        'success' => false,
                        'message' => $responseData['result']['description'] ?? 'Transacción no exitosa',
                        'result_code' => $resultCode,
                        'transaction_data' => $responseData,
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'Error al verificar el pago - respuesta inválida (sin campo result)',
                'error_data' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('Datafast: Error al verificar pago', [
                'error' => $e->getMessage(),
                'resource_path' => $resourcePath,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Error de comunicación al verificar pago: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Implementación de la interfaz PaymentGatewayInterface
     */
    public function processPayment(array $paymentData, float $amount): array
    {
        return $this->createCheckout([
            'amount' => $amount,
            'customer' => $paymentData['customer'] ?? [],
            'shipping' => $paymentData['shipping'] ?? [],
            'billing' => $paymentData['billing'] ?? [],
            'items' => $paymentData['items'] ?? [],
            'transaction_id' => 'TXN_'.time().'_'.uniqid(),
        ]);
    }

    public function refundPayment(string $paymentId, ?float $amount = null): array
    {
        return [
            'success' => false,
            'message' => 'Reembolsos no implementados aún',
        ];
    }

    public function checkPaymentStatus(string $paymentId): array
    {
        return [
            'success' => false,
            'message' => 'Usar verifyPayment con resourcePath',
        ];
    }

    /**
     * Cambiar entre Fase 1 y Fase 2 dinámicamente
     */
    public function setPhase2(bool $usePhase2): void
    {
        $this->usePhase2 = $usePhase2;
    }
}
