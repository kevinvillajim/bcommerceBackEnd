<?php

namespace App\Providers;

use App\Http\Middleware\ProductInteractionMiddleware;
use App\Infrastructure\Services\FileUploadService;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class ProductServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // ProductRepositoryInterface se registra ahora en RepositoryServiceProvider

        // Registrar el servicio de carga de archivos
        $this->app->singleton(FileUploadService::class, function ($app) {
            return new FileUploadService;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Registrar middleware para seguimiento de interacciones
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('track.product.view', ProductInteractionMiddleware::class);

        // Políticas ahora se manejan en AuthServiceProvider
    }
}
