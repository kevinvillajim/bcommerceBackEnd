<?php

namespace App\Domain\Entities;

class CartItemEntity
{
    private int $id;

    private int $cartId;

    private int $productId;

    private int $quantity;

    private float $price;

    private float $subtotal;

    private array $attributes = [];

    public function __construct(
        int $id,
        int $cartId,
        int $productId,
        int $quantity,
        float $price,
        float $subtotal = 0,
        array $attributes = []
    ) {
        $this->id = $id;
        $this->cartId = $cartId;
        $this->productId = $productId;
        $this->quantity = $quantity;
        $this->price = $price;
        $this->subtotal = $subtotal ?: ($price * $quantity);
        $this->attributes = $attributes;
    }

    // Getters
    public function getId(): int
    {
        return $this->id;
    }

    public function getCartId(): int
    {
        return $this->cartId;
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

    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
