<?php

namespace App\UseCases\Order;

use App\Domain\Repositories\OrderRepositoryInterface;

class UserOrderStatsUseCase
{
    private OrderRepositoryInterface $orderRepository;

    public function __construct(OrderRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    /**
     * Obtener estadísticas de pedidos de un usuario
     */
    public function execute(int $userId): array
    {
        $totalOrders = $this->orderRepository->countTotalOrdersForUser($userId);
        $totalSpent = $this->orderRepository->getTotalSpentForUser($userId);

        return [
            'totalOrders' => $totalOrders,
            'totalSpent' => $totalSpent,
        ];
    }
}
