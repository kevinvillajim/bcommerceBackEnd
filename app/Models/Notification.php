<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'read',
        'read_at',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array',
        'read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public const TYPE_PROMOTION = 'promotion';

    public const TYPE_DISCOUNT_CODE_GENERATED = 'discount_code_generated';

    /**
     * Constantes para definir los diferentes tipos de notificación para usuarios regulares
     */
    public const TYPE_NEW_MESSAGE = 'new_message';

    public const TYPE_FEEDBACK_RESPONSE = 'feedback_response';

    public const TYPE_ORDER_STATUS = 'order_status';

    public const TYPE_PRODUCT_UPDATE = 'product_update';

    public const TYPE_SHIPPING_UPDATE = 'shipping_update';

    public const TYPE_RATING_RECEIVED = 'rating_received';

    public const TYPE_RATING_REQUEST = 'rating_request';

    public const TYPE_RATING_REMINDER = 'rating_reminder';

    public const TYPE_SELLER_APPLICATION_APPROVED = 'seller_application_approved';

    public const TYPE_SELLER_APPLICATION_REJECTED = 'seller_application_rejected';

    /**
     * Constantes para definir los diferentes tipos de notificación para vendedores
     */
    public const TYPE_NEW_ORDER = 'new_order';

    public const TYPE_LOW_STOCK = 'low_stock';

    public const TYPE_SHIPPING_DELAY = 'shipping_delay';

    public const TYPE_SELLER_STRIKE = 'seller_strike';

    public const TYPE_ACCOUNT_BLOCKED = 'account_blocked';

    public const TYPE_SELLER_RATED = 'seller_rated';

    public const TYPE_SELLER_RANK_CHANGED = 'seller_rank_changed';

    /**
     * Constantes para definir los diferentes tipos de notificación para administradores
     */
    // Constantes para definir los diferentes tipos de notificación para administradores
    public const TYPE_DAILY_SALES = 'daily_sales';

    public const TYPE_ADMIN_FEEDBACK = 'admin_feedback';

    public const TYPE_ADMIN_SELLER_STRIKE = 'admin_seller_strike';

    public const TYPE_SELLER_BLOCKED = 'admin_seller_blocked';

    public const TYPE_SHIPPING_DELAY_ADMIN = 'admin_shipping_delay';

    public const TYPE_PRODUCT_OUT_OF_STOCK = 'admin_out_of_stock';

    public const TYPE_ADMIN_MESSAGE = 'admin_message';

    public const TYPE_LOW_RATING = 'admin_low_rating';

    public const TYPE_SELLER_RANK_UP = 'admin_seller_rank_up';

    /**
     * Obtener el usuario relacionado con la notificación.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Determinar si la notificación está leída.
     */
    public function isRead(): bool
    {
        return $this->read;
    }

    /**
     * Marcar la notificación como leída.
     */
    public function markAsRead(): bool
    {
        $this->read = true;
        $this->read_at = now();

        return $this->save();
    }

    /**
     * Obtener una URL para la notificación basada en su tipo y datos.
     */
    public function getUrl(): ?string
    {
        switch ($this->type) {
            case self::TYPE_NEW_MESSAGE:
                return isset($this->data['chat_id'])
                    ? "/chats/{$this->data['chat_id']}"
                    : null;

            case self::TYPE_FEEDBACK_RESPONSE:
                return isset($this->data['feedback_id'])
                    ? "/feedback/{$this->data['feedback_id']}"
                    : null;

            case self::TYPE_DISCOUNT_CODE_GENERATED:
                return '/user/discount-codes';

            case self::TYPE_ORDER_STATUS:
                return isset($this->data['order_id'])
                    ? "/orders/{$this->data['order_id']}"
                    : null;

            case self::TYPE_PRODUCT_UPDATE:
                return isset($this->data['product_id'])
                    ? "/products/{$this->data['product_id']}"
                    : null;

            case self::TYPE_SHIPPING_UPDATE:
                return isset($this->data['tracking_number'])
                    ? "/shipping/track/{$this->data['tracking_number']}"
                    : null;

            case self::TYPE_RATING_RECEIVED:
                return isset($this->data['rating_id'])
                    ? "/ratings/received/{$this->data['rating_id']}"
                    : null;

            case self::TYPE_NEW_ORDER:
                return isset($this->data['order_id'])
                    ? "/seller/orders/{$this->data['order_id']}"
                    : null;

            case self::TYPE_LOW_STOCK:
                return isset($this->data['product_id'])
                    ? "/seller/products/{$this->data['product_id']}/edit"
                    : null;

            case self::TYPE_SHIPPING_DELAY:
                return isset($this->data['order_id'])
                    ? "/seller/shipping/{$this->data['order_id']}"
                    : null;

            case self::TYPE_SELLER_STRIKE:
                return isset($this->data['message_id'])
                    ? "/seller/messages/{$this->data['message_id']}"
                    : '/seller/account';

            case self::TYPE_ACCOUNT_BLOCKED:
                return '/seller/account';

            case self::TYPE_SELLER_RATED:
                return isset($this->data['rating_id'])
                    ? "/seller/ratings/{$this->data['rating_id']}"
                    : null;

            case self::TYPE_SELLER_APPLICATION_APPROVED:
                return '/profile?tab=seller-application';

            case self::TYPE_SELLER_APPLICATION_REJECTED:
                return '/profile?tab=seller-application';

            default:
                return null;
        }
    }
}
