<?php

namespace App\Providers;

use App\Services\EcuadorTimeService;
use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;

class TimezoneServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Registrar EcuadorTimeService como singleton
        $this->app->singleton(EcuadorTimeService::class, function ($app) {
            return new EcuadorTimeService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Configurar Carbon para usar timezone de Ecuador por defecto
        Carbon::setLocale(config('app.locale', 'es'));
        
        // Establecer timezone por defecto para toda la aplicación
        date_default_timezone_set(config('app.timezone', 'America/Guayaquil'));
        
        // Configurar Carbon con timezone de Ecuador
        Carbon::setTestNow(null); // Resetear cualquier tiempo de prueba
    }
}