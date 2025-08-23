<?php

namespace App\Infrastructure\Services;

use App\Domain\Interfaces\DeunaServiceInterface;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeunaService implements DeunaServiceInterface
{
    private string $apiUrl;

    private string $apiKey;

    private string $apiSecret;

    private string $webhookSecret;

    private string $environment;

    private string $pointOfSale;

    private int $timeout;

    private int $retryAttempts;

    private int $retryDelay;

    public function __construct()
    {
        // Validate configuration only when service is actually instantiated
        \App\Providers\DeunaServiceProvider::validateConfiguration();

        $this->apiUrl = config('deuna.api_url');
        $this->apiKey = config('deuna.api_key');
        $this->apiSecret = config('deuna.api_secret');
        $this->webhookSecret = config('deuna.webhook_secret');
        $this->environment = config('deuna.environment');
        $this->pointOfSale = config('deuna.point_of_sale');
        $this->timeout = config('deuna.timeout', 30);
        $this->retryAttempts = config('deuna.retry_attempts', 3);
        $this->retryDelay = config('deuna.retry_delay', 1000);
    }

    /**
     * Create a new payment with DeUna
     * Based on Official DeUna API V2 Documentation
     */
    public function createPayment(array $paymentData): array
    {
        try {
            $this->validatePaymentData($paymentData);

            // DeUna API V2 payload structure - EXACTLY as per documentation
            $payload = [
                'pointOfSale' => $this->pointOfSale,
                'qrType' => $paymentData['qr_type'] ?? 'dynamic', // static or dynamic
                'amount' => (float) $paymentData['amount'],
                'detail' => $this->buildPaymentDetail($paymentData),
                'internalTransactionReference' => $this->generateShortReference($paymentData['order_id']),
                'format' => $paymentData['format'] ?? '2', // QR + Link by default
            ];

            Log::info('DeUna: Creating payment request', [
                'payload' => $payload,
                'order_id' => $paymentData['order_id'],
            ]);

            $response = $this->makeApiRequest('POST', '/merchant/v1/payment/request', $payload);

            Log::info('DeUna: Payment created successfully', [
                'transaction_id' => $response['transactionId'] ?? null,
                'status' => $response['status'] ?? null,
                'order_id' => $paymentData['order_id'],
            ]);

            // Transform DeUna response to our standard format
            return $this->transformPaymentResponse($response, $paymentData);

        } catch (Exception $e) {
            Log::error('DeUna: Error creating payment', [
                'error' => $e->getMessage(),
                'order_id' => $paymentData['order_id'] ?? null,
            ]);

            throw new Exception('Error creating DeUna payment: '.$e->getMessage());
        }
    }

    /**
     * Get payment status from DeUna
     * Based on Official DeUna API Documentation
     */
    public function getPaymentStatus(string $paymentId): array
    {
        try {
            Log::info('DeUna: Getting payment status', ['payment_id' => $paymentId]);

            // DeUna API payload structure - EXACTLY as per documentation
            $payload = [
                'idTransacionReference' => $paymentId,
                'idType' => '0', // 0 for transactionId, 1 for internalTransactionReference
            ];

            $response = $this->makeApiRequest('POST', '/merchant/v1/payment/info', $payload);

            Log::info('DeUna: Payment status retrieved', [
                'payment_id' => $paymentId,
                'status' => $response['status'] ?? null,
            ]);

            return $this->transformStatusResponse($response);

        } catch (Exception $e) {
            Log::error('DeUna: Error getting payment status', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId,
            ]);

            throw new Exception('Error getting DeUna payment status: '.$e->getMessage());
        }
    }

    /**
     * Cancel a payment using DeUna API
     * Based on Official DeUna API V2 Documentation
     */
    public function cancelPayment(string $paymentId, string $reason = ''): array
    {
        try {
            Log::info('DeUna: Cancelling payment', [
                'payment_id' => $paymentId,
                'reason' => $reason,
            ]);

            // DeUna API payload structure for cancellation
            $payload = [
                'pointOfSale' => $this->pointOfSale,
                'transactionId' => $paymentId,
            ];

            if (! empty($reason)) {
                $payload['reason'] = $reason;
            }

            Log::info('DeUna: Cancel payload', ['payload' => $payload]);

            $response = $this->makeApiRequest('POST', '/merchant/v1/payment/cancel', $payload);

            Log::info('DeUna: Payment cancelled successfully', [
                'payment_id' => $paymentId,
                'response' => $response,
            ]);

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'status' => 'cancelled',
                'reason' => $reason,
                'cancelled_at' => now()->toISOString(),
                'raw_response' => $response,
            ];

        } catch (Exception $e) {
            Log::error('DeUna: Error cancelling payment', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId,
            ]);

            throw new Exception('Error cancelling DeUna payment: '.$e->getMessage());
        }
    }

    /**
     * Void/Refund a payment using DeUna API
     * Based on Official DeUna API V2 Documentation
     */
    public function refundPayment(string $paymentId, float $amount, string $reason = ''): array
    {
        try {
            Log::info('DeUna: Processing void/refund payment', [
                'payment_id' => $paymentId,
                'amount' => $amount,
                'reason' => $reason,
            ]);

            // DeUna API payload structure for void/refund
            $payload = [
                'pointOfSale' => $this->pointOfSale,
                'transactionId' => $paymentId,
                'amount' => (float) $amount,
            ];

            if (! empty($reason)) {
                $payload['reason'] = $reason;
            }

            Log::info('DeUna: Void payload', ['payload' => $payload]);

            $response = $this->makeApiRequest('POST', '/merchant/v1/payment/void', $payload);

            Log::info('DeUna: Payment void/refund processed successfully', [
                'payment_id' => $paymentId,
                'amount' => $amount,
                'response' => $response,
            ]);

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'status' => 'refunded',
                'refund_amount' => $amount,
                'reason' => $reason,
                'refunded_at' => now()->toISOString(),
                'raw_response' => $response,
            ];

        } catch (Exception $e) {
            Log::error('DeUna: Error processing void/refund', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId,
                'amount' => $amount,
            ]);

            throw new Exception('Error processing DeUna void/refund: '.$e->getMessage());
        }
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        try {
            // Remove the 'sha256=' prefix if present
            $signature = str_replace('sha256=', '', $signature);

            // Calculate expected signature
            $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);

            // Use timing-safe comparison
            $isValid = hash_equals($expectedSignature, $signature);

            Log::info('DeUna: Webhook signature verification', [
                'is_valid' => $isValid,
            ]);

            return $isValid;

        } catch (Exception $e) {
            Log::error('DeUna: Error verifying webhook signature', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate QR code for payment
     * DeUna provides QR codes directly, so this method returns the provided QR
     */
    public function generateQrCode(string $paymentUrl): string
    {
        // Since DeUna provides QR codes directly in their response,
        // we just return the URL or base64 QR code they provide
        return $paymentUrl;
    }

    /**
     * Make API request to DeUna
     */
    private function makeApiRequest(string $method, string $endpoint, array $payload = []): array
    {
        $url = $this->apiUrl.$endpoint;

        $headers = [
            'x-api-key' => $this->apiKey,
            'x-api-secret' => $this->apiSecret,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $attempt = 0;
        while ($attempt < $this->retryAttempts) {
            try {
                $response = Http::withHeaders($headers)
                    ->timeout($this->timeout);

                if ($method === 'POST') {
                    $response = $response->post($url, $payload);
                } elseif ($method === 'GET') {
                    $response = $response->get($url, $payload);
                }

                if ($response->successful()) {
                    $responseData = $response->json();
                    Log::info('DeUna API Raw Response', [
                        'url' => $url,
                        'response' => $responseData,
                        'has_qr' => isset($responseData['qr']),
                        'qr_length' => isset($responseData['qr']) ? strlen($responseData['qr']) : 0,
                    ]);

                    return $responseData;
                }

                // Log error response
                Log::error('DeUna API Error Response', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $url,
                    'method' => $method,
                    'attempt' => $attempt + 1,
                ]);

                // If it's the last attempt, throw exception
                if ($attempt === $this->retryAttempts - 1) {
                    $errorBody = $response->json();
                    $errorMessage = $errorBody['message'] ?? $errorBody['error'] ?? 'Unknown API error';

                    if (is_array($errorMessage)) {
                        $errorMessage = json_encode($errorMessage, JSON_UNESCAPED_UNICODE);
                    }

                    throw new Exception('DeUna API Error: '.$errorMessage, $response->status());
                }

                $attempt++;
                usleep($this->retryDelay * 1000);

            } catch (Exception $e) {
                Log::error('DeUna API Request Exception', [
                    'error' => $e->getMessage(),
                    'url' => $url,
                    'method' => $method,
                    'attempt' => $attempt + 1,
                ]);

                if ($attempt === $this->retryAttempts - 1) {
                    throw $e;
                }

                $attempt++;
                usleep($this->retryDelay * 1000);
            }
        }

        throw new Exception('Failed to make API request after '.$this->retryAttempts.' attempts');
    }

    /**
     * Transform DeUna payment response to our standard format
     */
    private function transformPaymentResponse(array $deunaResponse, array $originalPaymentData): array
    {
        Log::info('DeUna: Transforming payment response', [
            'deuna_keys' => array_keys($deunaResponse),
            'has_qr' => isset($deunaResponse['qr']),
            'has_deeplink' => isset($deunaResponse['deeplink']),
            'qr_value' => $deunaResponse['qr'] ?? 'NOT_FOUND',
        ]);

        return [
            'success' => true,
            'payment_id' => $deunaResponse['transactionId'] ?? null,
            'transaction_id' => $deunaResponse['transactionId'] ?? null,
            'order_id' => $originalPaymentData['order_id'],
            'amount' => $originalPaymentData['amount'],
            'currency' => 'USD', // DeUna works in USD
            'status' => 'created',
            'qr_code_base64' => $deunaResponse['qr'] ?? null,
            'payment_url' => $deunaResponse['deeplink'] ?? null,
            'numeric_code' => $deunaResponse['numericCode'] ?? null,
            'raw_response' => $deunaResponse,
        ];
    }

    /**
     * Transform DeUna status response to our standard format
     * Handles both API response and Webhook response formats
     */
    private function transformStatusResponse(array $deunaResponse): array
    {
        // Handle different response formats from DeUna

        // Webhook format: status: "SUCCESS", idTransaction: "...", transferNumber: "..."
        if (isset($deunaResponse['idTransaction']) && isset($deunaResponse['transferNumber'])) {
            $statusMap = [
                'SUCCESS' => 'completed',
                'PENDING' => 'pending',
                'FAILED' => 'failed',
                'CANCELLED' => 'cancelled',
            ];

            $deunaStatus = strtoupper($deunaResponse['status'] ?? 'PENDING');
            $mappedStatus = $statusMap[$deunaStatus] ?? 'pending';

            return [
                'payment_id' => $deunaResponse['idTransaction'],
                'status' => $mappedStatus,
                'amount' => $deunaResponse['amount'] ?? null,
                'currency' => $deunaResponse['currency'] ?? 'USD',
                'transfer_number' => $deunaResponse['transferNumber'] ?? null,
                'date' => $deunaResponse['date'] ?? null,
                'branch_id' => $deunaResponse['branchId'] ?? null,
                'pos_id' => $deunaResponse['posId'] ?? null,
                'description' => $deunaResponse['description'] ?? null,
                'customer_identification' => $deunaResponse['customerIdentification'] ?? null,
                'customer_full_name' => $deunaResponse['customerFullName'] ?? null,
                'internal_transaction_reference' => $deunaResponse['internalTransactionReference'] ?? null,
                'transaction_details' => $deunaResponse,
                'updated_at' => now(),
            ];
        }

        // API response format: status: "PENDING/APPROVED", transactionId: "...", amount: number
        $statusMap = [
            'PENDING' => 'pending',
            'APPROVED' => 'completed',
            'COMPLETED' => 'completed',
            'FAILED' => 'failed',
            'CANCELLED' => 'cancelled',
            'REJECTED' => 'failed',
        ];

        $deunaStatus = strtoupper($deunaResponse['status'] ?? 'PENDING');
        $mappedStatus = $statusMap[$deunaStatus] ?? 'pending';

        return [
            'payment_id' => $deunaResponse['transactionId'] ?? null,
            'status' => $mappedStatus,
            'amount' => $deunaResponse['amount'] ?? null,
            'currency' => $deunaResponse['currency'] ?? 'USD',
            'transfer_number' => $deunaResponse['transferNumber'] ?? null,
            'date' => $deunaResponse['date'] ?? null,
            'branch_id' => $deunaResponse['branchId'] ?? null,
            'pos_id' => $deunaResponse['posId'] ?? null,
            'description' => $deunaResponse['description'] ?? null,
            'orderer_name' => $deunaResponse['ordererName'] ?? null,
            'orderer_identification' => $deunaResponse['ordererIdentification'] ?? null,
            'internal_transaction_reference' => $deunaResponse['internalTransactionReference'] ?? null,
            'transaction_details' => $deunaResponse,
            'updated_at' => now(),
        ];
    }

    /**
     * Build payment detail string for DeUna
     */
    private function buildPaymentDetail(array $paymentData): string
    {
        $items = $paymentData['items'] ?? [];
        if (empty($items)) {
            return 'Compra en '.config('app.name');
        }

        // Build a descriptive detail from items (max 50 chars)
        $detail = '';
        $itemCount = 0;

        foreach ($items as $item) {
            if ($itemCount > 0) {
                $detail .= ', ';
            }
            $detail .= $item['name'];
            $itemCount++;

            // DeUna has a 50 character limit
            if (strlen($detail) > 40) {
                if ($itemCount < count($items)) {
                    $detail .= '...';
                }
                break;
            }
        }

        return substr($detail, 0, 50);
    }

    /**
     * Generate a short reference for DeUna (max 20 characters)
     */
    private function generateShortReference(string $orderId): string
    {
        // If order ID is already 20 chars or less, use it
        if (strlen($orderId) <= 20) {
            return $orderId;
        }

        // Extract meaningful parts and create shorter version
        // Example: ORDER-1755006417854-MVOR694JA -> O1755006417854M694JA (20 chars)
        if (preg_match('/ORDER-(\d+)-([A-Z0-9]+)/', $orderId, $matches)) {
            $timestamp = $matches[1];
            $code = $matches[2];

            // Create short version: O + timestamp + first 4 chars of code
            $shortRef = 'O'.$timestamp.substr($code, 0, 4);

            // If still too long, truncate timestamp
            if (strlen($shortRef) > 20) {
                $shortRef = 'O'.substr($timestamp, -10).substr($code, 0, 4);
            }

            return substr($shortRef, 0, 20);
        }

        // Fallback: use last 20 characters
        return substr($orderId, -20);
    }

    /**
     * Validate payment data
     */
    private function validatePaymentData(array $paymentData): void
    {
        $required = ['order_id', 'amount', 'customer'];

        foreach ($required as $field) {
            if (! isset($paymentData[$field]) || empty($paymentData[$field])) {
                throw new Exception("Required field '{$field}' is missing or empty");
            }
        }

        // Validate amount
        if (! is_numeric($paymentData['amount']) || $paymentData['amount'] <= 0) {
            throw new Exception('Amount must be a positive number');
        }

        // Validate customer data
        if (! isset($paymentData['customer']['email']) || ! filter_var($paymentData['customer']['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Valid customer email is required');
        }

        if (! isset($paymentData['customer']['name']) || empty($paymentData['customer']['name'])) {
            throw new Exception('Customer name is required');
        }

        // Validate items data to avoid price issues
        if (isset($paymentData['items']) && is_array($paymentData['items'])) {
            foreach ($paymentData['items'] as $index => $item) {
                if (! isset($item['name']) || empty($item['name'])) {
                    throw new Exception("Item {$index}: name is required");
                }

                if (! isset($item['quantity']) || ! is_numeric($item['quantity']) || $item['quantity'] <= 0) {
                    throw new Exception("Item {$index}: valid quantity is required");
                }

                if (! isset($item['price']) || ! is_numeric($item['price']) || $item['price'] < 0) {
                    throw new Exception("Item {$index}: valid price is required (got: ".($item['price'] ?? 'null').')');
                }
            }
        }

        // Validate format if provided
        if (isset($paymentData['format'])) {
            $validFormats = ['0', '1', '2', '3', '4'];
            if (! in_array($paymentData['format'], $validFormats)) {
                throw new Exception('Invalid format. Must be one of: '.implode(', ', $validFormats));
            }
        }

        // Validate QR type if provided
        if (isset($paymentData['qr_type'])) {
            $validTypes = ['static', 'dynamic'];
            if (! in_array($paymentData['qr_type'], $validTypes)) {
                throw new Exception('Invalid QR type. Must be static or dynamic');
            }
        }
    }
}
