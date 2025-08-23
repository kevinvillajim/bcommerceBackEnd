<?php

namespace App\Providers;

use App\Domain\Repositories\ShoppingCartRepositoryInterface;
use App\Infrastructure\Repositories\EloquentShoppingCartRepository;
use App\Services\PricingService;
use App\UseCases\Cart\ApplyCartDiscountCodeUseCase;
use Illuminate\Support\ServiceProvider;

class ShoppingCartServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(ShoppingCartRepositoryInterface::class, EloquentShoppingCartRepository::class);

        $this->app->bind(ApplyCartDiscountCodeUseCase::class, function ($app) {
            return new ApplyCartDiscountCodeUseCase(
                $app->make(PricingService::class)
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
