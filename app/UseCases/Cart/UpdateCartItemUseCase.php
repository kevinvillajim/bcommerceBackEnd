<?php

namespace App\UseCases\Cart;

use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\ShoppingCartRepositoryInterface;

class UpdateCartItemUseCase
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

    public function execute(int $userId, int $itemId, int $quantity): array
    {
        // Verificar cantidad válida
        if ($quantity <= 0) {
            throw new \Exception('La cantidad debe ser mayor a cero');
        }

        // Obtener carrito del usuario
        $cart = $this->cartRepository->findByUserId($userId);

        if (! $cart) {
            throw new \Exception('Carrito no encontrado');
        }

        // Verificar que el item pertenece a este carrito y obtener el producto ID
        $productId = null;
        foreach ($cart->getItems() as $item) {
            if ($item->getId() === $itemId) {
                $productId = $item->getProductId();
                break;
            }
        }

        if (! $productId) {
            throw new \Exception('Item no encontrado en el carrito');
        }

        // Verificar stock del producto
        $product = $this->productRepository->findById($productId);

        if ($product->getStock() < $quantity) {
            throw new \Exception('Stock insuficiente');
        }

        // Actualizar la cantidad
        $updated = $this->cartRepository->updateItemQuantity($cart->getId(), $itemId, $quantity);

        if (! $updated) {
            throw new \Exception('No se pudo actualizar el item');
        }

        // Obtener carrito actualizado
        $updatedCart = $this->cartRepository->findByUserId($userId);

        return [
            'success' => true,
            'cart' => $updatedCart,
        ];
    }
}
