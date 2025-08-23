<?php

namespace App\UseCases\Order;

use App\Domain\Repositories\OrderRepositoryInterface;
use App\Events\OrderStatusChanged;

class ConfirmOrderReceptionUseCase
{
    private OrderRepositoryInterface $orderRepository;

    public function __construct(OrderRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    /**
     * Confirmar la recepción de un pedido
     *
     * @throws \Exception
     */
    public function execute(int $orderId, int $userId): bool
    {
        // Obtener el pedido
        $order = $this->orderRepository->findById($orderId);

        if (! $order) {
            throw new \Exception('Pedido no encontrado');
        }

        // Verificar que pertenezca al usuario
        if ($order->getUserId() !== $userId) {
            throw new \Exception('No autorizado para confirmar este pedido');
        }

        // Verificar el estado
        $currentStatus = $order->getStatus();
        if ($currentStatus !== 'shipped' && $currentStatus !== 'out_for_delivery') {
            throw new \Exception('Este pedido no está en estado de envío');
        }

        // Actualizar a entregado
        $order->setStatus('delivered');
        $this->orderRepository->save($order);

        // Disparar evento
        event(new OrderStatusChanged($order->getId(), $currentStatus, 'delivered', 'main_order'));

        return true;
    }
}
