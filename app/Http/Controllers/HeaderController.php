<?php

namespace App\Http\Controllers;

use App\Domain\Repositories\FavoriteRepositoryInterface;
use App\UseCases\Cart\GetCartUseCase;
use App\UseCases\Notification\GetUserNotificationsUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HeaderController extends Controller
{
    private GetCartUseCase $getCartUseCase;

    private GetUserNotificationsUseCase $getUserNotificationsUseCase;

    private FavoriteRepositoryInterface $favoriteRepository;

    public function __construct(
        GetCartUseCase $getCartUseCase,
        GetUserNotificationsUseCase $getUserNotificationsUseCase,
        FavoriteRepositoryInterface $favoriteRepository
    ) {
        $this->getCartUseCase = $getCartUseCase;
        $this->getUserNotificationsUseCase = $getUserNotificationsUseCase;
        $this->favoriteRepository = $favoriteRepository;

        $this->middleware('jwt.auth');
    }

    /**
     * Obtener contadores para el header
     */
    public function counters(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            // Obtener contadores en paralelo
            $cartResult = $this->getCartUseCase->execute($userId);
            $cartCount = $cartResult['cart'] ? count($cartResult['cart']->getItems()) : 0;
            $favoritesCount = $this->favoriteRepository->countUserFavorites($userId);

            $notificationsResult = $this->getUserNotificationsUseCase->execute($userId, 1, 0, true);
            $notificationsCount = ($notificationsResult['status'] === 'success')
                ? ($notificationsResult['data']['unread_count'] ?? 0)
                : 0;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'cart_count' => $cartCount,
                    'favorites_count' => $favoritesCount,
                    'notifications_count' => $notificationsCount,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('HeaderController error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener contadores',
                'data' => [
                    'cart_count' => 0,
                    'favorites_count' => 0,
                    'notifications_count' => 0,
                ],
            ], 500);
        }
    }
}
