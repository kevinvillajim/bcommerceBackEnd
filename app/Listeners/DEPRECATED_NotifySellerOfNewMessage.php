<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Infrastructure\Services\NotificationService;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotifySellerOfNewMessage
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     */
    public function handle(MessageSent $event): void
    {
        try {
            // Obtener el mensaje
            $message = Message::find($event->messageId);
            if (! $message) {
                Log::error('Message not found for notification', ['message_id' => $event->messageId]);

                return;
            }

            // Obtener el chat asociado
            $chat = Chat::find($event->chatId);
            if (! $chat) {
                Log::error('Chat not found for notification', ['chat_id' => $event->chatId]);

                return;
            }

            // No notificar si el remitente es el vendedor
            $sellerId = $chat->seller_id;
            $seller = Seller::find($sellerId);

            if (! $seller) {
                Log::error('Seller not found for notification', ['seller_id' => $sellerId]);

                return;
            }

            $sellerUserId = $seller->user_id;

            // Si el mensaje lo envió el vendedor, no notificar al vendedor
            if ($message->sender_id === $sellerUserId) {
                return;
            }

            // Obtener información del usuario y producto para la notificación
            $sender = User::find($event->senderId);
            $senderName = $sender ? $sender->name : 'Usuario';

            // Crear la notificación para el vendedor
            $this->notificationService->createNotification(
                $sellerUserId,
                'new_message',
                'Nuevo mensaje recibido',
                "Has recibido un nuevo mensaje de {$senderName}",
                [
                    'chat_id' => $chat->id,
                    'message_id' => $message->id,
                    'sender_id' => $event->senderId,
                    'content_preview' => substr($message->content, 0, 50).(strlen($message->content) > 50 ? '...' : ''),
                ]
            );

            Log::info('Notificación enviada al vendedor por nuevo mensaje', [
                'seller_id' => $sellerUserId,
                'message_id' => $message->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending seller new message notification', [
                'error' => $e->getMessage(),
                'message_id' => $event->messageId,
            ]);
        }
    }
}
