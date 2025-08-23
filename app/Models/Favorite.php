<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favorite extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'notify_price_change',
        'notify_promotion',
        'notify_low_stock',
    ];

    protected $casts = [
        'notify_price_change' => 'boolean',
        'notify_promotion' => 'boolean',
        'notify_low_stock' => 'boolean',
    ];

    /**
     * Get the user that owns the favorite
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the product that is favorited
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
