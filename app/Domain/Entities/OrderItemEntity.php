<?php

namespace App\Domain\Entities;

class OrderItemEntity
{
    private ?int $id;

    private ?int $orderId;

    private int $productId;

    private int $quantity;

    private float $price;

    private float $subtotal;

    private ?\DateTime $createdAt;

    private ?\DateTime $updatedAt;

    public function __construct(
        int $productId,
        int $quantity,
        float $price,
        ?int $orderId = null,
        ?int $id = null,
        ?\DateTime $createdAt = null,
        ?\DateTime $updatedAt = null
    ) {
        $this->productId = $productId;
        $this->quantity = $quantity;
        $this->price = $price;
        $this->subtotal = $price * $quantity;
        $this->orderId = $orderId;
        $this->id = $id;
        $this->createdAt = $createdAt ?? new \DateTime;
        $this->updatedAt = $updatedAt ?? new \DateTime;
    }

    /**
     * Crear un nuevo item desde datos primitivos
     */
    public static function create(
        int $productId,
        int $quantity,
        float $price
    ): self {
        return new self($productId, $quantity, $price);
    }

    /**
     * Reconstruir un item desde la base de datos
     */
    public static function reconstitute(
        int $id,
        int $orderId,
        int $productId,
        int $quantity,
        float $price,
        float $subtotal,
        string $createdAt,
        string $updatedAt
    ): self {
        $item = new self(
            $productId,
            $quantity,
            $price,
            $orderId,
            $id,
            new \DateTime($createdAt),
            new \DateTime($updatedAt)
        );

        // Sobrescribir el subtotal con el valor de la base de datos
        // (normalmente debería ser igual a price * quantity)
        $item->subtotal = $subtotal;

        return $item;
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderId(): ?int
    {
        return $this->orderId;
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getSubtotal(): float
    {
        return $this->subtotal;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    // Setters
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setOrderId(int $orderId): void
    {
        $this->orderId = $orderId;
        $this->updateTimestamp();
    }

    public function setQuantity(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be greater than zero');
        }

        $this->quantity = $quantity;
        $this->recalculateSubtotal();
        $this->updateTimestamp();
    }

    public function setPrice(float $price): void
    {
        if ($price < 0) {
            throw new \InvalidArgumentException('Price cannot be negative');
        }

        $this->price = $price;
        $this->recalculateSubtotal();
        $this->updateTimestamp();
    }

    // Métodos de negocio
    public function incrementQuantity(int $amount = 1): void
    {
        $this->quantity += $amount;
        $this->recalculateSubtotal();
        $this->updateTimestamp();
    }

    public function decrementQuantity(int $amount = 1): void
    {
        if ($this->quantity <= $amount) {
            throw new \InvalidArgumentException('Cannot decrement quantity below 1');
        }

        $this->quantity -= $amount;
        $this->recalculateSubtotal();
        $this->updateTimestamp();
    }

    private function recalculateSubtotal(): void
    {
        $this->subtotal = $this->price * $this->quantity;
    }

    private function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTime;
    }

    /**
     * Convierte la entidad a un array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->orderId,
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'subtotal' => $this->subtotal,
            'created_at' => $this->createdAt ? $this->createdAt->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updatedAt ? $this->updatedAt->format('Y-m-d H:i:s') : null,
        ];
    }
}
