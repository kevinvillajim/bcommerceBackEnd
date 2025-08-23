<?php

namespace App\UseCases\Notification;

use App\Domain\Repositories\NotificationRepositoryInterface;
use Illuminate\Support\Facades\Log;

class MarkNotificationAsReadUseCase
{
    private NotificationRepositoryInterface $notificationRepository;

    public function __construct(NotificationRepositoryInterface $notificationRepository)
    {
        $this->notificationRepository = $notificationRepository;
    }

    /**
     * Marcar una notificación como leída
     */
    public function execute(int $notificationId, int $userId): array
    {
        try {
            $notification = $this->notificationRepository->findById($notificationId);

            if (! $notification) {
                return [
                    'status' => 'error',
                    'message' => 'Notificación no encontrada',
                ];
            }

            if ($notification->getUserId() !== $userId) {
                return [
                    'status' => 'error',
                    'message' => 'No tienes permiso para marcar esta notificación',
                ];
            }

            $success = $this->notificationRepository->markAsRead($notificationId);

            if (! $success) {
                return [
                    'status' => 'error',
                    'message' => 'Error al marcar notificación como leída',
                ];
            }

            $unreadCount = $this->notificationRepository->countUnreadByUserId($userId);

            return [
                'status' => 'success',
                'message' => 'Notificación marcada como leída',
                'data' => [
                    'unread_count' => $unreadCount,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error al marcar notificación como leída: '.$e->getMessage());

            return [
                'status' => 'error',
                'message' => 'Error al marcar notificación como leída',
                'error' => $e->getMessage(),
            ];
        }
    }
}
