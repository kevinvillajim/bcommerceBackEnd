<?php

namespace App\UseCases\Order;

use App\Domain\Repositories\OrderRepositoryInterface;

class UserOrdersUseCase
{
    private OrderRepositoryInterface $orderRepository;

    public function __construct(OrderRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    /**
     * Obtener pedidos de un usuario
     */
    public function execute(int $userId, int $limit = 10, int $offset = 0): array
    {
        return $this->orderRepository->getOrdersForUser($userId, $limit, $offset);
    }
}
