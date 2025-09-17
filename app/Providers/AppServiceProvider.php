<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        MailServiceProvider::class, // ✅ Mail system provider
        TimezoneServiceProvider::class, // ✅ Ecuador timezone configuration

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
        PaymentServiceProvider::class, // ✅ Arquitectura centralizada de pagos
        PricingServiceProvider::class,
        AccountingServiceProvider::class, // ✅ Sistema de contabilidad y SRI
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
        // Configure critical rate limiters for security
        $this->configureRateLimiters();

        // Register DeUna provider on-demand for payment-related routes only
        $this->registerDeunaProviderOnDemand();

        // Configure Ecuador timezone for Carbon globally
        $this->configureEcuadorTimezone();
    }

    /**
     * Configure critical rate limiters for security
     */
    private function configureRateLimiters(): void
    {
        // Checkout rate limiter - más restrictivo
        RateLimiter::for('checkout', function (Request $request) {
            return [
                // 5 intentos por minuto por IP
                Limit::perMinute(5)->by($request->ip()),
                // 10 intentos por minuto por usuario autenticado
                Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()),
            ];
        });

        // Webhook rate limiter - moderado pero protegido
        RateLimiter::for('webhook', function (Request $request) {
            return [
                // 30 webhooks por minuto por IP (para servicios legítimos)
                Limit::perMinute(30)->by($request->ip()),
                // 100 por hora máximo
                Limit::perHour(100)->by($request->ip()),
            ];
        });

        // Payment rate limiter - muy restrictivo
        RateLimiter::for('payment', function (Request $request) {
            return [
                // 5 intentos por minuto por IP
                Limit::perMinute(5)->by($request->ip()),
                // 10 intentos por minuto por usuario
                Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()),
                // 20 por hora máximo
                Limit::perHour(20)->by($request->user()?->id ?: $request->ip()),
            ];
        });
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

    /**
     * Configure Ecuador timezone for Carbon globally
     */
    private function configureEcuadorTimezone(): void
    {
        // Set Ecuador timezone as default for all Carbon instances
        $timezone = config('app.timezone', 'America/Guayaquil');

        // Configure PHP's default timezone
        date_default_timezone_set($timezone);

        // Configure Carbon's default timezone for new instances
        Carbon::setLocale(config('app.locale', 'es'));

        // Log timezone configuration for verification (commented to avoid log spam)
        // if (config('app.debug')) {
        //     $now = Carbon::now();
        //     \Log::debug('Ecuador Timezone Configuration', [
        //         'php_timezone' => date_default_timezone_get(),
        //         'carbon_timezone' => $now->timezoneName,
        //         'current_time' => $now->format('Y-m-d H:i:s P'),
        //         'offset' => $now->format('P'),
        //     ]);
        // }
    }
}
