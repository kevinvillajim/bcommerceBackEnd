<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_number',
        'transaction_date',
        'description',
        'type',
        'user_id',
        'order_id',
        'is_posted',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'is_posted' => 'boolean',
    ];

    protected $appends = [
        'is_balanced',
        'balance'
    ];

    public function entries()
    {
        return $this->hasMany(AccountingEntry::class, 'transaction_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class, 'transaction_id');
    }

    /**
     * Calcula si la transacción está balanceada (débitos = créditos)
     */
    public function getIsBalancedAttribute(): bool
    {
        $totalDebits = $this->entries->sum('debit_amount');
        $totalCredits = $this->entries->sum('credit_amount');

        // Considerar balanceada si la diferencia es menor a 0.01 (centavos)
        return abs($totalDebits - $totalCredits) < 0.01;
    }

    /**
     * Calcula la diferencia entre débitos y créditos
     */
    public function getBalanceAttribute(): float
    {
        $totalDebits = $this->entries->sum('debit_amount');
        $totalCredits = $this->entries->sum('credit_amount');

        return round($totalDebits - $totalCredits, 2);
    }
}
