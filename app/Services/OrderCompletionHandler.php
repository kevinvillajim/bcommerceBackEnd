<?php

namespace App\Services;

use App\Events\OrderCompleted;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para manejar la finalización de órdenes y disparar eventos
 */
class OrderCompletionHandler
{
    /**
     * Marcar una orden como completada y disparar el evento correspondiente
     */
    public function markOrderAsCompleted(int $orderId): bool
    {
        try {
            $order = Order::find($orderId);

            if (! $order) {
                Log::error('Orden no encontrada para completar', ['order_id' => $orderId]);

                return false;
            }

            // Verificar que la orden esté en un estado que permita completarla
            $allowedStatuses = ['delivered', 'shipped'];
            if (! in_array($order->status, $allowedStatuses)) {
                Log::warning('Orden no puede ser completada desde su estado actual', [
                    'order_id' => $orderId,
                    'current_status' => $order->status,
                ]);

                return false;
            }

            // Actualizar el estado a completado
            $order->status = 'completed';
            $order->completed_at = now();
            $order->save();

            // 🔥 IMPORTANTE: Disparar el evento OrderCompleted
            Log::info('Disparando evento OrderCompleted', ['order_id' => $orderId]);
            event(new OrderCompleted($orderId));

            return true;
        } catch (\Exception $e) {
            Log::error('Error al completar orden: '.$e->getMessage(), ['order_id' => $orderId]);

            return false;
        }
    }

    /**
     * Autocompletar órdenes entregadas después de X días
     * Este método puede ser llamado desde un comando programado
     */
    public function autoCompleteDeliveredOrders(int $daysAfterDelivery = 7): int
    {
        try {
            $completedCount = 0;

            // Buscar órdenes entregadas hace más de X días que no han sido completadas
            $ordersToComplete = $this->findDeliveredOrdersOlderThan($daysAfterDelivery);

            foreach ($ordersToComplete as $order) {
                if ($this->markOrderAsCompleted($order->id)) {
                    $completedCount++;
                    Log::info('Orden auto-completada', [
                        'order_id' => $order->id,
                        'days_since_delivery' => $daysAfterDelivery,
                    ]);
                }
            }

            if ($completedCount > 0) {
                Log::info('Auto-completadas órdenes entregadas', [
                    'count' => $completedCount,
                    'days_threshold' => $daysAfterDelivery,
                ]);
            }

            return $completedCount;
        } catch (\Exception $e) {
            Log::error('Error en auto-completado de órdenes: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Marcar como entregado y programar auto-completado
     */
    public function markOrderAsDelivered(int $orderId): bool
    {
        try {
            $order = Order::find($orderId);

            if (! $order) {
                Log::error('Orden no encontrada para marcar como entregada', ['order_id' => $orderId]);

                return false;
            }

            // Actualizar estado a entregado
            $order->status = 'delivered';
            $order->delivered_at = now();
            $order->save();

            Log::info('Orden marcada como entregada', [
                'order_id' => $orderId,
                'delivered_at' => now(),
            ]);

            // Nota: Aquí podrías programar un job para auto-completar en X días
            // dispatch(new AutoCompleteOrderJob($orderId))->delay(now()->addDays(7));

            return true;
        } catch (\Exception $e) {
            Log::error('Error al marcar orden como entregada: '.$e->getMessage(), ['order_id' => $orderId]);

            return false;
        }
    }

    /**
     * 🔧 NUEVO: Buscar órdenes entregadas hace más de X días que no han sido completadas
     */
    private function findDeliveredOrdersOlderThan(int $days): \Illuminate\Database\Eloquent\Collection
    {
        try {
            $cutoffDate = now()->subDays($days);

            return Order::where('status', 'delivered')
                ->where('delivered_at', '<=', $cutoffDate)
                ->whereNotIn('status', ['completed', 'cancelled', 'refunded'])
                ->get();
        } catch (\Exception $e) {
            Log::error('Error buscando órdenes entregadas antiguas: '.$e->getMessage());

            return collect();
        }
    }

    /**
     * 🔧 NUEVO: Verificar si una orden puede ser completada automáticamente
     */
    public function canOrderBeAutoCompleted(int $orderId): bool
    {
        try {
            $order = Order::find($orderId);

            if (! $order) {
                return false;
            }

            // Solo órdenes entregadas pueden ser auto-completadas
            if ($order->status !== 'delivered') {
                return false;
            }

            // Verificar que han pasado suficientes días desde la entrega
            if (! $order->delivered_at) {
                return false;
            }

            $daysSinceDelivery = now()->diffInDays($order->delivered_at);

            return $daysSinceDelivery >= 7; // 7 días por defecto

        } catch (\Exception $e) {
            Log::error('Error verificando si orden puede ser auto-completada: '.$e->getMessage());

            return false;
        }
    }

    /**
     * 🔧 NUEVO: Obtener órdenes candidatas para auto-completar
     */
    public function getOrdersCandidatesForAutoCompletion(): \Illuminate\Database\Eloquent\Collection
    {
        try {
            return Order::where('status', 'delivered')
                ->whereNotNull('delivered_at')
                ->where('delivered_at', '<=', now()->subDays(7))
                ->get();
        } catch (\Exception $e) {
            Log::error('Error obteniendo candidatos para auto-completar: '.$e->getMessage());

            return collect();
        }
    }
}
