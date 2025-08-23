<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\ChatEntity;
use App\Domain\Entities\MessageEntity;
use App\Domain\Repositories\ChatRepositoryInterface;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Seller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class EloquentChatRepository implements ChatRepositoryInterface
{
    public function createChat(ChatEntity $chatEntity): ChatEntity
    {
        $chat = new Chat;
        $chat->user_id = $chatEntity->getUserId();
        $chat->seller_id = $chatEntity->getSellerId();
        $chat->product_id = $chatEntity->getProductId();
        $chat->status = $chatEntity->getStatus();
        $chat->save();

        return new ChatEntity(
            $chat->user_id,
            $chat->seller_id,
            $chat->product_id,
            $chat->status,
            [],
            $chat->id,
            new \DateTime($chat->created_at),
            new \DateTime($chat->updated_at)
        );
    }

    public function getChatById(int $id): ?ChatEntity
    {
        $chat = Chat::find($id);
        if (! $chat) {
            return null;
        }

        $messages = $this->mapMessages($chat->messages);

        return new ChatEntity(
            $chat->user_id,
            $chat->seller_id,
            $chat->product_id,
            $chat->status,
            $messages,
            $chat->id,
            new \DateTime($chat->created_at),
            new \DateTime($chat->updated_at)
        );
    }

    /**
     * Obtiene los chats donde el usuario es el comprador
     * CORRECCIÓN: Mejorar el registro de logs y la consulta para obtener chats por userId
     */
    public function getChatsByUserId(int $userId): array
    {
        // Registrar la operación para diagnóstico
        Log::info("Obteniendo chats donde el usuario {$userId} es el comprador");

        // CORRECCIÓN 1: Mejorar la consulta para incluir información necesaria
        $chats = Chat::where('user_id', $userId)
            ->with(['messages' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(1);
            }])
            ->with(['user', 'seller', 'product'])
            ->orderBy('updated_at', 'desc')
            ->get();

        Log::info('Se encontraron '.$chats->count()." chats para el usuario {$userId}");

        return $this->mapChats($chats);
    }

    /**
     * Obtiene los chats donde el usuario es el vendedor
     * CORRECCIÓN: Mejorar la consulta para obtener chats por sellerId
     */
    public function getChatsBySellerId(int $sellerId): array
    {
        // Registrar la operación para diagnóstico
        Log::info("Obteniendo chats donde el usuario {$sellerId} es el vendedor");

        // CORRECCIÓN: Intento múltiple por diferentes campos para encontrar mejor

        // Paso 1: Intentar buscar directamente por seller_id en la tabla de chats
        $sellerChats = Chat::where('seller_id', $sellerId)
            ->with(['messages' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(1);
            }])
            ->with(['user', 'seller', 'product'])
            ->orderBy('updated_at', 'desc')
            ->get();

        Log::info("Búsqueda directa por seller_id={$sellerId} encontró ".$sellerChats->count().' chats');

        // Si ya encontramos chats, usar esos directamente
        if ($sellerChats->count() > 0) {
            return $this->mapChats($sellerChats);
        }

        // Paso 2: Verificar si el usuario tiene un registro en la tabla de vendedores
        // y buscar también por el ID del vendedor
        $sellerRecord = Seller::where('user_id', $sellerId)->first();

        if ($sellerRecord) {
            Log::info("El usuario {$sellerId} tiene un registro de vendedor con ID {$sellerRecord->id}");

            // Intentar buscar por ID de vendedor
            $sellerIdChats = Chat::where('seller_id', $sellerRecord->id)
                ->with(['messages' => function ($query) {
                    $query->orderBy('created_at', 'desc')->limit(1);
                }])
                ->with(['user', 'seller', 'product'])
                ->orderBy('updated_at', 'desc')
                ->get();

            Log::info("Búsqueda por ID de vendedor seller_id={$sellerRecord->id} encontró ".$sellerIdChats->count().' chats');

            // Si encontramos chats, usar esos directamente
            if ($sellerIdChats->count() > 0) {
                return $this->mapChats($sellerIdChats);
            }
        }

        // Paso 3: CORRECCIÓN IMPORTANTE - Intento final consultando por múltiples criterios para cubrir casos especiales
        // Intenta obtener todos los chats relacionados de alguna manera con este vendedor
        $finalQuery = Chat::where(function ($query) use ($sellerId, $sellerRecord) {
            // Busca por seller_id = userId
            $query->where('seller_id', $sellerId);

            // O por el ID del registro de vendedor si existe
            if ($sellerRecord) {
                $query->orWhere('seller_id', $sellerRecord->id);
            }

            // O por algún otro criterio que pueda estar relacionado al vendedor
            // Por ejemplo: Si los chats pueden tener un seller_user_id en vez de seller_id
            if (Schema::hasColumn('chats', 'seller_user_id')) {
                $query->orWhere('seller_user_id', $sellerId);
            }
        })
            ->with(['messages' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(1);
            }])
            ->with(['user', 'seller', 'product'])
            ->orderBy('updated_at', 'desc')
            ->get();

        Log::info('Consulta final encontró '.$finalQuery->count()." chats para el vendedor {$sellerId}");

        return $this->mapChats($finalQuery);
    }

    public function addMessage(MessageEntity $message): MessageEntity
    {
        $dbMessage = new Message;
        $dbMessage->chat_id = $message->getChatId();
        $dbMessage->sender_id = $message->getSenderId();
        $dbMessage->content = $message->getContent();
        $dbMessage->is_read = $message->isRead();
        $dbMessage->save();

        // Actualizar el timestamp del chat
        $chat = Chat::find($message->getChatId());
        if ($chat) {
            $chat->touch();
        }

        return new MessageEntity(
            $dbMessage->chat_id,
            $dbMessage->sender_id,
            $dbMessage->content,
            $dbMessage->is_read,
            $dbMessage->id,
            new \DateTime($dbMessage->created_at),
            new \DateTime($dbMessage->updated_at)
        );
    }

    public function getMessagesForChat(int $chatId, int $limit = 50, int $offset = 0): array
    {
        $messages = Message::where('chat_id', $chatId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $this->mapMessages($messages);
    }

    /**
     * Marca todos los mensajes no leídos como leídos
     * CORRECCIÓN: Mejorar la función para ser más eficiente y registrar resultados
     */
    public function markMessagesAsRead(int $chatId, int $userId): void
    {
        // Registrar la operación para diagnóstico
        Log::info("Marcando mensajes como leídos en chat {$chatId} para usuario {$userId}");

        // CORRECCIÓN 4: Verificar solo los mensajes no leídos
        $unreadCount = Message::where('chat_id', $chatId)
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->count();

        if ($unreadCount === 0) {
            Log::info("No hay mensajes sin leer en el chat {$chatId} para el usuario {$userId}");

            return;
        }

        // Marcar como leídos
        $updated = Message::where('chat_id', $chatId)
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        Log::info("Se marcaron {$updated} mensajes como leídos en el chat {$chatId} para el usuario {$userId}");
    }

    /**
     * Mapear entidades de Chat desde modelos Eloquent
     * CORRECCIÓN: Mejorar el mapeo para incluir más información necesaria en el frontend
     */
    private function mapChats($chats): array
    {
        $mappedChats = [];
        foreach ($chats as $chat) {
            // CORRECCIÓN 5: Obtener el conteo de mensajes no leídos para este chat
            $unreadCount = Message::where('chat_id', $chat->id)
                ->where('is_read', false)
                ->count();

            // CORRECCIÓN 6: Obtener el último mensaje para este chat
            $lastMessage = $chat->messages->first();
            $lastMessageEntity = null;

            if ($lastMessage) {
                $lastMessageEntity = new MessageEntity(
                    $lastMessage->chat_id,
                    $lastMessage->sender_id,
                    $lastMessage->content,
                    $lastMessage->is_read,
                    $lastMessage->id,
                    new \DateTime($lastMessage->created_at),
                    new \DateTime($lastMessage->updated_at)
                );
            }

            // Crear la entidad de chat con la información adicional
            $chatEntity = new ChatEntity(
                $chat->user_id,
                $chat->seller_id,
                $chat->product_id,
                $chat->status,
                [],
                $chat->id,
                new \DateTime($chat->created_at),
                new \DateTime($chat->updated_at)
            );

            // Añadir meta-información útil
            $chatEntity->setMetaInfo([
                'unreadCount' => $unreadCount,
                'lastMessage' => $lastMessageEntity,
                'user' => $chat->user ? [
                    'id' => $chat->user->id,
                    'name' => $chat->user->name,
                    'avatar' => $chat->user->avatar ?? null,
                ] : null,
                'seller' => $chat->seller ? [
                    'id' => $chat->seller->id,
                    'name' => $chat->seller->name,
                    'avatar' => $chat->seller->avatar ?? null,
                ] : null,
                'product' => $chat->product ? [
                    'id' => $chat->product->id,
                    'name' => $chat->product->name,
                    'image' => $chat->product->image ?? $chat->product->thumbnail ?? null,
                ] : null,
            ]);

            $mappedChats[] = $chatEntity;
        }

        return $mappedChats;
    }

    private function mapMessages($messages): array
    {
        $messageEntities = [];
        if ($messages) {
            foreach ($messages as $message) {
                $messageEntities[] = new MessageEntity(
                    $message->chat_id,
                    $message->sender_id,
                    $message->content,
                    $message->is_read,
                    $message->id,
                    new \DateTime($message->created_at),
                    new \DateTime($message->updated_at)
                );
            }
        }

        return $messageEntities;
    }
}
