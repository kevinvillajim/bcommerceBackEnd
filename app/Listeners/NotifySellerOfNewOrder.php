<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Infrastructure\Services\NotificationService;
use App\Models\Order;
use App\Models\Seller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NotifySellerOfNewOrder
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle OrderCreated event - Notifica al vendedor inmediatamente cuando se crea la orden
     */
    public function handle(OrderCreated $event): void
    {
        try {
            // 🛡️ ANTI-DUPLICACIÓN: Verificar si este evento ya se procesó recientemente
            $cacheKey = "order_notification_{$event->orderId}";

            if (Cache::has($cacheKey)) {
                Log::info('⚠️ NotifySellerOfNewOrder: Evento duplicado detectado y bloqueado', [
                    'order_id' => $event->orderId,
                    'cache_key' => $cacheKey,
                ]);

                return;
            }

            // Marcar como procesado por 5 minutos
            Cache::put($cacheKey, true, 300);

            Log::info('🔔 NotifySellerOfNewOrder: Procesando OrderCreated event', [
                'order_id' => $event->orderId,
                'user_id' => $event->userId,
                'seller_id' => $event->sellerId,
            ]);

            $order = Order::find($event->orderId);
            if (! $order) {
                Log::error('❌ Order not found for new order notification', ['order_id' => $event->orderId]);

                return;
            }

            // 🔧 MEJORADO: Soporte para órdenes multi-seller
            if ($event->sellerId) {
                // Orden de un solo vendedor
                $this->notifySingleSeller($order, $event->sellerId);
            } else {
                // Orden multi-seller - notificar a cada vendedor por sus productos
                $this->notifyMultipleSellers($order);
            }

        } catch (\Exception $e) {
            Log::error('❌ Error sending seller new order notification', [
                'error' => $e->getMessage(),
                'order_id' => $event->orderId,
                'seller_id' => $event->sellerId,
            ]);
        }
    }

    /**
     * Notificar a un solo vendedor
     */
    private function notifySingleSeller(Order $order, int $sellerId): void
    {
        $seller = Seller::find($sellerId);
        if (! $seller) {
            Log::error('❌ Seller not found for notification', ['seller_id' => $sellerId]);

            return;
        }

        Log::info('📧 Enviando notificación de nueva orden a vendedor', [
            'seller_id' => $sellerId,
            'seller_user_id' => $seller->user_id,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'total' => $order->total,
        ]);

        $this->notificationService->notifyNewOrderToSeller($order);

        Log::info('✅ Notificación de nueva orden enviada exitosamente', [
            'seller_id' => $sellerId,
            'order_id' => $order->id,
        ]);
    }

    /**
     * Notificar a múltiples vendedores (orden multi-seller)
     */
    private function notifyMultipleSellers(Order $order): void
    {
        Log::info('🏪 Procesando orden multi-seller', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ]);

        // Obtener todos los items de la orden agrupados por vendedor
        $itemsBySeller = [];
        foreach ($order->items as $item) {
            $sellerId = $item->product->user_id ?? null;
            if ($sellerId) {
                $seller = Seller::where('user_id', $sellerId)->first();
                if ($seller) {
                    if (! isset($itemsBySeller[$seller->id])) {
                        $itemsBySeller[$seller->id] = [
                            'seller' => $seller,
                            'items' => [],
                            'subtotal' => 0,
                        ];
                    }
                    $itemsBySeller[$seller->id]['items'][] = $item;
                    $itemsBySeller[$seller->id]['subtotal'] += ($item->price * $item->quantity);
                }
            }
        }

        Log::info('📊 Items agrupados por vendedor', [
            'order_id' => $order->id,
            'sellers_count' => count($itemsBySeller),
            'sellers' => array_keys($itemsBySeller),
        ]);

        // Notificar a cada vendedor por separado
        foreach ($itemsBySeller as $sellerId => $sellerData) {
            try {
                $seller = $sellerData['seller'];
                $items = $sellerData['items'];
                $subtotal = $sellerData['subtotal'];

                Log::info('📧 Enviando notificación multi-seller', [
                    'seller_id' => $sellerId,
                    'seller_name' => $seller->store_name,
                    'items_count' => count($items),
                    'subtotal' => $subtotal,
                ]);

                // Crear una orden "virtual" para este vendedor específico
                $sellerOrder = clone $order;
                $sellerOrder->seller_id = $sellerId;
                $sellerOrder->total = $subtotal;

                $this->notificationService->createNotification(
                    $seller->user_id,
                    'new_order',
                    "Nuevo pedido #{$order->order_number}",
                    'Has recibido un nuevo pedido con '.count($items).' producto(s) por un monto de $'.number_format($subtotal, 2).'.',
                    [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'total' => $subtotal,
                        'original_total' => $order->total,
                        'user_id' => $order->user_id,
                        'seller_id' => $sellerId,
                        'items_count' => count($items),
                        'is_multi_seller' => true,
                        'action_url' => '/seller/orders',
                        'priority' => 'high',
                    ]
                );

                Log::info('✅ Notificación multi-seller enviada', [
                    'seller_id' => $sellerId,
                    'order_id' => $order->id,
                ]);

            } catch (\Exception $e) {
                Log::error('❌ Error notificando vendedor en orden multi-seller', [
                    'seller_id' => $sellerId,
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
