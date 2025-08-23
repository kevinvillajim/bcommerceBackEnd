<?php

namespace App\Domain\Entities;

use DateTime;

class FavoriteEntity
{
    private int $id;

    private int $userId;

    private int $productId;

    private bool $notifyPriceChange;

    private bool $notifyPromotion;

    private bool $notifyLowStock;

    private DateTime $createdAt;

    private ?DateTime $updatedAt;

    /**
     * Constructor for FavoriteEntity
     */
    public function __construct(
        int $userId,
        int $productId,
        bool $notifyPriceChange = true,
        bool $notifyPromotion = true,
        bool $notifyLowStock = true,
        ?int $id = null,
        ?DateTime $createdAt = null,
        ?DateTime $updatedAt = null
    ) {
        $this->userId = $userId;
        $this->productId = $productId;
        $this->notifyPriceChange = $notifyPriceChange;
        $this->notifyPromotion = $notifyPromotion;
        $this->notifyLowStock = $notifyLowStock;
        $this->id = $id ?? 0;
        $this->createdAt = $createdAt ?? new DateTime;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function isNotifyPriceChange(): bool
    {
        return $this->notifyPriceChange;
    }

    public function setNotifyPriceChange(bool $notifyPriceChange): void
    {
        $this->notifyPriceChange = $notifyPriceChange;
        $this->updatedAt = new DateTime;
    }

    public function isNotifyPromotion(): bool
    {
        return $this->notifyPromotion;
    }

    public function setNotifyPromotion(bool $notifyPromotion): void
    {
        $this->notifyPromotion = $notifyPromotion;
        $this->updatedAt = new DateTime;
    }

    public function isNotifyLowStock(): bool
    {
        return $this->notifyLowStock;
    }

    public function setNotifyLowStock(bool $notifyLowStock): void
    {
        $this->notifyLowStock = $notifyLowStock;
        $this->updatedAt = new DateTime;
    }
}
