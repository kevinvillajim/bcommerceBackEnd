<?php

namespace App\UseCases\Payment;

use App\Domain\Entities\DeunaPaymentEntity;
use App\Domain\Interfaces\DeunaServiceInterface;
use App\Domain\Repositories\DeunaPaymentRepositoryInterface;
use App\Domain\Repositories\OrderRepositoryInterface;
use Exception;
use Illuminate\Support\Facades\Log;

class CreateDeunaPaymentUseCase
{
    public function __construct(
        private DeunaServiceInterface $deunaService,
        private DeunaPaymentRepositoryInterface $deunaPaymentRepository,
        private OrderRepositoryInterface $orderRepository
    ) {}

    /**
     * Execute the use case to create a DeUna payment
     *
     * @throws Exception
     */
    public function execute(array $paymentData): array
    {
        try {
            Log::info('Creating DeUna payment', [
                'transaction_reference' => $paymentData['order_id'] ?? null,
                'amount' => $paymentData['amount'] ?? null,
            ]);

            // Validate input data
            $this->validatePaymentData($paymentData);

            // Check if payment already exists for this transaction reference
            $existingPayment = $this->deunaPaymentRepository->findByOrderId($paymentData['order_id']);
            if ($existingPayment && $existingPayment->isPending()) {
                Log::info('Existing pending payment found, returning existing payment', [
                    'payment_id' => $existingPayment->getPaymentId(),
                    'transaction_reference' => $paymentData['order_id'],
                ]);

                return [
                    'success' => true,
                    'payment' => $existingPayment->toArray(),
                    'message' => 'Using existing pending payment',
                ];
            }

            // Prepare payment data for DeUna API (no need to look up existing order)
            $deunaPaymentData = $this->preparePaymentDataForDeuna($paymentData);

            // Create payment with DeUna
            $deunaResponse = $this->deunaService->createPayment($deunaPaymentData);

            Log::info('DeUna response in UseCase', [
                'qr_code_base64_exists' => isset($deunaResponse['qr_code_base64']),
                'qr_code_base64_value' => $deunaResponse['qr_code_base64'] ?? 'NOT_SET',
                'qr_code_base64_length' => isset($deunaResponse['qr_code_base64']) ? strlen($deunaResponse['qr_code_base64']) : 0,
                'all_keys' => array_keys($deunaResponse),
            ]);

            // Create payment entity
            $paymentEntity = new DeunaPaymentEntity([
                'payment_id' => $deunaResponse['payment_id'],
                'order_id' => $paymentData['order_id'],
                'amount' => $paymentData['amount'],
                'currency' => $deunaResponse['currency'] ?? 'USD',
                'status' => $deunaResponse['status'] ?? 'created',
                'customer' => $paymentData['customer'],
                'items' => $paymentData['items'] ?? [],
                'transaction_id' => $deunaResponse['transaction_id'],
                'qr_code' => $deunaResponse['qr_code_base64'],
                'payment_url' => $deunaResponse['payment_url'],
                'metadata' => array_merge(
                    $paymentData['metadata'] ?? [], // Preserve frontend metadata (includes user_id)
                    [
                        'qr_type' => $paymentData['qr_type'] ?? 'dynamic',
                        'format' => $paymentData['format'] ?? '2',
                        'numeric_code' => $deunaResponse['numeric_code'] ?? null,
                        'raw_response' => $deunaResponse['raw_response'] ?? null,
                    ]
                ),
            ]);

            // Save payment to database
            $savedPayment = $this->deunaPaymentRepository->create($paymentEntity);

            Log::info('DeUna payment created successfully', [
                'payment_id' => $savedPayment->getPaymentId(),
                'transaction_reference' => $savedPayment->getOrderId(),
                'amount' => $savedPayment->getAmount(),
                'qr_code_from_saved_payment' => $savedPayment->getQrCode() ? 'PRESENT' : 'NULL',
                'qr_code_length_from_saved' => $savedPayment->getQrCode() ? strlen($savedPayment->getQrCode()) : 0,
            ]);

            $finalResult = [
                'success' => true,
                'payment' => $savedPayment->toArray(),
                'qr_code' => $savedPayment->getQrCode(),
                'payment_url' => $savedPayment->getPaymentUrl(),
                'numeric_code' => $deunaResponse['numeric_code'] ?? null,
                'message' => 'Payment created successfully',
            ];

            Log::info('UseCase final response', [
                'qr_code_in_final_response' => isset($finalResult['qr_code']) ? 'PRESENT' : 'NULL',
                'qr_code_value_in_final' => $finalResult['qr_code'] ?? 'NOT_SET',
                'qr_code_length_in_final' => isset($finalResult['qr_code']) && $finalResult['qr_code'] ? strlen($finalResult['qr_code']) : 0,
            ]);

            return $finalResult;

        } catch (Exception $e) {
            Log::error('Error creating DeUna payment', [
                'error' => $e->getMessage(),
                'transaction_reference' => $paymentData['order_id'] ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            throw new Exception('Failed to create DeUna payment: '.$e->getMessage());
        }
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
        if (! isset($paymentData['customer']['email']) ||
            ! filter_var($paymentData['customer']['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Valid customer email is required');
        }

        if (! isset($paymentData['customer']['name']) || empty($paymentData['customer']['name'])) {
            throw new Exception('Customer name is required');
        }
    }

    /**
     * Prepare payment data for DeUna API call
     */
    private function preparePaymentDataForDeuna(array $paymentData): array
    {
        // Use items provided in payload or create a simple item
        $items = $paymentData['items'] ?? [[
            'name' => 'Purchase #'.$paymentData['order_id'],
            'quantity' => 1,
            'price' => (float) $paymentData['amount'],
            'description' => 'Purchase from '.config('app.name'),
        ]];

        return [
            'order_id' => $paymentData['order_id'],
            'amount' => (float) $paymentData['amount'],
            'currency' => $paymentData['currency'] ?? 'USD',
            'customer' => $paymentData['customer'],
            'items' => $items,
            'qr_type' => $paymentData['qr_type'] ?? 'dynamic',
            'format' => $paymentData['format'] ?? '2', // QR + Link
            'metadata' => array_merge(
                $paymentData['metadata'] ?? [],
                [
                    'transaction_reference' => $paymentData['order_id'],
                    'created_from' => 'bcommerce_api',
                    'timestamp' => now()->toISOString(),
                ]
            ),
        ];
    }

    /**
     * Extract items from payload (deprecated - now handled directly in preparePaymentDataForDeuna)
     */
    private function getOrderItems($order): array
    {
        // This method is deprecated but kept for backward compatibility
        return [
            [
                'name' => 'Legacy Order Item',
                'quantity' => 1,
                'price' => 0.0,
                'description' => 'Legacy order item',
            ],
        ];
    }
}
