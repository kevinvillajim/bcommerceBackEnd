<?php

namespace App\UseCases\Notification;

use App\Domain\Repositories\NotificationRepositoryInterface;
use Illuminate\Support\Facades\Log;

class MarkAllNotificationsAsReadUseCase
{
    private NotificationRepositoryInterface $notificationRepository;

    public function __construct(NotificationRepositoryInterface $notificationRepository)
    {
        $this->notificationRepository = $notificationRepository;
    }

    /**
     * Marcar todas las notificaciones como leídas
     */
    public function execute(int $userId): array
    {
        try {
            $success = $this->notificationRepository->markAllAsRead($userId);

            if (! $success) {
                return [
                    'status' => 'error',
                    'message' => 'No hay notificaciones para marcar como leídas',
                ];
            }

            return [
                'status' => 'success',
                'message' => 'Todas las notificaciones marcadas como leídas',
                'data' => [
                    'unread_count' => 0,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error al marcar todas las notificaciones como leídas: '.$e->getMessage());

            return [
                'status' => 'error',
                'message' => 'Error al marcar todas las notificaciones como leídas',
                'error' => $e->getMessage(),
            ];
        }
    }
}
