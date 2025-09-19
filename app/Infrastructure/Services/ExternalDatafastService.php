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

            // Simple validation
            if (!isset($orderData['amount']) || $orderData['amount'] <= 0) {
                throw new \Exception('Invalid amount for checkout');
            }

            // Calculate tax amounts for external payments (amount already includes IVA)
            $amount = $orderData['amount'];
            // Para pagos externos: el valor ya viene CON IVA incluido
            // Calculamos la desglose para Datafast pero no modificamos el total
            $taxRate = 0.15; // 15% IVA Ecuador (fijo para desglose)
            $baseImponible = round($amount / (1 + $taxRate), 2); // Base sin IVA
            $taxAmount = round($amount - $baseImponible, 2); // IVA calculado
            $base0 = 0.00; // Productos exentos de impuestos

            // Build data for external payments with all required parameters
            $data = [
                'entityId' => $this->entityId,
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => 'USD',
                'paymentType' => 'DB',
                'customer.givenName' => $orderData['customer']['given_name'] ?? 'Cliente',
                'customer.surname' => $orderData['customer']['surname'] ?? 'Externo',
                'customer.email' => $orderData['customer']['email'],
                'customer.phone' => $orderData['customer']['phone'],
                'shipping.street1' => $orderData['shipping']['street'],
                'shipping.city' => $orderData['shipping']['city'],
                'shipping.country' => 'EC',
                'billing.street1' => $orderData['billing']['street'],
                'billing.city' => $orderData['billing']['city'],
                'billing.country' => 'EC',

                // URL de resultado corregida - frontend puerto 3000
                'shopperResultUrl' => env('FRONTEND_URL', 'http://localhost:3000') . '/pay/' . $orderData['link_code'] . '/result',

                // Parámetros críticos del comercio (igual que servicio original)
                'customParameters[SHOPPER_MID]' => $this->isProduction ?
                    config('services.datafast.production.mid') :
                    config('services.datafast.test.mid', '1000000505'),
                'customParameters[SHOPPER_TID]' => $this->isProduction ?
                    config('services.datafast.production.tid') :
                    config('services.datafast.test.tid', 'PD100406'),
                'customParameters[SHOPPER_ECI]' => '0103910',
                'customParameters[SHOPPER_PSERV]' => '17913101',
                'customParameters[SHOPPER_VERSIONDF]' => '2',

                // Parámetros de impuestos obligatorios
                'customParameters[SHOPPER_VAL_BASE0]' => number_format($base0, 2, '.', ''),
                'customParameters[SHOPPER_VAL_BASEIMP]' => number_format($baseImponible, 2, '.', ''),
                'customParameters[SHOPPER_VAL_IVA]' => number_format($taxAmount, 2, '.', ''),
            ];

            Log::info('ExternalDatafastService: Creating checkout with external payment parameters', [
                'url' => $url,
                'amount_with_iva_included' => $data['amount'],
                'customer_email' => $data['customer.email'],
                'shopper_result_url' => $data['shopperResultUrl'],
                'has_merchant_params' => isset($data['customParameters[SHOPPER_MID]']),
                'has_tax_params' => isset($data['customParameters[SHOPPER_VAL_IVA]']),
                'base_imponible_calculated' => $baseImponible,
                'tax_amount_calculated' => $taxAmount,
                'external_payment_mode' => 'amount_includes_iva',
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
}