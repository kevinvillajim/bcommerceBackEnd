<?php

namespace App\Listeners;

use App\Events\ProductStockUpdated;
use App\Infrastructure\Services\NotificationService;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class NotifySellerOfLowStock
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     */
    public function handle(ProductStockUpdated $event): void
    {
        try {
            $product = Product::find($event->productId);
            if (! $product) {
                Log::error('Product not found for notification', ['product_id' => $event->productId]);

                return;
            }

            // Default threshold is 5
            $threshold = config('app.low_stock_threshold', 5);

            // Only notify if the new stock is below or equal to the threshold
            if ($event->newStock <= $threshold) {
                $this->notificationService->notifyLowStockToSeller($product, $threshold);
            }
        } catch (\Exception $e) {
            Log::error('Error sending seller low stock notification', [
                'error' => $e->getMessage(),
                'product_id' => $event->productId,
            ]);
        }
    }
}
