<?php

namespace App\UseCases\Cart;

use App\Domain\Repositories\ShoppingCartRepositoryInterface;

class GetCartUseCase
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
            // Crear un nuevo carrito vacío
            $cart = new \App\Domain\Entities\ShoppingCartEntity(
                0,
                $userId,
                [],
                0
            );
            $cart = $this->cartRepository->save($cart);
        }

        return [
            'cart' => $cart,
        ];
    }
}
