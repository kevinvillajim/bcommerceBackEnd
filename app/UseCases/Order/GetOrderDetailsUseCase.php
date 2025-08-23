<?php

namespace App\UseCases\Order;

use App\Domain\Repositories\OrderRepositoryInterface;

class GetOrderDetailsUseCase
{
    private OrderRepositoryInterface $orderRepository;

    public function __construct(
        OrderRepositoryInterface $orderRepository
    ) {
        $this->orderRepository = $orderRepository;
    }

    /**
     * Ejecuta el caso de uso para obtener los detalles completos de un pedido
     *
     * @throws \Exception
     */
    public function execute(int $orderId): array
    {
        // Verificar que la orden exista
        $order = $this->orderRepository->findById($orderId);

        if (! $order) {
            throw new \Exception('El pedido no existe');
        }

        // Obtener detalles completos incluyendo información de productos, usuario, etc.
        $orderDetails = $this->orderRepository->getOrderDetails($orderId);

        if (empty($orderDetails)) {
            throw new \Exception('No se pudieron obtener los detalles del pedido');
        }

        return $orderDetails;
    }
}
