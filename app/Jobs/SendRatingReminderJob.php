<?php

namespace App\Jobs;

use App\Domain\Repositories\RatingRepositoryInterface;
use App\Infrastructure\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendRatingReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $userId;

    private int $orderId;

    private string $orderNumber;

    /**
     * Create a new job instance.
     */
    public function __construct(int $userId, int $orderId, string $orderNumber)
    {
        $this->userId = $userId;
        $this->orderId = $orderId;
        $this->orderNumber = $orderNumber;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService, RatingRepositoryInterface $ratingRepository): void
    {
        try {
            // Verificar si el usuario ya ha valorado algo de esta orden
            $hasRatedAnything = $ratingRepository->hasUserRatedAnythingFromOrder($this->userId, $this->orderId);

            // Si todavía no ha valorado nada, enviamos recordatorio
            if (! $hasRatedAnything) {
                $notificationService->sendRatingReminderNotification(
                    $this->userId,
                    $this->orderId,
                    $this->orderNumber
                );
            }
        } catch (\Exception $e) {
            Log::error('Error enviando recordatorio de valoración', [
                'error' => $e->getMessage(),
                'order_id' => $this->orderId,
                'user_id' => $this->userId,
            ]);
        }
    }
}
