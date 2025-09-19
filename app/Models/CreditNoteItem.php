<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditNoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_note_id',
        'product_id',           // Puede ser null para items manuales
        'product_code',         // Código interno del producto/servicio
        'product_name',         // Descripción del producto/servicio
        'quantity',             // Cantidad
        'unit_price',           // Precio unitario
        'discount',             // Descuento aplicado
        'subtotal',             // Subtotal sin IVA
        'tax_rate',             // Porcentaje de IVA (15.00, 12.00, 0.00, etc.)
        'tax_amount',           // Monto del IVA calculado
        'codigo_iva',           // Código de IVA SRI: 0=0%, 2=12%, 3=14%, 4=15%, 6=No objeto, 7=Exento
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
    ];

    // ✅ Relaciones
    public function creditNote()
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calcular totales del item automáticamente
     */
    public function calculateTotals(): void
    {
        // Calcular subtotal
        $this->subtotal = ($this->unit_price * $this->quantity) - $this->discount;

        // Calcular IVA basado en el código
        $this->tax_amount = $this->calculateTaxAmount();

        $this->save();
    }

    /**
     * Calcular monto de IVA basado en código SRI
     */
    private function calculateTaxAmount(): float
    {
        $taxRate = match ($this->codigo_iva) {
            '0' => 0.00,    // 0% IVA
            '2' => 12.00,   // 12% IVA
            '3' => 14.00,   // 14% IVA
            '4' => 15.00,   // 15% IVA (actual Ecuador 2024)
            '6' => 0.00,    // No objeto de IVA
            '7' => 0.00,    // Exento de IVA
            default => 15.00 // Default 15%
        };

        $this->tax_rate = $taxRate;
        return round(($this->subtotal * $taxRate) / 100, 2);
    }

    /**
     * Crear item de nota de crédito desde item de factura
     */
    public static function createFromInvoiceItem(CreditNote $creditNote, InvoiceItem $invoiceItem, array $overrides = []): self
    {
        $item = self::create(array_merge([
            'credit_note_id' => $creditNote->id,
            'product_id' => $invoiceItem->product_id,
            'product_code' => $invoiceItem->product_code,
            'product_name' => $invoiceItem->product_name,
            'quantity' => $invoiceItem->quantity,
            'unit_price' => $invoiceItem->unit_price,
            'discount' => $invoiceItem->discount,
            'subtotal' => $invoiceItem->subtotal,
            'tax_rate' => $invoiceItem->tax_rate,
            'tax_amount' => $invoiceItem->tax_amount,
            'codigo_iva' => '4', // Default 15% para Ecuador 2024
        ], $overrides));

        // Recalcular totales si se hicieron cambios
        if (!empty($overrides)) {
            $item->calculateTotals();
        }

        return $item;
    }
}