<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeunaPayment extends Model
{
    use HasFactory;

    protected $table = 'deuna_payments';

    protected $fillable = [
        'payment_id',
        'order_id',
        'amount',
        'currency',
        'status',
        'customer',
        'items',
        'transaction_id',
        'qr_code_base64',
        'payment_url',
        'numeric_code',
        'point_of_sale',
        'qr_type',
        'format',
        'metadata',
        'failure_reason',
        'refund_amount',
        'cancel_reason',
        'raw_create_response',
        'raw_status_response',
        'completed_at',
        'cancelled_at',
        'refunded_at',
    ];

    protected $casts = [
        'customer' => 'array',
        'items' => 'array',
        'metadata' => 'array',
        'raw_create_response' => 'array',
        'raw_status_response' => 'array',
        'amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    protected $dates = [
        'completed_at',
        'cancelled_at',
        'refunded_at',
    ];

    /**
     * Relationship with Order model
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    public function scopeByOrderId($query, string $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    public function scopeByPaymentId($query, string $paymentId)
    {
        return $query->where('payment_id', $paymentId);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays($days));
    }

    /**
     * Accessors
     */
    public function getCustomerNameAttribute(): ?string
    {
        return $this->customer['name'] ?? null;
    }

    public function getCustomerEmailAttribute(): ?string
    {
        return $this->customer['email'] ?? null;
    }

    public function getCustomerPhoneAttribute(): ?string
    {
        return $this->customer['phone'] ?? null;
    }

    public function getFormattedAmountAttribute(): string
    {
        return '$'.number_format($this->amount, 2);
    }

    public function getFormattedRefundAmountAttribute(): ?string
    {
        return $this->refund_amount ? '$'.number_format($this->refund_amount, 2) : null;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'green',
            'pending' => 'yellow',
            'created' => 'blue',
            'failed' => 'red',
            'cancelled' => 'gray',
            'refunded' => 'purple',
            default => 'gray'
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'Completado',
            'pending' => 'Pendiente',
            'created' => 'Creado',
            'failed' => 'Fallido',
            'cancelled' => 'Cancelado',
            'refunded' => 'Reembolsado',
            default => 'Desconocido'
        };
    }

    /**
     * Helper methods
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['created', 'pending']);
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['created', 'pending']);
    }

    public function canBeRefunded(): bool
    {
        return $this->status === 'completed';
    }

    public function hasQrCode(): bool
    {
        return ! empty($this->qr_code_base64);
    }

    public function hasPaymentUrl(): bool
    {
        return ! empty($this->payment_url);
    }

    public function hasNumericCode(): bool
    {
        return ! empty($this->numeric_code);
    }

    /**
     * Status update methods
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => Carbon::now(),
        ]);
    }

    public function markAsPending(): void
    {
        $this->update([
            'status' => 'pending',
        ]);
    }

    public function markAsFailed(string $reason = ''): void
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
        ]);
    }

    public function markAsCancelled(string $reason = ''): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancel_reason' => $reason,
            'cancelled_at' => Carbon::now(),
        ]);
    }

    public function markAsRefunded(?float $amount = null): void
    {
        $this->update([
            'status' => 'refunded',
            'refund_amount' => $amount ?? $this->amount,
            'refunded_at' => Carbon::now(),
        ]);
    }

    /**
     * Data update methods
     */
    public function updateTransactionId(string $transactionId): void
    {
        $this->update(['transaction_id' => $transactionId]);
    }

    public function updateQrCode(string $qrCodeBase64): void
    {
        $this->update(['qr_code_base64' => $qrCodeBase64]);
    }

    public function updatePaymentUrl(string $paymentUrl): void
    {
        $this->update(['payment_url' => $paymentUrl]);
    }

    public function updateNumericCode(string $numericCode): void
    {
        $this->update(['numeric_code' => $numericCode]);
    }

    public function updateRawCreateResponse(array $response): void
    {
        $this->update(['raw_create_response' => $response]);
    }

    public function updateRawStatusResponse(array $response): void
    {
        $this->update(['raw_status_response' => $response]);
    }

    /**
     * Get payment summary for API responses
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'order_id' => $this->order_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'qr_code_base64' => $this->qr_code_base64,
            'payment_url' => $this->payment_url,
            'numeric_code' => $this->numeric_code,
            'customer' => [
                'name' => $this->customer_name,
                'email' => $this->customer_email,
                'phone' => $this->customer_phone,
            ],
            'created_at' => $this->created_at,
            'completed_at' => $this->completed_at,
            'cancelled_at' => $this->cancelled_at,
            'refunded_at' => $this->refunded_at,
        ];
    }

    /**
     * Get detailed payment information
     */
    public function toDetailedArray(): array
    {
        return array_merge($this->toApiArray(), [
            'items' => $this->items,
            'metadata' => $this->metadata,
            'transaction_id' => $this->transaction_id,
            'point_of_sale' => $this->point_of_sale,
            'qr_type' => $this->qr_type,
            'format' => $this->format,
            'failure_reason' => $this->failure_reason,
            'refund_amount' => $this->refund_amount,
            'cancel_reason' => $this->cancel_reason,
            'updated_at' => $this->updated_at,
        ]);
    }
}
