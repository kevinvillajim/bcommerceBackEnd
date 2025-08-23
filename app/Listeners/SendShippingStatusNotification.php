<?php

namespace App\Listeners;

use App\Events\ShippingStatusUpdated;
use App\Infrastructure\Services\NotificationService;
use App\Models\Shipping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendShippingStatusNotification
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * 🔧 CORREGIDO: Handle the event with anti-duplication protection
     */
    public function handle(ShippingStatusUpdated $event): void
    {
        try {
            // 🛡️ ANTI-DUPLICACIÓN: Crear clave única para este evento
            $cacheKey = "shipping_notification_{$event->shippingId}_{$event->currentStatus}";

            if (Cache::has($cacheKey)) {
                Log::info('⚠️ SendShippingStatusNotification: Evento duplicado detectado y bloqueado', [
                    'shipping_id' => $event->shippingId,
                    'status' => $event->currentStatus,
                    'cache_key' => $cacheKey,
                ]);

                return;
            }

            // Marcar como procesado por 10 minutos
            Cache::put($cacheKey, true, 600);

            Log::info('📦 SendShippingStatusNotification: Procesando ShippingStatusUpdated event', [
                'shipping_id' => $event->shippingId,
                'previous_status' => $event->previousStatus,
                'current_status' => $event->currentStatus,
            ]);

            $shipping = Shipping::find($event->shippingId);
            if (! $shipping) {
                Log::error('❌ Shipping not found for notification', ['shipping_id' => $event->shippingId]);

                return;
            }

            $this->notificationService->notifyShippingUpdate($shipping, $event->previousStatus);

            Log::info('✅ Notificación de shipping enviada exitosamente', [
                'shipping_id' => $event->shippingId,
                'status' => $event->currentStatus,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error sending shipping status notification', [
                'error' => $e->getMessage(),
                'shipping_id' => $event->shippingId,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
