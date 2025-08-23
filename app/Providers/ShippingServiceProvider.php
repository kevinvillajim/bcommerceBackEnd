<?php

namespace App\Providers;

use App\Domain\Interfaces\ShippingTrackingInterface;
use App\Infrastructure\External\ShippingAPI\ShippingAPIAdapter;
use Illuminate\Support\ServiceProvider;

class ShippingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(ShippingTrackingInterface::class, ShippingAPIAdapter::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
