<?php

namespace App\Models;

use App\Domain\ValueObjects\ShippingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingHistory extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'shipping_history';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'shipping_id',
        'status',
        'status_description',
        'location',
        'details',
        'timestamp',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'location' => 'array',
        'timestamp' => 'datetime',
    ];

    /**
     * Get the shipping that owns the history entry.
     */
    public function shipping(): BelongsTo
    {
        return $this->belongsTo(Shipping::class);
    }

    /**
     * Scope to order history entries chronologically
     */
    public function scopeChronological($query)
    {
        return $query->orderBy('timestamp', 'asc');
    }

    /**
     * Create a new shipping history entry
     */
    public static function createEntry(
        int $shippingId,
        string $status,
        ?string $location = null,
        ?string $details = null
    ): self {
        return self::create([
            'shipping_id' => $shippingId,
            'status' => $status,
            'status_description' => ShippingStatus::getDescription($status),
            'location' => $location,
            'details' => $details,
            'timestamp' => now(),
        ]);
    }
}
