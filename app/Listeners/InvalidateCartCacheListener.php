<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class InvalidateCartCacheListener
{
    /**
     * Handle the event.
     */
    public function handle(OrderCreated $event): void
    {
        try {
            Log::info('🛒 INVALIDANDO CACHE DEL CARRITO', [
                'user_id' => $event->userId,
                'order_id' => $event->orderId,
                'reason' => 'Order created successfully',
            ]);

            // Invalidar diferentes tipos de cache del carrito que pueda tener el frontend
            $cacheKeys = [
                "cart_items_user_{$event->userId}",
                "cart_count_user_{$event->userId}",
                "cart_total_user_{$event->userId}",
                "user_cart_{$event->userId}",
                "shopping_cart_{$event->userId}",
                "header_cart_{$event->userId}",
                "cart_summary_{$event->userId}",
            ];

            foreach ($cacheKeys as $key) {
                if (Cache::has($key)) {
                    Cache::forget($key);
                    Log::debug("✅ Cache invalidado: {$key}");
                }
            }

            // También invalidar cualquier cache con patrón wildcard si el sistema lo soporta
            try {
                // Intentar invalidar patrones de cache (depende del driver de cache)
                Cache::tags(["user_cart_{$event->userId}", 'cart'])->flush();
            } catch (\Exception $e) {
                // Si no soporta tags, no hay problema
                Log::debug('Cache tags no soportado, continuando...');
            }

            Log::info('✅ Cache del carrito invalidado exitosamente', [
                'user_id' => $event->userId,
                'keys_processed' => count($cacheKeys),
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error invalidando cache del carrito', [
                'user_id' => $event->userId,
                'order_id' => $event->orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // No lanzar excepción para no afectar el flujo principal
        }
    }
}
