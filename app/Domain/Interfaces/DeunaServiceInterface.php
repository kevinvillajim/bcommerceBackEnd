<?php

namespace App\Domain\Interfaces;

interface DeunaServiceInterface
{
    /**
     * Create a new payment with DeUna
     *
     * @throws \Exception
     */
    public function createPayment(array $paymentData): array;

    /**
     * Get payment status from DeUna
     *
     * @throws \Exception
     */
    public function getPaymentStatus(string $paymentId): array;

    /**
     * Cancel a payment
     *
     * @throws \Exception
     */
    public function cancelPayment(string $paymentId, string $reason): array;

    /**
     * Refund a payment
     *
     * @throws \Exception
     */
    public function refundPayment(string $paymentId, float $amount, string $reason): array;

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool;

    /**
     * Generate QR code for payment
     *
     * @return string Base64 encoded QR code
     *
     * @throws \Exception
     */
    public function generateQrCode(string $paymentUrl): string;
}
