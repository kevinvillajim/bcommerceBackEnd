<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AccountingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Registrar interfaces y repositorios
        $this->app->bind(
            \App\Domain\Repositories\AccountingRepositoryInterface::class,
            \App\Infrastructure\Repositories\EloquentAccountingRepository::class
        );

        $this->app->bind(
            \App\Domain\Repositories\InvoiceRepositoryInterface::class,
            \App\Infrastructure\Repositories\EloquentInvoiceRepository::class
        );

        $this->app->bind(
            \App\Domain\Interfaces\SriServiceInterface::class,
            \App\Infrastructure\External\SRI\SriService::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
