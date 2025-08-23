<?php

namespace App\Listeners;

use App\Events\SellerRankChanged;
use App\Infrastructure\Services\NotificationService;
use App\Models\Seller;
use Illuminate\Support\Facades\Log;

class NotifyAdminSellerRankUp
{
    private NotificationService $notificationService;

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
                Log::error('Seller not found for notification', ['seller_id' => $event->sellerId]);

                return;
            }

            $this->notificationService->notifyAdminSellerRankUp($seller, $event->oldRank, $event->newRank);
        } catch (\Exception $e) {
            Log::error('Error sending admin seller rank up notification', [
                'error' => $e->getMessage(),
                'seller_id' => $event->sellerId,
            ]);
        }
    }
}
