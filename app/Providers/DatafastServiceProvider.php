<?php

namespace App\Providers;

use App\Domain\Interfaces\PaymentGatewayInterface;
use App\Infrastructure\External\PaymentGateway\DatafastService;
use Illuminate\Support\ServiceProvider;

class DatafastServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Registrar DatafastService como singleton
        $this->app->singleton(DatafastService::class, function ($app) {
            return new DatafastService($app->make(\App\Services\ConfigurationService::class));
        });

        // Usar Datafast como el gateway de pago por defecto,
        // descomentar:
        /*
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            return $app->make(DatafastService::class);
        });
        */
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
