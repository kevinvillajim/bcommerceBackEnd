<?php

namespace App\UseCases\Favorite;

use App\Domain\Repositories\FavoriteRepositoryInterface;

class UpdateFavoriteNotificationsUseCase
{
    private FavoriteRepositoryInterface $favoriteRepository;

    /**
     * Constructor
     */
    public function __construct(FavoriteRepositoryInterface $favoriteRepository)
    {
        $this->favoriteRepository = $favoriteRepository;
    }

    /**
     * Update notification preferences for a favorite
     *
     * @throws \Exception
     */
    public function execute(
        int $userId,
        int $favoriteId,
        bool $notifyPriceChange,
        bool $notifyPromotion,
        bool $notifyLowStock
    ): array {
        // Get favorite
        $favorite = $this->favoriteRepository->findById($favoriteId);

        if (! $favorite) {
            throw new \Exception('Favorite not found');
        }

        // Check if the favorite belongs to the user
        if ($favorite->getUserId() !== $userId) {
            throw new \Exception('Unauthorized: This favorite does not belong to you');
        }

        // Update notification preferences
        $updatedFavorite = $this->favoriteRepository->updateNotificationPreferences(
            $favoriteId,
            $notifyPriceChange,
            $notifyPromotion,
            $notifyLowStock
        );

        return [
            'success' => true,
            'message' => 'Notification preferences updated',
            'favorite' => [
                'id' => $updatedFavorite->getId(),
                'product_id' => $updatedFavorite->getProductId(),
                'notify_price_change' => $updatedFavorite->isNotifyPriceChange(),
                'notify_promotion' => $updatedFavorite->isNotifyPromotion(),
                'notify_low_stock' => $updatedFavorite->isNotifyLowStock(),
            ],
        ];
    }
}
