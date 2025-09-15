<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Robust Configuration Service
 *
 * This service provides a robust way to manage application configurations
 * with multiple fallback mechanisms to ensure the application doesn't fail
 * due to configuration loading issues.
 *
 * Features:
 * - Multiple fallback levels (cache -> database -> env -> hardcoded defaults)
 * - Graceful error handling
 * - Connection state awareness
 * - Lazy loading to avoid bootstrap issues
 * - Comprehensive logging for debugging
 */
class RobustConfigurationService
{
    /**
     * Cache TTL in seconds
     */
    protected const CACHE_TTL = 3600; // 1 hour

    /**
     * Prefix for cache keys
     */
    protected const CACHE_PREFIX = 'robust_config.';

    /**
     * Track if database is available
     */
    protected static $databaseAvailable = null;

    /**
     * In-memory cache for the current request
     */
    protected static $memoryCache = [];

    /**
     * Default configuration values (ultimate fallback)
     */
    protected static $defaults = [
        // Email configurations
        'email.smtpHost' => 'mail.comersia.app',
        'email.smtpPort' => 465,
        'email.smtpUsername' => 'info@comersia.app',
        'email.smtpPassword' => null,
        'email.smtpEncryption' => 'ssl',
        'email.senderEmail' => 'info@comersia.app',
        'email.senderName' => 'Comersia App',
        'email.supportEmail' => 'soporte@comersia.app',
        'email.verificationTimeout' => 24,
        'email.verificationRequired' => true,
        'email.verificationBypass' => false,

        // Security configurations
        'security.passwordMinLength' => 8,
        'security.passwordRequireSpecial' => true,
        'security.passwordRequireUppercase' => true,
        'security.passwordRequireNumbers' => true,
        'security.sessionTimeout' => 120, // minutos - Default session timeout

        // System configurations
        'system.maintenanceMode' => false,
        'system.debugMode' => false,
        'system.timezone' => 'America/Guayaquil',

        // Business configurations
        'business.taxRate' => 0.15,
        'business.taxName' => 'IVA',
        'business.shippingCost' => 5.00,
        'business.freeShippingThreshold' => 50.00,
        'business.platformCommissionRate' => 0.10,
        'business.sellerEarningsRate' => 0.90,
    ];

    /**
     * Get configuration value with multiple fallbacks
     *
     * @param  string  $key  Configuration key
     * @param  mixed  $default  Default value if all fallbacks fail
     * @return mixed Configuration value
     */
    public function getConfig(string $key, $default = null)
    {
        // Level 1: Check in-memory cache
        if (isset(static::$memoryCache[$key])) {
            return static::$memoryCache[$key];
        }

        // Level 2: Check Laravel cache (if available)
        $value = $this->getFromCache($key);
        if ($value !== null) {
            static::$memoryCache[$key] = $value;

            return $value;
        }

        // Level 3: Try to get from database (if available)
        $value = $this->getFromDatabase($key);
        if ($value !== null) {
            $this->saveToCache($key, $value);
            static::$memoryCache[$key] = $value;

            return $value;
        }

        // Level 4: Check environment variables
        $value = $this->getFromEnvironment($key);
        if ($value !== null) {
            static::$memoryCache[$key] = $value;

            return $value;
        }

        // Level 5: Use hardcoded defaults
        $value = static::$defaults[$key] ?? $default;
        static::$memoryCache[$key] = $value;

        return $value;
    }

    /**
     * Get value from cache
     */
    protected function getFromCache(string $key)
    {
        try {
            if ($this->isCacheAvailable()) {
                $cacheKey = static::CACHE_PREFIX.$key;

                return Cache::get($cacheKey);
            }
        } catch (\Exception $e) {
            Log::debug("Cache not available for config key: {$key}");
        }

        return null;
    }

    /**
     * Save value to cache
     */
    protected function saveToCache(string $key, $value): void
    {
        try {
            if ($this->isCacheAvailable()) {
                $cacheKey = static::CACHE_PREFIX.$key;
                Cache::put($cacheKey, $value, static::CACHE_TTL);
            }
        } catch (\Exception $e) {
            Log::debug("Could not cache config key: {$key}");
        }
    }

    /**
     * Get value from database
     */
    protected function getFromDatabase(string $key)
    {
        if (! $this->isDatabaseAvailable()) {
            return null;
        }

        try {
            $config = DB::table('configurations')
                ->where('key', $key)
                ->first();

            if ($config && $config->value !== null) {
                return $this->parseValue($config->value);
            }
        } catch (\Exception $e) {
            Log::debug("Database query failed for config key: {$key}", [
                'error' => $e->getMessage(),
            ]);

            // Mark database as unavailable for this request
            static::$databaseAvailable = false;
        }

        return null;
    }

    /**
     * Get value from environment variables
     */
    protected function getFromEnvironment(string $key)
    {
        // Map configuration keys to environment variables
        $envMappings = [
            'email.smtpHost' => 'MAIL_HOST',
            'email.smtpPort' => 'MAIL_PORT',
            'email.smtpUsername' => 'MAIL_USERNAME',
            'email.smtpPassword' => 'MAIL_PASSWORD',
            'email.smtpEncryption' => 'MAIL_ENCRYPTION',
            'email.senderEmail' => 'MAIL_FROM_ADDRESS',
            'email.senderName' => 'MAIL_FROM_NAME',
        ];

        if (isset($envMappings[$key])) {
            $envValue = env($envMappings[$key]);
            if ($envValue !== null) {
                return $this->parseValue($envValue);
            }
        }

        return null;
    }

    /**
     * Set configuration value
     */
    public function setConfig(string $key, $value): bool
    {
        Log::info("Setting config: {$key}", ['value' => $value]);

        // Update memory cache
        static::$memoryCache[$key] = $value;

        // Clear cache
        $this->clearCache($key);

        // Try to save to database
        if ($this->isDatabaseAvailable()) {
            try {
                // Prepare value for storage
                $storageValue = $this->prepareValueForStorage($value);

                DB::table('configurations')->updateOrInsert(
                    ['key' => $key],
                    [
                        'value' => $storageValue,
                        'updated_at' => now(),
                    ]
                );

                return true;
            } catch (\Exception $e) {
                Log::error("Failed to save config to database: {$key}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return false;
    }

    /**
     * Delete configuration
     */
    public function deleteConfig(string $key): bool
    {
        // Remove from memory cache
        unset(static::$memoryCache[$key]);

        // Clear cache
        $this->clearCache($key);

        // Try to delete from database
        if ($this->isDatabaseAvailable()) {
            try {
                DB::table('configurations')
                    ->where('key', $key)
                    ->delete();

                return true;
            } catch (\Exception $e) {
                Log::error("Failed to delete config: {$key}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return false;
    }

    /**
     * Clear cache for a specific key
     */
    protected function clearCache(string $key): void
    {
        try {
            if ($this->isCacheAvailable()) {
                $cacheKey = static::CACHE_PREFIX.$key;
                Cache::forget($cacheKey);
            }
        } catch (\Exception $e) {
            Log::debug("Could not clear cache for config key: {$key}");
        }
    }

    /**
     * Check if database is available
     */
    protected function isDatabaseAvailable(): bool
    {
        // If we've already checked and it failed, don't try again in this request
        if (static::$databaseAvailable === false) {
            return false;
        }

        // If we've already confirmed it works, use it
        if (static::$databaseAvailable === true) {
            return true;
        }

        // First time checking - test the connection
        try {
            DB::connection()->getPdo();
            static::$databaseAvailable = true;

            return true;
        } catch (\Exception $e) {
            Log::info('Database not available for configuration service', [
                'reason' => $e->getMessage(),
            ]);
            static::$databaseAvailable = false;

            return false;
        }
    }

    /**
     * Check if cache is available
     */
    protected function isCacheAvailable(): bool
    {
        try {
            // Check if cache driver is available and not 'array' (which is temporary)
            $driver = Config::get('cache.default');

            return $driver && $driver !== 'array' && $driver !== 'null';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Parse stored value to correct type
     */
    protected function parseValue($value)
    {
        // Handle null
        if ($value === null) {
            return null;
        }

        // Handle JSON
        if ($this->isJson($value)) {
            return json_decode($value, true);
        }

        // Handle booleans
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }

        // Handle numbers
        if (is_numeric($value)) {
            if (strpos($value, '.') !== false) {
                return (float) $value;
            }

            return (int) $value;
        }

        return $value;
    }

    /**
     * Prepare value for database storage
     */
    protected function prepareValueForStorage($value): string
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    /**
     * Check if string is valid JSON
     */
    protected function isJson($string): bool
    {
        if (! is_string($string)) {
            return false;
        }

        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Clear all cached configurations
     */
    public function clearAllCache(): void
    {
        static::$memoryCache = [];

        if ($this->isCacheAvailable()) {
            try {
                // Get all cache keys with our prefix and clear them
                Cache::flush();
            } catch (\Exception $e) {
                Log::debug('Could not clear all configuration cache');
            }
        }
    }

    /**
     * Get diagnostic information about the service
     */
    public function getDiagnostics(): array
    {
        return [
            'database_available' => $this->isDatabaseAvailable(),
            'cache_available' => $this->isCacheAvailable(),
            'cache_driver' => Config::get('cache.default'),
            'memory_cache_count' => count(static::$memoryCache),
            'defaults_count' => count(static::$defaults),
        ];
    }
}
