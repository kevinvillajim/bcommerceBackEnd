<?php

namespace App\Providers;

use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\RatingRepositoryInterface;
use App\Domain\Repositories\SellerRepositoryInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Services\ConfigurationService;
use App\UseCases\Rating\RateProductUseCase;
use App\UseCases\Rating\RateSellerUseCase;
use App\UseCases\Rating\RateUserUseCase;
use Illuminate\Support\ServiceProvider;

class RatingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Registrar el servicio de configuración
        $this->app->singleton(ConfigurationService::class, function ($app) {
            return new ConfigurationService;
        });

        // Registrar casos de uso
        $this->app->bind(RateSellerUseCase::class, function ($app) {
            return new RateSellerUseCase(
                $app->make(RatingRepositoryInterface::class),
                $app->make(SellerRepositoryInterface::class),
                $app->make(OrderRepositoryInterface::class),
                $app->make(ConfigurationService::class)
            );
        });

        $this->app->bind(RateProductUseCase::class, function ($app) {
            return new RateProductUseCase(
                $app->make(RatingRepositoryInterface::class),
                $app->make(ProductRepositoryInterface::class),
                $app->make(OrderRepositoryInterface::class),
                $app->make(ConfigurationService::class)
            );
        });

        $this->app->bind(RateUserUseCase::class, function ($app) {
            return new RateUserUseCase(
                $app->make(RatingRepositoryInterface::class),
                $app->make(SellerRepositoryInterface::class),
                $app->make(OrderRepositoryInterface::class),
                $app->make(UserRepositoryInterface::class)
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
