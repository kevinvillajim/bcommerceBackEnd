<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Create a new notification for a user
     */
    public function createNotification(
        int $userId,
        string $type,
        string $title,
        string $message,
        array $data = []
    ): ?Notification {
        try {
            return Notification::create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data,
                'read' => false,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create notification', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Send discount code notification to user
     */
    public function sendDiscountCodeNotification(int $userId, string $discountCode, int $percentage, string $expiresAt): ?Notification
    {
        $title = '¡Código de descuento generado!';
        $message = "Tu feedback fue aprobado. Usa el código '{$discountCode}' y obtén {$percentage}% de descuento. Válido hasta: {$expiresAt}";

        $data = [
            'discount_code' => $discountCode,
            'percentage' => $percentage,
            'expires_at' => $expiresAt,
        ];

        return $this->createNotification(
            $userId,
            Notification::TYPE_DISCOUNT_CODE_GENERATED,
            $title,
            $message,
            $data
        );
    }

    /**
     * Send feedback response notification to user
     */
    public function sendFeedbackResponseNotification(int $userId, int $feedbackId, string $status, string $adminNotes = ''): ?Notification
    {
        $title = $status === 'approved' ? 'Feedback aprobado' : 'Feedback revisado';
        $message = $status === 'approved'
            ? 'Tu sugerencia fue aprobada y será considerada para futuras mejoras.'
            : 'Tu sugerencia fue revisada. '.($adminNotes ? "Nota del administrador: {$adminNotes}" : '');

        $data = [
            'feedback_id' => $feedbackId,
            'status' => $status,
            'admin_notes' => $adminNotes,
        ];

        return $this->createNotification(
            $userId,
            Notification::TYPE_FEEDBACK_RESPONSE,
            $title,
            $message,
            $data
        );
    }

    /**
     * Get unread notifications count for a user
     */
    public function getUnreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('read', false)
            ->count();
    }

    /**
     * Get notifications for a user with pagination
     */
    public function getUserNotifications(int $userId, int $limit = 10, int $offset = 0): array
    {
        $notifications = Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        $total = Notification::where('user_id', $userId)->count();

        return [
            'notifications' => $notifications,
            'total' => $total,
            'unread_count' => $this->getUnreadCount($userId),
        ];
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if (! $notification) {
            return false;
        }

        return $notification->markAsRead();
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(int $userId): bool
    {
        try {
            Notification::where('user_id', $userId)
                ->where('read', false)
                ->update([
                    'read' => true,
                    'read_at' => now(),
                ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to mark all notifications as read', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
