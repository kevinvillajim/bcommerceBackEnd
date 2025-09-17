<?php

namespace App\Providers;

use App\Factories\PaymentValidatorFactory;
use App\Infrastructure\External\PaymentGateway\DatafastService;
use App\Services\CheckoutDataService;
use App\Services\PaymentProcessingService;
use App\Validators\Payment\Datafast\DatafastAPIValidator;
use App\Validators\Payment\Datafast\DatafastTestValidator;
use App\Validators\Payment\Datafast\DatafastWebhookValidator;
use App\Validators\Payment\Datafast\DatafastWidgetValidator;
use App\Validators\Payment\Deuna\DeunaSimulationValidator;
use App\Validators\Payment\Deuna\DeunaTestValidator;
use App\Validators\Payment\Deuna\DeunaWebhookValidator;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider para arquitectura centralizada de pagos
 */
class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Registrar servicios centrales de pagos
        $this->app->singleton(PaymentProcessingService::class);
        $this->app->singleton(CheckoutDataService::class);

        // ✅ CORREGIDO: PaymentValidatorFactory requiere Container como dependencia
        $this->app->singleton(PaymentValidatorFactory::class, function ($app) {
            return new PaymentValidatorFactory($app);
        });

        // Registrar validadores de Datafast
        $this->app->bind(DatafastWidgetValidator::class, function ($app) {
            return new DatafastWidgetValidator($app->make(\App\Infrastructure\External\PaymentGateway\DatafastService::class));
        });

        $this->app->bind(DatafastTestValidator::class);

        $this->app->bind(DatafastAPIValidator::class, function ($app) {
            return new DatafastAPIValidator($app->make(\App\Infrastructure\External\PaymentGateway\DatafastService::class));
        });

        $this->app->bind(DatafastWebhookValidator::class);

        // Registrar validadores de Deuna
        $this->app->bind(DeunaWebhookValidator::class);
        $this->app->bind(DeunaTestValidator::class);
        $this->app->bind(DeunaSimulationValidator::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
