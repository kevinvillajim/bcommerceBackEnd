<?php

namespace App\UseCases\Order;

use App\Domain\Repositories\OrderRepositoryInterface;

class UserOrdersByProductUseCase
{
    private OrderRepositoryInterface $orderRepository;

    public function __construct(OrderRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    /**
     * Obtener pedidos de un usuario que contienen un producto específico
     */
    public function execute(int $userId, int $productId, int $limit = 10, int $offset = 0): array
    {
        $orders = $this->orderRepository->getOrdersWithProductForUser($userId, $productId);

        // Apply pagination manually since the interface method doesn't support it
        return array_slice($orders, $offset, $limit);
    }
}
