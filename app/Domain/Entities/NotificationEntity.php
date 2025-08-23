<?php

namespace App\Domain\Entities;

class NotificationEntity
{
    private ?int $id;

    private int $userId;

    private string $type;

    private string $title;

    private string $message;

    private array $data;

    private bool $read;

    private ?\DateTime $readAt;

    private \DateTime $createdAt;

    public function __construct(
        int $userId,
        string $type,
        string $title,
        string $message,
        array $data = [],
        bool $read = false,
        ?int $id = null,
        ?\DateTime $readAt = null,
        ?\DateTime $createdAt = null
    ) {
        $this->userId = $userId;
        $this->type = $type;
        $this->title = $title;
        $this->message = $message;
        $this->data = $data;
        $this->read = $read;
        $this->id = $id;
        $this->readAt = $readAt;
        $this->createdAt = $createdAt ?? new \DateTime;
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

    public function getType(): string
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function isRead(): bool
    {
        return $this->read;
    }

    public function getReadAt(): ?\DateTime
    {
        return $this->readAt;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    // Setters and Business Logic
    public function markAsRead(): void
    {
        $this->read = true;
        $this->readAt = new \DateTime;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
            'read' => $this->read,
            'read_at' => $this->readAt ? $this->readAt->format('Y-m-d H:i:s') : null,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
