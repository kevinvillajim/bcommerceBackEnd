<?php

namespace App\Listeners;

use App\Events\ProductUpdated;
use App\Models\Product;
use App\UseCases\Notification\CheckProductUpdatesForUsersUseCase;
use Illuminate\Support\Facades\Log;

class SendProductUpdateNotifications
{
    private CheckProductUpdatesForUsersUseCase $checkProductUpdatesForUsersUseCase;

    public function __construct(CheckProductUpdatesForUsersUseCase $checkProductUpdatesForUsersUseCase)
    {
        $this->checkProductUpdatesForUsersUseCase = $checkProductUpdatesForUsersUseCase;
    }

    /**
     * Handle the event.
     */
    public function handle(ProductUpdated $event): void
    {
        try {
            $product = Product::find($event->productId);
            if (! $product) {
                Log::error('Product not found for notification', ['product_id' => $event->productId]);

                return;
            }

            $this->checkProductUpdatesForUsersUseCase->execute($product, $event->changes);
        } catch (\Exception $e) {
            Log::error('Error sending product update notifications', [
                'error' => $e->getMessage(),
                'product_id' => $event->productId,
            ]);
        }
    }
}
