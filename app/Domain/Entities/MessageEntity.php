<?php

namespace App\Domain\Entities;

class MessageEntity
{
    private ?int $id;

    private int $chatId;

    private int $senderId;

    private string $content;

    private bool $isRead;

    private \DateTime $createdAt;

    private \DateTime $updatedAt;

    public function __construct(
        int $chatId,
        int $senderId,
        string $content,
        bool $isRead = false,
        ?int $id = null,
        ?\DateTime $createdAt = null,
        ?\DateTime $updatedAt = null
    ) {
        $this->chatId = $chatId;
        $this->senderId = $senderId;
        $this->content = $content;
        $this->isRead = $isRead;
        $this->id = $id;
        $this->createdAt = $createdAt ?? new \DateTime;
        $this->updatedAt = $updatedAt ?? new \DateTime;
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChatId(): int
    {
        return $this->chatId;
    }

    public function getSenderId(): int
    {
        return $this->senderId;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    // Setters
    public function markAsRead(): void
    {
        $this->isRead = true;
        $this->updatedAt = new \DateTime;
    }
}
