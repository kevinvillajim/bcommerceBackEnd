<?php

namespace App\Services;

use App\Events\OrderCompleted;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * 🔧 NUEVO: Servicio para manejar cambios de estado de órdenes
 * Este servicio se encarga de disparar los eventos necesarios cuando cambia el estado de una orden
 */
class OrderStatusHandler
{
    /**
     * Manejar el cambio de estado de una orden
     */
    public function handleStatusChange(Order $order, string $newStatus, string $previousStatus): void
    {
        try {
            Log::info('Manejando cambio de estado de orden', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
            ]);

            // 🔧 CRÍTICO: Disparar evento OrderCompleted cuando la orden se marca como entregada o completada
            if (in_array($newStatus, ['delivered', 'completed']) && ! in_array($previousStatus, ['delivered', 'completed'])) {
                Log::info('Disparando evento OrderCompleted para solicitar valoraciones', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'user_id' => $order->user_id,
                ]);

                // Disparar el evento que activará las notificaciones de rating
                event(new OrderCompleted($order->id));
            }

            // Aquí puedes agregar otros eventos según el estado
            // Por ejemplo: OrderShipped, OrderCancelled, etc.

        } catch (\Exception $e) {
            Log::error('Error manejando cambio de estado de orden: '.$e->getMessage(), [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Marcar una orden como entregada y disparar eventos
     */
    public function markAsDelivered(int $orderId): bool
    {
        try {
            $order = Order::find($orderId);

            if (! $order) {
                Log::error('Orden no encontrada para marcar como entregada', ['order_id' => $orderId]);

                return false;
            }

            $previousStatus = $order->status;

            // Actualizar estado y fecha de entrega
            $order->status = 'delivered';
            $order->delivered_at = now();
            $order->save();

            Log::info('Orden marcada como entregada', [
                'order_id' => $orderId,
                'previous_status' => $previousStatus,
                'delivered_at' => $order->delivered_at,
            ]);

            // Manejar el cambio de estado (esto disparará los eventos)
            $this->handleStatusChange($order, 'delivered', $previousStatus);

            return true;
        } catch (\Exception $e) {
            Log::error('Error marcando orden como entregada: '.$e->getMessage(), [
                'order_id' => $orderId,
            ]);

            return false;
        }
    }

    /**
     * Marcar una orden como completada y disparar eventos
     */
    public function markAsCompleted(int $orderId): bool
    {
        try {
            $order = Order::find($orderId);

            if (! $order) {
                Log::error('Orden no encontrada para marcar como completada', ['order_id' => $orderId]);

                return false;
            }

            $previousStatus = $order->status;

            // Actualizar estado y fecha de completado
            $order->status = 'completed';
            $order->completed_at = now();
            $order->save();

            Log::info('Orden marcada como completada', [
                'order_id' => $orderId,
                'previous_status' => $previousStatus,
                'completed_at' => $order->completed_at,
            ]);

            // Manejar el cambio de estado (esto disparará los eventos)
            $this->handleStatusChange($order, 'completed', $previousStatus);

            return true;
        } catch (\Exception $e) {
            Log::error('Error marcando orden como completada: '.$e->getMessage(), [
                'order_id' => $orderId,
            ]);

            return false;
        }
    }

    /**
     * Auto-completar órdenes entregadas hace más de X días
     *
     * @return int Número de órdenes auto-completadas
     */
    public function autoCompleteDeliveredOrders(int $daysThreshold = 7): int
    {
        try {
            $cutoffDate = now()->subDays($daysThreshold);

            // Buscar órdenes entregadas hace más de X días que no estén completadas
            $ordersToComplete = Order::where('status', 'delivered')
                ->where('delivered_at', '<=', $cutoffDate)
                ->whereNull('completed_at')
                ->get();

            Log::info('Auto-completando órdenes entregadas', [
                'threshold_days' => $daysThreshold,
                'cutoff_date' => $cutoffDate,
                'orders_found' => $ordersToComplete->count(),
            ]);

            $completedCount = 0;

            foreach ($ordersToComplete as $order) {
                if ($this->markAsCompleted($order->id)) {
                    $completedCount++;
                }
            }

            Log::info('Auto-completado de órdenes finalizado', [
                'orders_processed' => $ordersToComplete->count(),
                'orders_completed' => $completedCount,
            ]);

            return $completedCount;
        } catch (\Exception $e) {
            Log::error('Error en auto-completado de órdenes: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(string $orderId, string $status): void
    {
        try {
            $order = Order::find($orderId);

            if (! $order) {
                Log::error('Order not found for status update', ['order_id' => $orderId]);

                return;
            }

            $previousStatus = $order->status;
            $order->status = $status;
            $order->save();

            $this->handleStatusChange($order, $status, $previousStatus);
        } catch (\Exception $e) {
            Log::error('Error updating order status: '.$e->getMessage(), [
                'order_id' => $orderId,
                'status' => $status,
            ]);
        }
    }
}
