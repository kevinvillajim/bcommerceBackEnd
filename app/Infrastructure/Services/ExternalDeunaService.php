<?php

namespace App\Infrastructure\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalDeunaService
{
    private string $apiUrl;
    private string $apiKey;
    private string $apiSecret;
    private string $environment;
    private string $pointOfSale;
    private int $timeout;

    public function __construct()
    {
        $this->apiUrl = config('deuna.api_url');
        $this->apiKey = config('deuna.api_key');
        $this->apiSecret = config('deuna.api_secret');
        $this->environment = config('deuna.environment');
        $this->pointOfSale = config('deuna.point_of_sale');
        $this->timeout = config('deuna.timeout', 30);

        Log::info('ExternalDeunaService initialized', [
            'api_url' => $this->apiUrl,
            'environment' => $this->environment,
            'point_of_sale' => $this->pointOfSale,
        ]);
    }

    /**
     * Create order for external payments - simplified version
     */
    public function createOrder(array $orderData): array
    {
        try {
            // Simplified validation for external payments
            $this->validateExternalOrderData($orderData);

            // Generate shorter transaction reference (max 20 chars)
            $shortRef = 'EXT' . substr(str_replace(['_', '-'], '', $orderData['order_id']), -16);

            $payload = [
                'pointOfSale' => $this->pointOfSale,
                'amount' => (float) $orderData['amount'],
                'detail' => $orderData['description'] ?? 'Pago externo',
                'internalTransactionReference' => $shortRef,
                'qrType' => 'dynamic', // Required field
                'format' => '2', // QR + Link
            ];

            Log::info('ExternalDeunaService: Creating external order', [
                'payload' => $payload,
                'order_id' => $orderData['order_id'],
                'customer_data' => json_encode($orderData['customer'] ?? []),
            ]);

            $response = $this->makeApiRequest('POST', '/merchant/v1/payment/request', $payload);

            Log::info('ExternalDeunaService: Order created successfully', [
                'transaction_id' => $response['transactionId'] ?? null,
                'order_id' => $orderData['order_id'],
            ]);

            return [
                'success' => true,
                'order_id' => $response['transactionId'] ?? null,
                'checkout_url' => $response['deeplink'] ?? null,
                'qr_code' => $response['qr'] ?? null,
                'raw_response' => $response,
            ];

        } catch (Exception $e) {
            Log::error('ExternalDeunaService: Error creating order', [
                'error' => $e->getMessage(),
                'order_id' => $orderData['order_id'] ?? null,
                'payload_sent' => json_encode($payload ?? []),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Make API request to DeUna
     */
    private function makeApiRequest(string $method, string $endpoint, array $payload = []): array
    {
        $url = $this->apiUrl . $endpoint;

        $headers = [
            'x-api-key' => $this->apiKey,
            'x-api-secret' => $this->apiSecret,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $response = Http::withHeaders($headers)
            ->timeout($this->timeout)
            ->post($url, $payload);

        Log::info('ExternalDeunaService: API response received', [
            'status' => $response->status(),
            'body' => $response->body(),
            'headers' => $response->headers(),
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        $errorBody = $response->json();

        // Safe error message extraction for Deuna API structure
        $errorMessage = 'Unknown API error';

        // Deuna specific error structure
        if (isset($errorBody['message']['response']['message']) && is_array($errorBody['message']['response']['message'])) {
            $errorMessage = implode(', ', $errorBody['message']['response']['message']);
        } elseif (isset($errorBody['message']) && is_string($errorBody['message'])) {
            $errorMessage = $errorBody['message'];
        } elseif (isset($errorBody['error']) && is_string($errorBody['error'])) {
            $errorMessage = $errorBody['error'];
        } elseif (isset($errorBody['error']) && is_array($errorBody['error'])) {
            $errorMessage = 'API Error: ' . json_encode($errorBody['error']);
        }

        throw new Exception('DeUna API Error: ' . $errorMessage, $response->status());
    }

    /**
     * Simple validation for external orders
     */
    private function validateExternalOrderData(array $orderData): void
    {
        $required = ['order_id', 'amount', 'customer'];

        foreach ($required as $field) {
            if (!isset($orderData[$field]) || empty($orderData[$field])) {
                throw new Exception("Required field '{$field}' is missing");
            }
        }

        if (!is_numeric($orderData['amount']) || $orderData['amount'] <= 0) {
            throw new Exception('Amount must be a positive number');
        }

        // Basic customer validation
        if (!isset($orderData['customer']['email'])) {
            throw new Exception('Customer email is required');
        }

        if (!filter_var($orderData['customer']['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Valid customer email is required');
        }
    }
}