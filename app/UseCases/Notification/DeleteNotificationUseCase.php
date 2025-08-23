<?php

namespace App\UseCases\Notification;

use App\Domain\Repositories\NotificationRepositoryInterface;
use Illuminate\Support\Facades\Log;

class DeleteNotificationUseCase
{
    private NotificationRepositoryInterface $notificationRepository;

    public function __construct(NotificationRepositoryInterface $notificationRepository)
    {
        $this->notificationRepository = $notificationRepository;
    }

    /**
     * Eliminar una notificación
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
                    'message' => 'No tienes permiso para eliminar esta notificación',
                ];
            }

            $success = $this->notificationRepository->delete($notificationId);

            if (! $success) {
                return [
                    'status' => 'error',
                    'message' => 'Error al eliminar notificación',
                ];
            }

            $unreadCount = $this->notificationRepository->countUnreadByUserId($userId);

            return [
                'status' => 'success',
                'message' => 'Notificación eliminada correctamente',
                'data' => [
                    'unread_count' => $unreadCount,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error al eliminar notificación: '.$e->getMessage());

            return [
                'status' => 'error',
                'message' => 'Error al eliminar notificación',
                'error' => $e->getMessage(),
            ];
        }
    }
}
