<?php

namespace App\UseCases\Cart;

use App\Domain\Entities\CartItemEntity;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\ShoppingCartRepositoryInterface;

class AddItemToCartUseCase
{
    private ShoppingCartRepositoryInterface $cartRepository;

    private ProductRepositoryInterface $productRepository;

    public function __construct(
        ShoppingCartRepositoryInterface $cartRepository,
        ProductRepositoryInterface $productRepository
    ) {
        $this->cartRepository = $cartRepository;
        $this->productRepository = $productRepository;
    }

    public function execute(int $userId, int $productId, int $quantity = 1, array $attributes = []): array
    {
        // Verificar que el producto existe y está disponible
        $product = $this->productRepository->findById($productId);
        if (! $product) {
            throw new \Exception('Producto no encontrado');
        }

        if ($product->getStock() < $quantity) {
            throw new \Exception('Stock insuficiente');
        }

        // Obtener o crear carrito para el usuario
        $cart = $this->cartRepository->findByUserId($userId);

        if (! $cart) {
            // Crear un nuevo carrito con ID temporal
            $cart = new \App\Domain\Entities\ShoppingCartEntity(
                0, // ID temporal
                $userId,
                [],
                0
            );
            $cart = $this->cartRepository->save($cart);
        }

        // Crear el item del carrito
        $cartItem = new CartItemEntity(
            0, // ID temporal
            $cart->getId(),
            $productId,
            $quantity,
            $product->getPrice(),
            $quantity * $product->getPrice(),
            $attributes
        );

        // Añadir el item al carrito
        $addedItem = $this->cartRepository->addItem($cart->getId(), $cartItem);

        // Refrescar el carrito completo
        $updatedCart = $this->cartRepository->findByUserId($userId);

        return [
            'cart' => $updatedCart,
            'item' => $addedItem,
        ];
    }
}
