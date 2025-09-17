<?php

namespace App\Domain\Entities;

class SellerEntity
{
    private ?int $id;

    private int $userId;

    private string $storeName;

    private ?string $description;

    private string $status;

    private string $verificationLevel;

    private float $commissionRate;

    private int $totalSales;

    private bool $isFeatured;

    private ?float $averageRating;

    private ?int $totalRatings;

    /**
     * SellerEntity constructor.
     */
    public function __construct(
        int $userId,
        string $storeName,
        ?string $description = null,
        string $status = 'pending',
        string $verificationLevel = 'none',
        float $commissionRate = 10.0,
        int $totalSales = 0,
        bool $isFeatured = false,
        ?int $id = null,
        ?float $averageRating = null,
        ?int $totalRatings = null
    ) {
        $this->userId = $userId;
        $this->storeName = $storeName;
        $this->description = $description;
        $this->status = $status;
        $this->verificationLevel = $verificationLevel;
        $this->commissionRate = $commissionRate;
        $this->totalSales = $totalSales;
        $this->isFeatured = $isFeatured;
        $this->id = $id;
        $this->averageRating = $averageRating;
        $this->totalRatings = $totalRatings;
    }

    /**
     * Get the seller ID
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set the seller ID
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * Get the user ID
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Get the store name
     */
    public function getStoreName(): string
    {
        return $this->storeName;
    }

    /**
     * Set the store name
     */
    public function setStoreName(string $storeName): void
    {
        $this->storeName = $storeName;
    }

    /**
     * Get the description
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Set the description
     */
    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * Get the status
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Set the status
     */
    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    /**
     * Get the verification level
     */
    public function getVerificationLevel(): string
    {
        return $this->verificationLevel;
    }

    /**
     * Set the verification level
     */
    public function setVerificationLevel(string $verificationLevel): void
    {
        $this->verificationLevel = $verificationLevel;
    }

    /**
     * Get the commission rate
     */
    public function getCommissionRate(): float
    {
        return $this->commissionRate;
    }

    /**
     * Set the commission rate
     */
    public function setCommissionRate(float $commissionRate): void
    {
        $this->commissionRate = $commissionRate;
    }

    /**
     * Get the total sales
     */
    public function getTotalSales(): int
    {
        return $this->totalSales;
    }

    /**
     * Set the total sales
     */
    public function setTotalSales(int $totalSales): void
    {
        $this->totalSales = $totalSales;
    }

    /**
     * Get featured status
     */
    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    /**
     * Set featured status
     */
    public function setIsFeatured(bool $isFeatured): void
    {
        $this->isFeatured = $isFeatured;
    }

    /**
     * Get the average rating
     */
    public function getAverageRating(): ?float
    {
        return $this->averageRating;
    }

    /**
     * Set the average rating
     */
    public function setAverageRating(?float $averageRating): void
    {
        $this->averageRating = $averageRating;
    }

    /**
     * Get the total ratings
     */
    public function getTotalRatings(): ?int
    {
        return $this->totalRatings;
    }

    /**
     * Set the total ratings
     */
    public function setTotalRatings(?int $totalRatings): void
    {
        $this->totalRatings = $totalRatings;
    }

    /**
     * Check if the seller is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Increment total sales
     */
    public function incrementSales(int $amount = 1): void
    {
        $this->totalSales += $amount;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'store_name' => $this->storeName,
            'description' => $this->description,
            'status' => $this->status,
            'verification_level' => $this->verificationLevel,
            // 'commission_rate' => $this->commissionRate, // TODO: Implementar comisiones individuales en el futuro - usar configuración global del admin (se obtiene dinámicamente)
            'total_sales' => $this->totalSales,
            'is_featured' => $this->isFeatured,
            'average_rating' => $this->averageRating,
            'total_ratings' => $this->totalRatings,
        ];
    }
}
