<?php

namespace App\Http\Controllers;

use App\Domain\Repositories\DiscountCodeRepositoryInterface;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserDiscountCodeController extends Controller
{
    private NotificationService $notificationService;

    private DiscountCodeRepositoryInterface $discountCodeRepository;

    public function __construct(
        NotificationService $notificationService,
        DiscountCodeRepositoryInterface $discountCodeRepository
    ) {
        $this->notificationService = $notificationService;
        $this->discountCodeRepository = $discountCodeRepository;
    }

    /**
     * Get user notifications
     */
    public function getNotifications(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $limit = $request->input('limit', 10);
            $offset = $request->input('offset', 0);

            $result = $this->notificationService->getUserNotifications($userId, $limit, $offset);

            return response()->json([
                'status' => 'success',
                'data' => $result['notifications'],
                'meta' => [
                    'total' => $result['total'],
                    'unread_count' => $result['unread_count'],
                    'limit' => $limit,
                    'offset' => $offset,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user notifications: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching notifications',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markNotificationAsRead(int $notificationId): JsonResponse
    {
        try {
            $userId = Auth::id();
            $success = $this->notificationService->markAsRead($notificationId, $userId);

            if (! $success) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Notification not found or unauthorized',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Notification marked as read',
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking notification as read: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error marking notification as read',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllNotificationsAsRead(): JsonResponse
    {
        try {
            $userId = Auth::id();
            $success = $this->notificationService->markAllAsRead($userId);

            return response()->json([
                'status' => 'success',
                'message' => 'All notifications marked as read',
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking all notifications as read: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error marking notifications as read',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's discount codes
     */
    public function getUserDiscountCodes(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $limit = $request->input('limit', 10);
            $offset = $request->input('offset', 0);
            $includeExpired = $request->input('include_expired', false);

            $discountCodes = $this->discountCodeRepository->findByUserId($userId, $limit, $offset, ! $includeExpired);
            $total = $this->discountCodeRepository->countByUserId($userId, ! $includeExpired);

            $formattedCodes = array_map(function ($code) {
                return [
                    'id' => $code->getId(),
                    'code' => $code->getCode(),
                    'discount_percentage' => $code->getDiscountPercentage(),
                    'is_used' => $code->getIsUsed(),
                    'used_at' => $code->getUsedAt(),
                    'expires_at' => $code->getExpiresAt(),
                    'feedback_id' => $code->getFeedbackId(),
                    'is_expired' => $code->getExpiresAt() && strtotime($code->getExpiresAt()) < time(),
                ];
            }, $discountCodes);

            return response()->json([
                'status' => 'success',
                'data' => $formattedCodes,
                'meta' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user discount codes: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching discount codes',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get unread notifications count
     */
    public function getUnreadNotificationsCount(): JsonResponse
    {
        try {
            $userId = Auth::id();
            $count = $this->notificationService->getUnreadCount($userId);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'unread_count' => $count,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching unread notifications count: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching unread count',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}
