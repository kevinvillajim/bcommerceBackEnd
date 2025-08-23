<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Infrastructure\Services\NotificationService;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendOrderStatusNotification
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * 🔧 CORREGIDO: Handle the event with anti-duplication protection
     */
    public function handle(OrderStatusChanged $event): void
    {
        try {
            // Solo procesar eventos de tipo 'order', no 'seller_order'
            if ($event->orderType === 'seller_order') {
                return;
            }

            // 🛡️ ANTI-DUPLICACIÓN: Crear clave única para este evento
            $cacheKey = "order_notification_{$event->orderId}_{$event->currentStatus}";

            if (Cache::has($cacheKey)) {
                Log::info('⚠️ SendOrderStatusNotification: Evento duplicado detectado y bloqueado', [
                    'order_id' => $event->orderId,
                    'status' => $event->currentStatus,
                    'cache_key' => $cacheKey,
                ]);

                return;
            }

            // Marcar como procesado por 10 minutos
            Cache::put($cacheKey, true, 600);

            Log::info('📎 SendOrderStatusNotification: Procesando OrderStatusChanged event', [
                'order_id' => $event->orderId,
                'previous_status' => $event->previousStatus,
                'current_status' => $event->currentStatus,
            ]);

            $order = Order::find($event->orderId);
            if (! $order) {
                Log::error('❌ Order not found for notification', ['order_id' => $event->orderId]);

                return;
            }

            // Enviar notificación de cambio de estado
            $this->notificationService->notifyOrderStatusChange($order, $event->previousStatus);

            // Generar notificación de rating cuando se entrega
            if ($event->currentStatus === 'delivered' && ! $this->notificationService->hasNotification($order->id, 'rating_request')) {
                $this->notificationService->sendRatingRequestNotification(
                    $order->user_id,
                    $order->id,
                    $order->order_number
                );

                Log::info('Rating request notification sent for delivered order', [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                ]);
            }

            Log::info('✅ Notificación de order status enviada exitosamente', [
                'order_id' => $event->orderId,
                'status' => $event->currentStatus,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error sending order status notification', [
                'error' => $e->getMessage(),
                'order_id' => $event->orderId,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
