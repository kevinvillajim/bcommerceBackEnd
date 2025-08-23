<?php

namespace App\Domain\Entities;

class DiscountCodeEntity
{
    private ?int $id;

    private int $feedbackId;

    private string $code;

    private float $discountPercentage;

    private bool $isUsed;

    private ?int $usedBy;

    private ?string $usedAt;

    private ?int $usedOnProductId;

    private ?string $expiresAt;

    private ?string $createdAt;

    private ?string $updatedAt;

    /**
     * DiscountCodeEntity constructor.
     */
    public function __construct(
        int $feedbackId,
        string $code,
        float $discountPercentage = 5.00,
        bool $isUsed = false,
        ?int $usedBy = null,
        ?string $usedAt = null,
        ?int $usedOnProductId = null,
        ?string $expiresAt = null,
        ?int $id = null,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->feedbackId = $feedbackId;
        $this->code = $code;
        $this->discountPercentage = $discountPercentage;
        $this->isUsed = $isUsed;
        $this->usedBy = $usedBy;
        $this->usedAt = $usedAt;
        $this->usedOnProductId = $usedOnProductId;
        $this->expiresAt = $expiresAt;
        $this->id = $id;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFeedbackId(): int
    {
        return $this->feedbackId;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getDiscountPercentage(): float
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

    public function getExpiresAt(): ?string
    {
        return $this->expiresAt;
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
    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function setFeedbackId(int $feedbackId): void
    {
        $this->feedbackId = $feedbackId;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function setDiscountPercentage(float $discountPercentage): void
    {
        $this->discountPercentage = $discountPercentage;
    }

    public function setIsUsed(bool $isUsed): void
    {
        $this->isUsed = $isUsed;
    }

    public function setUsedBy(?int $usedBy): void
    {
        $this->usedBy = $usedBy;
    }

    public function setUsedAt(?string $usedAt): void
    {
        $this->usedAt = $usedAt;
    }

    public function setUsedOnProductId(?int $usedOnProductId): void
    {
        $this->usedOnProductId = $usedOnProductId;
    }

    public function setExpiresAt(?string $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    // Business methods
    public function markAsUsed(int $userId, int $productId): void
    {
        $this->isUsed = true;
        $this->usedBy = $userId;
        $this->usedAt = date('Y-m-d H:i:s');
        $this->usedOnProductId = $productId;
    }

    public function isValid(): bool
    {
        if ($this->isUsed) {
            return false;
        }

        if ($this->expiresAt && strtotime($this->expiresAt) < time()) {
            return false;
        }

        return true;
    }

    public function setExpirationDate(int $daysFromNow = 30): void
    {
        $expirationDate = date('Y-m-d H:i:s', strtotime("+{$daysFromNow} days"));
        $this->expiresAt = $expirationDate;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'feedback_id' => $this->feedbackId,
            'code' => $this->code,
            'discount_percentage' => $this->discountPercentage,
            'is_used' => $this->isUsed,
            'used_by' => $this->usedBy,
            'used_at' => $this->usedAt,
            'used_on_product_id' => $this->usedOnProductId,
            'expires_at' => $this->expiresAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
