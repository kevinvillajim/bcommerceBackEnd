<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\RatingEntity;

interface RatingRepositoryInterface
{
    /**
     * Find a rating by ID
     */
    public function findById(int $id): ?RatingEntity;

    /**
     * Create a new rating
     */
    public function create(RatingEntity $ratingEntity): RatingEntity;

    /**
     * Update a rating
     */
    public function update(RatingEntity $ratingEntity): RatingEntity;

    /**
     * Delete a rating
     */
    public function delete(int $id): bool;

    /**
     * Get ratings for a seller
     */
    public function getSellerRatings(int $sellerId, int $limit = 10, int $offset = 0): array;

    /**
     * Get ratings for a product
     */
    public function getProductRatings(int $productId, int $limit = 10, int $offset = 0): array;

    /**
     * Get ratings given by a user
     */
    public function getUserGivenRatings(int $userId, int $limit = 10, int $offset = 0): array;

    /**
     * Get ratings received by a user (as a seller)
     */
    public function getUserReceivedRatings(int $userId, int $limit = 10, int $offset = 0): array;

    /**
     * Check if a user has already rated a seller for a specific order
     */
    public function hasUserRatedSeller(int $userId, int $sellerId, ?int $orderId = null, ?int $productId = null): bool;

    /**
     * Get average rating for a seller
     */
    public function getAverageSellerRating(int $sellerId): float;

    /**
     * Get average rating for a product
     */
    public function getAverageProductRating(int $productId): float;

    /**
     * Check if a seller has already rated a user for a specific order
     */
    public function hasSellerRatedUser(int $sellerUserId, int $userId, ?int $orderId = null): bool;

    /**
     * Check if a user has rated anything from a specific order
     */
    public function hasUserRatedAnythingFromOrder(int $userId, int $orderId): bool;

    /**
     * Buscar todas las valoraciones con filtros
     */
    public function findAllWithFilters(
        int $page = 1,
        int $perPage = 10,
        ?string $status = null,
        ?string $type = null,
        ?int $ratingValue = null,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array;

    /**
     * Contar todas las valoraciones con filtros
     */
    public function countAllWithFilters(
        ?string $status = null,
        ?string $type = null,
        ?int $ratingValue = null,
        ?string $fromDate = null,
        ?string $toDate = null
    ): int;

    /**
     * Contar todas las valoraciones
     */
    public function countAll(): int;

    /**
     * Contar valoraciones por estado
     */
    public function countByStatus(string $status): int;

    /**
     * Check if a user has already rated a seller for a specific order
     */
    public function hasUserRatedSellerForOrder(int $userId, int $sellerId, int $orderId): bool;

    /**
     * Check if a user has already rated a seller recently
     */
    public function hasUserRatedSellerRecently(int $userId, int $sellerId): bool;

    /**
     * Check if a user has already rated a product for a specific order
     */
    public function hasUserRatedProduct(int $userId, int $productId, ?int $orderId = null): bool;
}
