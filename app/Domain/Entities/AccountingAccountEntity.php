<?php

namespace App\Domain\Entities;

class AccountingAccountEntity
{
    public function __construct(
        public ?int $id = null,
        public string $code = '',
        public string $name = '',
        public string $type = '',
        public ?string $description = null,
        public bool $isActive = true
    ) {}
}
