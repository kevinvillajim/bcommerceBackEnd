<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'seller_id',
        'total',
        'status',
        'payment_status',
        'payment_method',
        'shipping_data',
        'order_number',
    ];

    protected $casts = [
        'shipping_data' => 'json',
        // ✅ ECUADOR TIMEZONE: Timestamps con zona horaria correcta
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * Get the order that owns the seller order.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the seller that owns the seller order.
     */
    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * Get the order items for the seller order.
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'seller_order_id');
    }

    /**
     * Alias para la relación orderItems (para compatibilidad con código existente)
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class, 'seller_order_id');
    }

    /**
     * Get the shipping for this seller order.
     * NUEVA RELACIÓN - Cada SellerOrder tiene su propio envío
     */
    public function shipping()
    {
        return $this->hasOne(Shipping::class, 'seller_order_id');
    }
}
