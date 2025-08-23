<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EmailVerificationToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns the token
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a new verification token
     */
    public static function generateToken(): string
    {
        return Str::random(60);
    }

    /**
     * Create a new verification token for a user
     */
    public static function createForUser(int $userId, int $hoursToExpire = 24): self
    {
        // Delete any existing tokens for this user
        self::where('user_id', $userId)->delete();

        return self::create([
            'user_id' => $userId,
            'token' => self::generateToken(),
            'expires_at' => Carbon::now()->addHours($hoursToExpire),
        ]);
    }

    /**
     * Check if the token is expired
     */
    public function isExpired(): bool
    {
        return Carbon::now()->isAfter($this->expires_at);
    }

    /**
     * Find a valid token
     */
    public static function findValidToken(string $token): ?self
    {
        return self::where('token', $token)
            ->where('expires_at', '>', Carbon::now())
            ->first();
    }

    /**
     * Clean up expired tokens
     */
    public static function cleanupExpiredTokens(): int
    {
        return self::where('expires_at', '<', Carbon::now())->delete();
    }
}
