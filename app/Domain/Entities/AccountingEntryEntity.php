<?php

namespace App\Domain\Entities;

class AccountingEntryEntity
{
    public function __construct(
        public ?int $id = null,
        public ?int $transactionId = null,
        public int $accountId = 0,
        public float $debitAmount = 0,
        public float $creditAmount = 0,
        public ?string $notes = null
    ) {}
}
