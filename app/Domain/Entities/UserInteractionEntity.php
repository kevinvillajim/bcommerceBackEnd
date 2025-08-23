<?php

namespace App\Domain\Entities;

class UserInteractionEntity
{
    private ?int $id;

    private int $userId;

    private string $type;

    private int $itemId;

    private array $metadata;

    private \DateTime $createdAt;

    public function __construct(
        int $userId,
        string $type,
        int $itemId,
        array $metadata = [],
        ?int $id = null
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->type = $type;
        $this->itemId = $itemId;
        $this->metadata = $metadata;
        $this->createdAt = new \DateTime;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getItemId(): int
    {
        return $this->itemId;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
}
