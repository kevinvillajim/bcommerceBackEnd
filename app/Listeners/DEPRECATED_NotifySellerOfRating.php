<?php

namespace App\Listeners;

use App\Events\RatingCreated;
use App\Infrastructure\Services\NotificationService;
use App\Models\Rating;
use App\Services\ConfigurationService;
use Illuminate\Support\Facades\Log;

class NotifySellerOfRating
{
    private NotificationService $notificationService;

    private ConfigurationService $configService;

    public function __construct(NotificationService $notificationService,
        ConfigurationService $configService)
    {
        $this->notificationService = $notificationService;
        $this->configService = $configService;
    }

    /**
     * Handle the event.
     */
    public function handle(RatingCreated $event): void
    {
        try {
            $rating = Rating::find($event->ratingId);
            if (! $rating) {
                Log::error('Rating not found for notification', ['rating_id' => $event->ratingId]);

                return;
            }

            // Solo procesar valoraciones de usuario a vendedor
            if ($rating->type !== 'user_to_seller') {
                Log::debug('Rating type not user_to_seller, skipping notification', [
                    'rating_id' => $event->ratingId,
                    'type' => $rating->type,
                ]);

                return;
            }

            // 🔧 CORREGIDO: Solo notificar al vendedor si la valoración está aprobada
            // o si está por encima del threshold (aprobación automática)
            if ($rating->status === 'approved') {
                Log::info('Notificando vendedor de nueva valoración', [
                    'rating_id' => $event->ratingId,
                    'rating_value' => $rating->rating,
                    'status' => $rating->status,
                ]);

                $this->notificationService->notifyRatingReceived($rating);
            } else {
                Log::debug('Rating not approved yet, notification will be sent when approved', [
                    'rating_id' => $event->ratingId,
                    'status' => $rating->status,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error sending seller rating notification', [
                'error' => $e->getMessage(),
                'rating_id' => $event->ratingId,
            ]);
        }
    }
}
