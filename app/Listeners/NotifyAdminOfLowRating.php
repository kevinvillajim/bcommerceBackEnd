<?php

namespace App\Listeners;

use App\Events\RatingCreated;
use App\Infrastructure\Services\NotificationService;
use App\Models\Rating;
use App\Services\ConfigurationService;
use Illuminate\Support\Facades\Log;

class NotifyAdminOfLowRating
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

            // Solo procesar calificaciones para vendedores
            if ($rating->type !== 'user_to_seller') {
                return;
            }

            // 🔧 CORREGIDO: Obtener threshold dinámico de la configuración
            $autoApproveThreshold = $this->configService->getConfig('ratings.auto_approve_threshold', 2);

            // Notificar al admin si la calificación está por debajo o igual al threshold
            if ($rating->rating <= $autoApproveThreshold) {
                Log::info('Notificando admin de rating bajo', [
                    'rating_id' => $event->ratingId,
                    'rating_value' => $rating->rating,
                    'threshold' => $autoApproveThreshold,
                ]);

                $this->notificationService->notifyAdminLowRating($rating);
            }
        } catch (\Exception $e) {
            Log::error('Error sending admin low rating notification', [
                'error' => $e->getMessage(),
                'rating_id' => $event->ratingId,
            ]);
        }
    }
}
