<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'product_id',
        'product_code',    // slug del producto (único) - codigoPrincipal para SRI
        'product_name',    // name del producto - descripcion para SRI
        'quantity',        // cantidad
        'unit_price',      // precioUnitario
        'discount',        // descuento (siempre 0 por ahora)
        'subtotal',        // precioTotalSinImpuesto
        'tax_rate',        // tarifa IVA (15.00)
        'tax_amount',      // valor IVA
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
