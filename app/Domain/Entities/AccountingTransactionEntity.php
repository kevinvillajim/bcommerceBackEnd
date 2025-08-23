<?php

namespace App\Domain\Entities;

use DateTime;

class AccountingTransactionEntity
{
    public function __construct(
        public ?int $id = null,
        public string $referenceNumber = '',
        public DateTime $transactionDate = new DateTime,
        public string $description = '',
        public string $type = '',
        public ?int $userId = null,
        public ?int $orderId = null,
        public bool $isPosted = false,
        public array $entries = []
    ) {}

    public function addEntry(AccountingEntryEntity $entry): self
    {
        $this->entries[] = $entry;

        return $this;
    }

    public function getBalance(): float
    {
        $debitTotal = 0;
        $creditTotal = 0;

        foreach ($this->entries as $entry) {
            $debitTotal += $entry->debitAmount;
            $creditTotal += $entry->creditAmount;
        }

        return $debitTotal - $creditTotal;
    }

    public function isBalanced(): bool
    {
        return abs($this->getBalance()) < 0.01;
    }
}
