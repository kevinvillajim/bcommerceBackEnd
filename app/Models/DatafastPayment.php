<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatafastPayment extends Model
{
    protected $fillable = [
        // Relaciones
        'user_id',
        'order_id',

        // Identificadores únicos de Datafast
        'transaction_id',
        'checkout_id',
        'datafast_payment_id',
        'resource_path',

        // Información financiera
        'amount',
        'calculated_total',
        'subtotal',
        'shipping_cost',
        'tax',
        'currency',

        // Estados del pago
        'status',
        'payment_status',
        'result_code',
        'result_description',

        // Información del cliente
        'customer_given_name',
        'customer_middle_name',
        'customer_surname',
        'customer_phone',
        'customer_doc_id',
        'customer_email',

        // Información de envío
        'shipping_address',
        'shipping_city',
        'shipping_country',

        // Información técnica
        'environment',
        'phase',
        'widget_url',
        'client_ip',
        'user_agent',

        // Datos de descuentos
        'discount_code',
        'discount_info',

        // Logs y debugging
        'request_data',
        'response_data',
        'verification_data',
        'error_message',
        'notes',

        // Timestamps específicos
        'checkout_created_at',
        'payment_attempted_at',
        'payment_completed_at',
        'verification_completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'calculated_total' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount_info' => 'array',
        'request_data' => 'array',
        'response_data' => 'array',
        'verification_data' => 'array',
        'checkout_created_at' => 'datetime',
        'payment_attempted_at' => 'datetime',
        'payment_completed_at' => 'datetime',
        'verification_completed_at' => 'datetime',
    ];

    // Relaciones
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByEnvironment($query, $environment)
    {
        return $query->where('environment', $environment);
    }

    public function scopeByPhase($query, $phase)
    {
        return $query->where('phase', $phase);
    }

    // Mutators y Accessors
    public function getFormattedAmountAttribute()
    {
        return '$'.number_format($this->amount, 2);
    }

    public function getIsCompletedAttribute()
    {
        return $this->status === 'completed';
    }

    public function getIsFailedAttribute()
    {
        return $this->status === 'failed';
    }

    public function getIsPendingAttribute()
    {
        return $this->status === 'pending';
    }

    // Métodos helper
    public function markAsCompleted($paymentId = null, $resultCode = null, $description = null)
    {
        $this->update([
            'status' => 'completed',
            'datafast_payment_id' => $paymentId,
            'result_code' => $resultCode,
            'result_description' => $description,
            'payment_completed_at' => now(),
        ]);
    }

    public function markAsFailed($errorMessage = null, $resultCode = null)
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'result_code' => $resultCode,
        ]);
    }

    public function markAsProcessing()
    {
        $this->update([
            'status' => 'processing',
            'payment_attempted_at' => now(),
        ]);
    }
}
