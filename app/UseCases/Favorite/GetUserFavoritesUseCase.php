<?php

namespace App\UseCases\Favorite;

use App\Domain\Repositories\FavoriteRepositoryInterface;

class GetUserFavoritesUseCase
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
     * Get user's favorite products
     */
    public function execute(int $userId, int $limit = 10, int $offset = 0): array
    {
        $favorites = $this->favoriteRepository->getUserFavorites($userId, $limit, $offset);
        $total = $this->favoriteRepository->countUserFavorites($userId);

        return [
            'favorites' => $favorites,
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total,
            ],
        ];
    }
}
