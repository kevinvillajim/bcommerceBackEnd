<?php

namespace App\Listeners;

use App\Events\ShippingDelayed;
use App\Infrastructure\Services\NotificationService;
use App\Models\Seller;
use App\Models\Shipping;
use Illuminate\Support\Facades\Log;

class NotifySellerOfShippingDelay
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     */
    public function handle(ShippingDelayed $event): void
    {
        try {
            $shipping = Shipping::find($event->shippingId);
            if (! $shipping) {
                Log::error('Shipping not found for notification', ['shipping_id' => $event->shippingId]);

                return;
            }

            $seller = Seller::find($event->sellerId);
            if (! $seller) {
                Log::error('Seller not found for notification', ['seller_id' => $event->sellerId]);

                return;
            }

            // Create notification directly with the seller's user_id
            $this->notificationService->createNotification(
                $seller->user_id,
                'shipping_delay',
                'Shipping delay detected',
                'A shipping has not been updated for several days',
                [
                    'shipping_id' => $shipping->id,
                    'order_id' => $shipping->order_id,
                    'tracking_number' => $shipping->tracking_number,
                    'days_without_update' => $event->daysWithoutUpdate,
                ]
            );

            // Notificar a administradores
            $this->notificationService->notifyAdminShippingDelay($shipping, $event->daysWithoutUpdate);

        } catch (\Exception $e) {
            Log::error('Error sending seller shipping delay notification', [
                'error' => $e->getMessage(),
                'shipping_id' => $event->shippingId,
                'seller_id' => $event->sellerId,
            ]);
        }
    }
}
