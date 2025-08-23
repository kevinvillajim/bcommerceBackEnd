<?php

namespace App\Providers;

use App\Services\ConfigurationService;
use App\Services\PricingService;
use Illuminate\Support\ServiceProvider;

class PricingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Registrar PricingService como singleton para optimización
        $this->app->singleton(PricingService::class, function ($app) {
            return new PricingService(
                $app->make(ConfigurationService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
