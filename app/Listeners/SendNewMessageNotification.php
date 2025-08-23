<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Infrastructure\Services\NotificationService;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Seller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendNewMessageNotification
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * 🔧 MEJORADO: Handle all message notifications without duplication
     */
    public function handle(MessageSent $event): void
    {
        try {
            // 🛡️ ANTI-DUPLICACIÓN: Verificar si este evento ya se procesó recientemente
            $cacheKey = "message_notification_{$event->messageId}";

            if (Cache::has($cacheKey)) {
                Log::info('⚠️ SendNewMessageNotification: Evento duplicado detectado y bloqueado', [
                    'message_id' => $event->messageId,
                    'cache_key' => $cacheKey,
                ]);

                return;
            }

            // Marcar como procesado por 5 minutos
            Cache::put($cacheKey, true, 300);

            Log::info('📧 SendNewMessageNotification: Procesando MessageSent event', [
                'message_id' => $event->messageId,
                'chat_id' => $event->chatId ?? 'not_provided',
                'sender_id' => $event->senderId ?? 'not_provided',
            ]);

            $message = Message::find($event->messageId);
            if (! $message) {
                Log::error('❌ Message not found for notification', ['message_id' => $event->messageId]);

                return;
            }

            $chat = Chat::find($message->chat_id);
            if (! $chat) {
                Log::error('❌ Chat not found for notification', ['chat_id' => $message->chat_id]);

                return;
            }

            Log::info('📋 Message details', [
                'message_id' => $message->id,
                'sender_id' => $message->sender_id,
                'chat' => [
                    'id' => $chat->id,
                    'user_id' => $chat->user_id,
                    'seller_id' => $chat->seller_id,
                    'product_id' => $chat->product_id,
                ],
            ]);

            // 🔄 NUEVO: Determinar quién debe recibir la notificación
            $recipientId = $this->determineRecipient($message, $chat);

            if (! $recipientId) {
                Log::warning('⚠️ No se pudo determinar destinatario de la notificación', [
                    'message_id' => $message->id,
                    'sender_id' => $message->sender_id,
                    'chat_user_id' => $chat->user_id,
                    'chat_seller_id' => $chat->seller_id,
                ]);

                return;
            }

            Log::info('🎯 Destinatario determinado', [
                'recipient_id' => $recipientId,
                'sender_id' => $message->sender_id,
            ]);

            // Crear la notificación usando el servicio existente
            $this->notificationService->notifyNewMessage($message, $chat);

            Log::info('✅ Notificación de mensaje enviada exitosamente', [
                'message_id' => $message->id,
                'recipient_id' => $recipientId,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error sending new message notification', [
                'error' => $e->getMessage(),
                'message_id' => $event->messageId,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Determinar quién debe recibir la notificación del mensaje
     */
    private function determineRecipient(Message $message, Chat $chat): ?int
    {
        // El destinatario es la otra persona en el chat, no quien envía
        if ($message->sender_id === $chat->user_id) {
            // El usuario envía mensaje, notificar al vendedor
            $seller = Seller::find($chat->seller_id);
            if ($seller) {
                Log::info('👤 Usuario envía mensaje a vendedor', [
                    'user_id' => $message->sender_id,
                    'seller_id' => $chat->seller_id,
                    'seller_user_id' => $seller->user_id,
                ]);

                return $seller->user_id;
            }
        } else {
            // El vendedor envía mensaje, notificar al usuario
            $seller = Seller::find($chat->seller_id);
            if ($seller && $message->sender_id === $seller->user_id) {
                Log::info('👨‍💼 Vendedor envía mensaje a usuario', [
                    'seller_user_id' => $message->sender_id,
                    'user_id' => $chat->user_id,
                ]);

                return $chat->user_id;
            }
        }

        Log::error('❌ No se pudo determinar destinatario', [
            'message_sender_id' => $message->sender_id,
            'chat_user_id' => $chat->user_id,
            'chat_seller_id' => $chat->seller_id,
        ]);

        return null;
    }
}
