<?php

namespace App\Listeners;

use App\Events\SellerRankChanged;
use App\Infrastructure\Services\NotificationService;
use App\Models\Seller;
use Illuminate\Support\Facades\Log;

class NotifySellerRankChanged
{
    private NotificationService $notificationService;

    /**
     * Create the event listener.
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     */
    public function handle(SellerRankChanged $event): void
    {
        try {
            $seller = Seller::find($event->sellerId);
            if (! $seller) {
                Log::error('Seller not found for rank change notification', ['seller_id' => $event->sellerId]);

                return;
            }

            $notification = $this->notificationService->notifySellerRankChanged($seller, $event->oldRank, $event->newRank);

            if ($notification) {
                Log::info('Notification sent to seller about rank change', [
                    'seller_id' => $event->sellerId,
                    'old_rank' => $event->oldRank,
                    'new_rank' => $event->newRank,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error sending seller rank change notification', [
                'error' => $e->getMessage(),
                'seller_id' => $event->sellerId,
                'old_rank' => $event->oldRank,
                'new_rank' => $event->newRank,
            ]);
        }
    }
}
