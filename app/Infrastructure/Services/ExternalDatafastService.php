<?php

namespace App\Infrastructure\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalDatafastService
{
    private string $baseUrl;
    private string $entityId;
    private string $authorization;
    private bool $isProduction;

    public function __construct()
    {
        $this->isProduction = config('app.env') === 'production';

        if ($this->isProduction) {
            $this->baseUrl = config('services.datafast.production_url');
            $this->entityId = config('services.datafast.production.entity_id');
            $this->authorization = config('services.datafast.production.authorization');
        } else {
            $this->baseUrl = config('services.datafast.test_url');
            $this->entityId = config('services.datafast.test.entity_id');
            $this->authorization = config('services.datafast.test.authorization');
        }

        Log::info('ExternalDatafastService initialized', [
            'base_url' => $this->baseUrl,
            'environment' => $this->isProduction ? 'production' : 'test',
        ]);
    }

    /**
     * Create checkout for external payments - simplified version
     */
    public function createCheckout(array $orderData): array
    {
        try {
            $url = $this->baseUrl . '/v1/checkouts';

            // USAR BUILDPHASE2DATA COMO SISTEMA PRINCIPAL
            $data = $this->buildPhase2Data($orderData);

            Log::info('ExternalDatafastService: Creating checkout with Fase 2 data', [
                'url' => $url,
                'amount' => $data['amount'],
                'customer_name' => $data['customer.givenName'] . ' ' . $data['customer.surname'],
                'doc_id' => $data['customer.identificationDocId'],
                'has_merchant_params' => isset($data['customParameters[SHOPPER_MID]']),
                'has_tax_params' => isset($data['customParameters[SHOPPER_VAL_IVA]']),
                'use_phase2' => true,
            ]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => $this->authorization,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ])
                ->asForm()
                ->post($url, $data);

            $responseData = $response->json();

            Log::info('ExternalDatafastService: Response received', [
                'http_status' => $response->status(),
                'has_checkout_id' => isset($responseData['id']),
                'checkout_id' => $responseData['id'] ?? null,
            ]);

            if ($response->successful() && isset($responseData['id'])) {
                $widgetBaseUrl = rtrim($this->baseUrl, '/') . '/v1/paymentWidgets.js';
                $widgetUrl = $widgetBaseUrl . '?checkoutId=' . $responseData['id'];

                return [
                    'success' => true,
                    'checkout_id' => $responseData['id'],
                    'widget_url' => $widgetUrl,
                    'message' => 'Checkout created successfully',
                ];
            }

            $errorMessage = $responseData['result']['description'] ?? 'Error creating checkout';

            return [
                'success' => false,
                'message' => $errorMessage,
                'full_response' => $responseData,
            ];

        } catch (\Exception $e) {
            Log::error('ExternalDatafastService: Error creating checkout', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error communicating with Datafast: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verify payment for external payments
     */
    public function verifyPayment(string $resourcePath): array
    {
        try {
            // Clean resourcePath
            if (strpos($resourcePath, 'http') === 0) {
                $parsedUrl = parse_url($resourcePath);
                $resourcePath = $parsedUrl['path'] ?? $resourcePath;
            }

            if (strpos($resourcePath, '/') !== 0) {
                $resourcePath = '/' . $resourcePath;
            }

            $url = $this->baseUrl . $resourcePath . '?entityId=' . $this->entityId;

            Log::info('ExternalDatafastService: Verifying payment', [
                'resource_path' => $resourcePath,
                'url' => $url,
            ]);

            $response = Http::withHeaders([
                'Authorization' => $this->authorization,
            ])->get($url);

            $responseData = $response->json();

            Log::info('ExternalDatafastService: Verification response', [
                'http_status' => $response->status(),
                'response_data' => $responseData,
            ]);

            if ($response->successful() && isset($responseData['result'])) {
                $resultCode = $responseData['result']['code'] ?? '';

                // Success codes
                $successCodes = [
                    '000.000.000', // Production success
                    '000.100.110', // Test mode success
                    '000.100.112', // Test mode success
                ];

                if (in_array($resultCode, $successCodes)) {
                    return [
                        'success' => true,
                        'payment_id' => $responseData['id'] ?? null,
                        'status' => 'completed',
                        'result_code' => $resultCode,
                        'message' => 'Payment completed successfully',
                        'transaction_data' => $responseData,
                    ];
                }
            }

            Log::info('ExternalDatafastService: Payment verification failed', [
                'response_data' => $responseData,
                'result_code' => $responseData['result']['code'] ?? 'unknown',
            ]);

            return [
                'success' => false,
                'message' => 'Payment verification failed',
                'transaction_data' => $responseData,
            ];

        } catch (\Exception $e) {
            Log::error('ExternalDatafastService: Error verifying payment', [
                'error' => $e->getMessage(),
                'resource_path' => $resourcePath,
            ]);

            return [
                'success' => false,
                'message' => 'Error verifying payment: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Construir datos para Datafast Fase 2 - External Payments
     * Adaptado para valores fijos que YA incluyen IVA
     */
    private function buildPhase2Data(array $orderData): array
    {
        // Validar estructura básica requerida para Fase 2
        $this->validatePhase2Structure($orderData);

        // Para External Payments: el amount YA incluye IVA, solo desglosar
        $amount = $orderData['amount'];
        $taxRate = 0.15; // 15% IVA Ecuador (fijo para desglose)
        $baseImponible = round($amount / (1 + $taxRate), 2); // Base sin IVA
        $taxAmount = round($amount - $baseImponible, 2); // IVA calculado
        $base0 = 0.00; // Productos exentos de impuestos

        $customer = $orderData['customer'];

        $data = [
            'entityId' => $this->entityId,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => 'USD',
            'paymentType' => 'DB',

            // Datos del cliente - usando métodos de formato
            'customer.givenName' => $this->sanitizeString($customer['given_name'], 48),
            'customer.middleName' => $this->sanitizeString($customer['middle_name'] ?? '', 50),
            'customer.surname' => $this->sanitizeString($customer['surname'], 48),
            'customer.ip' => $this->getValidIp($customer['ip'] ?? request()->ip()),
            'customer.merchantCustomerId' => $this->sanitizeString($customer['id'], 16),
            'merchantTransactionId' => $this->sanitizeString($orderData['transaction_id'] ?? ('TXN_'.time()), 255),
            'customer.email' => $this->validateEmail($customer['email']),
            'customer.identificationDocType' => 'IDCARD',
            'customer.identificationDocId' => $this->formatDocumentId($customer['doc_id']),
            'customer.phone' => $this->formatPhone($customer['phone']),

            // Datos de envío y facturación
            'shipping.street1' => $this->sanitizeString(
                $this->validateAddress($orderData['shipping']['street'] ?? $orderData['shipping']['address'] ?? null),
                100
            ),
            'shipping.city' => $this->sanitizeString($orderData['shipping']['city'], 50),
            'shipping.country' => $this->formatCountryCode($orderData['shipping']['country'] ?? 'EC'),
            'billing.street1' => $this->sanitizeString(
                $this->validateAddress($orderData['billing']['street'] ?? $orderData['billing']['address'] ?? null),
                100
            ),
            'billing.city' => $this->sanitizeString($orderData['billing']['city'], 50),
            'billing.country' => $this->formatCountryCode($orderData['billing']['country'] ?? 'EC'),

            // Modo de prueba Fase 2
            'testMode' => 'EXTERNAL',

            // URL de resultado para External Payments
            'shopperResultUrl' => env('FRONTEND_URL', 'http://localhost:3000') . '/pay/' . $orderData['link_code'] . '/result',

            // Parámetros de impuestos
            'customParameters[SHOPPER_VAL_BASE0]' => number_format($base0, 2, '.', ''),
            'customParameters[SHOPPER_VAL_BASEIMP]' => number_format($baseImponible, 2, '.', ''),
            'customParameters[SHOPPER_VAL_IVA]' => number_format($taxAmount, 2, '.', ''),

            // Datos del comercio
            'customParameters[SHOPPER_MID]' => $this->isProduction ?
                config('services.datafast.production.mid') :
                config('services.datafast.test.mid', '1000000505'),
            'customParameters[SHOPPER_TID]' => $this->isProduction ?
                config('services.datafast.production.tid') :
                config('services.datafast.test.tid', 'PD100406'),

            // Datos de identificación
            'customParameters[SHOPPER_ECI]' => '0103910',
            'customParameters[SHOPPER_PSERV]' => '17913101',
            'customParameters[SHOPPER_VERSIONDF]' => '2',

            // Risk parameters (nombre del comercio)
            'risk.parameters[USER_DATA2]' => $this->sanitizeString(config('app.name', 'MiComercio'), 30),

            // Datos del producto para External Payments - sanitizado como sistema principal
            'cart.items[0].name' => $this->sanitizeString($orderData['description'] ?? 'Pago Externo', 255),
            'cart.items[0].description' => $this->sanitizeString($orderData['description'] ?? 'Pago mediante link externo', 255),
            'cart.items[0].price' => number_format($amount, 2, '.', ''),
            'cart.items[0].quantity' => max(1, intval(1)),
        ];

        // Validar datos finales
        $this->validateBuiltPhase2Data($data);

        Log::info('External Datafast Fase 2: Datos completos construidos', [
            'amount' => $data['amount'],
            'customer_name' => $data['customer.givenName'].' '.$data['customer.surname'],
            'doc_id' => $data['customer.identificationDocId'],
            'base_imponible' => $baseImponible,
            'tax_amount' => $taxAmount,
        ]);

        return $data;
    }

    /**
     * Validar estructura básica requerida para Fase 2
     */
    private function validatePhase2Structure(array $orderData): void
    {
        $requiredFields = ['customer', 'shipping', 'billing'];

        foreach ($requiredFields as $field) {
            if (!isset($orderData[$field])) {
                throw new \Exception("Campo requerido faltante para Fase 2: {$field}");
            }
        }

        if (!is_array($orderData['customer'])) {
            throw new \Exception('El campo customer debe ser un array');
        }
    }

    /**
     * Validar datos finales construidos para Fase 2
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
        return substr($value, 0, $maxLength);
    }

    /**
     * Formatear documento de identidad (cédula)
     */
    private function formatDocumentId(string $docId): string
    {
        if (empty($docId)) {
            throw new \Exception('Cédula del cliente es requerida');
        }

        // Remover todo lo que no sea dígito
        $docId = preg_replace('/\D/', '', $docId);

        if (strlen($docId) !== 10) {
            throw new \Exception('Cédula del cliente debe tener exactamente 10 dígitos: ' . $docId);
        }

        return $docId;
    }

    /**
     * Formatear teléfono
     */
    private function formatPhone(string $phone): string
    {
        if (empty($phone)) {
            throw new \Exception('Teléfono del cliente es requerido');
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
}