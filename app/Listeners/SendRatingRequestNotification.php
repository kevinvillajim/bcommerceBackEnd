<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Events\ShippingStatusUpdated;
use App\Infrastructure\Services\NotificationService;
use App\Jobs\SendRatingReminderJob;
use App\Models\Order;
use App\Models\Shipping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendRatingRequestNotification
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle multiple events: OrderCompleted and ShippingStatusUpdated
     */
    public function handle($event): void
    {
        if ($event instanceof OrderCompleted) {
            $this->handleOrderCompleted($event);
        } elseif ($event instanceof ShippingStatusUpdated) {
            $this->handleShippingDelivered($event);
        }
    }

    /**
     * Handle the OrderCompleted event (CÓDIGO EXISTENTE)
     */
    private function handleOrderCompleted(OrderCompleted $event): void
    {
        try {
            $order = Order::find($event->orderId);
            if (! $order) {
                Log::error('Orden no encontrada para enviar solicitud de valoración', ['order_id' => $event->orderId]);

                return;
            }

            // Verificar duplicados antes de enviar notificación
            if (! $this->notificationService->hasNotification($order->id, 'rating_request')) {
                // Enviar notificación al usuario
                $this->notificationService->sendRatingRequestNotification(
                    $order->user_id,
                    $order->id,
                    $order->order_number
                );

                // Programar recordatorio para 7 días después si no ha valorado
                $this->scheduleRatingReminder($order);
            }
        } catch (\Exception $e) {
            Log::error('Error enviando solicitud de valoración', [
                'error' => $e->getMessage(),
                'order_id' => $event->orderId,
            ]);
        }
    }

    /**
     * 🔧 CORREGIDO: Handle the ShippingStatusUpdated event with anti-duplication
     */
    private function handleShippingDelivered(ShippingStatusUpdated $event): void
    {
        try {
            // Solo procesar si el estado es "delivered"
            if ($event->currentStatus !== 'delivered') {
                return;
            }

            // 🛡️ ANTI-DUPLICACIÓN: Crear clave única para este evento de rating
            $cacheKey = "rating_request_{$event->shippingId}_delivered";

            if (Cache::has($cacheKey)) {
                Log::info('⚠️ SendRatingRequestNotification: Rating duplicado detectado y bloqueado', [
                    'shipping_id' => $event->shippingId,
                    'cache_key' => $cacheKey,
                ]);

                return;
            }

            // Marcar como procesado por 30 minutos (más tiempo para ratings)
            Cache::put($cacheKey, true, 1800);

            $shipping = Shipping::with(['order.items.product'])->find($event->shippingId);
            if (! $shipping) {
                Log::error('❌ Shipping no encontrado para notificación de rating', [
                    'shipping_id' => $event->shippingId,
                ]);

                return;
            }

            $order = $shipping->order;
            if (! $order) {
                Log::error('❌ Orden no encontrada para notificación de rating', [
                    'shipping_id' => $event->shippingId,
                ]);

                return;
            }

            Log::info('🎆 Generando ratings para productos entregados', [
                'order_id' => $order->id,
                'tracking_number' => $shipping->tracking_number,
            ]);

            // Enviar notificación general de orden entregada
            $shippingData = [
                'tracking_number' => $shipping->tracking_number,
                'carrier' => $shipping->carrier ?? null,
                'delivery_date' => now()->toDateTimeString(),
            ];

            $this->notificationService->sendDeliveredOrderRatingNotification(
                $order->user_id,
                $order->id,
                $order->order_number,
                $shippingData
            );

            Log::info('✅ Notificación de rating por orden entregada enviada', [
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'tracking_number' => $shipping->tracking_number,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error general enviando notificaciones de rating por entrega', [
                'error' => $e->getMessage(),
                'shipping_id' => $event->shippingId ?? 'unknown',
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Programar recordatorio de valoración (CÓDIGO EXISTENTE)
     */
    private function scheduleRatingReminder(Order $order): void
    {
        // Programar job para ejecutarse en 7 días
        SendRatingReminderJob::dispatch($order->user_id, $order->id, $order->order_number)
            ->delay(now()->addDays(7));
    }
}
