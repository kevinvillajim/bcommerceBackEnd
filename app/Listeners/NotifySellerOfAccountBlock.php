<?php

namespace App\Listeners;

use App\Events\SellerAccountBlocked;
use App\Infrastructure\Services\NotificationService;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotifySellerOfAccountBlock
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     */
    public function handle(SellerAccountBlocked $event): void
    {
        try {
            $user = User::find($event->userId);
            if (! $user) {
                Log::error('User not found for notification', ['user_id' => $event->userId]);

                return;
            }

            // Block the user if not already blocked
            if (! $user->is_blocked) {
                $user->is_blocked = true;
                $user->save();
            }

            $this->notificationService->notifySellerAccountBlocked($user, $event->reason);
        } catch (\Exception $e) {
            Log::error('Error sending seller account block notification', [
                'error' => $e->getMessage(),
                'user_id' => $event->userId,
            ]);
        }
    }
}
