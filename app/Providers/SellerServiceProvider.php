<?php

namespace App\Providers;

use App\Domain\Repositories\SellerRepositoryInterface;
use App\UseCases\Seller\CreateSellerUseCase;
use App\UseCases\Seller\GetTopSellersUseCase;
use Illuminate\Support\ServiceProvider;

class SellerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register use cases
        $this->app->bind(CreateSellerUseCase::class, function ($app) {
            return new CreateSellerUseCase(
                $app->make(SellerRepositoryInterface::class)
            );
        });

        $this->app->bind(GetTopSellersUseCase::class, function ($app) {
            return new GetTopSellersUseCase(
                $app->make(SellerRepositoryInterface::class)
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
