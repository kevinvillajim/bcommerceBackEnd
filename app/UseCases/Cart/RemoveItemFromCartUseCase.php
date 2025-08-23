<?php

namespace App\UseCases\Cart;

use App\Domain\Repositories\ShoppingCartRepositoryInterface;

class RemoveItemFromCartUseCase
{
    private ShoppingCartRepositoryInterface $cartRepository;

    public function __construct(ShoppingCartRepositoryInterface $cartRepository)
    {
        $this->cartRepository = $cartRepository;
    }

    public function execute(int $userId, int $itemId): array
    {
        // Obtener carrito del usuario
        $cart = $this->cartRepository->findByUserId($userId);

        if (! $cart) {
            throw new \Exception('Carrito no encontrado');
        }

        // Verificar que el item pertenece a este carrito
        $itemExists = false;
        foreach ($cart->getItems() as $item) {
            if ($item->getId() === $itemId) {
                $itemExists = true;
                break;
            }
        }

        if (! $itemExists) {
            throw new \Exception('Item no encontrado en el carrito');
        }

        // Eliminar el item
        $removed = $this->cartRepository->removeItem($cart->getId(), $itemId);

        if (! $removed) {
            throw new \Exception('No se pudo eliminar el item del carrito');
        }

        // Obtener carrito actualizado
        $updatedCart = $this->cartRepository->findByUserId($userId);

        return [
            'success' => true,
            'cart' => $updatedCart,
        ];
    }
}
