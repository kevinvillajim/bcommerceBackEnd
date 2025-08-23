<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

/**
 * CategoryId Value Object
 */
final class CategoryId
{
    private int $value;

    public function __construct(int $value)
    {
        $this->ensureIsValidId($value);
        $this->value = $value;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function equals(CategoryId $anotherId): bool
    {
        return $this->value === $anotherId->getValue();
    }

    private function ensureIsValidId(int $id): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Category ID must be a positive integer');
        }
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
