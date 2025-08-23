<?php

namespace App\Infrastructure\Services;

use App\Domain\Entities\NotificationEntity;
use App\Domain\Repositories\NotificationRepositoryInterface;
use App\Models\Admin;
use App\Models\Chat;
use App\Models\Feedback;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rating;
use App\Models\Seller;
use App\Models\Shipping;
use App\Models\User;
use App\Models\UserStrike;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    private NotificationRepositoryInterface $notificationRepository;

    public function __construct(NotificationRepositoryInterface $notificationRepository)
    {
        $this->notificationRepository = $notificationRepository;
    }

    /**
     * Crear una notificación para un usuario
     */
    public function createNotification(int $userId, string $type, string $title, string $message, array $data = []): NotificationEntity
    {
        $notification = new NotificationEntity(
            $userId,
            $type,
            $title,
            $message,
            $data
        );

        return $this->notificationRepository->create($notification);
    }

    /**
     * 🔧 CORREGIDO: Enviar notificación de nuevo mensaje
     */
    public function notifyNewMessage(Message $message, Chat $chat): ?NotificationEntity
    {
        // 🔧 CORRECCIÓN: Determinar correctamente el destinatario
        $recipientId = null;

        if ($message->sender_id === $chat->user_id) {
            // Usuario envía mensaje → notificar al vendedor
            $seller = Seller::find($chat->seller_id);
            $recipientId = $seller ? $seller->user_id : null;
        } else {
            // Vendedor envía mensaje → notificar al usuario
            $recipientId = $chat->user_id;
        }

        if (! $recipientId) {
            Log::error('❌ No se pudo determinar destinatario de notificación', [
                'message_id' => $message->id,
                'sender_id' => $message->sender_id,
                'chat_user_id' => $chat->user_id,
                'chat_seller_id' => $chat->seller_id,
            ]);

            return null;
        }

        // Obtener información del producto
        $product = Product::find($chat->product_id);
        $productName = $product ? $product->name : 'un producto';

        // Obtener información del remitente
        $sender = User::find($message->sender_id);
        $senderName = $sender ? $sender->name : 'Un usuario';

        $title = "Nuevo mensaje de {$senderName}";
        $messageText = "Has recibido un nuevo mensaje sobre {$productName}";

        $data = [
            'chat_id' => $chat->id,
            'message_id' => $message->id,
            'sender_id' => $message->sender_id,
            'product_id' => $chat->product_id,
        ];

        Log::info('✅ Enviando notificación de mensaje', [
            'recipient_id' => $recipientId,
            'sender_id' => $message->sender_id,
            'chat_id' => $chat->id,
        ]);

        return $this->createNotification(
            $recipientId,
            Notification::TYPE_NEW_MESSAGE,
            $title,
            $messageText,
            $data
        );
    }

    /**
     * Enviar notificación de respuesta a feedback
     */
    public function notifyFeedbackResponse(Feedback $feedback): ?NotificationEntity
    {
        if ($feedback->status === 'pending') {
            return null; // Solo notificar cuando hay resolución
        }

        $title = "Tu feedback ha sido {$feedback->status}";
        $message = $feedback->status === 'approved'
            ? "Tu feedback \"{$feedback->title}\" ha sido aprobado."
            : "Tu feedback \"{$feedback->title}\" ha sido rechazado.";

        if ($feedback->admin_notes) {
            $message .= " Notas del administrador: {$feedback->admin_notes}";
        }

        $data = [
            'feedback_id' => $feedback->id,
            'status' => $feedback->status,
            'has_discount' => $feedback->discountCode()->exists(),
        ];

        if ($feedback->discountCode()->exists()) {
            $discountCode = $feedback->discountCode;
            $data['discount_code'] = $discountCode->code;
            $data['discount_percentage'] = $discountCode->discount_percentage;
            $data['expires_at'] = $discountCode->expires_at;

            $message .= " ¡Te hemos otorgado un código de descuento del {$discountCode->discount_percentage}%: {$discountCode->code}!";
        }

        return $this->createNotification(
            $feedback->user_id,
            Notification::TYPE_FEEDBACK_RESPONSE,
            $title,
            $message,
            $data
        );
    }

    /**
     * Enviar notificación de cambio de estado de orden
     */
    public function notifyOrderStatusChange(Order $order, string $previousStatus): ?NotificationEntity
    {
        $statusDescriptions = [
            'pending' => 'pendiente',
            'processing' => 'en procesamiento',
            'paid' => 'pagada',
            'shipped' => 'enviada',
            'delivered' => 'entregada',
            'completed' => 'completada',
            'cancelled' => 'cancelada',
            'refunded' => 'reembolsada',
        ];

        $currentStatusDesc = $statusDescriptions[$order->status] ?? $order->status;
        $previousStatusDesc = $statusDescriptions[$previousStatus] ?? $previousStatus;

        $title = "Tu orden #{$order->order_number} ha cambiado de estado";
        $message = "Tu orden ha pasado de '{$previousStatusDesc}' a '{$currentStatusDesc}'.";

        $data = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'previous_status' => $previousStatus,
            'current_status' => $order->status,
        ];

        return $this->createNotification(
            $order->user_id,
            Notification::TYPE_ORDER_STATUS,
            $title,
            $message,
            $data
        );
    }

    /**
     * Enviar notificación de nuevo pedido al vendedor
     */
    public function notifyNewOrderToSeller(Order $order): ?NotificationEntity
    {
        // Obtener el vendedor
        $seller = Seller::find($order->seller_id);
        if (! $seller) {
            return null;
        }

        $title = "Nuevo pedido #{$order->order_number}";
        $message = "Has recibido un nuevo pedido por un monto de \${$order->total}.";

        $data = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'total' => $order->total,
            'user_id' => $order->user_id,
        ];

        return $this->createNotification(
            $seller->user_id,
            Notification::TYPE_NEW_ORDER,
            $title,
            $message,
            $data
        );
    }

    /**
     * Enviar notificación de actualización de precio o stock de producto
     */
    public function notifyProductUpdate(Product $product, array $changes, int $userId): ?NotificationEntity
    {
        $title = "Actualización en el producto: {$product->name}";
        $message = '';

        $data = [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'changes' => $changes,
        ];

        if (isset($changes['price'])) {
            $oldPrice = $changes['price']['old'];
            $newPrice = $changes['price']['new'];

            if ($newPrice < $oldPrice) {
                $message .= "¡El precio ha bajado de \${$oldPrice} a \${$newPrice}! ";
                $data['price_decreased'] = true;
            } else {
                $message .= "El precio ha cambiado de \${$oldPrice} a \${$newPrice}. ";
                $data['price_decreased'] = false;
            }
        }

        if (isset($changes['stock'])) {
            $oldStock = $changes['stock']['old'];
            $newStock = $changes['stock']['new'];

            if ($newStock <= 5 && $newStock > 0) {
                $message .= "¡Quedan solo {$newStock} unidades disponibles! ";
                $data['low_stock'] = true;
            } elseif ($newStock === 0) {
                $message .= 'El producto se ha agotado. ';
                $data['out_of_stock'] = true;
            } elseif ($oldStock === 0 && $newStock > 0) {
                $message .= '¡El producto está disponible nuevamente! ';
                $data['back_in_stock'] = true;
            }
        }

        if (empty($message)) {
            return null;
        }

        return $this->createNotification(
            $userId,
            Notification::TYPE_PRODUCT_UPDATE,
            $title,
            $message,
            $data
        );
    }

    /**
     * Notificar stock bajo al vendedor
     */
    public function notifyLowStockToSeller(Product $product, int $threshold = 5): ?NotificationEntity
    {
        if ($product->stock > $threshold) {
            return null;
        }

        $seller = User::find($product->user_id);
        if (! $seller) {
            return null;
        }

        $title = "Stock bajo para: {$product->name}";
        $message = $product->stock === 0
            ? "¡Tu producto {$product->name} se ha agotado completamente!"
            : "¡Tu producto {$product->name} tiene un stock bajo! Solo quedan {$product->stock} unidades.";

        $data = [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'stock' => $product->stock,
            'threshold' => $threshold,
        ];

        return $this->createNotification(
            $seller->id,
            Notification::TYPE_LOW_STOCK,
            $title,
            $message,
            $data
        );
    }

    /**
     * Enviar notificación de actualización de estado de envío
     */
    public function notifyShippingUpdate(Shipping $shipping, string $previousStatus): ?NotificationEntity
    {
        $order = Order::find($shipping->order_id);
        if (! $order) {
            return null;
        }

        $statusDescriptions = [
            'pending' => 'Pendiente',
            'processing' => 'En procesamiento',
            'ready_for_pickup' => 'Listo para recoger',
            'picked_up' => 'Recogido por transportista',
            'in_transit' => 'En tránsito',
            'out_for_delivery' => 'En camino para entrega',
            'delivered' => 'Entregado',
            'exception' => 'Problema en el envío',
            'returned' => 'Devuelto al remitente',
            'cancelled' => 'Cancelado',
        ];

        $statusDesc = $statusDescriptions[$shipping->status] ?? $shipping->status;

        $title = "Actualización de envío #{$shipping->tracking_number}";
        $message = "Tu envío ahora está: {$statusDesc}";

        if ($shipping->status === 'delivered') {
            $message = '¡Tu pedido ha sido entregado!';
        } elseif ($shipping->status === 'out_for_delivery') {
            $message = '¡Tu pedido está en camino a tu dirección!';
        } elseif (strpos($shipping->status, 'exception') === 0) {
            $message = 'Ha ocurrido un problema con tu envío. Por favor revisa los detalles.';
        }

        $data = [
            'order_id' => $shipping->order_id,
            'tracking_number' => $shipping->tracking_number,
            'status' => $shipping->status,
            'previous_status' => $previousStatus,
            'location' => $shipping->current_location,
            'estimated_delivery' => $shipping->estimated_delivery ? $shipping->estimated_delivery->format('Y-m-d') : null,
        ];

        return $this->createNotification(
            $order->user_id,
            Notification::TYPE_SHIPPING_UPDATE,
            $title,
            $message,
            $data
        );
    }

    /**
     * Notificar al vendedor de un envío sin actualización
     */
    public function notifySellerOfShippingDelay(Shipping $shipping, int $daysThreshold = 2): ?NotificationEntity
    {
        $order = Order::find($shipping->order_id);
        if (! $order || ! $order->seller_id) {
            return null;
        }

        $lastUpdated = $shipping->last_updated ?? $shipping->updated_at;
        $daysSinceLastUpdate = now()->diffInDays($lastUpdated);

        if ($daysSinceLastUpdate < $daysThreshold) {
            return null;
        }

        $seller = Seller::find($order->seller_id);
        if (! $seller) {
            return null;
        }

        $title = "¡Envío sin actualizar! #{$shipping->tracking_number}";
        $message = "Un envío no ha sido actualizado en {$daysSinceLastUpdate} días. ".
            'Por favor actualiza el estado del envío para mantener informado al cliente.';

        $data = [
            'shipping_id' => $shipping->id,
            'order_id' => $shipping->order_id,
            'tracking_number' => $shipping->tracking_number,
            'current_status' => $shipping->status,
            'days_since_update' => $daysSinceLastUpdate,
            'threshold' => $daysThreshold,
        ];

        return $this->createNotification(
            $seller->user_id,
            Notification::TYPE_SHIPPING_DELAY,
            $title,
            $message,
            $data
        );
    }

    /**
     * 🔧 MEJORADO: Notificar al vendedor de una nueva valoración aprobada
     */
    public function notifyRatingReceived(Rating $rating): ?NotificationEntity
    {
        try {
            // Solo procesar valoraciones de usuario a vendedor que estén aprobadas
            if ($rating->type !== 'user_to_seller' || $rating->status !== 'approved') {
                Log::debug('Rating no elegible para notificación al vendedor', [
                    'rating_id' => $rating->id,
                    'type' => $rating->type,
                    'status' => $rating->status,
                ]);

                return null;
            }

            $seller = Seller::find($rating->seller_id);
            if (! $seller) {
                Log::error('Vendedor no encontrado para notificación de rating', [
                    'seller_id' => $rating->seller_id,
                    'rating_id' => $rating->id,
                ]);

                return null;
            }

            // Obtener información del usuario que valoró
            $user = User::find($rating->user_id);
            $userName = $user ? $user->name : 'Un usuario';

            // Crear mensaje personalizado según la puntuación
            $stars = str_repeat('⭐', $rating->rating);
            $title = "Nueva valoración recibida {$stars}";

            if ($rating->rating >= 4) {
                $message = "¡Excelente! {$userName} te ha dado {$rating->rating} estrellas.";
            } elseif ($rating->rating >= 3) {
                $message = "{$userName} te ha valorado con {$rating->rating} estrellas.";
            } else {
                $message = "{$userName} te ha dado {$rating->rating} estrellas. Revisa los comentarios para mejorar.";
            }

            if ($rating->title) {
                $message .= " Título: \"{$rating->title}\"";
            }

            $data = [
                'rating_id' => $rating->id,
                'rating_value' => $rating->rating,
                'rating_title' => $rating->title,
                'rating_comment' => $rating->comment,
                'user_id' => $rating->user_id,
                'user_name' => $userName,
                'order_id' => $rating->order_id,
                'action_url' => '/seller/ratings',
                'priority' => $rating->rating <= 2 ? 'high' : 'medium',
                'category' => 'seller_rating_received',
            ];

            Log::info('Enviando notificación de rating al vendedor', [
                'seller_id' => $seller->id,
                'rating_id' => $rating->id,
                'rating_value' => $rating->rating,
            ]);

            return $this->createNotification(
                $seller->user_id,
                Notification::TYPE_SELLER_RATED,
                $title,
                $message,
                $data
            );
        } catch (\Exception $e) {
            Log::error('Error notificando rating al vendedor: '.$e->getMessage(), [
                'rating_id' => $rating->id ?? 'unknown',
            ]);

            return null;
        }
    }

    /**
     * Notificar al vendedor cuando recibe un strike
     */
    public function notifySellerAboutStrike(UserStrike $strike): ?NotificationEntity
    {
        $user = User::find($strike->user_id);
        if (! $user) {
            return null;
        }

        // Verificar si el usuario es un vendedor
        $seller = Seller::where('user_id', $user->id)->first();
        if (! $seller) {
            return null;
        }

        // Obtener el conteo de strikes
        $strikeCount = UserStrike::where('user_id', $user->id)->count();

        $title = 'Has recibido un strike';
        $message = "Has recibido un strike por: {$strike->reason}. ";

        if ($strikeCount >= 3) {
            $message .= 'Tu cuenta ha sido bloqueada por acumular 3 o más strikes.';
        } else {
            $message .= "Tienes {$strikeCount} de 3 strikes permitidos. Con 3 strikes tu cuenta será bloqueada.";
        }

        $data = [
            'strike_id' => $strike->id,
            'reason' => $strike->reason,
            'strike_count' => $strikeCount,
            'message_id' => $strike->message_id,
            'is_blocked' => $strikeCount >= 3,
        ];

        return $this->createNotification(
            $user->id,
            Notification::TYPE_SELLER_STRIKE,
            $title,
            $message,
            $data
        );
    }

    /**
     * Notificar al vendedor cuando su cuenta ha sido bloqueada
     */
    public function notifySellerAccountBlocked(User $user, string $reason = 'Acumulación de strikes'): ?NotificationEntity
    {
        // Verificar si el usuario es un vendedor
        $seller = Seller::where('user_id', $user->id)->first();
        if (! $seller) {
            return null;
        }

        $title = 'Tu cuenta ha sido bloqueada';
        $message = "Tu cuenta de vendedor ha sido bloqueada. Razón: {$reason}";

        $data = [
            'reason' => $reason,
            'seller_id' => $seller->id,
        ];

        return $this->createNotification(
            $user->id,
            Notification::TYPE_ACCOUNT_BLOCKED,
            $title,
            $message,
            $data
        );
    }

    /**
     * Notificar a un usuario sobre productos de interés
     *
     * @param  string  $reason  [interested, visited, cart]
     */
    public function notifyProductOfInterest(User $user, Product $product, string $reason, array $changes): ?NotificationEntity
    {
        $reasonText = '';
        switch ($reason) {
            case 'interested':
                $reasonText = 'de tu interés';
                break;
            case 'visited':
                $reasonText = 'que has visitado recientemente';
                break;
            case 'cart':
                $reasonText = 'en tu carrito';
                break;
            default:
                $reasonText = 'que sigues';
        }

        $title = "Actualización en un producto {$reasonText}";
        $message = "El producto \"{$product->name}\" ha sido actualizado. ";

        $data = [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'reason' => $reason,
            'changes' => $changes,
        ];

        if (isset($changes['price'])) {
            $oldPrice = $changes['price']['old'];
            $newPrice = $changes['price']['new'];

            if ($newPrice < $oldPrice) {
                $priceDiff = $oldPrice - $newPrice;
                $discountPercent = round(($priceDiff / $oldPrice) * 100);

                $message .= "¡El precio ha bajado un {$discountPercent}% de \${$oldPrice} a \${$newPrice}!";
                $data['price_decreased'] = true;
                $data['discount_percent'] = $discountPercent;
            }
        }

        if (isset($changes['stock']) && ! isset($changes['price'])) {
            $oldStock = $changes['stock']['old'];
            $newStock = $changes['stock']['new'];

            if ($newStock <= 5 && $newStock > 0) {
                $message .= "¡Quedan solo {$newStock} unidades disponibles!";
                $data['low_stock'] = true;
            } elseif ($oldStock === 0 && $newStock > 0) {
                $message .= '¡El producto está disponible nuevamente!';
                $data['back_in_stock'] = true;
            }
        }

        return $this->createNotification(
            $user->id,
            Notification::TYPE_PRODUCT_UPDATE,
            $title,
            $message,
            $data
        );
    }

    /**
     * Notificar ventas diarias a los administradores
     */
    public function notifyAdminDailySales(array $salesData): array
    {
        $admins = Admin::where('status', 'active')->get();
        $notifications = [];

        $title = 'Reporte de ventas diarias';
        $message = 'Total de ventas del día: $'.number_format($salesData['total'], 2);

        foreach ($admins as $admin) {
            $notification = $this->createNotification(
                $admin->user_id,
                Notification::TYPE_DAILY_SALES,
                $title,
                $message,
                $salesData
            );

            $notifications[] = $notification;
        }

        return $notifications;
    }

    /**
     * Notificar feedback a los administradores
     */
    public function notifyAdminFeedback(Feedback $feedback): array
    {
        $admins = Admin::where('status', 'active')->get();
        $notifications = [];

        $userType = $feedback->seller_id ? 'vendedor' : 'usuario';
        $title = "Nuevo feedback recibido de un $userType";
        $message = "{$feedback->title}: {$feedback->description}";

        $data = [
            'feedback_id' => $feedback->id,
            'user_id' => $feedback->user_id,
            'seller_id' => $feedback->seller_id,
            'type' => $feedback->type,
        ];

        foreach ($admins as $admin) {
            $notification = $this->createNotification(
                $admin->user_id,
                Notification::TYPE_ADMIN_FEEDBACK,
                $title,
                $message,
                $data
            );

            $notifications[] = $notification;
        }

        return $notifications;
    }

    /**
     * Notificar strike o bloqueo de vendedor a los administradores
     */
    public function notifyAdminSellerStrike(UserStrike $strike, ?Message $message = null, bool $isBlocked = false): array
    {
        $admins = Admin::where('status', 'active')->get();
        $notifications = [];

        $seller = Seller::where('user_id', $strike->user_id)->first();
        if (! $seller) {
            return [];
        }

        $title = $isBlocked ?
            "Vendedor bloqueado: {$seller->store_name}" :
            "Strike aplicado a vendedor: {$seller->store_name}";

        $messageText = $isBlocked ?
            "El vendedor ha sido bloqueado automáticamente por acumular múltiples strikes. Razón: {$strike->reason}" :
            "Se ha aplicado un strike al vendedor. Razón: {$strike->reason}";

        $data = [
            'strike_id' => $strike->id,
            'user_id' => $strike->user_id,
            'seller_id' => $seller->id,
            'reason' => $strike->reason,
            'message_id' => $message ? $message->id : null,
            'message_content' => $message ? $message->content : null,
            'is_blocked' => $isBlocked,
        ];

        $type = $isBlocked ? Notification::TYPE_SELLER_BLOCKED : Notification::TYPE_ADMIN_SELLER_STRIKE;

        foreach ($admins as $admin) {
            $notification = $this->createNotification(
                $admin->user_id,
                $type,
                $title,
                $messageText,
                $data
            );

            $notifications[] = $notification;
        }

        return $notifications;
    }

    /**
     * Notificar a administradores sobre retraso en envío
     */
    public function notifyAdminShippingDelay(Shipping $shipping, int $daysWithoutUpdate): array
    {
        $admins = Admin::where('status', 'active')->get();
        $notifications = [];

        $order = Order::find($shipping->order_id);
        if (! $order || ! $order->seller_id) {
            return [];
        }

        $seller = Seller::find($order->seller_id);
        if (! $seller) {
            return [];
        }

        $title = "Envío sin actualizar por {$daysWithoutUpdate} días";
        $message = "El vendedor {$seller->store_name} no ha actualizado el estado del envío #{$shipping->tracking_number} en {$daysWithoutUpdate} días.";

        $data = [
            'shipping_id' => $shipping->id,
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'tracking_number' => $shipping->tracking_number,
            'days_without_update' => $daysWithoutUpdate,
            'current_status' => $shipping->status,
        ];

        foreach ($admins as $admin) {
            $notification = $this->createNotification(
                $admin->user_id,
                Notification::TYPE_SHIPPING_DELAY_ADMIN,
                $title,
                $message,
                $data
            );

            $notifications[] = $notification;
        }

        return $notifications;
    }

    /**
     * Notificar a administradores sobre producto sin stock
     */
    public function notifyAdminOutOfStock(Product $product): array
    {
        $admins = Admin::where('status', 'active')->get();
        $notifications = [];

        $seller = User::find($product->user_id);
        $sellerName = $seller ? $seller->name : 'Desconocido';

        $title = "Producto agotado: {$product->name}";
        $message = "El producto {$product->name} del vendedor {$sellerName} se ha agotado completamente.";

        $data = [
            'product_id' => $product->id,
            'user_id' => $product->user_id,
            'category_id' => $product->category_id,
        ];

        foreach ($admins as $admin) {
            $notification = $this->createNotification(
                $admin->user_id,
                Notification::TYPE_PRODUCT_OUT_OF_STOCK,
                $title,
                $message,
                $data
            );

            $notifications[] = $notification;
        }

        return $notifications;
    }

    /**
     * Notificar a administradores sobre mensaje recibido
     */
    public function notifyAdminMessage(Message $message, Chat $chat, int $adminId): ?NotificationEntity
    {
        // Solo notificar si el admin está en la chat
        $admin = Admin::where('user_id', $adminId)->first();
        if (! $admin) {
            return null;
        }

        $sender = User::find($message->sender_id);
        $senderName = $sender ? $sender->name : 'Usuario desconocido';

        $title = "Nuevo mensaje de {$senderName}";
        $messageText = "Has recibido un nuevo mensaje: \"{$message->content}\"";

        $data = [
            'chat_id' => $chat->id,
            'message_id' => $message->id,
            'sender_id' => $message->sender_id,
            'content' => $message->content,
        ];

        return $this->createNotification(
            $adminId,
            Notification::TYPE_ADMIN_MESSAGE,
            $title,
            $messageText,
            $data
        );
    }

    /**
     * Notificar a administradores sobre calificación baja
     */
    public function notifyAdminLowRating(Rating $rating): array
    {
        // Solo notificar si la calificación es menor a 1
        if ($rating->rating > 1 || $rating->type !== 'user_to_seller') {
            return [];
        }

        $admins = Admin::where('status', 'active')->get();
        $notifications = [];

        $seller = Seller::find($rating->seller_id);
        $sellerName = $seller ? $seller->store_name : 'Desconocido';

        $title = "Calificación baja para vendedor: {$sellerName}";
        $message = "Un usuario ha dado una calificación de {$rating->rating}/5 al vendedor {$sellerName}.";

        if ($rating->comment) {
            $message .= " Comentario: \"{$rating->comment}\"";
        }

        $data = [
            'rating_id' => $rating->id,
            'seller_id' => $rating->seller_id,
            'user_id' => $rating->user_id,
            'rating' => $rating->rating,
            'comment' => $rating->comment,
        ];

        foreach ($admins as $admin) {
            $notification = $this->createNotification(
                $admin->user_id,
                Notification::TYPE_LOW_RATING,
                $title,
                $message,
                $data
            );

            $notifications[] = $notification;
        }

        return $notifications;
    }

    /**
     * Notificar a administradores sobre cambio de nivel de vendedor
     */
    public function notifyAdminSellerRankUp(Seller $seller, string $oldLevel, string $newLevel): array
    {
        $admins = Admin::where('status', 'active')->get();
        $notifications = [];

        $title = "Vendedor ascendido: {$seller->store_name}";
        $message = "El vendedor {$seller->store_name} ha subido de nivel {$oldLevel} a {$newLevel}.";

        $data = [
            'seller_id' => $seller->id,
            'old_level' => $oldLevel,
            'new_level' => $newLevel,
            'user_id' => $seller->user_id,
        ];

        foreach ($admins as $admin) {
            $notification = $this->createNotification(
                $admin->user_id,
                Notification::TYPE_SELLER_RANK_UP,
                $title,
                $message,
                $data
            );

            $notifications[] = $notification;
        }

        return $notifications;
    }

    /**
     * Notificar al vendedor sobre cambio de nivel
     */
    public function notifySellerRankChanged(Seller $seller, string $oldRank, string $newRank): ?NotificationEntity
    {
        if (! $seller || ! $seller->user_id) {
            return null;
        }

        $title = 'Tu nivel de vendedor ha cambiado';
        $message = "¡Felicidades! Tu nivel ha cambiado de {$oldRank} a {$newRank}.";

        // Si es una subida de nivel, añadimos un mensaje de felicitación
        if ($this->isRankPromotion($oldRank, $newRank)) {
            $message .= ' Has sido promovido a un nivel superior. Sigue así para disfrutar de más beneficios.';
        } else {
            $message .= ' Revisa los criterios para mantener tu nivel actual.';
        }

        $data = [
            'seller_id' => $seller->id,
            'old_rank' => $oldRank,
            'new_rank' => $newRank,
            'is_promotion' => $this->isRankPromotion($oldRank, $newRank),
        ];

        return $this->createNotification(
            $seller->user_id,
            Notification::TYPE_SELLER_RANK_CHANGED,
            $title,
            $message,
            $data
        );
    }

    /**
     * Determinar si el cambio de nivel es una promoción
     */
    private function isRankPromotion(string $oldRank, string $newRank): bool
    {
        $ranks = [
            'bronze' => 1,
            'silver' => 2,
            'gold' => 3,
            'platinum' => 4,
            'diamond' => 5,
        ];

        $oldRankValue = $ranks[strtolower($oldRank)] ?? 0;
        $newRankValue = $ranks[strtolower($newRank)] ?? 0;

        return $newRankValue > $oldRankValue;
    }

    /**
     * Enviar notificación de solicitud de valoración
     */
    public function sendRatingRequestNotification(int $userId, int $orderId, string $orderNumber): bool
    {
        try {
            $user = User::find($userId);

            if (! $user) {
                Log::error('Usuario no encontrado para solicitud de valoración', ['user_id' => $userId]);

                return false;
            }

            // 🔧 MEJORADO: Crear notificación más detallada
            $this->createNotification(
                $userId,
                Notification::TYPE_RATING_REQUEST,
                ' ¡Valora tu compra!',
                "Tu pedido #{$orderNumber} ha sido entregado. ¡Comparte tu experiencia y ayuda a otros compradores!",
                [
                    'order_id' => $orderId,
                    'order_number' => $orderNumber,
                    'action_url' => '/pending-rating', // Ruta al frontend
                    'action_text' => 'Valorar ahora',
                    'expires_at' => now()->addDays(30)->toDateTimeString(),
                    'priority' => 'high', // Alta prioridad para valoraciones
                    'category' => 'rating_request',
                ]
            );

            Log::info('Notificación de solicitud de valoración enviada', [
                'user_id' => $userId,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error enviando notificación de solicitud de valoración: '.$e->getMessage(), [
                'user_id' => $userId,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
            ]);

            return false;
        }
    }

    /**
     * Enviar recordatorio de valoración pendiente
     */
    public function sendRatingReminderNotification(int $userId, int $orderId, string $orderNumber): bool
    {
        try {
            $user = User::find($userId);

            if (! $user) {
                Log::error('Usuario no encontrado para recordatorio de valoración', ['user_id' => $userId]);

                return false;
            }

            // Verificar si ya valoró antes de enviar recordatorio
            $hasRated = $this->checkIfUserHasRatedOrder($userId, $orderId);
            if ($hasRated) {
                Log::info('Usuario ya valoró la orden, no enviando recordatorio', [
                    'user_id' => $userId,
                    'order_id' => $orderId,
                ]);

                return true; // No es error, simplemente ya no es necesario
            }

            // Crear recordatorio
            $this->createNotification(
                $userId,
                Notification::TYPE_RATING_REMINDER,
                'Recordatorio: Valora tu compra',
                "¿Olvidaste valorar tu pedido #{$orderNumber}? Tu opinión es muy valiosa para nuestra comunidad.",
                [
                    'order_id' => $orderId,
                    'order_number' => $orderNumber,
                    'action_url' => '/pending-rating',
                    'action_text' => 'Valorar ahora',
                    'expires_at' => now()->addDays(15)->toDateTimeString(),
                    'priority' => 'medium',
                    'category' => 'rating_reminder',
                    'is_reminder' => true,
                ]
            );

            Log::info('Recordatorio de valoración enviado', [
                'user_id' => $userId,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error enviando recordatorio de valoración: '.$e->getMessage(), [
                'user_id' => $userId,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
            ]);

            return false;
        }
    }

    /**
     * Enviar notificación de solicitud de valoración cuando un envío es entregado
     *
     * @param  array  $shippingData  Datos adicionales del envío
     */
    public function sendDeliveredOrderRatingNotification(int $userId, int $orderId, string $orderNumber, array $shippingData = []): bool
    {
        try {
            $user = User::find($userId);

            if (! $user) {
                Log::error('Usuario no encontrado para solicitud de valoración de envío', ['user_id' => $userId]);

                return false;
            }

            // Verificar si ya valoró antes de enviar notificación
            $hasRated = $this->checkIfUserHasRatedOrder($userId, $orderId);
            if ($hasRated) {
                Log::info('Usuario ya valoró la orden, no enviando notificación de envío', [
                    'user_id' => $userId,
                    'order_id' => $orderId,
                ]);

                return true;
            }

            // Crear notificación optimizada para envíos entregados
            $this->createNotification(
                $userId,
                Notification::TYPE_RATING_REQUEST, // Assuming this constant exists or will be defined
                ' ¡Tu pedido ha sido entregado!',
                "Tu pedido #{$orderNumber} ha sido entregado exitosamente. ¿Cómo fue tu experiencia?",
                [
                    'order_id' => $orderId,
                    'order_number' => $orderNumber,
                    'action_url' => '/pending-rating',
                    'action_text' => 'Valorar compra',
                    'source' => 'shipping_delivered', // Distinguir del OrderCompleted
                    'tracking_number' => $shippingData['tracking_number'] ?? null,
                    'delivery_date' => now()->toDateTimeString(),
                    'expires_at' => now()->addDays(30)->toDateTimeString(),
                    'priority' => 'high',
                    'category' => 'rating_request_delivery',
                ]
            );

            Log::info('Notificación de valoración por entrega enviada', [
                'user_id' => $userId,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'tracking_number' => $shippingData['tracking_number'] ?? null,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error enviando notificación de valoración por entrega: '.$e->getMessage(), [
                'user_id' => $userId,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'shipping_data' => $shippingData,
            ]);

            return false;
        }
    }

    /**
     * NUEVO: Enviar notificación de producto específico entregado para valoración
     *
     * @param  array  $productData  Datos del producto específico
     */
    public function sendProductDeliveredRatingNotification(int $userId, int $orderId, string $orderNumber, array $productData = []): bool
    {
        try {
            $user = User::find($userId);

            if (! $user) {
                Log::error('Usuario no encontrado para solicitud de valoración de producto', ['user_id' => $userId]);

                return false;
            }

            $productName = $productData['product_name'] ?? 'tu producto';
            $productId = $productData['product_id'] ?? null;
            $sellerId = $productData['seller_id'] ?? null;

            // Verificar si ya valoró este producto específico
            if ($productId) {
                $hasRatedProduct = Rating::where('user_id', $userId)
                    ->where('order_id', $orderId)
                    ->where('product_id', $productId)
                    ->exists();

                if ($hasRatedProduct) {
                    Log::info('Usuario ya valoró este producto', [
                        'user_id' => $userId,
                        'order_id' => $orderId,
                        'product_id' => $productId,
                    ]);

                    return true;
                }
            }

            // Crear notificación específica para el producto
            $this->createNotification(
                $userId,
                Notification::TYPE_RATING_REQUEST,
                "📦 ¡{$productName} ha sido entregado!",
                "Tu producto {$productName} del pedido #{$orderNumber} ha sido entregado. ¿Cómo fue tu experiencia?",
                [
                    'order_id' => $orderId,
                    'order_number' => $orderNumber,
                    'product_id' => $productId,
                    'product_name' => $productName,
                    'seller_id' => $sellerId,
                    'action_url' => '/pending-rating',
                    'action_text' => 'Valorar producto',
                    'source' => 'product_delivered',
                    'tracking_number' => $productData['tracking_number'] ?? null,
                    'delivery_date' => $productData['delivery_date'] ?? now()->toDateTimeString(),
                    'expires_at' => now()->addDays(30)->toDateTimeString(),
                    'priority' => 'high',
                    'category' => 'rating_request_product_delivery',
                    'quantity' => $productData['quantity'] ?? 1,
                    'price' => $productData['price'] ?? null,
                ]
            );

            Log::info('Notificación de valoración de producto específico enviada', [
                'user_id' => $userId,
                'order_id' => $orderId,
                'product_id' => $productId,
                'product_name' => $productName,
                'tracking_number' => $productData['tracking_number'] ?? null,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error enviando notificación de valoración de producto: '.$e->getMessage(), [
                'user_id' => $userId,
                'order_id' => $orderId,
                'product_data' => $productData,
            ]);

            return false;
        }
    }

    /**
     * Verificar si un usuario ya valoró una orden específica
     */
    private function checkIfUserHasRatedOrder(int $userId, int $orderId): bool
    {
        try {
            // Verificar si existe alguna valoración del usuario para esta orden
            $hasRating = Rating::where('user_id', $userId)
                ->where('order_id', $orderId)
                ->exists();

            return $hasRating;
        } catch (\Exception $e) {
            Log::error('Error verificando si usuario ha valorado orden: '.$e->getMessage());

            return false; // En caso de error, asumir que no ha valorado para enviar la notificación
        }
    }

    /**
     * Verificar si ya existe una notificación para un tipo y orden específicos
     */
    public function hasNotification(int $orderId, string $type): bool
    {
        return Notification::where('data->order_id', $orderId)
            ->where('type', $type)
            ->exists();
    }
}
