<?php

namespace App\UseCases\Favorite;

use App\Domain\Entities\FavoriteEntity;
use App\Domain\Repositories\FavoriteRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\UseCases\Recommendation\TrackUserInteractionsUseCase;

class ToggleFavoriteUseCase
{
    private FavoriteRepositoryInterface $favoriteRepository;

    private ProductRepositoryInterface $productRepository;

    private TrackUserInteractionsUseCase $trackUserInteractionsUseCase;

    /**
     * Constructor
     */
    public function __construct(
        FavoriteRepositoryInterface $favoriteRepository,
        ProductRepositoryInterface $productRepository,
        TrackUserInteractionsUseCase $trackUserInteractionsUseCase
    ) {
        $this->favoriteRepository = $favoriteRepository;
        $this->productRepository = $productRepository;
        $this->trackUserInteractionsUseCase = $trackUserInteractionsUseCase;
    }

    /**
     * Toggle product favorite status
     *
     * @throws \Exception
     */
    public function execute(
        int $userId,
        int $productId,
        array $notificationPreferences = []
    ): array {
        // Check if product exists
        $product = $this->productRepository->findById($productId);
        if (! $product) {
            throw new \Exception('Product not found');
        }

        // Check if the product is already favorited
        $isFavorite = $this->favoriteRepository->exists($userId, $productId);

        if ($isFavorite) {
            // Remove from favorites
            $result = $this->favoriteRepository->remove($userId, $productId);

            // Track unfavorite interaction for recommendation system
            $this->trackUserInteractionsUseCase->execute(
                $userId,
                'unfavorite',
                $productId,
                ['product_name' => $product->getName()]
            );

            return [
                'is_favorite' => false,
                'message' => 'Product removed from favorites',
                'success' => $result,
            ];
        } else {
            // Set default notification preferences
            $notifyPriceChange = $notificationPreferences['notify_price_change'] ?? true;
            $notifyPromotion = $notificationPreferences['notify_promotion'] ?? true;
            $notifyLowStock = $notificationPreferences['notify_low_stock'] ?? true;

            // Create new favorite
            $favorite = new FavoriteEntity(
                $userId,
                $productId,
                $notifyPriceChange,
                $notifyPromotion,
                $notifyLowStock
            );

            $savedFavorite = $this->favoriteRepository->add($favorite);

            // Track favorite interaction for recommendation system
            $this->trackUserInteractionsUseCase->execute(
                $userId,
                'favorite',
                $productId,
                ['product_name' => $product->getName()]
            );

            return [
                'is_favorite' => true,
                'favorite_id' => $savedFavorite->getId(),
                'message' => 'Product added to favorites',
                'success' => true,
            ];
        }
    }
}
