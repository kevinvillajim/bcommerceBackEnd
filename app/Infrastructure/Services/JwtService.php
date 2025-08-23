<?php

namespace App\Infrastructure\Services;

use App\Domain\Interfaces\JwtServiceInterface;
use App\Helpers\TokenHelper;
use App\Models\User;
use App\Services\ConfigurationService;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtService implements JwtServiceInterface
{
    private ConfigurationService $configService;

    public function __construct(ConfigurationService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Get TTL from database configuration
     */
    private function getSessionTimeout(): int
    {
        try {
            // Get sessionTimeout from database configuration (in minutes)
            $sessionTimeout = $this->configService->getConfig('security.sessionTimeout', 60);

            // Ensure it's an integer
            if (is_numeric($sessionTimeout)) {
                return (int) $sessionTimeout;
            }

            // Default to 60 minutes if invalid
            return 60;
        } catch (\Exception $e) {
            Log::error('Error getting session timeout from configuration', [
                'error' => $e->getMessage(),
            ]);

            return 60; // Default fallback
        }
    }

    /**
     * Ensure TTL is a safe integer
     *
     * @param  mixed  $ttl
     */
    private function sanitizeTTL($ttl): int
    {
        // If it's already an integer, return it
        if (is_int($ttl)) {
            return $ttl;
        }

        // If it's a numeric string, convert to int
        if (is_numeric($ttl)) {
            return (int) $ttl;
        }

        // Default to 60 minutes
        return 60;
    }

    /**
     * Genera un token JWT para un usuario
     *
     * @return string|null
     */
    public function generateToken(User $user): string
    {
        try {
            // Get TTL from database configuration
            $ttl = $this->getSessionTimeout();

            // Temporarily modify the config to use database setting
            config(['jwt.ttl' => $ttl]);

            Log::info('Token Generation Debug', [
                'user_id' => $user->id,
                'ttl_from_db' => $ttl,
                'ttl_type' => gettype($ttl),
                'source' => 'database_configuration',
            ]);

            $token = JWTAuth::fromUser($user);
            if (! $token) {
                throw new \RuntimeException('No se pudo generar el token');
            }

            return $token;
        } catch (JWTException $e) {
            Log::error('Error al generar token JWT', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);
            throw new \RuntimeException('Error al generar el token JWT');
        }
    }

    /**
     * Get user from token
     *
     * @return mixed
     *
     * @throws JWTException
     */
    public function getUserFromToken(string $token)
    {
        return JWTAuth::setToken($token)->authenticate();
    }

    /**
     * Validate token
     */
    public function validateToken(string $token): bool
    {
        $validationResult = TokenHelper::validateToken($token);

        return $validationResult['valid'];
    }

    /**
     * Refresh token
     *
     * @throws JWTException
     */
    public function refreshToken(bool $forceForever = false, bool $resetClaims = false): string
    {
        try {
            // Get current token from request
            $token = $this->parseToken();
            if (! $token) {
                throw new JWTException('No token found in request');
            }

            // Get current session timeout from database
            $ttl = $this->getSessionTimeout();

            // Update JWT config before refreshing
            config(['jwt.ttl' => $ttl]);

            // Validate the current token first
            $validationResult = TokenHelper::validateToken($token);
            if (! $validationResult['valid']) {
                throw new JWTException('Token is invalid');
            }

            Log::info('Token Refresh Debug', [
                'ttl_from_db' => $ttl,
                'source' => 'database_configuration',
            ]);

            // Refresh the token with new TTL
            return JWTAuth::refresh($token);
        } catch (JWTException $e) {
            Log::error('Error refreshing token: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Invalidate token
     *
     * @throws JWTException
     */
    public function invalidateToken(string $token): bool
    {
        try {
            // Validate the token first
            $validationResult = TokenHelper::validateToken($token);
            if (! $validationResult['valid']) {
                return false;
            }

            // Invalidate the token
            JWTAuth::setToken($token)->invalidate();

            return true;
        } catch (JWTException $e) {
            Log::error('Error invalidating token: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Parse token from request
     *
     * @return string|null
     */
    public function parseToken()
    {
        try {
            return JWTAuth::parseToken()->getToken()->get();
        } catch (JWTException $e) {
            return null;
        }
    }

    /**
     * Obtiene el usuario autenticado actual
     */
    public function getAuthenticatedUser(): ?User
    {
        try {
            if (! $token = JWTAuth::getToken()) {
                return null;
            }

            return JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException $e) {
            Log::warning('Token expirado', ['error' => $e->getMessage()]);

            return null;
        } catch (TokenInvalidException $e) {
            Log::warning('Token inválido', ['error' => $e->getMessage()]);

            return null;
        } catch (JWTException $e) {
            Log::warning('Token ausente', ['error' => $e->getMessage()]);

            return null;
        } catch (\Exception $e) {
            Log::error('Error al obtener usuario autenticado: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Obtiene el tiempo de vida del token en segundos
     */
    public function getTokenTTL(): int
    {
        try {
            // Get TTL from database configuration
            $ttl = $this->getSessionTimeout();

            return $ttl * 60; // Convert minutes to seconds
        } catch (\Exception $e) {
            Log::error('Error getting token TTL', [
                'error' => $e->getMessage(),
            ]);

            return 3600; // Fallback to 1 hour in seconds
        }
    }
}
