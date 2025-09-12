<?php

namespace App\Providers;

use App\Domain\Repositories\AccountingRepositoryInterface;
use App\Infrastructure\Repositories\EloquentAccountingRepository;
use App\UseCases\Accounting\GenerateAccountingReportUseCase;
use App\UseCases\Accounting\GenerateInvoiceFromOrderUseCase;
use App\UseCases\Accounting\GenerateInvoicePdfUseCase;
use Illuminate\Support\ServiceProvider;

class AccountingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Registrar el repositorio de contabilidad
        $this->app->bind(AccountingRepositoryInterface::class, EloquentAccountingRepository::class);

        // Registrar casos de uso de contabilidad
        $this->app->bind(GenerateAccountingReportUseCase::class, function ($app) {
            return new GenerateAccountingReportUseCase(
                $app->make(AccountingRepositoryInterface::class)
            );
        });

        $this->app->bind(GenerateInvoiceFromOrderUseCase::class, function ($app) {
            return new GenerateInvoiceFromOrderUseCase(
                $app->make(AccountingRepositoryInterface::class)
            );
        });

        $this->app->bind(GenerateInvoicePdfUseCase::class, function ($app) {
            return new GenerateInvoicePdfUseCase();
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