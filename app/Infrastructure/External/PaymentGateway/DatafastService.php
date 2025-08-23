<?php

namespace App\Infrastructure\External\PaymentGateway;

use App\Domain\Interfaces\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DatafastService implements PaymentGatewayInterface
{
    private string $baseUrl;

    private string $entityId;

    private string $authorization;

    private bool $isProduction;

    private bool $usePhase2; // Control para cambiar entre Fase 1 y Fase 2

    public function __construct()
    {
        $this->isProduction = config('app.env') === 'production';
        $this->usePhase2 = config('services.datafast.use_phase2', false); // Por defecto Fase 1

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

            Log::info('Datafast: Creando checkout', [
                'url' => $url,
                'entity_id' => $this->entityId,
                'amount' => $data['amount'] ?? null,
                'data_count' => count($data),
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

            Log::info('Datafast: Respuesta de checkout', [
                'status' => $response->status(),
                'has_id' => isset($responseData['id']),
                'result_code' => $responseData['result']['code'] ?? null,
            ]);

            if ($response->successful() && isset($responseData['id'])) {
                return [
                    'success' => true,
                    'checkout_id' => $responseData['id'],
                    'widget_url' => $this->baseUrl.'/v1/paymentWidgets.js?checkoutId='.$responseData['id'],
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
        // Validar datos requeridos para Fase 2
        $this->validatePhase2Data($orderData);

        // Calcular impuestos (ejemplo: IVA 15%)
        $amount = $orderData['amount'];
        $taxRate = 0.15; // 15% IVA
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
            'customer.merchantCustomerId' => $this->sanitizeString($customer['id'] ?? '1', 16),
            'merchantTransactionId' => $this->sanitizeString($orderData['transaction_id'] ?? ('TXN_'.time()), 255),
            'customer.email' => $this->validateEmail($customer['email'] ?? 'test@example.com'),
            'customer.identificationDocType' => 'IDCARD',
            'customer.identificationDocId' => $this->formatDocumentId($customer['doc_id'] ?? '1234567890'),
            'customer.phone' => $this->formatPhone($customer['phone'] ?? '0999999999'),

            // Datos de envío y facturación - con longitudes validadas
            'shipping.street1' => $this->sanitizeString($orderData['shipping']['address'] ?? 'Dirección de prueba', 100),
            'shipping.country' => $this->formatCountryCode($orderData['shipping']['country'] ?? 'EC'),
            'billing.street1' => $this->sanitizeString($orderData['billing']['address'] ?? 'Dirección de prueba', 100),
            'billing.country' => $this->formatCountryCode($orderData['billing']['country'] ?? 'EC'),

            // Modo de prueba (solo en ambiente de pruebas)
            'testMode' => 'EXTERNAL',

            // URL de resultado para redirección después del pago
            'shopperResultUrl' => config('app.url').'/datafast-result',

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

        Log::info('Datafast Fase 2: Datos completos construidos', [
            'amount' => $data['amount'],
            'customer_name' => $data['customer.givenName'].' '.$data['customer.surname'],
            'doc_id' => $data['customer.identificationDocId'],
            'transaction_id' => $data['merchantTransactionId'],
        ]);

        return $data;
    }

    /**
     * Validar datos requeridos para Fase 2
     */
    private function validatePhase2Data(array $orderData): void
    {
        $requiredFields = ['customer', 'shipping', 'transaction_id'];

        foreach ($requiredFields as $field) {
            if (! isset($orderData[$field])) {
                throw new \Exception("Campo requerido faltante para Fase 2: {$field}");
            }
        }

        $customerFields = ['given_name', 'surname', 'email'];
        foreach ($customerFields as $field) {
            if (empty($orderData['customer'][$field])) {
                throw new \Exception("Campo de cliente requerido faltante: {$field}");
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
    private function formatDocumentId(string $docId): string
    {
        // Remover todo lo que no sea dígito
        $docId = preg_replace('/\D/', '', $docId);

        // Asegurar que tenga exactamente 10 dígitos
        return str_pad(substr($docId, 0, 10), 10, '0', STR_PAD_LEFT);
    }

    /**
     * Formatear teléfono
     */
    private function formatPhone(string $phone): string
    {
        // Remover espacios y caracteres especiales, mantener solo números y +
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // Asegurar longitud entre 7 y 25 caracteres
        if (strlen($phone) < 7) {
            return '0999999999'; // Teléfono por defecto
        }

        return substr($phone, 0, 25);
    }

    /**
     * Validar email
     */
    private function validateEmail(string $email): string
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'test@example.com'; // Email por defecto
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

            Log::info('Datafast: Respuesta de verificación', [
                'status' => $response->status(),
                'result_code' => $responseData['result']['code'] ?? 'no_code',
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
