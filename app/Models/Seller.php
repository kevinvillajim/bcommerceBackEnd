<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seller extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_name',
        'description',
        'status',
        'verification_level',
        'commission_rate',
        'total_sales',
        'is_featured',
        'featured_at',
        'featured_expires_at',
        'featured_reason',
    ];

    protected $casts = [
        'commission_rate' => 'float',
        'total_sales' => 'integer',
        'is_featured' => 'boolean',
        'featured_at' => 'datetime',
        'featured_expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Asegurar que los timestamps estén habilitados
    public $timestamps = true;

    /**
     * Get the user that owns the seller profile
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the products for the seller
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'user_id', 'user_id');
    }

    /**
     * Get the ratings for the seller
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class, 'seller_id');
    }

    /**
     * Get the average rating for the seller
     */
    public function getAverageRatingAttribute(): float
    {
        return $this->ratings()
            ->where('type', 'user_to_seller')
            ->where('status', 'approved')
            ->avg('rating') ?? 0;
    }

    /**
     * Get the total number of ratings for the seller
     */
    public function getTotalRatingsAttribute(): int
    {
        return $this->ratings()
            ->where('type', 'user_to_seller')
            ->where('status', 'approved')
            ->count();
    }

    /**
     * Get the seller trustworthiness score based on ratings and returns
     */
    /**
     * Get the seller trustworthiness score based on ratings and returns
     */
    public function getTrustworthinessScoreAttribute(): float
    {
        // Base score starts with the average rating (0-5)
        $baseScore = $this->getAverageRatingAttribute();

        // Get return rate (percentage of orders that were returned)
        $totalOrders = Order::where('seller_id', $this->id)->count();
        $returnedOrders = Order::where('seller_id', $this->id)
            ->where('status', 'returned')
            ->count();

        $returnRate = $totalOrders > 0 ? ($returnedOrders / $totalOrders) : 0;

        // Calculate a score that decreases as return rate increases
        $returnScore = 5 * (1 - $returnRate);

        // Factor in the number of ratings (more ratings = more reliable score)
        $ratingsWeight = min($this->getTotalRatingsAttribute() / 10, 1); // Caps at 10 ratings

        // Calculate final trustworthiness score
        $trustScore = ($baseScore * 0.7) + ($returnScore * 0.3);

        // Weight by the number of ratings
        $weightedScore = $trustScore * $ratingsWeight + ((5 / 2) * (1 - $ratingsWeight));

        return round($weightedScore, 1);
    }

    /**
     * Automatically update seller status when user is blocked
     */
    protected static function boot()
    {
        parent::boot();

        // Cuando se actualiza un modelo Seller
        static::updating(function ($seller) {
            // Comprobar si el usuario asociado está bloqueado
            if ($seller->user && $seller->user->is_blocked) {
                $seller->status = 'inactive';
            }
        });

        // Observe User changes to affect Seller
        User::updated(function ($user) {
            if ($user->is_blocked) {
                // Si el usuario ha sido bloqueado, actualiza su perfil de vendedor a inactivo
                $seller = Seller::where('user_id', $user->id)->first();
                if ($seller) {
                    $seller->status = 'inactive';
                    $seller->save();
                }
            }
        });
    }

    /**
     * Scope a query to only include active sellers
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include featured sellers
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope a query to only include currently featured sellers (not expired)
     */
    public function scopeCurrentlyFeatured($query)
    {
        return $query->where('is_featured', true)
            ->where(function ($q) {
                $q->whereNull('featured_expires_at')
                    ->orWhere('featured_expires_at', '>', now());
            });
    }

    /**
     * Check if the seller is currently featured (not expired)
     */
    public function isCurrentlyFeatured(): bool
    {
        if (! $this->is_featured) {
            return false;
        }

        // If no expiration date, it's permanently featured
        if (! $this->featured_expires_at) {
            return true;
        }

        // Check if not expired
        return $this->featured_expires_at->isFuture();
    }

    /**
     * Make this seller featured for a specific duration
     */
    public function makeFeatured(int $days = 30, string $reason = 'admin'): void
    {
        $this->update([
            'is_featured' => true,
            'featured_at' => now(),
            'featured_expires_at' => now()->addDays($days),
            'featured_reason' => $reason,
        ]);
    }

    /**
     * Remove featured status
     */
    public function removeFeatured(): void
    {
        $this->update([
            'is_featured' => false,
            'featured_at' => null,
            'featured_expires_at' => null,
            'featured_reason' => null,
        ]);
    }

    /**
     * Check if featured status has expired and update it
     */
    public function checkAndUpdateFeaturedStatus(): bool
    {
        if ($this->is_featured && $this->featured_expires_at && $this->featured_expires_at->isPast()) {
            $this->update(['is_featured' => false]);

            return true; // Status was updated
        }

        return false; // No change needed
    }

    /**
     * Scope a query to order sellers by trustworthiness score
     */
    public function scopeOrderByTrustworthiness($query, $direction = 'desc')
    {
        // Ordenar por rating promedio como aproximación del trustworthiness
        return $query->withAvg('ratings as average_rating', 'rating')
            ->orderBy('average_rating', $direction);
    }
}
