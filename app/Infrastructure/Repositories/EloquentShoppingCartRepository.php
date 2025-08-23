<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\CartItemEntity;
use App\Domain\Entities\ShoppingCartEntity;
use App\Domain\Repositories\ShoppingCartRepositoryInterface;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ShoppingCart;
use Illuminate\Support\Facades\DB;

class EloquentShoppingCartRepository implements ShoppingCartRepositoryInterface
{
    public function findByUserId(int $userId): ?ShoppingCartEntity
    {
        $cart = ShoppingCart::where('user_id', $userId)
            ->with('items.product')
            ->first();

        if (! $cart) {
            return null;
        }

        $itemEntities = [];
        foreach ($cart->items as $item) {
            $itemEntities[] = new CartItemEntity(
                $item->id,
                $cart->id,
                $item->product_id,
                $item->quantity,
                $item->price,
                $item->subtotal,
                $item->attributes ? json_decode($item->attributes, true) : []
            );
        }

        return new ShoppingCartEntity(
            $cart->id,
            $cart->user_id,
            $itemEntities,
            $cart->total,
            new \DateTime($cart->created_at),
            new \DateTime($cart->updated_at)
        );
    }

    public function save(ShoppingCartEntity $cartEntity): ShoppingCartEntity
    {
        return DB::transaction(function () use ($cartEntity) {
            // Si el ID es 0, es un carrito nuevo
            if ($cartEntity->getId() === 0) {
                $cart = ShoppingCart::create([
                    'user_id' => $cartEntity->getUserId(),
                    'total' => $cartEntity->getTotal(),
                ]);
            } else {
                $cart = ShoppingCart::updateOrCreate(
                    ['id' => $cartEntity->getId()],
                    ['user_id' => $cartEntity->getUserId(), 'total' => $cartEntity->getTotal()]
                );
            }

            return new ShoppingCartEntity(
                $cart->id,
                $cart->user_id,
                $cartEntity->getItems(),
                $cart->total,
                new \DateTime($cart->created_at),
                new \DateTime($cart->updated_at)
            );
        });
    }

    public function addItem(int $cartId, CartItemEntity $itemEntity): CartItemEntity
    {
        return DB::transaction(function () use ($cartId, $itemEntity) {
            // Verificar que el producto existe y obtener su precio actual
            $product = Product::findOrFail($itemEntity->getProductId());

            // Verificar si el item ya existe en este carrito
            $existingItem = CartItem::where('cart_id', $cartId)
                ->where('product_id', $itemEntity->getProductId())
                ->first();

            if ($existingItem) {
                // Actualizar cantidad si ya existe
                $newQuantity = $existingItem->quantity + $itemEntity->getQuantity();
                $subtotal = $newQuantity * $product->price;

                $existingItem->update([
                    'quantity' => $newQuantity,
                    'subtotal' => $subtotal,
                ]);

                // Actualizar total del carrito
                $this->updateCartTotal($cartId);

                return new CartItemEntity(
                    $existingItem->id,
                    $cartId,
                    $existingItem->product_id,
                    $newQuantity,
                    $product->price,
                    $subtotal,
                    $existingItem->attributes ? json_decode($existingItem->attributes, true) : []
                );
            }

            // Crear nuevo item
            $item = CartItem::create([
                'cart_id' => $cartId,
                'product_id' => $itemEntity->getProductId(),
                'quantity' => $itemEntity->getQuantity(),
                'price' => $product->price,
                'subtotal' => $product->price * $itemEntity->getQuantity(),
                'attributes' => json_encode($itemEntity->getAttributes() ?? []),
            ]);

            // Actualizar total del carrito
            $this->updateCartTotal($cartId);

            return new CartItemEntity(
                $item->id,
                $item->cart_id,
                $item->product_id,
                $item->quantity,
                $item->price,
                $item->subtotal,
                $item->attributes ? json_decode($item->attributes, true) : []
            );
        });
    }

    public function removeItem(int $cartId, int $itemId): bool
    {
        $deleted = CartItem::where('cart_id', $cartId)
            ->where('id', $itemId)
            ->delete();

        if ($deleted) {
            $this->updateCartTotal($cartId);

            return true;
        }

        return false;
    }

    public function updateItemQuantity(int $cartId, int $itemId, int $quantity): bool
    {
        $item = CartItem::where('cart_id', $cartId)
            ->where('id', $itemId)
            ->first();

        if (! $item) {
            return false;
        }

        $item->quantity = $quantity;
        $item->subtotal = $item->price * $quantity;
        $item->save();

        $this->updateCartTotal($cartId);

        return true;
    }

    public function clearCart(int $cartId): bool
    {
        CartItem::where('cart_id', $cartId)->delete();

        ShoppingCart::where('id', $cartId)->update(['total' => 0]);

        return true;
    }

    public function deleteCart(int $cartId): bool
    {
        // Primero eliminar todos los items
        CartItem::where('cart_id', $cartId)->delete();

        // Luego eliminar el carrito
        return ShoppingCart::where('id', $cartId)->delete() > 0;
    }

    private function updateCartTotal(int $cartId): void
    {
        $total = CartItem::where('cart_id', $cartId)->sum('subtotal');
        ShoppingCart::where('id', $cartId)->update(['total' => $total]);
    }
}
