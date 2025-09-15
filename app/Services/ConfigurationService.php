<?php

namespace App\Services;

/**
 * Configuration Service
 *
 * This is now a facade that delegates to the RobustConfigurationService
 * to maintain backward compatibility while providing robust error handling.
 */
class ConfigurationService
{
    /**
     * The robust configuration service instance
     */
    protected RobustConfigurationService $robustService;

    /**
     * Create a new configuration service instance
     */
    public function __construct()
    {
        $this->robustService = new RobustConfigurationService;
    }

    /**
     * Get configuration value
     *
     * @param  string  $key  Configuration key
     * @param  mixed  $default  Default value if not found
     * @return mixed Configuration value
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->robustService->getConfig($key, $default);
    }

    /**
     * Set configuration value
     *
     * @param  string  $key  Configuration key
     * @param  mixed  $value  Configuration value
     * @return bool Success status
     */
    public function setConfig(string $key, $value): bool
    {
        return $this->robustService->setConfig($key, $value);
    }

    /**
     * Delete configuration
     *
     * @param  string  $key  Configuration key
     * @return bool Success status
     */
    public function deleteConfig(string $key): bool
    {
        return $this->robustService->deleteConfig($key);
    }

    /**
     * Clear all configuration cache
     */
    public function clearCache(): void
    {
        $this->robustService->clearAllCache();
    }

    /**
     * Get diagnostic information
     *
     * @return array Diagnostic data
     */
    public function getDiagnostics(): array
    {
        return $this->robustService->getDiagnostics();
    }
}
