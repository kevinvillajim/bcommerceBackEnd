<?php

namespace App\Domain\Entities;

class RatingEntity
{
    private ?int $id;

    private int $userId;

    private ?int $sellerId;

    private ?int $orderId;

    private ?int $productId;

    private float $rating;

    private ?string $title;

    private ?string $comment;

    private string $status;

    private string $type;

    /**
     * RatingEntity constructor.
     */
    public function __construct(
        int $userId,
        float $rating,
        string $type,
        ?int $sellerId = null,
        ?int $orderId = null,
        ?int $productId = null,
        ?string $title = null,
        ?string $comment = null,
        string $status = 'pending',
        ?int $id = null
    ) {
        $this->userId = $userId;
        $this->rating = $rating;
        $this->type = $type;
        $this->sellerId = $sellerId;
        $this->orderId = $orderId;
        $this->productId = $productId;
        $this->title = $title;
        $this->comment = $comment;
        $this->status = $status;
        $this->id = $id;
    }

    /**
     * Get the rating ID
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set the rating ID
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
     * Get the seller ID
     */
    public function getSellerId(): ?int
    {
        return $this->sellerId;
    }

    /**
     * Set the seller ID
     */
    public function setSellerId(?int $sellerId): void
    {
        $this->sellerId = $sellerId;
    }

    /**
     * Get the order ID
     */
    public function getOrderId(): ?int
    {
        return $this->orderId;
    }

    /**
     * Set the order ID
     */
    public function setOrderId(?int $orderId): void
    {
        $this->orderId = $orderId;
    }

    /**
     * Get the product ID
     */
    public function getProductId(): ?int
    {
        return $this->productId;
    }

    /**
     * Set the product ID
     */
    public function setProductId(?int $productId): void
    {
        $this->productId = $productId;
    }

    /**
     * Get the rating value
     */
    public function getRating(): float
    {
        return $this->rating;
    }

    /**
     * Set the rating value
     */
    public function setRating(float $rating): void
    {
        $this->rating = $rating;
    }

    /**
     * Get the title
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Set the title
     */
    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    /**
     * Get the comment
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * Set the comment
     */
    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
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
     * Get the type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set the type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * Check if the rating is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if the rating is from a verified purchase
     */
    public function isVerifiedPurchase(): bool
    {
        return $this->orderId !== null;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'seller_id' => $this->sellerId,
            'order_id' => $this->orderId,
            'product_id' => $this->productId,
            'rating' => $this->rating,
            'title' => $this->title,
            'comment' => $this->comment,
            'status' => $this->status,
            'type' => $this->type,
        ];
    }
}
