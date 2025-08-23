<?php

namespace App\Listeners;

use App\Events\SellerStrikeAdded;
use App\Infrastructure\Services\NotificationService;
use App\Models\Message;
use App\Models\Seller;
use App\Models\User;
use App\Models\UserStrike;
use Illuminate\Support\Facades\Log;

class NotifySellerOfStrike
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     */
    public function handle(SellerStrikeAdded $event): void
    {
        try {
            $strike = UserStrike::find($event->strikeId);
            if (! $strike) {
                Log::error('Strike not found for notification', ['strike_id' => $event->strikeId]);

                return;
            }

            $seller = Seller::where('user_id', $event->userId)->first();
            if (! $seller) {
                Log::error('Seller not found for notification', ['user_id' => $event->userId]);

                return;
            }

            // Obtener el mensaje relacionado con el strike (si existe)
            $message = null;
            if ($strike->message_id) {
                $message = Message::find($strike->message_id);
            }

            // Notificar al vendedor
            $this->notificationService->notifySellerAboutStrike($strike);

            // Notificar a los administradores sobre el strike
            $this->notificationService->notifyAdminSellerStrike($strike, $message);

            // Verificar si es el tercer strike
            $strikeCount = UserStrike::where('user_id', $event->userId)->count();
            if ($strikeCount >= 3) {
                $user = User::find($event->userId);
                if ($user) {
                    // Block the user
                    $user->is_blocked = true;
                    $user->save();

                    // Notificar al vendedor sobre el bloqueo
                    $this->notificationService->notifySellerAccountBlocked(
                        $user,
                        'Acumulación de 3 o más strikes por mensajes inapropiados'
                    );

                    // Notificar a los administradores sobre el bloqueo
                    $this->notificationService->notifyAdminSellerStrike($strike, $message, true);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error sending seller strike notification', [
                'error' => $e->getMessage(),
                'strike_id' => $event->strikeId,
                'user_id' => $event->userId,
            ]);
        }
    }
}
