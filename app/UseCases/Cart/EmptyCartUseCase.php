<?php

namespace App\UseCases\Cart;

use App\Domain\Repositories\ShoppingCartRepositoryInterface;

class EmptyCartUseCase
{
    private ShoppingCartRepositoryInterface $cartRepository;

    public function __construct(ShoppingCartRepositoryInterface $cartRepository)
    {
        $this->cartRepository = $cartRepository;
    }

    public function execute(int $userId): array
    {
        $cart = $this->cartRepository->findByUserId($userId);

        if (! $cart) {
            throw new \Exception('Carrito no encontrado');
        }

        $cleared = $this->cartRepository->clearCart($cart->getId());

        if (! $cleared) {
            throw new \Exception('No se pudo vaciar el carrito');
        }

        // Obtener carrito actualizado (ahora vacío)
        $updatedCart = $this->cartRepository->findByUserId($userId);

        return [
            'success' => true,
            'cart' => $updatedCart,
        ];
    }
}
