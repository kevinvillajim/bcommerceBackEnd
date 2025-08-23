<?php

namespace App\Domain\Entities;

class InvoiceItemEntity
{
    public function __construct(
        public ?int $id = null,
        public ?int $invoiceId = null,
        public int $productId = 0,
        public string $description = '',
        public int $quantity = 0,
        public float $unitPrice = 0,
        public float $discount = 0,
        public float $taxRate = 0,
        public float $taxAmount = 0,
        public float $total = 0,
        public ?string $sriProductCode = null
    ) {
        $this->recalculate();
    }

    public function recalculate(): void
    {
        $subtotal = $this->quantity * $this->unitPrice - $this->discount;
        $this->taxAmount = $subtotal * ($this->taxRate / 100);
        $this->total = $subtotal + $this->taxAmount;
    }
}
