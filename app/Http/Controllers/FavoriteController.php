<?php

namespace App\Http\Controllers;

use App\Http\Requests\ToggleFavoriteRequest;
use App\Http\Requests\UpdateFavoriteNotificationsRequest;
use App\UseCases\Favorite\GetUserFavoritesUseCase;
use App\UseCases\Favorite\ToggleFavoriteUseCase;
use App\UseCases\Favorite\UpdateFavoriteNotificationsUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    /**
     * Toggle a product's favorite status.
     */
    public function toggle(ToggleFavoriteRequest $request, ToggleFavoriteUseCase $useCase): JsonResponse
    {
        try {
            $result = $useCase->execute(
                auth()->id(),
                $request->input('product_id'),
                $request->input('notification_preferences', [])
            );

            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'data' => [
                    'is_favorite' => $result['is_favorite'],
                    'favorite_id' => $result['is_favorite'] ? $result['favorite_id'] : null,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get a user's favorite products.
     */
    public function index(Request $request, GetUserFavoritesUseCase $useCase): JsonResponse
    {
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);

        $result = $useCase->execute(auth()->id(), $limit, $offset);

        return response()->json([
            'status' => 'success',
            'data' => $result['favorites'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * Update notification preferences for a favorite.
     */
    public function updateNotifications(
        UpdateFavoriteNotificationsRequest $request,
        int $id,
        UpdateFavoriteNotificationsUseCase $useCase
    ): JsonResponse {
        try {
            $result = $useCase->execute(
                auth()->id(),
                $id,
                $request->input('notify_price_change'),
                $request->input('notify_promotion'),
                $request->input('notify_low_stock')
            );

            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'data' => $result['favorite'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Check if a product is favorited by the current user.
     *
     * @param  App\Domain\Repositories\FavoriteRepositoryInterface  $favoriteRepository
     */
    public function check(
        int $productId,
        \App\Domain\Repositories\FavoriteRepositoryInterface $favoriteRepository
    ): JsonResponse {
        $favorite = $favoriteRepository->findByUserAndProduct(auth()->id(), $productId);

        return response()->json([
            'status' => 'success',
            'data' => [
                'is_favorite' => $favorite !== null,
                'favorite_id' => $favorite ? $favorite->getId() : null,
                'notification_preferences' => $favorite ? [
                    'notify_price_change' => $favorite->isNotifyPriceChange(),
                    'notify_promotion' => $favorite->isNotifyPromotion(),
                    'notify_low_stock' => $favorite->isNotifyLowStock(),
                ] : null,
            ],
        ]);
    }
}
