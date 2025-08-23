<?php

namespace App\Domain\Entities;

use DateTime;

class InvoiceEntity
{
    public function __construct(
        public ?int $id = null,
        public string $invoiceNumber = '',
        public int $orderId = 0,
        public int $userId = 0,
        public int $sellerId = 0,
        public ?int $transactionId = null,
        public DateTime $issueDate = new DateTime,
        public float $subtotal = 0,
        public float $taxAmount = 0,
        public float $totalAmount = 0,
        public string $status = 'DRAFT',
        public ?string $sriAuthorizationNumber = null,
        public ?string $sriAccessKey = null,
        public ?string $cancellationReason = null,
        public ?DateTime $cancelledAt = null,
        public ?array $sriResponse = null,
        public array $items = []
    ) {}

    public function addItem(InvoiceItemEntity $item): self
    {
        $this->items[] = $item;
        $this->recalculateAmounts();

        return $this;
    }

    private function recalculateAmounts(): void
    {
        $this->subtotal = 0;
        $this->taxAmount = 0;

        foreach ($this->items as $item) {
            $this->subtotal += $item->quantity * $item->unitPrice - $item->discount;
            $this->taxAmount += $item->taxAmount;
        }

        $this->totalAmount = $this->subtotal + $this->taxAmount;
    }

    public function cancel(string $reason): self
    {
        $this->status = 'CANCELLED';
        $this->cancellationReason = $reason;
        $this->cancelledAt = new DateTime;

        return $this;
    }
}
