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

        // ✅ IDENTIFICADORES CLARIFICADOS:
        'transaction_id',        // ID único del sistema: ORDER_{timestamp}_{userId}_{uniqid} - Para rastreo interno
        'checkout_id',           // ID del checkout de Datafast - Retornado por API de Datafast al crear checkout
        'datafast_payment_id',   // ID específico del pago de Datafast - Retornado tras procesar pago exitoso
        'resource_path',         // Path de recurso de Datafast - Para verificación de estado del pago

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
        'shipping_identification',

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

    /**
     * ✅ NUEVO: Obtener estado como payment_status para respuestas JSON consistentes
     */
    public function getPaymentStatusAttribute()
    {
        return $this->status;
    }

    /**
     * ✅ NUEVO: Estados válidos del sistema
     */
    public static function getValidStatuses(): array
    {
        return ['pending', 'processing', 'completed', 'failed', 'error'];
    }

    /**
     * ✅ NUEVO: Verificar si el pago está en estado final
     */
    public function isFinalized(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'error']);
    }

    // ✅ MÉTODOS CLARIFICADORES PARA IDs

    /**
     * Obtener ID único del sistema (para rastreo interno)
     */
    public function getSystemTransactionId(): string
    {
        return $this->transaction_id;
    }

    /**
     * Obtener ID del checkout de Datafast (para widget y API)
     */
    public function getDatafastCheckoutId(): ?string
    {
        return $this->checkout_id;
    }

    /**
     * Obtener ID específico del pago de Datafast (tras pago exitoso)
     */
    public function getDatafastPaymentId(): ?string
    {
        return $this->datafast_payment_id;
    }

    /**
     * Obtener resource path para verificación (usado en API de verificación)
     */
    public function getResourcePath(): ?string
    {
        return $this->resource_path;
    }

    /**
     * ✅ NUEVO: Verificar si tiene todos los IDs necesarios para verificación
     */
    public function hasVerificationIds(): bool
    {
        return !empty($this->transaction_id) && !empty($this->resource_path);
    }

    /**
     * ✅ NUEVO: Verificar si el checkout fue creado exitosamente
     */
    public function hasDatafastCheckout(): bool
    {
        return !empty($this->checkout_id);
    }
}
