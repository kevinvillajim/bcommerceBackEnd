<?php

namespace App\Listeners;

use App\Events\FeedbackReviewed;
use App\Models\Feedback;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class NotifySellerOfFeedbackResponse
{
    public function handle(FeedbackReviewed $event)
    {
        try {
            $feedback = Feedback::findOrFail($event->feedbackId);

            // Create notification for the user who submitted the feedback
            Notification::create([
                'user_id' => $feedback->user_id,
                'type' => Notification::TYPE_FEEDBACK_RESPONSE,
                'title' => 'Respuesta a tu feedback',
                'message' => "Tu feedback #{$feedback->id} ha sido {$event->status}",
                'data' => json_encode([
                    'feedback_id' => $feedback->id,
                    'status' => $event->status,
                    'admin_id' => $event->adminId,
                ]),
            ]);
        } catch (\Exception $e) {
            Log::error('Error notifying user of feedback response', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
