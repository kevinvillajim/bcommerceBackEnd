<?php

namespace App\UseCases\Order;

use App\Domain\Repositories\OrderRepositoryInterface;
use App\Events\OrderPaid;
use App\Events\OrderStatusChanged;

class ProcessPaymentUseCase
{
    private OrderRepositoryInterface $orderRepository;

    public function __construct(
        OrderRepositoryInterface $orderRepository
    ) {
        $this->orderRepository = $orderRepository;
    }

    /**
     * Procesa el pago de un pedido
     *
     * @throws \Exception
     */
    public function execute(int $orderId, array $paymentData): bool
    {
        // Verificar que la orden exista
        $order = $this->orderRepository->findById($orderId);

        if (! $order) {
            throw new \Exception('El pedido no existe');
        }

        // Verificar que el pedido esté pendiente de pago
        if ($order->getPaymentStatus() === 'completed') {
            throw new \Exception('El pedido ya ha sido pagado');
        }

        // Información para actualizar
        $paymentInfo = [
            'payment_id' => $paymentData['payment_id'] ?? null,
            'payment_method' => $paymentData['payment_method'] ?? null,
            'payment_status' => $paymentData['payment_status'] ?? 'completed',
        ];

        // Si el pago es exitoso, cambiamos el estado del pedido a "paid"
        if ($paymentInfo['payment_status'] === 'completed') {
            $paymentInfo['status'] = 'paid';
        }

        // Guardar el estado anterior para el evento
        $previousStatus = $order->getStatus();

        // Actualizar la información de pago
        $success = $this->orderRepository->updatePaymentInfo($orderId, $paymentInfo);

        if (! $success) {
            throw new \Exception('No se pudo actualizar la información de pago');
        }

        // Si el pago es exitoso, disparar eventos correspondientes
        if ($paymentInfo['payment_status'] === 'completed') {
            // Disparar evento de cambio de estado
            event(new OrderStatusChanged($orderId, $previousStatus, 'paid', 'main_order'));

            // Disparar evento de pago completado
            $order = $this->orderRepository->findById($orderId); // Refrescar la orden
            event(new OrderPaid($orderId, $order->getSellerId(), $order->getTotal()));
        }

        return true;
    }
}
