<?php

namespace App\UseCases\Order;

use App\Domain\Repositories\OrderRepositoryInterface;

class ReorderPreviousPurchaseUseCase
{
    private OrderRepositoryInterface $orderRepository;

    private CreateOrderUseCase $createOrderUseCase;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        CreateOrderUseCase $createOrderUseCase
    ) {
        $this->orderRepository = $orderRepository;
        $this->createOrderUseCase = $createOrderUseCase;
    }

    /**
     * Crear un nuevo pedido basado en uno anterior
     *
     * @throws \Exception
     */
    public function execute(int $orderId, int $userId): array
    {
        // Verificar pedido original
        $order = $this->orderRepository->findById($orderId);

        if (! $order) {
            throw new \Exception('Pedido no encontrado');
        }

        if ($order->getUserId() !== $userId) {
            throw new \Exception('No autorizado para reordenar este pedido');
        }

        // Obtener detalles completos
        $orderDetails = $this->orderRepository->getOrderDetails($orderId);

        if (empty($orderDetails) || empty($orderDetails['items'])) {
            throw new \Exception('El pedido no tiene productos');
        }

        // Preparar datos para nuevo pedido
        $items = [];
        foreach ($orderDetails['items'] as $item) {
            $items[] = [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'subtotal' => $item['subtotal'],
            ];
        }

        $orderData = [
            'user_id' => $userId,
            'seller_id' => $order->getSellerId(),
            'items' => $items,
            'shipping_data' => $order->getShippingData(),
        ];

        // Crear nuevo pedido
        $newOrder = $this->createOrderUseCase->execute($orderData);

        return [
            'order_id' => $newOrder->getId(),
            'order_number' => $newOrder->getOrderNumber(),
        ];
    }
}
