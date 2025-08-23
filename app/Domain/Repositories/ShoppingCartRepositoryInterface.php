<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\CartItemEntity;
use App\Domain\Entities\ShoppingCartEntity;

interface ShoppingCartRepositoryInterface
{
    public function findByUserId(int $userId): ?ShoppingCartEntity;

    public function save(ShoppingCartEntity $cart): ShoppingCartEntity;

    public function addItem(int $cartId, CartItemEntity $item): CartItemEntity;

    public function removeItem(int $cartId, int $itemId): bool;

    public function updateItemQuantity(int $cartId, int $itemId, int $quantity): bool;

    public function clearCart(int $cartId): bool;

    public function deleteCart(int $cartId): bool;
}
