<?php

namespace App\Providers;

use App\Domain\Interfaces\ChatFilterInterface;
// Interfaces
use App\Domain\Interfaces\JwtServiceInterface;
use App\Domain\Interfaces\PaymentGatewayInterface;
use App\Domain\Interfaces\ShippingTrackingInterface;
use App\Domain\Services\PricingCalculatorService;
// Implementaciones
use App\Infrastructure\External\PaymentGateway\DatafastService;
use App\Infrastructure\Services\ChatFilterService;
use App\Infrastructure\Services\JwtService;
// Domain Services
use App\Infrastructure\Services\ShippingTrackingService;
use Illuminate\Support\ServiceProvider;

class InterfacesServiceProvider extends ServiceProvider
{
    /**
     * Mapeo de interfaces con sus implementaciones concretas
     *
     * @var array
     */
    protected $interfaces = [
        JwtServiceInterface::class => JwtService::class,
        PaymentGatewayInterface::class => DatafastService::class,
        ShippingTrackingInterface::class => ShippingTrackingService::class,
        ChatFilterInterface::class => ChatFilterService::class,
        // La interface de recomendaciones se registra en RecommendationServiceProvider
        // por su complejidad y numerosas dependencias
    ];

    /**
     * Register all application services interfaces.
     */
    public function register(): void
    {
        // Registrar todos los servicios definidos en el mapeo
        foreach ($this->interfaces as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }

        // Registrar PricingCalculatorService como singleton para eficiencia
        $this->app->singleton(PricingCalculatorService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
