<?php

namespace App\UseCases\Chat;

use App\Domain\Repositories\ChatRepositoryInterface;
use App\Models\Seller;
use Illuminate\Support\Facades\Log;

class GetChatUseCase
{
    private ChatRepositoryInterface $chatRepository;

    public function __construct(ChatRepositoryInterface $chatRepository)
    {
        $this->chatRepository = $chatRepository;
    }

    /**
     * Ejecuta el caso de uso para obtener un chat y sus mensajes
     *
     * @param  int  $chatId  ID del chat
     * @param  int  $userId  ID del usuario solicitante
     * @param  bool  $skipPermissionCheck  Si es true, omite la verificación de permisos (útil cuando ya se validó en el controlador)
     * @return array Resultado con chat y mensajes o error
     */
    public function execute(int $chatId, int $userId, bool $skipPermissionCheck = false): array
    {
        $chat = $this->chatRepository->getChatById($chatId);

        if (! $chat) {
            return [
                'success' => false,
                'message' => 'Chat no encontrado',
            ];
        }

        // Verificar que el usuario sea parte del Chat solo si no se omite la verificación
        if (! $skipPermissionCheck) {
            $hasAccess = false;

            // Caso 1: El usuario es comprador o vendedor directo
            if ($chat->getUserId() === $userId || $chat->getSellerId() === $userId) {
                $hasAccess = true;
                Log::info("GetChatUseCase: Acceso directo permitido para usuario $userId en chat $chatId");
            }
            // Caso 2: Verificación adicional para vendedores (el usuario es dueño de una tienda con ID igual al seller_id del chat)
            else {
                $seller = Seller::where('user_id', $userId)->first();
                if ($seller && $seller->id === $chat->getSellerId()) {
                    $hasAccess = true;
                    Log::info("GetChatUseCase: Acceso como vendedor permitido para usuario $userId (seller_id={$seller->id}) en chat $chatId");
                }
            }

            if (! $hasAccess) {
                Log::warning("GetChatUseCase: Acceso denegado para usuario $userId en chat $chatId");

                return [
                    'success' => false,
                    'message' => 'No tienes permiso para acceder a esta Chat',
                ];
            }
        } else {
            Log::info("GetChatUseCase: Omitiendo verificación de permisos para usuario $userId en chat $chatId");
        }

        // Marcar los mensajes como leídos
        $this->chatRepository->markMessagesAsRead($chatId, $userId);

        // Obtener los mensajes del Chat
        $messages = $this->chatRepository->getMessagesForChat($chatId);

        return [
            'success' => true,
            'chat' => $chat,
            'messages' => $messages,
        ];
    }
}
