<?php

namespace App\UseCases\Payment;

use App\Domain\Interfaces\DeunaServiceInterface;
use App\Domain\Repositories\DeunaPaymentRepositoryInterface;
use Exception;
use Illuminate\Support\Facades\Log;

class GenerateQRDeUnatUseCase
{
    public function __construct(
        private DeunaServiceInterface $deunaService,
        private DeunaPaymentRepositoryInterface $deunaPaymentRepository
    ) {}

    /**
     * Generate QR code for existing DeUna payment or create new one
     *
     * @throws Exception
     */
    public function execute(array $data): array
    {
        try {
            Log::info('Generating DeUna QR code', [
                'payment_id' => $data['payment_id'] ?? null,
                'order_id' => $data['order_id'] ?? null,
            ]);

            // If payment_id is provided, get existing payment
            if (isset($data['payment_id'])) {
                return $this->getExistingPaymentQR($data['payment_id']);
            }

            // If order_id is provided, get payment for that order
            if (isset($data['order_id'])) {
                return $this->getPaymentQRByOrderId($data['order_id']);
            }

            // If no existing payment, create new one
            if (isset($data['amount'], $data['customer'], $data['order_id'])) {
                return $this->createNewPaymentWithQR($data);
            }

            throw new Exception('Insufficient data to generate QR code. Need payment_id, order_id, or complete payment data.');
        } catch (Exception $e) {
            Log::error('Error generating DeUna QR code', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw new Exception('Failed to generate QR code: '.$e->getMessage());
        }
    }

    /**
     * Get QR code for existing payment
     */
    private function getExistingPaymentQR(string $paymentId): array
    {
        $payment = $this->deunaPaymentRepository->findByPaymentId($paymentId);

        if (! $payment) {
            throw new Exception('Payment not found: '.$paymentId);
        }

        // If payment already has QR code, return it
        if ($payment->getQrCode()) {
            return [
                'success' => true,
                'payment_id' => $payment->getPaymentId(),
                'qr_code_base64' => $payment->getQrCode(),
                'payment_url' => $payment->getPaymentUrl(),
                'status' => $payment->getStatus(),
                'amount' => $payment->getAmount(),
                'currency' => $payment->getCurrency(),
            ];
        }

        // If no QR code, try to get updated status from DeUna
        try {
            $statusResponse = $this->deunaService->getPaymentStatus($paymentId);

            // Update payment with new information if available
            if (isset($statusResponse['qr_code_base64']) || isset($statusResponse['payment_url'])) {
                $payment->setQrCode($statusResponse['qr_code_base64'] ?? '');
                $payment->setPaymentUrl($statusResponse['payment_url'] ?? '');
                $this->deunaPaymentRepository->update($payment);
            }

            return [
                'success' => true,
                'payment_id' => $payment->getPaymentId(),
                'qr_code_base64' => $statusResponse['qr_code_base64'] ?? $payment->getQrCode(),
                'payment_url' => $statusResponse['payment_url'] ?? $payment->getPaymentUrl(),
                'status' => $statusResponse['status'] ?? $payment->getStatus(),
                'amount' => $payment->getAmount(),
                'currency' => $payment->getCurrency(),
            ];

        } catch (Exception $e) {
            // If DeUna API call fails, return what we have
            return [
                'success' => true,
                'payment_id' => $payment->getPaymentId(),
                'qr_code_base64' => $payment->getQrCode(),
                'payment_url' => $payment->getPaymentUrl(),
                'status' => $payment->getStatus(),
                'amount' => $payment->getAmount(),
                'currency' => $payment->getCurrency(),
                'warning' => 'Could not fetch updated status from DeUna',
            ];
        }
    }

    /**
     * Get QR code by order ID
     */
    private function getPaymentQRByOrderId(string $orderId): array
    {
        $payment = $this->deunaPaymentRepository->findByOrderId($orderId);

        if (! $payment) {
            throw new Exception('No payment found for order: '.$orderId);
        }

        return $this->getExistingPaymentQR($payment->getPaymentId());
    }

    /**
     * Create new payment and return QR code
     */
    private function createNewPaymentWithQR(array $data): array
    {
        // Use CreateDeunaPaymentUseCase to create the payment
        $createUseCase = app(CreateDeunaPaymentUseCase::class);
        $result = $createUseCase->execute($data);

        if (! $result['success']) {
            throw new Exception('Failed to create payment for QR generation');
        }

        return [
            'success' => true,
            'payment_id' => $result['payment']['payment_id'],
            'qr_code_base64' => $result['qr_code'],
            'payment_url' => $result['payment_url'],
            'numeric_code' => $result['numeric_code'] ?? null,
            'status' => $result['payment']['status'],
            'amount' => $result['payment']['amount'],
            'currency' => $result['payment']['currency'],
            'created' => true,
        ];
    }

    /**
     * Generate custom QR code from URL (if needed)
     */
    public function generateCustomQR(string $paymentUrl): array
    {
        try {
            Log::info('Generating custom QR code', ['payment_url' => $paymentUrl]);

            $qrCode = $this->deunaService->generateQrCode($paymentUrl);

            return [
                'success' => true,
                'qr_code_base64' => $qrCode,
                'payment_url' => $paymentUrl,
            ];

        } catch (Exception $e) {
            Log::error('Error generating custom QR code', [
                'error' => $e->getMessage(),
                'payment_url' => $paymentUrl,
            ]);

            throw new Exception('Failed to generate custom QR code: '.$e->getMessage());
        }
    }
}
