<?php

namespace App\Http\Controllers;

use App\Domain\Entities\MessageEntity;
use App\Domain\Interfaces\ChatFilterInterface;
use App\Domain\Repositories\ChatRepositoryInterface;
use App\Events\MessageSent;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\UseCases\Chat\CreateChatUseCase;
use App\UseCases\Chat\FilterMessageUseCase;
use App\UseCases\Chat\GetChatUseCase;
use App\UseCases\Chat\SendMessageUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    private ChatRepositoryInterface $chatRepository;

    private ChatFilterInterface $chatFilter;

    private int $strikeLimit = 3;

    public function __construct(
        ChatRepositoryInterface $chatRepository,
        ChatFilterInterface $chatFilter
    ) {
        $this->chatRepository = $chatRepository;
        $this->chatFilter = $chatFilter;
    }

    /**
     * Display a listing of chats for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = Auth::id();

        // CORRECCIÓN 1: Mejorar la detección del rol del usuario
        $userRole = $request->user()->role ?? 'user';

        // CORRECCIÓN 2: Verificar si el usuario es un vendedor consultando la tabla de vendedores
        $isSeller = false;
        if ($userRole === 'seller') {
            $isSeller = true;
        } else {
            // Verificar si el usuario tiene un registro en la tabla de vendedores
            $sellerCheck = Seller::where('user_id', $userId)->exists();
            if ($sellerCheck) {
                $isSeller = true;
            }
        }

        // Determinar si obtenemos los chats como comprador o como vendedor
        if ($isSeller) {
            Log::info("Obteniendo chats como usuario normal con ID $userId");
            $chats = $this->chatRepository->getChatsByUserId($userId);
        } else {
            Log::info("Obteniendo chats como usuario normal con ID $userId");
            $chats = $this->chatRepository->getChatsByUserId($userId);
        }

        // Transformar las entidades a un formato adecuado para el frontend
        $formattedChats = [];
        foreach ($chats as $chat) {
            // Obtener información adicional para cada chat
            $chatMessages = $this->chatRepository->getMessagesForChat($chat->getId(), 1);
            $lastMessage = ! empty($chatMessages) ? $chatMessages[0] : null;
            $unreadCount = $this->getUnreadMessagesCount($chat->getId(), $userId);

            // Obtener información del user, seller y product
            $user = User::find($chat->getUserId());
            $seller = User::find($chat->getSellerId());
            $product = Product::find($chat->getProductId());

            // CORRECCIÓN 3: Obtener información del vendedor correctamente
            $sellerData = null;
            if ($seller) {
                $sellerInfo = Seller::where('user_id', $seller->id)->first();
                $sellerData = [
                    'id' => $seller->id,
                    'name' => $seller->name,
                    'avatar' => $seller->avatar ?? null,
                    'storeName' => ($sellerInfo && isset($sellerInfo->name)) ? $sellerInfo->name : 'Vendedor #'.$seller->id,
                ];
            }

            $formattedChats[] = [
                'id' => $chat->getId(),
                'userId' => $chat->getUserId(),
                'sellerId' => $chat->getSellerId(),
                'productId' => $chat->getProductId(),
                'status' => $chat->getStatus(),
                'createdAt' => $chat->getCreatedAt()->format('Y-m-d H:i:s'),
                'updatedAt' => $chat->getUpdatedAt()->format('Y-m-d H:i:s'),
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatar ?? null,
                ] : null,
                'seller' => $sellerData,
                'product' => $product ? [
                    'id' => $product->id,
                    'name' => $product->name,
                    'image' => $product->image ?? $product->thumbnail ?? null,
                    'price' => $product->price ?? null,
                ] : null,
                'lastMessage' => $lastMessage ? [
                    'id' => $lastMessage->getId(),
                    'content' => $lastMessage->getContent(),
                    'senderId' => $lastMessage->getSenderId(),
                    'isRead' => $lastMessage->isRead(),
                    'createdAt' => $lastMessage->getCreatedAt()->format('Y-m-d H:i:s'),
                ] : null,
                'unreadCount' => $unreadCount,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $formattedChats,
        ]);
    }

    /**
     * Display a listing of chats for the seller.
     * CORRECCIÓN: Método específico para vendedores
     */
    public function indexSeller(Request $request): JsonResponse
    {
        $userId = Auth::id();

        // Log detallado para depuración
        Log::info("Iniciando indexSeller para vendedor con ID $userId");

        // Verificar si el usuario es un vendedor
        $seller = Seller::where('user_id', $userId)->first();

        if (! $seller) {
            Log::warning("Usuario $userId intentó acceder a chats de vendedor pero no es un vendedor");

            return response()->json([
                'status' => 'error',
                'message' => 'Solo los vendedores pueden acceder a esta sección',
            ], 403);
        }

        Log::info("Vendedor verificado: {$seller->id}, user_id: {$userId}");

        // CORRECCIÓN IMPORTANTE: Obtener los chats donde el usuario es el vendedor
        // Intentamos obtener directamente de la tabla de chats sin usar el repositorio
        $chatsDirectQuery = Chat::where('seller_id', $userId)
            ->with(['messages' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(1);
            }])
            ->with(['user', 'product'])
            ->orderBy('updated_at', 'desc')
            ->get();

        Log::info("Consulta directa encontró {$chatsDirectQuery->count()} chats para vendedor $userId");

        // Si no encontramos chats directamente, intentar con el repositorio
        $chats = $this->chatRepository->getChatsBySellerId($userId);
        Log::info('Repositorio encontró '.count($chats)." chats para vendedor $userId");

        // Preferir la consulta directa si encontró chats
        if ($chatsDirectQuery->count() > 0 && count($chats) == 0) {
            Log::info('Usando resultados de consulta directa');
            // Convertir los resultados de la consulta directa a formattedChats
            $formattedChats = $chatsDirectQuery->map(function ($chat) use ($userId, $seller) {
                $lastMessage = $chat->messages->first();
                $unreadCount = Message::where('chat_id', $chat->id)
                    ->where('sender_id', '!=', $userId)
                    ->where('is_read', false)
                    ->count();

                return [
                    'id' => $chat->id,
                    'userId' => $chat->user_id,
                    'sellerId' => $chat->seller_id,
                    'productId' => $chat->product_id,
                    'status' => $chat->status,
                    'createdAt' => $chat->created_at,
                    'updatedAt' => $chat->updated_at,
                    'user' => $chat->user ? [
                        'id' => $chat->user->id,
                        'name' => $chat->user->name,
                        'avatar' => $chat->user->avatar ?? null,
                    ] : null,
                    'seller' => $seller ? [
                        'id' => $seller->user_id,
                        'name' => User::find($seller->user_id)->name ?? 'Vendedor',
                        'avatar' => User::find($seller->user_id)->avatar ?? null,
                        'storeName' => $seller->name ?? 'Tienda #'.$seller->id,
                    ] : null,
                    'product' => $chat->product ? [
                        'id' => $chat->product->id,
                        'name' => $chat->product->name,
                        'image' => $chat->product->image ?? $chat->product->thumbnail ?? null,
                        'price' => $chat->product->price ?? null,
                    ] : null,
                    'lastMessage' => $lastMessage ? [
                        'id' => $lastMessage->id,
                        'content' => $lastMessage->content,
                        'senderId' => $lastMessage->sender_id,
                        'isRead' => (bool) $lastMessage->is_read,
                        'createdAt' => $lastMessage->created_at,
                    ] : null,
                    'unreadCount' => $unreadCount,
                ];
            })->toArray();

            return response()->json([
                'status' => 'success',
                'data' => $formattedChats,
            ]);
        }

        // Seguir con el procesamiento normal si la consulta directa no encontró nada
        Log::info('Procesando '.count($chats).' chats del repositorio');

        // Transformar las entidades a un formato adecuado para el frontend
        $formattedChats = [];
        foreach ($chats as $chat) {
            // Obtener información adicional para cada chat
            $chatMessages = $this->chatRepository->getMessagesForChat($chat->getId(), 1);
            $lastMessage = ! empty($chatMessages) ? $chatMessages[0] : null;
            $unreadCount = $this->getUnreadMessagesCount($chat->getId(), $userId);

            // Obtener información del user, seller y product
            $user = User::find($chat->getUserId());
            $product = Product::find($chat->getProductId());

            // Obtener información del vendedor correctamente
            $sellerData = null;
            if ($seller) {
                $sellerData = [
                    'id' => $seller->user_id,
                    'name' => User::find($seller->user_id)->name ?? 'Vendedor',
                    'avatar' => User::find($seller->user_id)->avatar ?? null,
                    'storeName' => $seller->name ?? 'Tienda #'.$seller->id,
                ];
            }

            $formattedChats[] = [
                'id' => $chat->getId(),
                'userId' => $chat->getUserId(),
                'sellerId' => $chat->getSellerId(),
                'productId' => $chat->getProductId(),
                'status' => $chat->getStatus(),
                'createdAt' => $chat->getCreatedAt()->format('Y-m-d H:i:s'),
                'updatedAt' => $chat->getUpdatedAt()->format('Y-m-d H:i:s'),
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatar ?? null,
                ] : null,
                'seller' => $sellerData,
                'product' => $product ? [
                    'id' => $product->id,
                    'name' => $product->name,
                    'image' => $product->image ?? $product->thumbnail ?? null,
                    'price' => $product->price ?? null,
                ] : null,
                'lastMessage' => $lastMessage ? [
                    'id' => $lastMessage->getId(),
                    'content' => $lastMessage->getContent(),
                    'senderId' => $lastMessage->getSenderId(),
                    'isRead' => $lastMessage->isRead(),
                    'createdAt' => $lastMessage->getCreatedAt()->format('Y-m-d H:i:s'),
                ] : null,
                'unreadCount' => $unreadCount,
            ];
        }

        Log::info('Devolviendo '.count($formattedChats)." chats formateados para el vendedor $userId");

        return response()->json([
            'status' => 'success',
            'data' => $formattedChats,
        ]);
    }

    public function indexSellerById(Request $request, int $sellerId): JsonResponse
    {
        $loggedUserId = Auth::id();

        // Verificar si el usuario es un vendedor o admin
        $userCanAccess = false;
        $userRole = $request->user()->role ?? 'user';

        // VERIFICACIÓN DE PERMISOS COMPLETA

        // Caso 1: El usuario es un administrador (siempre tiene acceso)
        if ($userRole === 'admin') {
            $userCanAccess = true;
        }
        // Caso 2: El user_id coincide directamente con el seller_id solicitado
        elseif ($loggedUserId === $sellerId) {
            $userCanAccess = true;
        } else {
            // Caso 3: Verificar si el usuario logueado es un vendedor en la tabla sellers
            $sellerRecord = Seller::where('user_id', $loggedUserId)->first();

            if ($sellerRecord) {
                Log::info("Usuario $loggedUserId es un vendedor con seller_id={$sellerRecord->id}");

                // Caso 3.1: El usuario es dueño del seller_id solicitado
                if ($sellerRecord->id == $sellerId) {
                    $userCanAccess = true;
                }
                // Caso 3.2: El usuario está intentando acceder a chats donde figura como vendedor por su user_id
                else {
                    // Verificar si hay chats donde el usuario aparece como seller_id
                    $chatsCount = Chat::where('seller_id', $loggedUserId)->count();

                    if ($chatsCount > 0) {
                        $userCanAccess = true;
                        Log::info("Acceso permitido: Usuario $loggedUserId aparece como seller_id en $chatsCount chats");
                    }
                }

                // Caso 3.3: Verificar específicamente si el vendedor intenta acceder a sus propios chats
                // mediante la ruta /seller/chats/by-seller/[ID], donde ID es su seller_id (no su user_id)
                if (! $userCanAccess) {
                    // Verificar si hay chats donde el seller_id solicitado coincide con el ID del vendedor
                    $sellerChatsCount = Chat::where('seller_id', $sellerRecord->id)->count();

                    if ($sellerChatsCount > 0 && $sellerId == $sellerRecord->id) {
                        $userCanAccess = true;
                        Log::info("Acceso permitido: Usuario $loggedUserId (seller_id={$sellerRecord->id}) está accediendo a sus propios chats");
                    }
                }
            } else {
                Log::info("Usuario $loggedUserId no es un vendedor registrado");
            }
        }

        if (! $userCanAccess) {
            Log::warning("Usuario $loggedUserId intentó acceder a chats del vendedor $sellerId sin autorización");

            return response()->json([
                'status' => 'error',
                'message' => 'No tienes permiso para acceder a estos chats',
            ], 403);
        }

        // SOLUCIÓN SIMPLIFICADA: Buscar ÚNICAMENTE por seller_id
        // Sin planes alternativos o comprobaciones de user_id
        $chats = Chat::where('seller_id', $sellerId)
            ->with(['messages' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(1);
            }])
            ->with(['user', 'product'])
            ->orderBy('updated_at', 'desc')
            ->get();

        // Obtener información del vendedor solo para mostrar en la respuesta
        $sellerEntity = Seller::find($sellerId);
        $sellerUser = $sellerEntity ? User::find($sellerEntity->user_id) : null;

        // Preparar los datos del vendedor para la respuesta
        $sellerData = null;
        if ($sellerUser && $sellerEntity) {
            $sellerData = [
                'id' => $sellerUser->id,
                'name' => $sellerUser->name,
                'avatar' => $sellerUser->avatar ?? null,
                'storeName' => $sellerEntity->name ?? 'Vendedor #'.$sellerId,
                'sellerId' => $sellerEntity->id,
            ];
        }

        // Formatear los chats para la respuesta
        $formattedChats = $chats->map(function ($chat) use ($loggedUserId, $sellerData) {
            $lastMessage = $chat->messages->first();
            $unreadCount = Message::where('chat_id', $chat->id)
                ->where('sender_id', '!=', $loggedUserId)
                ->where('is_read', false)
                ->count();

            return [
                'id' => $chat->id,
                'userId' => $chat->user_id,
                'sellerId' => $chat->seller_id,
                'productId' => $chat->product_id,
                'status' => $chat->status,
                'createdAt' => $chat->created_at,
                'updatedAt' => $chat->updated_at,
                'user' => $chat->user ? [
                    'id' => $chat->user->id,
                    'name' => $chat->user->name,
                    'avatar' => $chat->user->avatar ?? null,
                ] : null,
                'seller' => $sellerData,
                'product' => $chat->product ? [
                    'id' => $chat->product->id,
                    'name' => $chat->product->name,
                    'image' => $chat->product->image ?? $chat->product->thumbnail ?? null,
                    'price' => $chat->product->price ?? null,
                ] : null,
                'lastMessage' => $lastMessage ? [
                    'id' => $lastMessage->id,
                    'content' => $lastMessage->content,
                    'senderId' => $lastMessage->sender_id,
                    'isRead' => (bool) $lastMessage->is_read,
                    'createdAt' => $lastMessage->created_at,
                ] : null,
                'unreadCount' => $unreadCount,
            ];
        })->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $formattedChats,
        ]);
    }

    /**
     * Store a newly created chat.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'seller_id' => 'required|integer|exists:users,id',
            'product_id' => 'required|integer|exists:products,id',
        ]);

        $userId = Auth::id();
        $sellerId = $request->input('seller_id');
        $productId = $request->input('product_id');

        // CORRECCIÓN 4: Prevenir que un vendedor inicie chat consigo mismo
        if ($userId === $sellerId) {
            return response()->json([
                'status' => 'error',
                'message' => 'No puedes iniciar un chat contigo mismo',
            ], 400);
        }

        // Verificar si ya existe un chat con este vendedor para este producto
        $existingChat = Chat::where('user_id', $userId)
            ->where('seller_id', $sellerId)
            ->where('product_id', $productId)
            ->first();

        if ($existingChat) {
            return response()->json([
                'status' => 'success',
                'message' => 'Ya existe un chat para este producto con este vendedor',
                'data' => [
                    'chat_id' => $existingChat->id,
                ],
            ]);
        }

        // Crear un nuevo chat
        $createChatUseCase = new CreateChatUseCase($this->chatRepository);
        $chat = $createChatUseCase->execute($userId, $sellerId, $productId);

        return response()->json([
            'status' => 'success',
            'message' => 'Chat creado correctamente',
            'data' => [
                'chat_id' => $chat->getId(),
            ],
        ], 201);
    }

    /**
     * Display the specified chat with its messages.
     */
    public function show(Request $request, int $chatId): JsonResponse
    {
        $userId = Auth::id();
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 50);
        $offset = ($page - 1) * $limit;

        $getChatUseCase = new GetChatUseCase($this->chatRepository);
        $result = $getChatUseCase->execute($chatId, $userId);

        if (! $result['success']) {
            return response()->json([
                'status' => 'error',
                'message' => $result['message'],
            ], 404);
        }

        // Obtener chat desde el resultado
        $chat = $result['chat'];

        // CORRECCIÓN 5: Verificar si el usuario puede acceder a este chat (como usuario o como vendedor)
        if ($chat->getUserId() !== $userId && $chat->getSellerId() !== $userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'No tienes permiso para acceder a este chat',
            ], 403);
        }

        // Obtener información adicional para el chat
        $user = User::find($chat->getUserId());
        $seller = User::find($chat->getSellerId());
        $product = Product::find($chat->getProductId());

        // CORRECCIÓN 6: Obtener información del vendedor más completa
        $sellerData = null;
        if ($seller) {
            $sellerInfo = Seller::where('user_id', $seller->id)->first();
            $sellerData = [
                'id' => $seller->id,
                'name' => $seller->name,
                'avatar' => $seller->avatar ?? null,
                'storeName' => ($sellerInfo && isset($sellerInfo->name)) ? $sellerInfo->name : 'Vendedor #'.$seller->id,
            ];
        }

        // Obtener mensajes paginados para el chat
        $messages = $this->chatRepository->getMessagesForChat($chatId, $limit, $offset);

        // Formatear mensajes para el frontend
        $formattedMessages = [];
        foreach ($messages as $message) {
            $sender = User::find($message->getSenderId());

            $formattedMessages[] = [
                'id' => $message->getId(),
                'chatId' => $message->getChatId(),
                'senderId' => $message->getSenderId(),
                'content' => $message->getContent(),
                'isRead' => $message->isRead(),
                'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
                'updatedAt' => $message->getUpdatedAt()->format('Y-m-d H:i:s'),
                'sender' => $sender ? [
                    'id' => $sender->id,
                    'name' => $sender->name,
                    'avatar' => $sender->avatar ?? null,
                ] : null,
                'isMine' => $message->getSenderId() === $userId,
            ];
        }

        // Formatear chat para el frontend
        $formattedChat = [
            'id' => $chat->getId(),
            'userId' => $chat->getUserId(),
            'sellerId' => $chat->getSellerId(),
            'productId' => $chat->getProductId(),
            'status' => $chat->getStatus(),
            'createdAt' => $chat->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $chat->getUpdatedAt()->format('Y-m-d H:i:s'),
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar ?? null,
            ] : null,
            'seller' => $sellerData,
            'product' => $product ? [
                'id' => $product->id,
                'name' => $product->name,
                'image' => $product->image ?? $product->thumbnail ?? null,
                'price' => $product->price ?? null,
            ] : null,
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'chat' => $formattedChat,
                'messages' => $formattedMessages,
                'pagination' => [
                    'currentPage' => (int) $page,
                    'limit' => (int) $limit,
                    'total' => $this->getTotalMessagesCount($chatId),
                ],
            ],
        ]);
    }

    /**
     * Display the specified chat with its messages for a seller.
     * CORREGIDO: Método completo con formato de respuesta
     */
    public function showSeller(Request $request, int $chatId): JsonResponse
    {
        $userId = Auth::id();
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 50);
        $offset = ($page - 1) * $limit;

        Log::info("showSeller: Usuario $userId solicita chat $chatId");

        // Verificar que el chat existe
        $chat = Chat::find($chatId);
        if (! $chat) {
            Log::warning("Chat $chatId no encontrado");

            return response()->json([
                'status' => 'error',
                'message' => 'Chat no encontrado',
            ], 404);
        }

        // Sistema mejorado de verificación de permisos
        $hasAccess = $this->userHasSellerAccessToChat($userId, $chat);

        if (! $hasAccess) {
            Log::warning("Usuario $userId intentó acceder a un chat como vendedor sin tener permiso: chat_id=$chatId, seller_id={$chat->seller_id}");

            return response()->json([
                'status' => 'error',
                'message' => 'No tienes permiso para acceder a esta Chat',
            ], 403);
        }

        try {
            // Obtener chat completo usando el caso de uso
            // IMPORTANTE: Pasamos skipPermissionCheck=true porque ya verificamos permisos
            $getChatUseCase = new GetChatUseCase($this->chatRepository);
            $result = $getChatUseCase->execute($chatId, $userId, true);

            if (! $result['success']) {
                Log::error("Error en GetChatUseCase: {$result['message']}");

                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                ], 404);
            }

            // Obtener chat desde el resultado
            $chatEntity = $result['chat'];

            // Obtener información adicional para el chat
            $user = User::find($chatEntity->getUserId());
            $seller = User::find($chatEntity->getSellerId());
            $product = Product::find($chatEntity->getProductId());

            // Obtener información del vendedor correctamente
            $sellerData = null;
            if ($seller) {
                $sellerInfo = Seller::where('user_id', $seller->id)->first();
                $sellerData = [
                    'id' => $seller->id,
                    'name' => $seller->name,
                    'avatar' => $seller->avatar ?? null,
                    'storeName' => ($sellerInfo && isset($sellerInfo->name)) ? $sellerInfo->name : 'Vendedor #'.$seller->id,
                ];
            }

            // Formatear el chat para el frontend
            $formattedChat = [
                'id' => $chatEntity->getId(),
                'userId' => $chatEntity->getUserId(),
                'sellerId' => $chatEntity->getSellerId(),
                'productId' => $chatEntity->getProductId(),
                'status' => $chatEntity->getStatus(),
                'createdAt' => $chatEntity->getCreatedAt()->format('Y-m-d H:i:s'),
                'updatedAt' => $chatEntity->getUpdatedAt()->format('Y-m-d H:i:s'),
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatar ?? null,
                ] : null,
                'seller' => $sellerData,
                'product' => $product ? [
                    'id' => $product->id,
                    'name' => $product->name,
                    'image' => $product->image ?? $product->thumbnail ?? null,
                    'price' => $product->price ?? null,
                ] : null,
            ];

            // Obtener mensajes
            $messages = $result['messages'];

            // Formatear mensajes para el frontend
            $formattedMessages = [];
            foreach ($messages as $message) {
                $sender = User::find($message->getSenderId());

                $formattedMessages[] = [
                    'id' => $message->getId(),
                    'chatId' => $message->getChatId(),
                    'senderId' => $message->getSenderId(),
                    'content' => $message->getContent(),
                    'isRead' => $message->isRead(),
                    'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
                    'updatedAt' => $message->getUpdatedAt()->format('Y-m-d H:i:s'),
                    'sender' => $sender ? [
                        'id' => $sender->id,
                        'name' => $sender->name,
                        'avatar' => $sender->avatar ?? null,
                    ] : null,
                    'isMine' => $message->getSenderId() === $userId,
                ];
            }

            Log::info("Enviando datos del chat $chatId con éxito");

            return response()->json([
                'status' => 'success',
                'data' => [
                    'chat' => $formattedChat,
                    'messages' => $formattedMessages,
                    'pagination' => [
                        'currentPage' => (int) $page,
                        'limit' => (int) $limit,
                        'total' => $this->getTotalMessagesCount($chatId),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error inesperado en showSeller: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al cargar el chat: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get messages for a specific chat with pagination.
     */
    public function getMessages(Request $request, int $chatId): JsonResponse
    {
        $userId = Auth::id();
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 20);
        $offset = ($page - 1) * $limit;

        // Verificar que el chat existe y que el usuario tiene permiso
        $chat = Chat::find($chatId);
        if (! $chat) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chat no encontrado',
            ], 404);
        }

        if ($chat->user_id !== $userId && $chat->seller_id !== $userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'No tienes permiso para acceder a este chat',
            ], 403);
        }

        // Obtener mensajes paginados
        $messages = $this->chatRepository->getMessagesForChat($chatId, $limit, $offset);

        // Formatear mensajes
        $formattedMessages = [];
        foreach ($messages as $message) {
            $sender = User::find($message->getSenderId());

            $formattedMessages[] = [
                'id' => $message->getId(),
                'chatId' => $message->getChatId(),
                'senderId' => $message->getSenderId(),
                'content' => $message->getContent(),
                'isRead' => $message->isRead(),
                'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
                'updatedAt' => $message->getUpdatedAt()->format('Y-m-d H:i:s'),
                'sender' => $sender ? [
                    'id' => $sender->id,
                    'name' => $sender->name,
                    'avatar' => $sender->avatar ?? null,
                ] : null,
                'isMine' => $message->getSenderId() === $userId,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'messages' => $formattedMessages,
                'pagination' => [
                    'currentPage' => (int) $page,
                    'limit' => (int) $limit,
                    'total' => $this->getTotalMessagesCount($chatId),
                ],
            ],
        ]);
    }

    /**
     * Get messages for a specific chat with pagination for a seller.
     * CORREGIDO: Permisos sincronizados
     */
    public function getMessagesSeller(Request $request, int $chatId): JsonResponse
    {
        $userId = Auth::id();
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 20);
        $offset = ($page - 1) * $limit;

        Log::info("getMessagesSeller: Usuario $userId solicita mensajes de chat $chatId");

        // Verificar que el chat existe
        $chat = Chat::find($chatId);
        if (! $chat) {
            Log::warning("Chat $chatId no encontrado");

            return response()->json([
                'status' => 'error',
                'message' => 'Chat no encontrado',
            ], 404);
        }

        // Sistema mejorado de verificación de permisos
        $hasAccess = $this->userHasSellerAccessToChat($userId, $chat);

        if (! $hasAccess) {
            Log::warning("Usuario $userId intentó acceder a mensajes de chat como vendedor sin tener permiso: chat_id=$chatId, seller_id={$chat->seller_id}");

            return response()->json([
                'status' => 'error',
                'message' => 'No tienes permiso para acceder a esta Chat',
            ], 403);
        }

        try {
            // Obtener mensajes paginados
            $messages = $this->chatRepository->getMessagesForChat($chatId, $limit, $offset);

            // También marcar automáticamente como leídos
            $this->chatRepository->markMessagesAsRead($chatId, $userId);

            // Formatear mensajes
            $formattedMessages = [];
            foreach ($messages as $message) {
                $sender = User::find($message->getSenderId());

                $formattedMessages[] = [
                    'id' => $message->getId(),
                    'chatId' => $message->getChatId(),
                    'senderId' => $message->getSenderId(),
                    'content' => $message->getContent(),
                    'isRead' => $message->isRead(),
                    'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
                    'updatedAt' => $message->getUpdatedAt()->format('Y-m-d H:i:s'),
                    'sender' => $sender ? [
                        'id' => $sender->id,
                        'name' => $sender->name,
                        'avatar' => $sender->avatar ?? null,
                    ] : null,
                    'isMine' => $message->getSenderId() === $userId,
                ];
            }

            Log::info('Enviando '.count($formattedMessages)." mensajes para el chat $chatId");

            return response()->json([
                'status' => 'success',
                'data' => [
                    'messages' => $formattedMessages,
                    'pagination' => [
                        'currentPage' => (int) $page,
                        'limit' => (int) $limit,
                        'total' => $this->getTotalMessagesCount($chatId),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error inesperado en getMessagesSeller: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al cargar los mensajes: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send a message to the specified chat.
     */
    public function storeMessage(Request $request, int $chatId): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $userId = Auth::id();
        $content = $request->input('content');

        // Verificar que el chat existe y que el usuario tiene permiso
        $chat = Chat::find($chatId);
        if (! $chat) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chat no encontrado',
            ], 404);
        }

        if ($chat->user_id !== $userId && $chat->seller_id !== $userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'No tienes permiso para enviar mensajes a este chat',
            ], 403);
        }

        // Verificar si el chat está activo
        if ($chat->status !== 'active') {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pueden enviar mensajes a un chat '.($chat->status === 'closed' ? 'cerrado' : 'archivado'),
            ], 400);
        }

        // Filtrar el mensaje para contenido prohibido
        $filterMessageUseCase = new FilterMessageUseCase(
            $this->chatFilter,
            $this->strikeLimit
        );

        $filterResult = $filterMessageUseCase->execute($content, $userId);

        if (! $filterResult['success']) {
            return response()->json([
                'status' => 'error',
                'message' => $filterResult['message'],
                'data' => [
                    'censored_content' => $filterResult['censored_content'] ?? null,
                ],
            ], 400);
        }

        // Enviar el mensaje
        $sendMessageUseCase = new SendMessageUseCase(
            $this->chatRepository,
            $this->chatFilter,
            $this->strikeLimit
        );

        $result = $sendMessageUseCase->execute($chatId, $userId, $content);

        if (! $result['success']) {
            return response()->json([
                'status' => 'error',
                'message' => $result['message'],
                'data' => [
                    'censored_content' => $result['censored_content'] ?? null,
                ],
            ], 400);
        }

        // Convertir la entidad MessageEntity a un formato para el frontend
        $messageEntity = $result['message'];
        $messageId = $messageEntity->getId();

        // Formatear el mensaje para el frontend
        $sender = User::find($userId);
        $formattedMessage = [
            'id' => $messageEntity->getId(),
            'chatId' => $messageEntity->getChatId(),
            'senderId' => $messageEntity->getSenderId(),
            'content' => $messageEntity->getContent(),
            'isRead' => $messageEntity->isRead(),
            'createdAt' => $messageEntity->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $messageEntity->getUpdatedAt()->format('Y-m-d H:i:s'),
            'sender' => $sender ? [
                'id' => $sender->id,
                'name' => $sender->name,
                'avatar' => $sender->avatar ?? null,
            ] : null,
            'isMine' => true,
        ];

        // Disparar el evento MessageSent con el ID del mensaje correcto
        if ($messageId) {
            Event::dispatch(new MessageSent($messageId, $chatId, $userId));
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Mensaje enviado correctamente',
            'data' => [
                'message' => $formattedMessage,
            ],
        ]);
    }

    /**
     * Send a message to the specified chat as a seller.
     * CORREGIDO: Mejorado el sistema de permisos
     */
    public function storeMessageSeller(Request $request, int $chatId): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $userId = Auth::id();
        $content = $request->input('content');

        Log::info("storeMessageSeller: Usuario $userId intenta enviar mensaje a chat $chatId");

        // Verificar que el chat existe
        $chat = Chat::find($chatId);
        if (! $chat) {
            Log::warning("Chat $chatId no encontrado");

            return response()->json([
                'status' => 'error',
                'message' => 'Chat no encontrado',
            ], 404);
        }

        // CORRECCIÓN: Sistema mejorado de verificación de permisos
        $hasAccess = $this->userHasSellerAccessToChat($userId, $chat);

        if (! $hasAccess) {
            Log::warning("Usuario $userId intentó enviar mensaje como vendedor sin tener permiso: chat_id=$chatId, seller_id={$chat->seller_id}");

            return response()->json([
                'status' => 'error',
                'message' => 'No tienes permiso para enviar mensajes a esta Chat',
            ], 403);
        }

        // Verificar si el chat está activo
        if ($chat->status !== 'active') {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pueden enviar mensajes a un chat '.($chat->status === 'closed' ? 'cerrado' : 'archivado'),
            ], 400);
        }

        try {
            // Filtrar el mensaje para contenido prohibido
            $filterMessageUseCase = new FilterMessageUseCase(
                $this->chatFilter,
                $this->strikeLimit
            );

            $filterResult = $filterMessageUseCase->execute($content, $userId);

            if (! $filterResult['success']) {
                return response()->json([
                    'status' => 'error',
                    'message' => $filterResult['message'],
                    'data' => [
                        'censored_content' => $filterResult['censored_content'] ?? null,
                    ],
                ], 400);
            }

            // Enviar el mensaje
            $sendMessageUseCase = new SendMessageUseCase(
                $this->chatRepository,
                $this->chatFilter,
                $this->strikeLimit
            );

            $result = $sendMessageUseCase->execute($chatId, $userId, $content);

            if (! $result['success']) {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                    'data' => [
                        'censored_content' => $result['censored_content'] ?? null,
                    ],
                ], 400);
            }

            // Convertir la entidad MessageEntity a un formato para el frontend
            $messageEntity = $result['message'];
            $messageId = $messageEntity->getId();

            // Formatear el mensaje para el frontend
            $sender = User::find($userId);
            $formattedMessage = [
                'id' => $messageEntity->getId(),
                'chatId' => $messageEntity->getChatId(),
                'senderId' => $messageEntity->getSenderId(),
                'content' => $messageEntity->getContent(),
                'isRead' => $messageEntity->isRead(),
                'createdAt' => $messageEntity->getCreatedAt()->format('Y-m-d H:i:s'),
                'updatedAt' => $messageEntity->getUpdatedAt()->format('Y-m-d H:i:s'),
                'sender' => $sender ? [
                    'id' => $sender->id,
                    'name' => $sender->name,
                    'avatar' => $sender->avatar ?? null,
                ] : null,
                'isMine' => true,
            ];

            // Disparar el evento MessageSent con el ID del mensaje correcto
            if ($messageId) {
                Event::dispatch(new MessageSent($messageId, $chatId, $userId));
            }

            Log::info("Mensaje enviado correctamente al chat $chatId");

            return response()->json([
                'status' => 'success',
                'message' => 'Mensaje enviado correctamente',
                'data' => [
                    'message' => $formattedMessage,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error inesperado en storeMessageSeller: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al enviar el mensaje: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the chat status (close, archive, reopen).
     */
    public function update(Request $request, int $chatId): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:active,closed,archived',
        ]);

        $userId = Auth::id();
        $status = $request->input('status');

        // Verificar que el chat existe y que el usuario tiene permiso
        $chat = Chat::find($chatId);
        if (! $chat) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chat no encontrado',
            ], 404);
        }

        if ($chat->user_id !== $userId && $chat->seller_id !== $userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'No tienes permiso para actualizar este chat',
            ], 403);
        }

        // Actualizar el estado del chat
        $chat->status = $status;
        $chat->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Estado del chat actualizado correctamente',
            'data' => [
                'chat_id' => $chat->id,
                'status' => $chat->status,
            ],
        ]);
    }

    /**
     * Update the chat status (close, archive, reopen) as a seller.
     * CORREGIDO: Mejorado el sistema de permisos
     */
    public function updateStatus(Request $request, int $chatId): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:active,closed,archived',
        ]);

        $userId = Auth::id();
        $status = $request->input('status');

        Log::info("updateStatus: Usuario $userId solicita cambiar estado del chat $chatId a '$status'");

        // Verificar que el chat existe
        $chat = Chat::find($chatId);
        if (! $chat) {
            Log::warning("Chat $chatId no encontrado");

            return response()->json([
                'status' => 'error',
                'message' => 'Chat no encontrado',
            ], 404);
        }

        // CORRECCIÓN: Sistema mejorado de verificación de permisos
        $hasAccess = $this->userHasSellerAccessToChat($userId, $chat);

        if (! $hasAccess) {
            Log::warning("Usuario $userId intentó actualizar estado de chat sin tener permiso: chat_id=$chatId, seller_id={$chat->seller_id}");

            return response()->json([
                'status' => 'error',
                'message' => 'No tienes permiso para actualizar esta Chat',
            ], 403);
        }

        try {
            // Actualizar el estado del chat
            $chat->status = $status;
            $chat->save();

            Log::info("Estado del chat $chatId actualizado a '$status'");

            return response()->json([
                'status' => 'success',
                'message' => 'Estado del chat actualizado correctamente',
                'data' => [
                    'chat_id' => $chat->id,
                    'status' => $chat->status,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error inesperado en updateStatus: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar estado del chat: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark messages as read.
     */
    public function markAsRead(Request $request, int $chatId): JsonResponse
    {
        $userId = Auth::id();

        // Verificar que el chat existe y que el usuario tiene permiso
        $chat = Chat::find($chatId);
        if (! $chat) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chat no encontrado',
            ], 404);
        }

        if ($chat->user_id !== $userId && $chat->seller_id !== $userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'No tienes permiso para acceder a este chat',
            ], 403);
        }

        // Marcar mensajes como leídos
        $this->chatRepository->markMessagesAsRead($chatId, $userId);

        return response()->json([
            'status' => 'success',
            'message' => 'Mensajes marcados como leídos',
        ]);
    }

    /**
     * Mark messages as read as a seller.
     * CORREGIDO: Mejorado el sistema de permisos
     */
    public function markAsReadSeller(int $chatId): JsonResponse
    {
        $userId = Auth::id();

        Log::info("markAsReadSeller: Usuario $userId solicita marcar como leídos los mensajes del chat $chatId");

        // Verificar que el chat existe
        $chat = Chat::find($chatId);
        if (! $chat) {
            Log::warning("Chat $chatId no encontrado");

            return response()->json([
                'status' => 'error',
                'message' => 'Chat no encontrado',
            ], 404);
        }

        // CORRECCIÓN: Sistema mejorado de verificación de permisos
        $hasAccess = $this->userHasSellerAccessToChat($userId, $chat);

        if (! $hasAccess) {
            Log::warning("Usuario $userId intentó marcar mensajes como leídos sin tener permiso: chat_id=$chatId, seller_id={$chat->seller_id}");

            return response()->json([
                'status' => 'error',
                'message' => 'No tienes permiso para acceder a esta Chat',
            ], 403);
        }

        try {
            // Marcar mensajes como leídos
            $this->chatRepository->markMessagesAsRead($chatId, $userId);

            Log::info("Mensajes del chat $chatId marcados como leídos para usuario $userId");

            return response()->json([
                'status' => 'success',
                'message' => 'Mensajes marcados como leídos',
            ]);
        } catch (\Exception $e) {
            Log::error('Error inesperado en markAsReadSeller: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al marcar mensajes como leídos: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark a specific message as read.
     */
    public function markMessageAsRead(int $chatId, int $messageId): JsonResponse
    {
        $userId = Auth::id();

        // Verificar que el chat existe y que el usuario tiene permiso
        $chat = Chat::find($chatId);
        if (! $chat) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chat no encontrado',
            ], 404);
        }

        if ($chat->user_id !== $userId && $chat->seller_id !== $userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'No tienes permiso para acceder a este chat',
            ], 403);
        }

        // Verificar que el mensaje existe
        $message = Message::find($messageId);
        if (! $message || $message->chat_id !== $chatId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mensaje no encontrado',
            ], 404);
        }

        // Sólo marcar como leído si el mensaje no es del usuario actual
        if ($message->sender_id !== $userId) {
            $message->is_read = true;
            $message->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Mensaje marcado como leído',
        ]);
    }

    /**
     * Remove the specified chat (soft delete or archive).
     */
    public function destroy(int $chatId): JsonResponse
    {
        $userId = Auth::id();

        // Verificar que el chat existe y que el usuario tiene permiso
        $chat = Chat::find($chatId);
        if (! $chat) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chat no encontrado',
            ], 404);
        }

        if ($chat->user_id !== $userId && $chat->seller_id !== $userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'No tienes permiso para eliminar este chat',
            ], 403);
        }

        // En lugar de eliminarlo, lo marcamos como archivado
        $chat->status = 'archived';
        $chat->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Chat archivado correctamente',
        ]);
    }

    /**
     * Get the count of unread messages for a chat.
     */
    private function getUnreadMessagesCount(int $chatId, int $userId): int
    {
        return Message::where('chat_id', $chatId)
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Get the total count of messages for a chat.
     */
    private function getTotalMessagesCount(int $chatId): int
    {
        return Message::where('chat_id', $chatId)->count();
    }

    /**
     * Método mejorado para verificar permisos de vendedor
     * Comprueba todas las combinaciones posibles de IDs de vendedor y usuario
     *
     * @param  int  $userId  ID del usuario que solicita acceso
     * @param  Chat  $chat  Instancia del chat al que se quiere acceder
     * @return bool true si el usuario tiene acceso, false en caso contrario
     */
    private function userHasSellerAccessToChat(int $userId, Chat $chat): bool
    {
        Log::info("Verificando permisos para usuario $userId en chat {$chat->id}");
        Log::info("Datos del chat: user_id={$chat->user_id}, seller_id={$chat->seller_id}, product_id={$chat->product_id}");

        // Caso 1: El usuario es directamente el vendedor en el chat
        if ($chat->seller_id == $userId) {
            Log::info("✅ ACCESO CONCEDIDO: Usuario $userId es directamente el seller_id del chat");

            return true;
        }

        // Caso 2: El usuario es un vendedor registrado
        $sellerRecord = Seller::where('user_id', $userId)->first();
        if ($sellerRecord) {
            Log::info("Usuario $userId tiene registro de vendedor con ID={$sellerRecord->getKey()}");

            // Caso 2.1: El ID del vendedor coincide con el seller_id del chat
            if ($chat->seller_id == $sellerRecord->getKey()) {
                Log::info("✅ ACCESO CONCEDIDO: El seller_id del chat ({$chat->seller_id}) coincide con el ID del vendedor del usuario ({$sellerRecord->getKey()})");

                return true;
            }
        }

        // Caso 3: Si el usuario es administrador, siempre tiene acceso
        $user = User::find($userId);
        if ($user && ($user->role === 'admin' || $user->role === 'superadmin')) {
            Log::info("✅ ACCESO CONCEDIDO: Usuario $userId tiene acceso como administrador");

            return true;
        }

        // Caso 4: El usuario tiene una tienda asociada con el mismo ID del vendedor en el chat
        $sellerStore = Seller::where('id', $chat->seller_id)->where('user_id', $userId)->first();
        if ($sellerStore) {
            Log::info("✅ ACCESO CONCEDIDO: Usuario $userId tiene una tienda con ID={$chat->seller_id}");

            return true;
        }

        // Caso 5: IMPORTANTE - Compatibilidad con implementaciones anteriores
        // Verificar si hay un vendedor con el ID del chat y comprobamos si pertenece al usuario actual
        $chatSeller = Seller::find($chat->seller_id);
        if ($chatSeller && $chatSeller->user_id == $userId) {
            Log::info("✅ ACCESO CONCEDIDO: El chat tiene seller_id={$chat->seller_id} que pertenece al usuario $userId");

            return true;
        }

        Log::warning("❌ ACCESO DENEGADO: Usuario $userId NO tiene permisos para acceder al chat {$chat->id}");

        return false;
    }

    /**
     * Actualizar estado de escritura en chat
     * 🔧 CORREGIDO: Validación correcta para sellers
     */
    public function updateTypingStatus(Request $request, int $chatId): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'required|integer',
                'is_typing' => 'required|boolean',
            ]);

            $userId = Auth::id();
            $requestUserId = $request->input('user_id');
            $isTyping = $request->input('is_typing');

            // Verificar que el usuario solo pueda actualizar su propio estado
            if ($userId !== $requestUserId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No tienes permiso para actualizar este estado',
                ], 403);
            }

            // Verificar que el chat existe
            $chat = Chat::find($chatId);
            if (! $chat) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Chat no encontrado',
                ], 404);
            }

            // 🔧 CORREGIDO: Usar validación mejorada para sellers
            $hasAccess = false;

            // Validación básica para usuarios regulares
            if ($chat->user_id === $userId) {
                $hasAccess = true;
            }
            // Validación avanzada para sellers
            elseif ($this->userHasSellerAccessToChat($userId, $chat)) {
                $hasAccess = true;
            }

            if (! $hasAccess) {
                Log::warning("Usuario $userId intentó actualizar typing status sin permisos: chat_id=$chatId");

                return response()->json([
                    'status' => 'error',
                    'message' => 'No tienes permiso para acceder a este chat',
                ], 403);
            }

            // Por ahora solo loguear el estado de escritura
            // En el futuro podrías implementar WebSockets o similar
            Log::info('Estado de escritura actualizado', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'is_typing' => $isTyping,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Estado de escritura actualizado correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar estado de escritura: '.$e->getMessage(), [
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar estado de escritura',
            ], 500);
        }
    }
}
