<?php

namespace App\UseCases\Notification;

use App\Domain\Repositories\NotificationRepositoryInterface;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class GetUserNotificationsUseCase
{
    private NotificationRepositoryInterface $notificationRepository;

    public function __construct(NotificationRepositoryInterface $notificationRepository)
    {
        $this->notificationRepository = $notificationRepository;
    }

    /**
     * Obtener notificaciones de un usuario
     */
    public function execute(int $userId, int $limit = 20, int $offset = 0, bool $onlyUnread = false): array
    {
        try {
            $notificationEntities = $onlyUnread
                ? $this->notificationRepository->findUnreadByUserId($userId, $limit, $offset)
                : $this->notificationRepository->findByUserId($userId, $limit, $offset);

            // Convertir entidades a array
            $notifications = [];
            foreach ($notificationEntities as $notification) {
                $notificationData = $notification->toArray();

                // Agregar URL basada en el tipo
                $notificationData['url'] = $this->getNotificationUrl($notification->getType(), $notification->getData());

                $notifications[] = $notificationData;
            }

            $unreadCount = $this->notificationRepository->countUnreadByUserId($userId);

            return [
                'status' => 'success',
                'data' => [
                    'notifications' => $notifications,
                    'unread_count' => $unreadCount,
                    'total' => count($notifications),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error al obtener notificaciones: '.$e->getMessage());

            return [
                'status' => 'error',
                'message' => 'Error al obtener notificaciones',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Obtener URL de destino según el tipo de notificación
     */
    private function getNotificationUrl(string $type, array $data): ?string
    {
        switch ($type) {
            case Notification::TYPE_NEW_MESSAGE:
                return isset($data['chat_id'])
                    ? "/chats/{$data['chat_id']}"
                    : null;

            case Notification::TYPE_FEEDBACK_RESPONSE:
                return isset($data['feedback_id'])
                    ? "/feedback/{$data['feedback_id']}"
                    : null;

            case Notification::TYPE_ORDER_STATUS:
                return isset($data['order_id'])
                    ? "/orders/{$data['order_id']}"
                    : null;

            case Notification::TYPE_PRODUCT_UPDATE:
                return isset($data['product_id'])
                    ? "/products/{$data['product_id']}"
                    : null;

            case Notification::TYPE_SHIPPING_UPDATE:
                return isset($data['tracking_number'])
                    ? "/shipping/track/{$data['tracking_number']}"
                    : null;

            case Notification::TYPE_RATING_RECEIVED:
                return isset($data['rating_id'])
                    ? "/ratings/received/{$data['rating_id']}"
                    : null;

            default:
                return null;
        }
    }
}
