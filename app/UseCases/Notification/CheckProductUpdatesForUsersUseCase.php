<?php

namespace App\UseCases\Notification;

use App\Infrastructure\Services\NotificationService;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckProductUpdatesForUsersUseCase
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Verificar cambios en productos y notificar a usuarios interesados
     */
    public function execute(Product $product, array $changes): array
    {
        try {
            // Solo procesamos cambios de precio o stock
            if (! isset($changes['price']) && ! isset($changes['stock'])) {
                return [
                    'status' => 'error',
                    'message' => 'No hay cambios relevantes para notificar',
                ];
            }

            $sentNotifications = [];

            // 1. Notificar a usuarios con el producto en su carrito
            $cartUsers = DB::table('cart_items')
                ->join('shopping_carts', 'cart_items.cart_id', '=', 'shopping_carts.id')
                ->where('cart_items.product_id', $product->id)
                ->select('shopping_carts.user_id')
                ->get()
                ->pluck('user_id')
                ->toArray();

            foreach ($cartUsers as $userId) {
                $user = User::find($userId);
                if ($user) {
                    $notification = $this->notificationService->notifyProductOfInterest(
                        $user,
                        $product,
                        'cart',
                        $changes
                    );

                    if ($notification) {
                        $sentNotifications[] = [
                            'user_id' => $userId,
                            'reason' => 'cart',
                            'notification_id' => $notification->getId(),
                        ];
                    }
                }
            }

            // 2. Notificar a usuarios que visitaron recientemente el producto
            $recentViewers = DB::table('user_interactions')
                ->where('item_id', $product->id)
                ->where('interaction_type', 'view_product')
                ->where('created_at', '>=', now()->subDays(7))
                ->select('user_id')
                ->distinct()
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->pluck('user_id')
                ->toArray();

            // Tomar los 2 últimos visitantes que no tengan ya notificación por carrito
            $recentUsers = array_diff($recentViewers, $cartUsers);
            $recentUsers = array_slice($recentUsers, 0, 2);

            foreach ($recentUsers as $userId) {
                $user = User::find($userId);
                if ($user) {
                    $notification = $this->notificationService->notifyProductOfInterest(
                        $user,
                        $product,
                        'visited',
                        $changes
                    );

                    if ($notification) {
                        $sentNotifications[] = [
                            'user_id' => $userId,
                            'reason' => 'visited',
                            'notification_id' => $notification->getId(),
                        ];
                    }
                }
            }

            // 3. Notificar a usuarios interesados en la categoría (por interacciones frecuentes)
            $interestedUsers = DB::table('user_interactions')
                ->join('products', 'user_interactions.item_id', '=', 'products.id')
                ->where('products.category_id', $product->category_id)
                ->where('user_interactions.interaction_type', 'view_product')
                ->select('user_interactions.user_id', DB::raw('COUNT(*) as view_count'))
                ->groupBy('user_interactions.user_id')
                ->having('view_count', '>=', 5)
                ->orderBy('view_count', 'desc')
                ->limit(10)
                ->get()
                ->pluck('user_id')
                ->toArray();

            // Excluir usuarios ya notificados por otras razones
            $interestedUsers = array_diff($interestedUsers, array_merge($cartUsers, $recentUsers));
            $interestedUsers = array_slice($interestedUsers, 0, 3); // Máximo 3 usuarios interesados

            foreach ($interestedUsers as $userId) {
                $user = User::find($userId);
                if ($user) {
                    $notification = $this->notificationService->notifyProductOfInterest(
                        $user,
                        $product,
                        'interested',
                        $changes
                    );

                    if ($notification) {
                        $sentNotifications[] = [
                            'user_id' => $userId,
                            'reason' => 'interested',
                            'notification_id' => $notification->getId(),
                        ];
                    }
                }
            }

            return [
                'status' => 'success',
                'message' => 'Notificaciones enviadas correctamente',
                'data' => [
                    'product_id' => $product->id,
                    'notifications_sent' => count($sentNotifications),
                    'notifications' => $sentNotifications,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error al enviar notificaciones de producto: '.$e->getMessage());

            return [
                'status' => 'error',
                'message' => 'Error al enviar notificaciones',
                'error' => $e->getMessage(),
            ];
        }
    }
}
