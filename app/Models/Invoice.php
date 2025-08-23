<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'order_id',
        'user_id',
        'seller_id',
        'transaction_id',
        'issue_date',
        'subtotal',
        'tax_amount',
        'total_amount',
        'status',
        'sri_authorization_number',
        'sri_access_key',
        'cancellation_reason',
        'cancelled_at',
        'sri_response',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'cancelled_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'sri_response' => 'json',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
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
}
