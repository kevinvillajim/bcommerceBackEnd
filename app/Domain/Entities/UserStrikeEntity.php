<?php

namespace App\Domain\Entities;

class UserStrikeEntity
{
    private ?int $id;

    private int $userId;

    private string $reason;

    private ?int $messageId;

    private ?int $createdBy;

    private ?string $createdAt;

    private ?string $updatedAt;

    public function __construct(
        int $userId,
        string $reason,
        ?int $messageId = null,
        ?int $createdBy = null,
        ?int $id = null,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->userId = $userId;
        $this->reason = $reason;
        $this->messageId = $messageId;
        $this->createdBy = $createdBy;
        $this->id = $id;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /**
     * Crea una nueva instancia de strike desde datos primitivos
     */
    public static function create(
        int $userId,
        string $reason,
        ?int $messageId = null,
        ?int $createdBy = null
    ): self {
        return new self(
            $userId,
            $reason,
            $messageId,
            $createdBy,
            null,
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        );
    }

    /**
     * Reconstruye un strike desde la base de datos
     */
    public static function reconstitute(
        int $id,
        int $userId,
        string $reason,
        ?int $messageId,
        ?int $createdBy,
        string $createdAt,
        string $updatedAt
    ): self {
        return new self(
            $userId,
            $reason,
            $messageId,
            $createdBy,
            $id,
            $createdAt,
            $updatedAt
        );
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

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getMessageId(): ?int
    {
        return $this->messageId;
    }

    public function getCreatedBy(): ?int
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
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;
        $this->updatedAt = date('Y-m-d H:i:s');

        return $this;
    }

    public function setMessageId(?int $messageId): self
    {
        $this->messageId = $messageId;
        $this->updatedAt = date('Y-m-d H:i:s');

        return $this;
    }

    // Método para convertir a array
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'reason' => $this->reason,
            'message_id' => $this->messageId,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
