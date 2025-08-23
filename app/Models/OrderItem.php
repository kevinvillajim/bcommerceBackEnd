<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'seller_order_id', // Nueva referencia a SellerOrder
        'product_id',
        'product_name',
        'product_sku',
        'product_image',
        'seller_id',
        'quantity',
        'price',
        'original_price',
        'subtotal',
        'volume_discount_percentage',
        'volume_savings',
        'discount_label',
        'attributes',
    ];

    /**
     * Los atributos que deben convertirse a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'quantity' => 'integer',
        'price' => 'float',
        'original_price' => 'float',
        'subtotal' => 'float',
        'volume_discount_percentage' => 'float',
        'volume_savings' => 'float',
        'attributes' => 'array',
    ];

    /**
     * Eventos del modelo.
     */
    protected static function boot()
    {
        parent::boot();

        // Calcular subtotal automáticamente si no se proporciona
        static::creating(function ($item) {
            if (empty($item->subtotal)) {
                $item->subtotal = $item->price * $item->quantity;
            }

            // ✅ NUEVO: Si no se proporciona original_price, usar price
            if (empty($item->original_price)) {
                $item->original_price = $item->price;
            }
        });

        static::updating(function ($item) {
            // Recalcular subtotal si cambia el precio o la cantidad
            if ($item->isDirty('price') || $item->isDirty('quantity')) {
                $item->subtotal = $item->price * $item->quantity;
            }
        });
    }

    /**
     * Relación con el pedido al que pertenece.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * Obtener el monto total ahorrado en este item
     */
    public function getTotalSavings(): float
    {
        return ($this->original_price - $this->price) * $this->quantity;
    }

    /**
     * Verificar si este item tiene descuentos por volumen
     */
    public function hasVolumeDiscount(): bool
    {
        return $this->volume_discount_percentage > 0;
    }

    /**
     * Obtener información completa del item para el frontend
     */
    public function getDetailedInfo(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'product_sku' => $this->product_sku,
            'product_image' => $this->product_image,
            'seller_id' => $this->seller_id,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'original_price' => $this->original_price,
            'subtotal' => $this->subtotal,
            'volume_discount_percentage' => $this->volume_discount_percentage,
            'volume_savings' => $this->volume_savings,
            'discount_label' => $this->discount_label,
            'total_savings' => $this->getTotalSavings(),
            'has_volume_discount' => $this->hasVolumeDiscount(),
        ];
    }

    /**
     * Relación con la orden de vendedor a la que pertenece.
     * Nueva relación para soportar órdenes multi-vendedor.
     */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    /**
     * Relación con el producto.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Incrementar la cantidad del ítem.
     */
    public function incrementQuantity(int $amount = 1): bool
    {
        $this->quantity += $amount;
        $this->subtotal = $this->price * $this->quantity;

        return $this->save();
    }

    /**
     * Decrementar la cantidad del ítem.
     */
    public function decrementQuantity(int $amount = 1): bool
    {
        if ($this->quantity <= $amount) {
            throw new \InvalidArgumentException('Cannot decrement quantity below 1');
        }

        $this->quantity -= $amount;
        $this->subtotal = $this->price * $this->quantity;

        return $this->save();
    }

    /**
     * Actualizar el precio del ítem.
     */
    public function updatePrice(float $price): bool
    {
        if ($price < 0) {
            throw new \InvalidArgumentException('Price cannot be negative');
        }

        $this->price = $price;
        $this->subtotal = $this->price * $this->quantity;

        return $this->save();
    }
}
