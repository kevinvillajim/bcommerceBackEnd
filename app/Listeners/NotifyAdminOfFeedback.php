<?php

namespace App\Listeners;

use App\Events\FeedbackCreated;
use App\Events\FeedbackReviewed;
use App\Models\Admin;
use App\Models\Feedback;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class NotifyAdminOfFeedback
{
    /**
     * Handle the event.
     *
     * @param  FeedbackCreated|FeedbackReviewed  $event
     */
    public function handle($event): void
    {
        try {
            // Determine the feedback ID
            $feedbackId = $event instanceof FeedbackCreated
                ? $event->feedbackId
                : $event->feedbackId;

            // Find the feedback
            $feedback = Feedback::findOrFail($feedbackId);

            // Find all active admin users
            $admins = Admin::where('status', 'active')->get();

            // If no active admins, log and return
            if ($admins->isEmpty()) {
                Log::warning('No active admins found to notify about feedback');

                return;
            }

            // Determine the status
            $status = $event instanceof FeedbackReviewed
                ? $event->status
                : 'pending';

            // Create notifications for all active admins
            foreach ($admins as $admin) {
                Notification::create([
                    'user_id' => $admin->user_id,
                    'type' => Notification::TYPE_ADMIN_FEEDBACK,
                    'title' => 'Nueva revisión de feedback',
                    'message' => "Feedback #{$feedback->id} ha sido {$status}",
                    'data' => json_encode([
                        'feedback_id' => $feedback->id,
                        'status' => $status,
                        'user_id' => $feedback->user_id,
                        'seller_id' => $feedback->seller_id,
                    ]),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error notifying admin of feedback', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
