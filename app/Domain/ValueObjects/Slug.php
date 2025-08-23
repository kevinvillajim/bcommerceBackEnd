<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

/**
 * Slug Value Object
 */
final class Slug
{
    private string $value;

    public function __construct(string $value)
    {
        $this->ensureIsValidSlug($value);
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(Slug $anotherSlug): bool
    {
        return $this->value === $anotherSlug->getValue();
    }

    private function ensureIsValidSlug(string $slug): void
    {
        if (empty($slug)) {
            throw new \InvalidArgumentException('Slug cannot be empty');
        }

        if (strlen($slug) > 255) {
            throw new \InvalidArgumentException('Slug cannot exceed 255 characters');
        }

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new \InvalidArgumentException('Slug format is invalid. Use lowercase letters, numbers, and hyphens only.');
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
