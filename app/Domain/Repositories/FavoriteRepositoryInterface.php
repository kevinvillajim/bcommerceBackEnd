<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\FavoriteEntity;

interface FavoriteRepositoryInterface
{
    /**
     * Add product to user's favorites
     */
    public function add(FavoriteEntity $favorite): FavoriteEntity;

    /**
     * Remove product from user's favorites
     */
    public function remove(int $userId, int $productId): bool;

    /**
     * Check if product is in user's favorites
     */
    public function exists(int $userId, int $productId): bool;

    /**
     * Get user's favorite products
     */
    public function getUserFavorites(int $userId, int $limit = 10, int $offset = 0): array;

    /**
     * Count user's favorite products
     */
    public function countUserFavorites(int $userId): int;

    /**
     * Get users who have favorited a product
     */
    public function getUsersWithFavorite(int $productId): array;

    /**
     * Update notification preferences
     */
    public function updateNotificationPreferences(
        int $favoriteId,
        bool $notifyPriceChange,
        bool $notifyPromotion,
        bool $notifyLowStock
    ): FavoriteEntity;

    /**
     * Find favorite by ID
     */
    public function findById(int $favoriteId): ?FavoriteEntity;

    /**
     * Find favorite by user ID and product ID
     */
    public function findByUserAndProduct(int $userId, int $productId): ?FavoriteEntity;
}
