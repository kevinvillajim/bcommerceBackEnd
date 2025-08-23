<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class TokenHelper
{
    /**
     * Validate and manage JWT token
     */
    public static function validateToken(?string $token): array
    {
        if (! $token) {
            return [
                'valid' => false,
                'message' => 'No token provided',
            ];
        }

        try {
            // Attempt to parse and authenticate the token
            /** @phpstan-ignore-next-line */
            $user = JWTAuth::setToken($token)->authenticate();

            // Check if token is about to expire
            /** @phpstan-ignore-next-line */
            $claims = JWTAuth::getPayload($token);
            $expiresAt = Carbon::createFromTimestamp($claims['exp']);
            $timeUntilExpiry = now()->diffInMinutes($expiresAt);

            return [
                'valid' => true,
                'user' => $user,
                'expires_in' => $timeUntilExpiry,
                'message' => $timeUntilExpiry > 0
                    ? "Token is valid and will expire in {$timeUntilExpiry} minutes"
                    : 'Token has expired',
            ];
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            Log::warning('Token has expired', ['error' => $e->getMessage()]);

            return [
                'valid' => false,
                'message' => 'Token has expired',
            ];
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            Log::warning('Token is invalid', ['error' => $e->getMessage()]);

            return [
                'valid' => false,
                'message' => 'Token is invalid',
            ];
        } catch (\Exception $e) {
            Log::error('Token validation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'valid' => false,
                'message' => 'Token validation failed',
            ];
        }
    }

    /**
     * Safely get token TTL (Time To Live)
     *
     * @return int Token TTL in minutes
     */
    public static function getTokenTTL(): int
    {
        try {
            // Prioritize getting TTL from JWT configuration
            $ttl = config('jwt.ttl', 60);

            // Ensure TTL is a positive integer
            return max(15, intval($ttl));
        } catch (\Exception $e) {
            Log::error('Error getting token TTL', [
                'error' => $e->getMessage(),
            ]);

            // Fallback to default 60 minutes
            return 60;
        }
    }

    /**
     * Check if token is close to expiration
     *
     * @param  int  $thresholdMinutes  Threshold for considering token close to expiry
     */
    public static function isTokenNearExpiry(string $token, int $thresholdMinutes = 15): bool
    {
        try {
            /** @phpstan-ignore-next-line */
            $claims = JWTAuth::getPayload($token);
            $expiresAt = Carbon::createFromTimestamp($claims['exp']);
            $minutesUntilExpiry = now()->diffInMinutes($expiresAt);

            return $minutesUntilExpiry <= $thresholdMinutes;
        } catch (\Exception $e) {
            Log::error('Error checking token expiry', [
                'error' => $e->getMessage(),
            ]);

            return true; // Assume token is near expiry if there's an error
        }
    }

    /**
     * Automatically refresh token if it's close to expiry
     *
     * @return string|null Refreshed token or null if refresh fails
     */
    public static function autoRefreshToken(string $token): ?string
    {
        if (self::isTokenNearExpiry($token)) {
            try {
                /** @phpstan-ignore-next-line */
                return JWTAuth::refresh($token);
            } catch (\Exception $e) {
                Log::error('Token auto-refresh failed', [
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        }

        return $token;
    }
}
