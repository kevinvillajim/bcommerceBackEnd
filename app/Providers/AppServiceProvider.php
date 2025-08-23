<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Todos los service providers personalizados de la aplicación
     *
     * @var array
     */
    protected $providers = [
        // Proveedores principales
        RepositoryServiceProvider::class,
        InterfacesServiceProvider::class,
        AuthServiceProvider::class,
        AccountingServiceProvider::class,
        MailServiceProvider::class, // ✅ Mail system provider

        // Proveedores específicos por dominio
        RecommendationServiceProvider::class,
        ProductServiceProvider::class,
        ShoppingCartServiceProvider::class,
        ShippingServiceProvider::class,
        SellerServiceProvider::class,
        RatingServiceProvider::class,
        EventServiceProvider::class,
        FeedbackServiceProvider::class,
        NotificationServiceProvider::class,
        FavoriteServiceProvider::class,
        JwtClaimsServiceProvider::class,
        SellerOrderServiceProvider::class,
        ChatServiceProvider::class,
        DatafastServiceProvider::class,
        PricingServiceProvider::class,
    ];

    /**
     * Mapeo de eventos a listeners
     */
    protected $listen = [
        'App\Events\OrderCompleted' => [
            'App\Listeners\GenerateInvoiceListener',
        ],
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registramos todos los service providers personalizados
        foreach ($this->providers as $provider) {
            $this->app->register($provider);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register DeUna provider on-demand for payment-related routes only
        $this->registerDeunaProviderOnDemand();
    }

    /**
     * Register DeUna service provider only when needed (payment routes)
     */
    private function registerDeunaProviderOnDemand(): void
    {
        // Always register for console commands to avoid instantiation issues
        if ($this->app->runningInConsole()) {
            if (! $this->app->providerIsLoaded(DeunaServiceProvider::class)) {
                $this->app->register(DeunaServiceProvider::class);
            }
            return;
        }

        // Only register if we're handling DeUna-related requests
        $request = request();
        $currentRoute = $request->getPathInfo();

        // Check if current request is DeUna-related
        $deunaRoutes = [
            '/api/deuna/',
            '/api/webhooks/deuna/',
        ];

        $isDeunaRequest = false;
        foreach ($deunaRoutes as $routePrefix) {
            if (str_starts_with($currentRoute, $routePrefix)) {
                $isDeunaRequest = true;
                break;
            }
        }

        // Register DeUna provider only for DeUna-related requests
        if ($isDeunaRequest) {
            if (! $this->app->providerIsLoaded(DeunaServiceProvider::class)) {
                $this->app->register(DeunaServiceProvider::class);
            }
        }
    }
}
