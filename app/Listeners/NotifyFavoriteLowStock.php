<?php

namespace App\Listeners;

use App\Domain\Repositories\FavoriteRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Events\ProductLowStock;
use App\Infrastructure\Services\NotificationService;
use App\Models\Notification;

class NotifyFavoriteLowStock
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
    public function handle(ProductLowStock $event): void
    {
        // Get the product details
        $product = $this->productRepository->findById($event->productId);
        if (! $product) {
            return;
        }

        // We only consider it low stock if it's 5 items or less
        if ($event->stock > 5) {
            return;
        }

        // Get users who have favorited this product and opted for low stock notifications
        $users = $this->favoriteRepository->getUsersWithFavorite($event->productId);

        foreach ($users as $user) {
            // Only notify users who opted in for low stock notifications
            if ($user['notify_low_stock']) {
                $stockMessage = "Only {$event->stock} item".($event->stock === 1 ? '' : 's').' left!';

                // Create notification
                $this->notificationService->createNotification(
                    $user['user_id'],
                    Notification::TYPE_LOW_STOCK,
                    "Low stock alert: {$product->getName()}",
                    $stockMessage." Get it before it's gone.",
                    [
                        'product_id' => $event->productId,
                        'stock' => $event->stock,
                        'product_name' => $product->getName(),
                        'product_image' => $product->getImages() ? $product->getImages()[0] : null,
                        'reason' => 'favorite',
                    ]
                );
            }
        }
    }
}
