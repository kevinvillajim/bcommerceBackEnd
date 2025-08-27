<?php

namespace App\Providers;

use App\Services\ConfigurationService;
use App\Services\PricingService;
use App\Services\PriceVerificationService;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Services\PricingCalculatorService;
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

        // Registrar PriceVerificationService para seguridad anti-tampering
        $this->app->singleton(PriceVerificationService::class, function ($app) {
            return new PriceVerificationService(
                $app->make(ProductRepositoryInterface::class),
                $app->make(PricingCalculatorService::class)
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
