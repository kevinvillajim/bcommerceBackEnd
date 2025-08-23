<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VolumeDiscount extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'min_quantity',
        'discount_percentage',
        'label',
        'active',
    ];

    protected $casts = [
        'min_quantity' => 'integer',
        'discount_percentage' => 'decimal:2',
        'active' => 'boolean',
    ];

    /**
     * Relación con producto
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope para descuentos activos
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope para ordenar por cantidad mínima
     */
    public function scopeOrderByQuantity($query, $direction = 'asc')
    {
        return $query->orderBy('min_quantity', $direction);
    }

    /**
     * Obtener el descuento aplicable para una cantidad específica
     */
    public static function getDiscountForQuantity(int $productId, int $quantity): ?VolumeDiscount
    {
        return self::where('product_id', $productId)
            ->active()
            ->where('min_quantity', '<=', $quantity)
            ->orderBy('min_quantity', 'desc')
            ->first();
    }

    /**
     * Obtener todos los niveles de descuento para un producto
     */
    public static function getDiscountTiers(int $productId): array
    {
        return self::where('product_id', $productId)
            ->active()
            ->orderBy('min_quantity', 'asc')
            ->get()
            ->map(function ($discount) {
                return [
                    'quantity' => $discount->min_quantity,
                    'discount' => $discount->discount_percentage,
                    'label' => $discount->label ?: "Descuento {$discount->min_quantity}+",
                ];
            })
            ->toArray();
    }

    /**
     * Calcular precio con descuento por volumen
     */
    public static function calculateVolumePrice(int $productId, float $basePrice, int $quantity): array
    {
        $discount = self::getDiscountForQuantity($productId, $quantity);

        if (! $discount) {
            return [
                'original_price' => $basePrice,
                'discounted_price' => $basePrice,
                'discount_percentage' => 0,
                'savings' => 0,
                'discount_label' => null,
            ];
        }

        $discountedPrice = $basePrice * (1 - $discount->discount_percentage / 100);
        $savings = $basePrice - $discountedPrice;

        return [
            'original_price' => $basePrice,
            'discounted_price' => $discountedPrice,
            'discount_percentage' => $discount->discount_percentage,
            'savings' => $savings,
            'discount_label' => $discount->label,
        ];
    }
}
