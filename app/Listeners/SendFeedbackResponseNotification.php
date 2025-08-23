<?php

namespace App\Listeners;

use App\Events\FeedbackReviewed;
use App\Infrastructure\Services\NotificationService;
use App\Models\Feedback;
use Illuminate\Support\Facades\Log;

class SendFeedbackResponseNotification
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     */
    public function handle(FeedbackReviewed $event): void
    {
        try {
            $feedback = Feedback::find($event->feedbackId);
            if (! $feedback) {
                Log::error('Feedback not found for notification', ['feedback_id' => $event->feedbackId]);

                return;
            }

            $this->notificationService->notifyFeedbackResponse($feedback);
        } catch (\Exception $e) {
            Log::error('Error sending feedback response notification', [
                'error' => $e->getMessage(),
                'feedback_id' => $event->feedbackId,
            ]);
        }
    }
}
