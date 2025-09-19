<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditNote extends Model
{
    use HasFactory;

    protected $fillable = [
        // Relaciones principales
        'invoice_id',         // Factura original que se modifica
        'order_id',          // Orden original (heredada de factura)
        'user_id',           // Usuario que creó la nota
        'transaction_id',    // Transacción contable

        // Datos de nota de crédito (formato SRI exacto)
        'credit_note_number',  // "000000001" (9 dígitos)
        'issue_date',         // Fecha de emisión
        'motivo',            // Motivo de la nota de crédito

        // Documento modificado
        'documento_modificado_tipo',   // "01" = Factura
        'documento_modificado_numero', // "001-001-000000123"
        'documento_modificado_fecha',  // Fecha del documento original

        // Totales financieros
        'subtotal',           // totalSinImpuestos
        'tax_amount',         // IVA calculado
        'total_amount',       // importeTotal
        'currency',           // DOLAR

        // Cliente (datos heredados de factura original)
        'customer_identification',     // Cédula/RUC
        'customer_identification_type', // "05" o "04"
        'customer_name',               // Nombre cliente
        'customer_email',              // Email cliente
        'customer_address',            // Dirección cliente
        'customer_phone',              // Teléfono cliente

        // Estados SRI exactos (idénticos a Invoice)
        'status',                 // DRAFT, SENT_TO_SRI, AUTHORIZED, etc.
        'sri_access_key',         // claveAcceso del SRI (49 dígitos)
        'sri_authorization_number', // numeroAutorizacion
        'sri_response',           // JSON respuesta completa
        'sri_error_message',      // Mensaje de error específico

        // Sistema de reintentos automático
        'retry_count',            // Contador (máx 12)
        'last_retry_at',          // Timestamp último reintento
        'created_via',            // manual, system
        'pdf_path',               // Ruta al PDF generado
        'email_sent_at',          // Timestamp de envío de email (protección anti-duplicados)
    ];

    protected $casts = [
        'issue_date' => 'datetime',
        'documento_modificado_fecha' => 'date',
        'last_retry_at' => 'datetime',
        'email_sent_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'retry_count' => 'integer',
        'sri_response' => 'json',
    ];

    // Estados constantes - BCommerce internos (idénticos a Invoice)
    const STATUS_DRAFT = 'DRAFT';
    const STATUS_SENT_TO_SRI = 'SENT_TO_SRI';
    const STATUS_FAILED = 'FAILED';
    const STATUS_DEFINITIVELY_FAILED = 'DEFINITIVELY_FAILED';

    // Estados constantes - SRI API v2 (idénticos a Invoice)
    const STATUS_PENDING = 'PENDING';
    const STATUS_PROCESSING = 'PROCESSING';
    const STATUS_RECEIVED = 'RECEIVED';
    const STATUS_AUTHORIZED = 'AUTHORIZED';
    const STATUS_REJECTED = 'REJECTED';
    const STATUS_NOT_AUTHORIZED = 'NOT_AUTHORIZED';
    const STATUS_RETURNED = 'RETURNED';
    const STATUS_SRI_ERROR = 'SRI_ERROR';

    // ✅ Relaciones
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

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
        return $this->hasMany(CreditNoteItem::class);
    }

    // ✅ Métodos auxiliares (idénticos a Invoice)

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
     * Marcar nota de crédito como autorizada por SRI
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
     * Marcar nota de crédito como fallida
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'sri_error_message' => $errorMessage,
        ]);
    }

    /**
     * Marcar nota de crédito como definitivamente fallida
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
     * Scope para notas de crédito fallidas que pueden reintentarse
     */
    public function scopeRetryable($query)
    {
        return $query->where('retry_count', '<', 12)
            ->whereIn('status', [self::STATUS_FAILED, self::STATUS_SENT_TO_SRI]);
    }

    /**
     * Scope para notas de crédito definitivamente fallidas
     */
    public function scopeDefinitivelyFailed($query)
    {
        return $query->where('status', self::STATUS_DEFINITIVELY_FAILED);
    }

    /**
     * Generar número de nota de crédito secuencial
     */
    public static function generateCreditNoteNumber(): string
    {
        $lastCreditNote = self::orderBy('id', 'desc')->first();
        $nextNumber = $lastCreditNote ? ($lastCreditNote->id + 1) : 1;

        return str_pad($nextNumber, 9, '0', STR_PAD_LEFT);
    }

    /**
     * Crear nota de crédito desde factura original
     */
    public static function createFromInvoice(Invoice $invoice, array $data): self
    {
        return self::create([
            // Relaciones
            'invoice_id' => $invoice->id,
            'order_id' => $invoice->order_id,
            'user_id' => $invoice->user_id,

            // Numeración y fechas
            'credit_note_number' => self::generateCreditNoteNumber(),
            'issue_date' => now(),
            'motivo' => $data['motivo'],

            // Documento modificado
            'documento_modificado_tipo' => '01', // Siempre factura
            'documento_modificado_numero' => $invoice->invoice_number,
            'documento_modificado_fecha' => $invoice->issue_date->format('Y-m-d'),

            // Datos del cliente (heredados de factura)
            'customer_identification' => $invoice->customer_identification,
            'customer_identification_type' => $invoice->customer_identification_type,
            'customer_name' => $invoice->customer_name,
            'customer_email' => $invoice->customer_email,
            'customer_address' => $invoice->customer_address,
            'customer_phone' => $invoice->customer_phone,

            // Totales (se calcularán después con los items)
            'subtotal' => $data['subtotal'] ?? 0,
            'tax_amount' => $data['tax_amount'] ?? 0,
            'total_amount' => $data['total_amount'] ?? 0,
            'currency' => $invoice->currency,

            // Estado inicial
            'status' => self::STATUS_DRAFT,
            'created_via' => 'manual',
        ]);
    }
}