<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rating extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'seller_id',
        'order_id',
        'product_id',
        'rating',
        'title',
        'comment',
        'status',
        'type',
    ];

    protected $casts = [
        'rating' => 'float',
    ];

    // Define rating types
    const TYPE_SELLER_TO_USER = 'seller_to_user';

    const TYPE_USER_TO_SELLER = 'user_to_seller';

    const TYPE_USER_TO_PRODUCT = 'user_to_product';

    // Define rating status
    const STATUS_PENDING = 'pending';

    const STATUS_APPROVED = 'approved';

    const STATUS_REJECTED = 'rejected';

    const STATUS_FLAGGED = 'flagged';

    /**
     * Get the user who gave the rating
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the seller who received the rating or gave the rating
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * Get the order associated with the rating
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the product associated with the rating
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope a query to only include approved ratings
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope a query to only include ratings for a specific type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get a formatted rating value (e.g., 4.5 out of 5)
     */
    public function getFormattedRatingAttribute(): string
    {
        return number_format($this->rating, 1).' de 5';
    }

    /**
     * Check if the rating is from a verified purchase
     */
    public function getIsVerifiedPurchaseAttribute(): bool
    {
        return $this->order_id !== null;
    }
}
