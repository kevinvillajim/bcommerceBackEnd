<?php

namespace App\Domain\Interfaces;

use App\Models\User;

interface JwtServiceInterface
{
    /**
     * Generate a token for a given user
     */
    public function generateToken(User $user): string;

    /**
     * Get user from token
     *
     * @return mixed
     */
    public function getUserFromToken(string $token);

    /**
     * Validate token
     */
    public function validateToken(string $token): bool;

    /**
     * Refresh token
     */
    public function refreshToken(bool $forceForever = false, bool $resetClaims = false): string;

    /**
     * Invalidate token
     */
    public function invalidateToken(string $token): bool;

    /**
     * Parse token from request
     *
     * @return string|null
     */
    public function parseToken();

    /**
     * Obtener el usuario autenticado actual
     */
    public function getAuthenticatedUser(): ?User;

    /**
     * Obtener el tiempo de vida del token en segundos
     */
    public function getTokenTTL(): int;
}
