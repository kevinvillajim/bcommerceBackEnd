<?php

namespace App\Listeners;

use App\Domain\Repositories\FavoriteRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Events\ProductPromotionAdded;
use App\Infrastructure\Services\NotificationService;
use App\Models\Notification;

class NotifyFavoritePromotion
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
    public function handle(ProductPromotionAdded $event): void
    {
        // Get the product details
        $product = $this->productRepository->findById($event->productId);
        if (! $product) {
            return;
        }

        // Get users who have favorited this product and opted for promotion notifications
        $users = $this->favoriteRepository->getUsersWithFavorite($event->productId);

        foreach ($users as $user) {
            // Only notify users who opted in for promotion notifications
            if ($user['notify_promotion']) {
                $promotionName = $event->promotionName ?? 'Special offer';
                $promotionMessage = 'Save '.number_format($event->discountPercentage, 0)."% on {$product->getName()}";

                // Add expiration date to message if available
                $expirationInfo = '';
                if ($event->expirationDate) {
                    $expirationInfo = ' until '.$event->expirationDate->format('M j, Y');
                }

                // Create notification
                $this->notificationService->createNotification(
                    $user['user_id'],
                    Notification::TYPE_PROMOTION,
                    $promotionName,
                    $promotionMessage.$expirationInfo,
                    [
                        'product_id' => $event->productId,
                        'discount_percentage' => $event->discountPercentage,
                        'promotion_name' => $promotionName,
                        'expiration_date' => $event->expirationDate ? $event->expirationDate->format('Y-m-d H:i:s') : null,
                        'product_name' => $product->getName(),
                        'product_image' => $product->getImages() ? $product->getImages()[0] : null,
                        'reason' => 'favorite',
                    ]
                );
            }
        }
    }
}
