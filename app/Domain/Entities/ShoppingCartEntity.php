<?php

namespace App\Domain\Entities;

class ShoppingCartEntity
{
    private int $id;

    private int $userId;

    private array $items = [];

    private float $total = 0;

    private \DateTime $createdAt;

    private \DateTime $updatedAt;

    public function __construct(int $id, int $userId, array $items = [], float $total = 0, ?\DateTime $createdAt = null, ?\DateTime $updatedAt = null)
    {
        $this->id = $id;
        $this->userId = $userId;
        $this->items = $items;
        $this->total = $total;
        $this->createdAt = $createdAt ?? new \DateTime;
        $this->updatedAt = $updatedAt ?? new \DateTime;
    }

    // Getters
    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getTotal(): float
    {
        return $this->total;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    // Métodos para manipular el carrito
    public function addItem(CartItemEntity $item): void
    {
        // Verificar si el producto ya existe en el carrito
        foreach ($this->items as $key => $existingItem) {
            if ($existingItem->getProductId() === $item->getProductId()) {
                // Actualizar cantidad si ya existe
                $newQuantity = $existingItem->getQuantity() + $item->getQuantity();
                $this->items[$key] = new CartItemEntity(
                    $existingItem->getId(),
                    $this->id,
                    $item->getProductId(),
                    $newQuantity,
                    $item->getPrice(),
                    $newQuantity * $item->getPrice()
                );
                $this->recalculateTotal();

                return;
            }
        }

        // Añadir nuevo item si no existe
        $this->items[] = $item;
        $this->recalculateTotal();
    }

    public function removeItem(int $itemId): void
    {
        foreach ($this->items as $key => $item) {
            if ($item->getId() === $itemId) {
                unset($this->items[$key]);
                $this->items = array_values($this->items);
                $this->recalculateTotal();

                return;
            }
        }
    }

    public function updateItemQuantity(int $itemId, int $quantity): void
    {
        foreach ($this->items as $key => $item) {
            if ($item->getId() === $itemId) {
                $this->items[$key] = new CartItemEntity(
                    $item->getId(),
                    $this->id,
                    $item->getProductId(),
                    $quantity,
                    $item->getPrice(),
                    $quantity * $item->getPrice()
                );
                $this->recalculateTotal();

                return;
            }
        }
    }

    public function empty(): void
    {
        $this->items = [];
        $this->total = 0;
    }

    private function recalculateTotal(): void
    {
        $this->total = 0;
        foreach ($this->items as $item) {
            $this->total += $item->getSubtotal();
        }
    }
}
