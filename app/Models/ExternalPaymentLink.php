<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ExternalPaymentLink extends Model
{
    protected $fillable = [
        'link_code',
        'customer_name',
        'amount',
        'description',
        'status',
        'payment_method',
        'transaction_id',
        'payment_id',
        'expires_at',
        'paid_at',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con el usuario que creó el link
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Generar código único para el link
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('link_code', $code)->exists());

        return $code;
    }

    /**
     * Verificar si el link está expirado
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Verificar si el link está disponible para pago
     */
    public function isAvailableForPayment(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    /**
     * Marcar como pagado
     */
    public function markAsPaid(string $paymentMethod, string $transactionId, ?string $paymentId = null): void
    {
        $this->update([
            'status' => 'paid',
            'payment_method' => $paymentMethod,
            'transaction_id' => $transactionId,
            'payment_id' => $paymentId,
            'paid_at' => now(),
        ]);
    }

    /**
     * Marcar como expirado
     */
    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    /**
     * Obtener URL pública del link
     */
    public function getPublicUrl(): string
    {
        return config('app.frontend_url') . '/pay/' . $this->link_code;
    }

    /**
     * Scope para filtrar por usuario
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Scope para links activos (pendientes y no expirados)
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'pending')
                    ->where('expires_at', '>', now());
    }

    /**
     * Scope para links pagados
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
}
