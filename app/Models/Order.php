<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'seller_id', // Este campo ahora es opcional, solo para órdenes de un solo vendedor
        'order_number',
        'total',
        'status',
        'payment_id',
        'payment_method',
        'payment_status',
        'payment_details',
        'shipping_data',
        'feedback_discount_code',
        'feedback_discount_amount',
        'feedback_discount_percentage',
        // ✅ NUEVO: Campos de pricing detallado
        'subtotal_products',
        'iva_amount',
        'shipping_cost',
        'total_discounts',
        'free_shipping',
        'free_shipping_threshold',
        'pricing_breakdown',
        'original_total',
        'volume_discount_savings',
        'seller_discount_savings',
        'volume_discounts_applied',
    ];

    /**
     * Los atributos que deben convertirse a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'total' => 'float',
        'payment_details' => 'array',
        'shipping_data' => 'array',
        'feedback_discount_amount' => 'float',
        'feedback_discount_percentage' => 'float',
        // ✅ NUEVO: Casts para campos de pricing
        'subtotal_products' => 'float',
        'iva_amount' => 'float',
        'shipping_cost' => 'float',
        'total_discounts' => 'float',
        'original_total' => 'float',
        'volume_discount_savings' => 'float',
        'seller_discount_savings' => 'float',
        'free_shipping_threshold' => 'float',
        'pricing_breakdown' => 'array',
        'free_shipping' => 'boolean',
        'volume_discounts_applied' => 'boolean',
        // ✅ ECUADOR TIMEZONE: Timestamps con zona horaria correcta
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * Eventos del modelo.
     */
    protected static function boot()
    {
        parent::boot();

        // Generar número de orden automáticamente si no se proporciona
        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = static::generateOrderNumber();
            }
        });
    }

    /**
     * Generar un número de orden único.
     */
    public static function generateOrderNumber()
    {
        $prefix = 'ORD';
        $timestamp = now()->format('YmdHis');
        $random = Str::random(4);

        return "{$prefix}-{$timestamp}-{$random}";
    }

    /**
     * Relación con el usuario propietario del pedido.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con el vendedor del pedido, si aplica para órdenes de un solo vendedor.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    /**
     * Relación con los items del pedido.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Relación con las órdenes de vendedor (SellerOrders).
     * Nueva relación para órdenes con múltiples vendedores.
     */
    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    /**
     * Verificar si el pedido está en un determinado estado.
     */
    public function isInStatus(string $status): bool
    {
        return $this->status === $status;
    }

    /**
     * Verificar si el pedido está pagado.
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'completed' || $this->payment_status === 'succeeded';
    }

    /**
     * Verificar si el pedido ha sido enviado.
     */
    public function isShipped(): bool
    {
        return $this->status === 'shipped' || $this->status === 'delivered';
    }

    /**
     * Verificar si el pedido puede ser cancelado.
     */
    public function canBeCancelled(): bool
    {
        return $this->status === 'pending' || $this->status === 'processing';
    }

    /**
     * Cambiar el estado del pedido.
     */
    public function changeStatus(string $status): bool
    {
        $this->status = $status;

        return $this->save();
    }

    /**
     * Establecer información de pago.
     */
    public function setPaymentInfo(?string $paymentId, ?string $paymentMethod, ?string $paymentStatus): bool
    {
        $this->payment_id = $paymentId;
        $this->payment_method = $paymentMethod;
        $this->payment_status = $paymentStatus;

        return $this->save();
    }

    /**
     * Recalcular el total del pedido basado en sus ítems.
     */
    public function recalculateTotal(): bool
    {
        $this->total = $this->items()->sum('subtotal');

        return $this->save();
    }

    /**
     * Verificar si la orden tiene múltiples vendedores
     */
    public function hasMultipleSellers(): bool
    {
        return $this->sellerOrders()->count() > 1;
    }
}
