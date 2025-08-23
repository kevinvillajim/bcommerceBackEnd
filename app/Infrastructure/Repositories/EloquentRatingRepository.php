<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\RatingEntity;
use App\Domain\Repositories\RatingRepositoryInterface;
use App\Models\Rating;
use App\Models\Seller;

class EloquentRatingRepository implements RatingRepositoryInterface
{
    /**
     * Find a rating by ID
     */
    public function findById(int $id): ?RatingEntity
    {
        $rating = Rating::find($id);

        if (! $rating) {
            return null;
        }

        return $this->mapToEntity($rating);
    }

    /**
     * Create a new rating
     */
    public function create(RatingEntity $ratingEntity): RatingEntity
    {
        $rating = Rating::create([
            'user_id' => $ratingEntity->getUserId(),
            'seller_id' => $ratingEntity->getSellerId(),
            'order_id' => $ratingEntity->getOrderId(),
            'product_id' => $ratingEntity->getProductId(),
            'rating' => $ratingEntity->getRating(),
            'title' => $ratingEntity->getTitle(),
            'comment' => $ratingEntity->getComment(),
            'status' => $ratingEntity->getStatus(),
            'type' => $ratingEntity->getType(),
        ]);

        return $this->mapToEntity($rating);
    }

    /**
     * Update a rating
     */
    public function update(RatingEntity $ratingEntity): RatingEntity
    {
        $rating = Rating::findOrFail($ratingEntity->getId());

        $rating->update([
            'rating' => $ratingEntity->getRating(),
            'title' => $ratingEntity->getTitle(),
            'comment' => $ratingEntity->getComment(),
            'status' => $ratingEntity->getStatus(),
        ]);

        return $this->mapToEntity($rating->fresh());
    }

    /**
     * Delete a rating
     */
    public function delete(int $id): bool
    {
        return Rating::destroy($id) > 0;
    }

    /**
     * Get ratings for a seller
     */
    public function getSellerRatings(int $sellerId, int $limit = 10, int $offset = 0): array
    {
        $ratings = Rating::where('seller_id', $sellerId)
            ->where('type', Rating::TYPE_USER_TO_SELLER)
            ->where('status', Rating::STATUS_APPROVED)
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $this->mapCollectionToEntities($ratings);
    }

    /**
     * Get ratings for a product
     */
    public function getProductRatings(int $productId, int $limit = 10, int $offset = 0): array
    {
        $ratings = Rating::where('product_id', $productId)
            ->where('type', Rating::TYPE_USER_TO_PRODUCT)
            ->where('status', Rating::STATUS_APPROVED)
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $this->mapCollectionToEntities($ratings);
    }

    /**
     * Get ratings given by a user
     */
    public function getUserGivenRatings(int $userId, int $limit = 10, int $offset = 0): array
    {
        $ratings = Rating::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $this->mapCollectionToEntities($ratings);
    }

    /**
     * Get ratings received by a user (as a seller)
     */
    public function getUserReceivedRatings(int $userId, int $limit = 10, int $offset = 0): array
    {
        // Get the seller ID for this user
        $seller = Seller::where('user_id', $userId)->first();

        if (! $seller) {
            return [];
        }

        $ratings = Rating::where('seller_id', $seller->id)
            ->where('type', Rating::TYPE_USER_TO_SELLER)
            ->where('status', Rating::STATUS_APPROVED)
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $this->mapCollectionToEntities($ratings);
    }

    /**
     * Check if a user has already rated a seller for a specific order and product
     */
    public function hasUserRatedSeller(int $userId, int $sellerId, ?int $orderId = null, ?int $productId = null): bool
    {
        $query = Rating::where('user_id', $userId)
            ->where('seller_id', $sellerId)
            ->where('type', Rating::TYPE_USER_TO_SELLER);

        if ($orderId) {
            $query->where('order_id', $orderId);
        }

        // Incluir product_id en la verificación para permitir múltiples ratings por vendedor
        if ($productId) {
            $query->where('product_id', $productId);
        }

        return $query->exists();
    }

    /**
     * Check if a user has already rated a product
     */
    public function hasUserRatedProduct(int $userId, int $productId, ?int $orderId = null): bool
    {
        $query = Rating::where('user_id', $userId)
            ->where('product_id', $productId)
            ->where('type', Rating::TYPE_USER_TO_PRODUCT);

        // Si se proporciona order_id, verificar esa combinación específica
        if ($orderId) {
            $query->where('order_id', $orderId);
        }

        return $query->exists();
    }

    /**
     * Get average rating for a seller
     */
    public function getAverageSellerRating(int $sellerId): float
    {
        $average = Rating::where('seller_id', $sellerId)
            ->where('type', Rating::TYPE_USER_TO_SELLER)
            ->where('status', Rating::STATUS_APPROVED)
            ->avg('rating');

        return round($average ?: 0, 1);
    }

    /**
     * Get average rating for a product
     */
    public function getAverageProductRating(int $productId): float
    {
        $average = Rating::where('product_id', $productId)
            ->where('type', Rating::TYPE_USER_TO_PRODUCT)
            ->where('status', Rating::STATUS_APPROVED)
            ->avg('rating');

        return round($average ?: 0, 1);
    }

    /**
     * Map a Rating model to a RatingEntity
     */
    private function mapToEntity(Rating $rating): RatingEntity
    {
        return new RatingEntity(
            $rating->user_id,
            $rating->rating,
            $rating->type,
            $rating->seller_id,
            $rating->order_id,
            $rating->product_id,
            $rating->title,
            $rating->comment,
            $rating->status,
            $rating->id
        );
    }

    /**
     * Map a collection of Rating models to an array of RatingEntities
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $ratings
     */
    private function mapCollectionToEntities($ratings): array
    {
        $entities = [];

        foreach ($ratings as $rating) {
            $entities[] = $this->mapToEntity($rating);
        }

        return $entities;
    }

    public function hasSellerRatedUser(int $sellerUserId, int $userId, ?int $orderId = null): bool
    {
        $query = Rating::where('user_id', $sellerUserId)
            ->where('type', Rating::TYPE_SELLER_TO_USER);

        // Find seller record by user ID
        $seller = Seller::where('user_id', $sellerUserId)->first();
        if ($seller) {
            $query->where('seller_id', $seller->id);
        }

        // Add condition to find ratings where the target user is specified user
        $query->whereHas('order', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });

        if ($orderId) {
            $query->where('order_id', $orderId);
        }

        return $query->exists();
    }

    public function hasUserRatedAnythingFromOrder(int $userId, int $orderId): bool
    {
        return Rating::where('user_id', $userId)
            ->where('order_id', $orderId)
            ->exists();
    }

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
    ): array {
        $query = Rating::query();

        // Aplicar filtros
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($type && $type !== 'all') {
            $query->where('type', $type);
        }

        if ($ratingValue) {
            $query->where('rating', $ratingValue);
        }

        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        // Cargar relaciones
        $query->with(['user', 'product', 'seller']);

        // Paginar resultados
        $offset = ($page - 1) * $perPage;
        $ratings = $query->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($perPage)
            ->get();

        return $this->mapCollectionToEntities($ratings);
    }

    /**
     * Contar todas las valoraciones con filtros
     */
    public function countAllWithFilters(
        ?string $status = null,
        ?string $type = null,
        ?int $ratingValue = null,
        ?string $fromDate = null,
        ?string $toDate = null
    ): int {
        $query = Rating::query();

        // Aplicar filtros
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($type && $type !== 'all') {
            $query->where('type', $type);
        }

        if ($ratingValue) {
            $query->where('rating', $ratingValue);
        }

        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        return $query->count();
    }

    /**
     * Contar todas las valoraciones
     */
    public function countAll(): int
    {
        return Rating::count();
    }

    /**
     * Contar valoraciones por estado
     */
    public function countByStatus(string $status): int
    {
        return Rating::where('status', $status)->count();
    }

    /**
     * Check if a user has already rated a seller for a specific order
     */
    public function hasUserRatedSellerForOrder(int $userId, int $sellerId, int $orderId): bool
    {
        return Rating::where('user_id', $userId)
            ->where('seller_id', $sellerId)
            ->where('order_id', $orderId)
            ->where('type', Rating::TYPE_USER_TO_SELLER)
            ->exists();
    }

    /**
     * Check if a user has already rated a seller recently
     */
    public function hasUserRatedSellerRecently(int $userId, int $sellerId): bool
    {
        return Rating::where('user_id', $userId)
            ->where('seller_id', $sellerId)
            ->where('type', Rating::TYPE_USER_TO_SELLER)
            ->where('created_at', '>=', now()->subDays(30))
            ->exists();
    }
}
