<?php

namespace App\Listeners;

use App\Events\RatingCreated;
use App\Infrastructure\Services\NotificationService;
use App\Models\Rating;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendRatingReceivedNotification
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * 🔧 MEJORADO: Handle all rating notifications without duplication
     */
    public function handle(RatingCreated $event): void
    {
        try {
            // 🛡️ ANTI-DUPLICACIÓN: Verificar si este evento ya se procesó recientemente
            $cacheKey = "rating_notification_{$event->ratingId}";

            if (Cache::has($cacheKey)) {
                Log::info('⚠️ SendRatingReceivedNotification: Evento duplicado detectado y bloqueado', [
                    'rating_id' => $event->ratingId,
                    'cache_key' => $cacheKey,
                ]);

                return;
            }

            // Marcar como procesado por 5 minutos
            Cache::put($cacheKey, true, 300);

            Log::info('🎆 SendRatingReceivedNotification: Procesando rating event', [
                'rating_id' => $event->ratingId,
            ]);

            $rating = Rating::find($event->ratingId);
            if (! $rating) {
                Log::error('❌ Rating not found for notification', ['rating_id' => $event->ratingId]);

                return;
            }

            Log::info('📋 Rating details', [
                'rating_id' => $rating->id,
                'type' => $rating->type,
                'status' => $rating->status,
                'rating_value' => $rating->rating,
                'seller_id' => $rating->seller_id,
                'user_id' => $rating->user_id,
            ]);

            // 🔄 NUEVO: Manejar todos los tipos de rating sin duplicación
            if ($rating->type === 'user_to_seller') {
                // Notificar al vendedor cuando recibe una valoración del usuario
                $this->handleUserToSellerRating($rating);
            } elseif ($rating->type === 'seller_to_user') {
                // Notificar al usuario cuando recibe una valoración del vendedor
                $this->handleSellerToUserRating($rating);
            }

            // 🔴 Notificar a admin si es una valoración muy baja (1 estrella o menos)
            if ($rating->rating <= 1) {
                $this->notifyAdminLowRating($rating);
            }

        } catch (\Exception $e) {
            Log::error('❌ Error sending rating received notification', [
                'error' => $e->getMessage(),
                'rating_id' => $event->ratingId,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Manejar valoración de usuario a vendedor
     */
    private function handleUserToSellerRating(Rating $rating): void
    {
        // Solo notificar si la valoración está aprobada
        if ($rating->status === 'approved') {
            Log::info('✅ Notificando vendedor de nueva valoración aprobada', [
                'rating_id' => $rating->id,
                'rating_value' => $rating->rating,
                'seller_id' => $rating->seller_id,
            ]);

            $this->notificationService->notifyRatingReceived($rating);

            Log::info('✅ Notificación enviada al vendedor exitosamente');
        } else {
            Log::debug('⏸️ Rating not approved yet, skipping seller notification', [
                'rating_id' => $rating->id,
                'status' => $rating->status,
            ]);
        }
    }

    /**
     * Manejar valoración de vendedor a usuario
     */
    private function handleSellerToUserRating(Rating $rating): void
    {
        Log::info('📧 Notificando usuario de nueva valoración del vendedor', [
            'rating_id' => $rating->id,
            'rating_value' => $rating->rating,
            'user_id' => $rating->user_id,
        ]);

        // Crear notificación para el usuario cuando el vendedor lo califica
        $stars = str_repeat('⭐', $rating->rating);
        $title = "Nueva valoración del vendedor {$stars}";
        $message = "Un vendedor te ha valorado con {$rating->rating} estrellas.";

        if ($rating->title) {
            $message .= " Título: \"{$rating->title}\"";
        }

        $this->notificationService->createNotification(
            $rating->user_id,
            'seller_rated_user',
            $title,
            $message,
            [
                'rating_id' => $rating->id,
                'rating_value' => $rating->rating,
                'rating_title' => $rating->title,
                'rating_comment' => $rating->comment,
                'seller_id' => $rating->seller_id,
                'order_id' => $rating->order_id,
                'action_url' => '/ratings',
                'priority' => 'medium',
            ]
        );

        Log::info('✅ Notificación enviada al usuario exitosamente');
    }

    /**
     * Notificar a administradores sobre valoraciones muy bajas
     */
    private function notifyAdminLowRating(Rating $rating): void
    {
        Log::info('🚨 Notificando admin de valoración muy baja', [
            'rating_id' => $rating->id,
            'rating_value' => $rating->rating,
            'type' => $rating->type,
        ]);

        $notifications = $this->notificationService->notifyAdminLowRating($rating);

        Log::info('✅ Notificación de rating bajo enviada a admins', [
            'admin_notifications_count' => count($notifications),
            'rating_id' => $rating->id,
        ]);
    }
}
