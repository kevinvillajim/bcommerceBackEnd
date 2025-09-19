<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        // Relaciones principales
        'order_id',
        'user_id',
        'transaction_id',

        // Datos de factura (formato SRI exacto)
        'invoice_number',     // "000000001" (9 dígitos)
        'issue_date',
        'subtotal',           // totalSinImpuestos
        'tax_amount',         // IVA calculado
        'total_amount',       // importeTotal
        'currency',           // DOLAR

        // Cliente (extracción exacta del checkout)
        'customer_identification',     // Cédula/RUC del campo nuevo
        'customer_identification_type', // "05" o "04" (dinámico)
        'customer_name',              // shipping.name o billing.name
        'customer_email',             // user.email
        'customer_address',           // shipping.address completa
        'customer_phone',             // shipping.phone

        // Estados SRI exactos
        'status',                 // DRAFT, SENT_TO_SRI, AUTHORIZED, REJECTED, FAILED, DEFINITIVELY_FAILED
        'sri_access_key',         // claveAcceso del SRI
        'sri_authorization_number', // numeroAutorizacion
        'sri_response',           // JSON respuesta completa
        'sri_error_message',      // Mensaje de error específico

        // Sistema de reintentos automático
        'retry_count',            // Contador (máx 9)
        'last_retry_at',          // Timestamp último reintento
        'created_via',            // checkout, manual

        // Sistema de PDF automático
        'pdf_path',               // Ruta del PDF generado
        'pdf_generated_at',       // Timestamp de generación del PDF

        // Sistema de emails automático
        'email_sent_at',          // Timestamp de envío de email (protección anti-duplicados)
    ];

    protected $casts = [
        'issue_date' => 'datetime',
        'last_retry_at' => 'datetime',
        'pdf_generated_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'retry_count' => 'integer',
        'sri_response' => 'json',
    ];

    // Estados constantes - BCommerce internos
    const STATUS_DRAFT = 'DRAFT';

    const STATUS_SENT_TO_SRI = 'SENT_TO_SRI';

    const STATUS_FAILED = 'FAILED';

    const STATUS_DEFINITIVELY_FAILED = 'DEFINITIVELY_FAILED';

    // Estados constantes - SRI API v2
    const STATUS_PENDING = 'PENDING';

    const STATUS_PROCESSING = 'PROCESSING';

    const STATUS_RECEIVED = 'RECEIVED';

    const STATUS_AUTHORIZED = 'AUTHORIZED';

    const STATUS_REJECTED = 'REJECTED';

    const STATUS_NOT_AUTHORIZED = 'NOT_AUTHORIZED';

    const STATUS_RETURNED = 'RETURNED';

    const STATUS_SRI_ERROR = 'SRI_ERROR';

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transaction()
    {
        return $this->belongsTo(AccountingTransaction::class, 'transaction_id');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function sriTransactions()
    {
        return $this->hasMany(SriTransaction::class);
    }

    /**
     * Obtener tipo de identificación dinámicamente
     */
    public function getCustomerIdentificationType(): string
    {
        $identification = $this->customer_identification;
        $length = strlen($identification);

        if ($length === 10) {
            return '05'; // Cédula
        } elseif ($length === 13 && substr($identification, -3) === '001') {
            return '04'; // RUC
        }

        return '05'; // Default cédula
    }

    /**
     * Marcar factura como autorizada por SRI
     */
    public function markAsAuthorized(string $accessKey, ?string $authNumber, array $response): void
    {
        $this->update([
            'status' => self::STATUS_AUTHORIZED,
            'sri_access_key' => $accessKey,
            'sri_authorization_number' => $authNumber,
            'sri_response' => $response,
            'sri_error_message' => null,
        ]);
    }

    /**
     * Marcar factura como fallida
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'sri_error_message' => $errorMessage,
        ]);
    }

    /**
     * Marcar factura como definitivamente fallida
     */
    public function markAsDefinitivelyFailed(): void
    {
        $this->update([
            'status' => self::STATUS_DEFINITIVELY_FAILED,
            'sri_error_message' => 'Máximo de reintentos alcanzado',
        ]);
    }

    /**
     * Incrementar contador de reintentos
     */
    public function incrementRetryCount(int $amount = 1): void
    {
        $this->increment('retry_count', $amount);
        $this->update(['last_retry_at' => now()]);
    }

    /**
     * Verificar si puede reintentarse
     */
    public function canRetry(): bool
    {
        return $this->retry_count < 12 &&
               in_array($this->status, [self::STATUS_FAILED, self::STATUS_SENT_TO_SRI]);
    }

    /**
     * Scope para facturas fallidas que pueden reintentarse
     */
    public function scopeRetryable($query)
    {
        return $query->where('retry_count', '<', 12)
            ->whereIn('status', [self::STATUS_FAILED, self::STATUS_SENT_TO_SRI]);
    }

    /**
     * Scope para facturas definitivamente fallidas
     */
    public function scopeDefinitivelyFailed($query)
    {
        return $query->where('status', self::STATUS_DEFINITIVELY_FAILED);
    }
}
