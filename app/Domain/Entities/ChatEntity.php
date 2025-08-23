<?php

namespace App\Domain\Entities;

class ChatEntity
{
    private ?int $id;

    private int $userId;

    private int $sellerId;

    private int $productId;

    private string $status;

    private array $messages = [];

    private \DateTime $createdAt;

    private \DateTime $updatedAt;

    // CORRECCIÓN: Añadir metaInfo para transportar información adicional
    private array $metaInfo = [];

    public function __construct(
        int $userId,
        int $sellerId,
        int $productId,
        string $status = 'active',
        array $messages = [],
        ?int $id = null,
        ?\DateTime $createdAt = null,
        ?\DateTime $updatedAt = null
    ) {
        $this->userId = $userId;
        $this->sellerId = $sellerId;
        $this->productId = $productId;
        $this->status = $status;
        $this->messages = $messages;
        $this->id = $id;
        $this->createdAt = $createdAt ?? new \DateTime;
        $this->updatedAt = $updatedAt ?? new \DateTime;
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

    public function getSellerId(): int
    {
        return $this->sellerId;
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    /**
     * Obtener información meta adicional
     */
    public function getMetaInfo(): array
    {
        return $this->metaInfo;
    }

    /**
     * Obtener un valor específico de la metaInfo
     *
     * @param  mixed  $default
     * @return mixed
     */
    public function getMeta(string $key, $default = null)
    {
        return $this->metaInfo[$key] ?? $default;
    }

    // Setters
    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->updatedAt = new \DateTime;
    }

    public function addMessage(MessageEntity $message): void
    {
        $this->messages[] = $message;
        $this->updatedAt = new \DateTime;
    }

    /**
     * Establecer información meta adicional
     */
    public function setMetaInfo(array $metaInfo): self
    {
        $this->metaInfo = $metaInfo;

        return $this;
    }

    /**
     * Añadir un valor específico a la metaInfo
     *
     * @param  mixed  $value
     */
    public function addMeta(string $key, $value): self
    {
        $this->metaInfo[$key] = $value;

        return $this;
    }
}
