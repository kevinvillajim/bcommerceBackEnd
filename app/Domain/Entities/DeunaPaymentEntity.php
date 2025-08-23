<?php

namespace App\Domain\Entities;

use Carbon\Carbon;

class DeunaPaymentEntity
{
    private ?int $id;

    private string $paymentId;

    private string $orderId;

    private float $amount;

    private string $currency;

    private string $status;

    private array $customer;

    private array $items;

    private ?string $transactionId;

    private ?string $qrCode;

    private ?string $paymentUrl;

    private ?array $metadata;

    private ?string $failureReason;

    private ?float $refundAmount;

    private ?string $cancelReason;

    private Carbon $createdAt;

    private ?Carbon $updatedAt;

    private ?Carbon $completedAt;

    private ?Carbon $cancelledAt;

    private ?Carbon $refundedAt;

    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? null;
        $this->paymentId = $data['payment_id'] ?? '';
        $this->orderId = $data['order_id'] ?? '';
        $this->amount = (float) ($data['amount'] ?? 0);
        $this->currency = $data['currency'] ?? 'USD';
        $this->status = $data['status'] ?? 'pending';
        $this->customer = $data['customer'] ?? [];
        $this->items = $data['items'] ?? [];
        $this->transactionId = $data['transaction_id'] ?? null;
        $this->qrCode = $data['qr_code'] ?? null;
        $this->paymentUrl = $data['payment_url'] ?? null;
        $this->metadata = $data['metadata'] ?? null;
        $this->failureReason = $data['failure_reason'] ?? null;
        $this->refundAmount = isset($data['refund_amount']) ? (float) $data['refund_amount'] : null;
        $this->cancelReason = $data['cancel_reason'] ?? null;
        $this->createdAt = isset($data['created_at']) ? Carbon::parse($data['created_at']) : Carbon::now();
        $this->updatedAt = isset($data['updated_at']) ? Carbon::parse($data['updated_at']) : null;
        $this->completedAt = isset($data['completed_at']) ? Carbon::parse($data['completed_at']) : null;
        $this->cancelledAt = isset($data['cancelled_at']) ? Carbon::parse($data['cancelled_at']) : null;
        $this->refundedAt = isset($data['refunded_at']) ? Carbon::parse($data['refunded_at']) : null;
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCustomer(): array
    {
        return $this->customer;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function getQrCode(): ?string
    {
        return $this->qrCode;
    }

    public function getPaymentUrl(): ?string
    {
        return $this->paymentUrl;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function getRefundAmount(): ?float
    {
        return $this->refundAmount;
    }

    public function getCancelReason(): ?string
    {
        return $this->cancelReason;
    }

    public function getCreatedAt(): Carbon
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?Carbon
    {
        return $this->updatedAt;
    }

    public function getCompletedAt(): ?Carbon
    {
        return $this->completedAt;
    }

    public function getCancelledAt(): ?Carbon
    {
        return $this->cancelledAt;
    }

    public function getRefundedAt(): ?Carbon
    {
        return $this->refundedAt;
    }

    // Setters
    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->updatedAt = Carbon::now();
    }

    public function setTransactionId(string $transactionId): void
    {
        $this->transactionId = $transactionId;
        $this->updatedAt = Carbon::now();
    }

    public function setQrCode(string $qrCode): void
    {
        $this->qrCode = $qrCode;
        $this->updatedAt = Carbon::now();
    }

    public function setPaymentUrl(string $paymentUrl): void
    {
        $this->paymentUrl = $paymentUrl;
        $this->updatedAt = Carbon::now();
    }

    public function setFailureReason(string $failureReason): void
    {
        $this->failureReason = $failureReason;
        $this->updatedAt = Carbon::now();
    }

    public function setRefundAmount(float $refundAmount): void
    {
        $this->refundAmount = $refundAmount;
        $this->refundedAt = Carbon::now();
        $this->updatedAt = Carbon::now();
    }

    public function setCancelReason(string $cancelReason): void
    {
        $this->cancelReason = $cancelReason;
        $this->cancelledAt = Carbon::now();
        $this->updatedAt = Carbon::now();
    }

    public function markAsCompleted(): void
    {
        $this->status = 'completed';
        $this->completedAt = Carbon::now();
        $this->updatedAt = Carbon::now();
    }

    public function markAsCancelled(string $reason = ''): void
    {
        $this->status = 'cancelled';
        $this->cancelReason = $reason;
        $this->cancelledAt = Carbon::now();
        $this->updatedAt = Carbon::now();
    }

    public function markAsRefunded(?float $amount = null): void
    {
        $this->status = 'refunded';
        $this->refundAmount = $amount ?? $this->amount;
        $this->refundedAt = Carbon::now();
        $this->updatedAt = Carbon::now();
    }

    // Helper methods
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'created']);
    }

    public function canBeRefunded(): bool
    {
        return $this->status === 'completed';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->paymentId,
            'order_id' => $this->orderId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'customer' => $this->customer,
            'items' => $this->items,
            'transaction_id' => $this->transactionId,
            'qr_code' => $this->qrCode,
            'payment_url' => $this->paymentUrl,
            'metadata' => $this->metadata,
            'failure_reason' => $this->failureReason,
            'refund_amount' => $this->refundAmount,
            'cancel_reason' => $this->cancelReason,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'completed_at' => $this->completedAt,
            'cancelled_at' => $this->cancelledAt,
            'refunded_at' => $this->refundedAt,
        ];
    }
}
