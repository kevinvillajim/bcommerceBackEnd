<?php

namespace App\Providers;

use App\Domain\Repositories\SellerOrderRepositoryInterface;
use App\Infrastructure\Repositories\EloquentSellerOrderRepository;
use Illuminate\Support\ServiceProvider;

class SellerOrderServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(SellerOrderRepositoryInterface::class, function ($app) {
            return new EloquentSellerOrderRepository;
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
