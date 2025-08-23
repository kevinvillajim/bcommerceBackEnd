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
}
