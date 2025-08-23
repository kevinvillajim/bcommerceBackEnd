<?php

namespace App\Providers;

use App\Domain\Interfaces\DeunaServiceInterface;
use App\Domain\Repositories\DeunaPaymentRepositoryInterface;
use App\Infrastructure\Repositories\EloquentDeunaPaymentRepository;
use App\Infrastructure\Services\DeunaService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class DeunaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind DeUna service interface to implementation
        $this->app->bind(DeunaServiceInterface::class, DeunaService::class);

        // Bind DeUna payment repository interface to implementation
        $this->app->bind(DeunaPaymentRepositoryInterface::class, EloquentDeunaPaymentRepository::class);

        // Register as singleton to avoid multiple instances
        $this->app->singleton(DeunaService::class, function ($app) {
            return new DeunaService;
        });

        $this->app->singleton(EloquentDeunaPaymentRepository::class, function ($app) {
            return new EloquentDeunaPaymentRepository;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Only validate configuration when actually needed (not on every request)
        // Configuration validation moved to service instantiation
    }

    /**
     * Validate DeUna configuration (called only when service is used)
     */
    public static function validateConfiguration(): void
    {
        static $validated = false;

        // Only validate once per request cycle
        if ($validated) {
            return;
        }

        $requiredConfigs = [
            'deuna.api_url',
            'deuna.api_key',
            'deuna.api_secret',
            'deuna.point_of_sale',
        ];

        $missingConfigs = [];
        foreach ($requiredConfigs as $config) {
            if (empty(config($config))) {
                $missingConfigs[] = $config;
            }
        }

        if (! empty($missingConfigs)) {
            Log::warning('DeUna configuration missing', ['missing' => $missingConfigs]);
        }

        // Only log when actually using DeUna service (not on every request)
        if (config('app.debug') && config('app.env') === 'local') {
            Log::info('DeUna Service initialized', [
                'api_url' => config('deuna.api_url'),
                'environment' => config('deuna.environment'),
                'point_of_sale' => config('deuna.point_of_sale'),
            ]);
        }

        $validated = true;
    }
}
