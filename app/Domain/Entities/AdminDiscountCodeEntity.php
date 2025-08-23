<?php

namespace App\Domain\Entities;

class AdminDiscountCodeEntity
{
    private ?int $id;

    private string $code;

    private int $discountPercentage;

    private bool $isUsed;

    private ?int $usedBy;

    private ?string $usedAt;

    private ?int $usedOnProductId;

    private string $expiresAt;

    private ?string $description;

    private int $createdBy;

    private ?string $createdAt;

    private ?string $updatedAt;

    public function __construct(
        string $code,
        int $discountPercentage,
        string $expiresAt,
        int $createdBy,
        bool $isUsed = false,
        ?int $usedBy = null,
        ?string $usedAt = null,
        ?int $usedOnProductId = null,
        ?string $description = null,
        ?int $id = null,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->code = $code;
        $this->discountPercentage = $discountPercentage;
        $this->expiresAt = $expiresAt;
        $this->createdBy = $createdBy;
        $this->isUsed = $isUsed;
        $this->usedBy = $usedBy;
        $this->usedAt = $usedAt;
        $this->usedOnProductId = $usedOnProductId;
        $this->description = $description;
        $this->id = $id;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getDiscountPercentage(): int
    {
        return $this->discountPercentage;
    }

    public function isUsed(): bool
    {
        return $this->isUsed;
    }

    public function getUsedBy(): ?int
    {
        return $this->usedBy;
    }

    public function getUsedAt(): ?string
    {
        return $this->usedAt;
    }

    public function getUsedOnProductId(): ?int
    {
        return $this->usedOnProductId;
    }

    public function getExpiresAt(): string
    {
        return $this->expiresAt;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCreatedBy(): int
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    // Setters
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function setDiscountPercentage(int $percentage): void
    {
        $this->discountPercentage = $percentage;
    }

    public function setExpiresAt(string $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function markAsUsed(int $userId, int $productId, string $usedAt): void
    {
        $this->isUsed = true;
        $this->usedBy = $userId;
        $this->usedOnProductId = $productId;
        $this->usedAt = $usedAt;
    }

    // Business methods
    public function isExpired(): bool
    {
        return strtotime($this->expiresAt) < time();
    }

    public function isValid(): bool
    {
        return ! $this->isUsed && ! $this->isExpired();
    }

    public function getDaysUntilExpiration(): int
    {
        $expiryTimestamp = strtotime($this->expiresAt);
        $currentTimestamp = time();
        $diffInSeconds = $expiryTimestamp - $currentTimestamp;

        return (int) ceil($diffInSeconds / (60 * 60 * 24));
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'discount_percentage' => $this->discountPercentage,
            'is_used' => $this->isUsed,
            'used_by' => $this->usedBy,
            'used_at' => $this->usedAt,
            'used_on_product_id' => $this->usedOnProductId,
            'expires_at' => $this->expiresAt,
            'description' => $this->description,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            // Campos calculados
            'is_valid' => $this->isValid(),
            'is_expired' => $this->isExpired(),
            'days_until_expiration' => $this->getDaysUntilExpiration(),
        ];
    }
}
