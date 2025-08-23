<?php

namespace App\Listeners;

use App\Domain\Repositories\FavoriteRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Events\ProductPriceChanged;
use App\Infrastructure\Services\NotificationService;
use App\Models\Notification;

class NotifyFavoritePriceChange
{
    /**
     * @var FavoriteRepositoryInterface
     */
    private $favoriteRepository;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var NotificationService
     */
    private $notificationService;

    /**
     * Create the event listener.
     */
    public function __construct(
        FavoriteRepositoryInterface $favoriteRepository,
        ProductRepositoryInterface $productRepository,
        NotificationService $notificationService
    ) {
        $this->favoriteRepository = $favoriteRepository;
        $this->productRepository = $productRepository;
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     */
    public function handle(ProductPriceChanged $event): void
    {
        // Get the product details
        $product = $this->productRepository->findById($event->productId);
        if (! $product) {
            return;
        }

        // Get users who have favorited this product and opted for price change notifications
        $users = $this->favoriteRepository->getUsersWithFavorite($event->productId);

        foreach ($users as $user) {
            // Only notify users who opted in for price change notifications
            if ($user['notify_price_change']) {
                // Determine if the price decreased or increased
                $priceAction = $event->newPrice < $event->oldPrice ? 'decreased' : 'increased';
                $priceDifference = abs($event->newPrice - $event->oldPrice);
                $percentChange = ($priceDifference / $event->oldPrice) * 100;

                // Create notification
                $this->notificationService->createNotification(
                    $user['user_id'],
                    Notification::TYPE_PRODUCT_UPDATE,
                    "Price {$priceAction}: {$product->getName()}",
                    "The price has {$priceAction} from {$event->oldPrice} to {$event->newPrice} (".
                        number_format($percentChange, 1)."% {$priceAction})",
                    [
                        'product_id' => $event->productId,
                        'old_price' => $event->oldPrice,
                        'new_price' => $event->newPrice,
                        'price_action' => $priceAction,
                        'price_difference' => $priceDifference,
                        'percent_change' => $percentChange,
                        'reason' => 'favorite',
                        'product_name' => $product->getName(),
                        'product_image' => $product->getImages() ? $product->getImages()[0] : null,
                    ]
                );
            }
        }
    }
}
