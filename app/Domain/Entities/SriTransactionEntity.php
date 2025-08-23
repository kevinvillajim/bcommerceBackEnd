<?php

namespace App\Domain\Entities;

class SriTransactionEntity
{
    public function __construct(
        public ?int $id = null,
        public ?int $invoiceId = null,
        public string $type = '',
        public array $requestData = [],
        public ?array $responseData = null,
        public bool $success = false,
        public ?string $errorMessage = null
    ) {}
}
