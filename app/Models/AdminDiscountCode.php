<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminDiscountCode extends Model
{
    use HasFactory;

    protected $table = 'admin_discount_codes';

    protected $fillable = [
        'code',
        'discount_percentage',
        'is_used',
        'used_by',
        'used_at',
        'used_on_product_id',
        'expires_at',
        'description',
        'created_by',
    ];

    protected $casts = [
        'is_used' => 'boolean',
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Usuario que usó el código de descuento
     */
    public function usedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    /**
     * Producto en el que se usó el código
     */
    public function usedOnProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'used_on_product_id');
    }

    /**
     * Admin que creó el código
     */
    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Verificar si el código está expirado
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Verificar si el código es válido (no usado y no expirado)
     */
    public function isValid(): bool
    {
        return ! $this->is_used && ! $this->isExpired();
    }

    /**
     * Obtener días restantes hasta la expiración
     */
    public function getDaysUntilExpiration(): int
    {
        if ($this->isExpired()) {
            return $this->expires_at->diffInDays(now()) * -1;
        }

        return now()->diffInDays($this->expires_at);
    }

    /**
     * Marcar el código como usado
     */
    public function markAsUsed(int $userId, int $productId): void
    {
        $this->update([
            'is_used' => true,
            'used_by' => $userId,
            'used_at' => now(),
            'used_on_product_id' => $productId,
        ]);
    }

    /**
     * Scopes
     */
    public function scopeValid($query)
    {
        return $query->where('is_used', false)
            ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeUsed($query)
    {
        return $query->where('is_used', true);
    }

    public function scopeUnused($query)
    {
        return $query->where('is_used', false);
    }

    public function scopeByPercentageRange($query, string $range)
    {
        switch ($range) {
            case '10':
                return $query->where('discount_percentage', '<=', 10);
            case '20':
                return $query->whereBetween('discount_percentage', [11, 20]);
            case '30':
                return $query->whereBetween('discount_percentage', [21, 30]);
            case '50+':
                return $query->where('discount_percentage', '>', 30);
            default:
                return $query;
        }
    }
}
