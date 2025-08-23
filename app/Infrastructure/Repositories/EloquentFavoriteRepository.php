<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\FavoriteEntity;
use App\Domain\Repositories\FavoriteRepositoryInterface;
use App\Models\Favorite;
use DateTime;

class EloquentFavoriteRepository implements FavoriteRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function add(FavoriteEntity $favorite): FavoriteEntity
    {
        $model = Favorite::create([
            'user_id' => $favorite->getUserId(),
            'product_id' => $favorite->getProductId(),
            'notify_price_change' => $favorite->isNotifyPriceChange(),
            'notify_promotion' => $favorite->isNotifyPromotion(),
            'notify_low_stock' => $favorite->isNotifyLowStock(),
        ]);

        return $this->mapModelToEntity($model);
    }

    /**
     * {@inheritDoc}
     */
    public function remove(int $userId, int $productId): bool
    {
        return Favorite::where('user_id', $userId)
            ->where('product_id', $productId)
            ->delete() > 0;
    }

    /**
     * {@inheritDoc}
     */
    public function exists(int $userId, int $productId): bool
    {
        return Favorite::where('user_id', $userId)
            ->where('product_id', $productId)
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function getUserFavorites(int $userId, int $limit = 10, int $offset = 0): array
    {
        $favorites = Favorite::with('product')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        $result = [];
        foreach ($favorites as $favorite) {
            $entity = $this->mapModelToEntity($favorite);
            // Add product data to the result
            $result[] = [
                'favorite' => $entity,
                'product' => $favorite->product ? $favorite->product->toArray() : null,
            ];
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function countUserFavorites(int $userId): int
    {
        return Favorite::where('user_id', $userId)->count();
    }

    /**
     * {@inheritDoc}
     */
    public function getUsersWithFavorite(int $productId): array
    {
        $favorites = Favorite::with('user')
            ->where('product_id', $productId)
            ->get();

        $users = [];
        foreach ($favorites as $favorite) {
            if ($favorite->user) {
                $users[] = [
                    'user_id' => $favorite->user->id,
                    'email' => $favorite->user->email,
                    'notify_price_change' => $favorite->notify_price_change,
                    'notify_promotion' => $favorite->notify_promotion,
                    'notify_low_stock' => $favorite->notify_low_stock,
                ];
            }
        }

        return $users;
    }

    /**
     * {@inheritDoc}
     */
    public function updateNotificationPreferences(
        int $favoriteId,
        bool $notifyPriceChange,
        bool $notifyPromotion,
        bool $notifyLowStock
    ): FavoriteEntity {
        $favorite = Favorite::findOrFail($favoriteId);

        $favorite->update([
            'notify_price_change' => $notifyPriceChange,
            'notify_promotion' => $notifyPromotion,
            'notify_low_stock' => $notifyLowStock,
        ]);

        return $this->mapModelToEntity($favorite->fresh());
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $favoriteId): ?FavoriteEntity
    {
        $favorite = Favorite::find($favoriteId);

        if (! $favorite) {
            return null;
        }

        return $this->mapModelToEntity($favorite);
    }

    /**
     * {@inheritDoc}
     */
    public function findByUserAndProduct(int $userId, int $productId): ?FavoriteEntity
    {
        $favorite = Favorite::where('user_id', $userId)
            ->where('product_id', $productId)
            ->first();

        if (! $favorite) {
            return null;
        }

        return $this->mapModelToEntity($favorite);
    }

    /**
     * Map a Favorite model to a FavoriteEntity
     */
    private function mapModelToEntity(Favorite $model): FavoriteEntity
    {
        return new FavoriteEntity(
            $model->user_id,
            $model->product_id,
            $model->notify_price_change,
            $model->notify_promotion,
            $model->notify_low_stock,
            $model->id,
            new DateTime($model->created_at),
            $model->updated_at ? new DateTime($model->updated_at) : null
        );
    }
}
