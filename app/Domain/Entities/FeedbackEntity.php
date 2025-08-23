<?php

namespace App\Domain\Entities;

class FeedbackEntity
{
    private ?int $id;

    private int $userId;

    private ?int $sellerId;

    private string $title;

    private string $description;

    private string $type;

    private string $status;

    private ?string $adminNotes;

    private ?int $reviewedBy;

    private ?string $reviewedAt;

    private ?string $createdAt;

    private ?string $updatedAt;

    /**
     * FeedbackEntity constructor.
     */
    public function __construct(
        int $userId,
        string $title,
        string $description,
        ?int $sellerId = null,
        string $type = 'improvement',
        string $status = 'pending',
        ?string $adminNotes = null,
        ?int $reviewedBy = null,
        ?string $reviewedAt = null,
        ?int $id = null,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->userId = $userId;
        $this->title = $title;
        $this->description = $description;
        $this->sellerId = $sellerId;
        $this->type = $type;
        $this->status = $status;
        $this->adminNotes = $adminNotes;
        $this->reviewedBy = $reviewedBy;
        $this->reviewedAt = $reviewedAt;
        $this->id = $id;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getSellerId(): ?int
    {
        return $this->sellerId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getAdminNotes(): ?string
    {
        return $this->adminNotes;
    }

    public function getReviewedBy(): ?int
    {
        return $this->reviewedBy;
    }

    public function getReviewedAt(): ?string
    {
        return $this->reviewedAt;
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

    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    public function setSellerId(?int $sellerId): void
    {
        $this->sellerId = $sellerId;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function setAdminNotes(?string $adminNotes): void
    {
        $this->adminNotes = $adminNotes;
    }

    public function setReviewedBy(?int $reviewedBy): void
    {
        $this->reviewedBy = $reviewedBy;
    }

    public function setReviewedAt(?string $reviewedAt): void
    {
        $this->reviewedAt = $reviewedAt;
    }

    // Business methods
    public function approve(int $adminId, ?string $notes = null): void
    {
        $this->status = 'approved';
        $this->reviewedBy = $adminId;
        $this->reviewedAt = date('Y-m-d H:i:s');

        if ($notes) {
            $this->adminNotes = $notes;
        }
    }

    public function reject(int $adminId, ?string $notes = null): void
    {
        $this->status = 'rejected';
        $this->reviewedBy = $adminId;
        $this->reviewedAt = date('Y-m-d H:i:s');

        if ($notes) {
            $this->adminNotes = $notes;
        }
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'seller_id' => $this->sellerId,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'status' => $this->status,
            'admin_notes' => $this->adminNotes,
            'reviewed_by' => $this->reviewedBy,
            'reviewed_at' => $this->reviewedAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
